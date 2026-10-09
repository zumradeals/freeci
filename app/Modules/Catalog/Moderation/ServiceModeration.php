<?php

namespace App\Modules\Catalog\Moderation;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Support\ServiceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Actions de modération (approuver, demander une correction avec motif, suspendre, remettre en ligne). Pas d'interface web à ce stade :
 * elles sont appelées par les commandes console `freeci:moderation:*`. Chaque action exige l'habilitation « administrateur »
 * EN VIGUEUR de l'acteur, refuse la modération de son propre service, verrouille les lignes, revérifie l'état et écrit l'historique.
 * La version approuvée est copiée dans `services` ; elle ne touche jamais aux accords des commandes existantes (instantanés).
 */
final class ServiceModeration
{
    private const LABEL = 'modération (console)';

    private string $label = self::LABEL;

    /** Même règles, canal différent (historique : « modération (web) » ou « modération (console) »). */
    public function via(string $label): static
    {
        $c = clone $this;
        $c->label = $label;

        return $c;
    }

    public function approve(User $moderator, string $versionId): Service
    {
        return DB::transaction(function () use ($moderator, $versionId) {
            [$service, $v] = $this->lockInReview($moderator, $versionId);
            $profile = $service->freelanceProfile;
            if ($profile->published_at === null) {
                throw new ServiceStateConflict('Le profil du freelance n’est pas publié : approbation impossible.');
            }
            $first = $service->status === ServiceStatus::InReview || $service->status === ServiceStatus::Draft;

            ServiceVersion::query()->where('service_id', $service->getKey())->where('state', 'published')->get()
                ->each(fn (ServiceVersion $old) => $old->forceFill(['state' => 'superseded'])->save());
            $now = now();
            $v->forceFill(['state' => 'published', 'decided_at' => $now, 'decided_by' => $moderator->getKey(), 'decision_note' => null, 'published_at' => $now])->save();

            $service->forceFill($v->only(['category_id', 'title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'images', 'brief_requires_files', 'delivery_requires_files', 'tiers', 'options']) + [
                'status' => ServiceStatus::Published->value, 'published_at' => $service->published_at ?? $now, 'is_demo' => $profile->is_demo,
            ]);
            if (str_starts_with($service->slug, 'brouillon-')) {
                $service->slug = $this->uniqueSlug($v->title);      // l'adresse publique est fixée à la première publication, puis ne change plus
            }
            if ($first) {
                $service->accepts_requests = true;
            }
            $service->save();
            ServiceHistory::log($service->getKey(), $v->getKey(), 'approved', $moderator, $this->label, null, ['number' => $v->number]);

            return $service;
        });
    }

    public function requestChanges(User $moderator, string $versionId, string $reason): Service
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($moderator, $versionId, $reason) {
            [$service, $v] = $this->lockInReview($moderator, $versionId);
            $v->forceFill(['state' => 'changes_requested', 'decided_at' => now(), 'decided_by' => $moderator->getKey(), 'decision_note' => $reason])->save();
            if ($service->status === ServiceStatus::InReview) {
                $service->forceFill(['status' => ServiceStatus::Draft->value])->save();       // jamais publié : retour en rédaction ; une version publiée reste en ligne
            }
            ServiceHistory::log($service->getKey(), $v->getKey(), 'changes_requested', $moderator, $this->label, $reason, ['number' => $v->number]);

            return $service;
        });
    }

    /** Retrait par la modération (motif obligatoire) : le service n'est plus proposé ; commandes et obligations en cours inchangées. */
    public function suspend(User $moderator, string $serviceId, string $reason): Service
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($moderator, $serviceId, $reason) {
            $this->assertModerator($moderator);
            $service = Service::query()->whereKey($serviceId)->lockForUpdate()->first() ?? throw new ServiceStateConflict('Service introuvable.');
            $this->assertNotOwner($moderator, $service);
            if ($service->status !== ServiceStatus::Published) {
                throw new ServiceStateConflict('Seul un service en ligne peut être suspendu.');
            }
            $service->forceFill(['status' => ServiceStatus::Suspended->value])->save();
            ServiceHistory::log($service->getKey(), null, 'suspended', $moderator, $this->label, $reason);

            return $service;
        });
    }

    public function reinstate(User $moderator, string $serviceId): Service
    {
        return DB::transaction(function () use ($moderator, $serviceId) {
            $this->assertModerator($moderator);
            $service = Service::query()->whereKey($serviceId)->lockForUpdate()->first() ?? throw new ServiceStateConflict('Service introuvable.');
            $this->assertNotOwner($moderator, $service);
            if ($service->status !== ServiceStatus::Suspended) {
                throw new ServiceStateConflict('Seul un service suspendu par la modération peut être remis en ligne ici.');
            }
            $service->forceFill(['status' => ServiceStatus::Published->value])->save();
            ServiceHistory::log($service->getKey(), null, 'reinstated', $moderator, $this->label);

            return $service;
        });
    }

    /** @return array{0: Service, 1: ServiceVersion} */
    private function lockInReview(User $moderator, string $versionId): array
    {
        $this->assertModerator($moderator);
        $v = ServiceVersion::query()->whereKey($versionId)->lockForUpdate()->first() ?? throw new ServiceStateConflict('Version introuvable.');
        $service = Service::query()->whereKey($v->service_id)->with('freelanceProfile')->lockForUpdate()->firstOrFail();
        $this->assertNotOwner($moderator, $service);
        if ($v->state !== 'in_review') {
            throw new ServiceStateConflict('Cette version n’est plus en contrôle (déjà traitée ou retirée par son auteur).');
        }

        return [$service, $v];
    }

    private function assertModerator(User $moderator): void
    {
        if (! $moderator->isAdministrator()) {
            throw new ModerationDenied('Habilitation administrateur en vigueur requise.');
        }
    }

    private function assertNotOwner(User $moderator, Service $service): void
    {
        if ($service->freelanceProfile->user_id === $moderator->getKey()) {
            throw new ModerationDenied('Vous ne pouvez pas modérer votre propre service.');
        }
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw new ServiceStateConflict('Le motif est obligatoire (10 à 1000 caractères) : il est visible du freelance.');
        }

        return $reason;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'service', 120, '');
        $slug = $base;
        for ($i = 2; Service::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
