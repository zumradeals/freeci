<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Exceptions\ServiceForbidden;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Catalog\Support\ServiceHistory;
use App\Modules\Catalog\Support\ServiceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rédaction d'un service par son PROPRIÉTAIRE. Toute écriture : propriétaire vérifié, ligne verrouillée, état revérifié.
 * Le contenu public (`services`) n'est JAMAIS touché ici : il ne change qu'à l'approbation d'une version (Moderation\*).
 * Un service d'autrui et un service inexistant répondent pareil (ServiceForbidden → 404).
 */
final class ServiceAuthoring
{
    private const OWNER = 'propriétaire';

    /** Service du propriétaire, verrouillé dans la transaction courante si demandé. */
    public function owned(User $owner, string $serviceId, bool $lock = false): Service
    {
        $q = Service::query()->whereKey($serviceId)->whereHas('freelanceProfile', fn ($p) => $p->where('user_id', $owner->getKey()));
        $service = ($lock ? $q->lockForUpdate() : $q)->first();
        if ($service === null) {
            throw new ServiceForbidden;
        }

        return $service;
    }

    /** Version de travail (brouillon, en contrôle ou à corriger). */
    public function working(Service $service): ?ServiceVersion
    {
        return ServiceVersion::query()->where('service_id', $service->getKey())->whereIn('state', ServiceVersion::OPEN)->first();
    }

    /** Services créés avant ce lot (ou par script) : version initiale = copie du contenu actuel. Idempotent. */
    public function ensureVersions(Service $service): void
    {
        if (ServiceVersion::query()->where('service_id', $service->getKey())->exists()) {
            return;
        }
        $state = match ($service->status) {
            ServiceStatus::Published, ServiceStatus::Suspended, ServiceStatus::Archived => 'published', ServiceStatus::InReview => 'in_review', default => 'draft',
        };
        ServiceVersion::create([
            'service_id' => $service->getKey(), 'number' => 1, 'state' => $state, 'category_id' => $service->category_id, 'title' => $service->title, 'summary' => $service->summary,
            'scope' => $service->scope, 'price_xof' => $service->price_xof, 'delivery_days' => $service->delivery_days, 'revisions_included' => $service->revisions_included,
            'deliverables' => $service->deliverables, 'exclusions' => $service->exclusions, 'client_inputs' => $service->client_inputs, 'images' => $service->images,
            'brief_requires_files' => $service->brief_requires_files, 'delivery_requires_files' => $service->delivery_requires_files,
            'published_at' => $state === 'published' ? ($service->published_at ?? now()) : null,
        ]);
    }

    public function create(User $owner, string $categoryId, string $title): Service
    {
        $profile = $owner->freelanceProfile;
        if ($profile === null || ! $owner->hasRole('freelance')) {
            throw new ServiceForbidden;
        }
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        [$min, $max] = config('freeci.catalog.title');
        $errors = [];
        if (mb_strlen($title) < $min || mb_strlen($title) > $max) {
            $errors['title'] = "Le titre doit faire entre {$min} et {$max} caractères.";
        } elseif (PrivateContact::found($title)) {
            $errors['title'] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : les échanges passent par FreeCI.';
        }
        if (! Category::query()->active()->whereKey($categoryId)->exists()) {
            $errors['category_id'] = 'Choisissez une catégorie.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($owner, $profile, $categoryId, $title) {
            $service = Service::create([
                'category_id' => $categoryId, 'freelance_profile_id' => $profile->getKey(), 'slug' => 'brouillon-'.substr((string) Str::uuid(), 0, 8), 'title' => $title,
                'summary' => '', 'scope' => '', 'price_xof' => 0, 'delivery_days' => 0, 'revisions_included' => 0, 'deliverables' => [], 'exclusions' => [], 'client_inputs' => [], 'images' => [],
                'status' => ServiceStatus::Draft->value, 'is_demo' => $profile->is_demo, 'delivery_requires_files' => true, 'brief_requires_files' => false,
            ]);
            $v = ServiceVersion::create(['service_id' => $service->getKey(), 'number' => 1, 'state' => 'draft', 'category_id' => $categoryId, 'title' => $title, 'created_by' => $owner->getKey()]);
            ServiceHistory::log($service->getKey(), $v->getKey(), 'created', $owner, self::OWNER);

            return $service;
        });
    }

    /** @param array<string, mixed> $input saisie brute du formulaire */
    public function save(User $owner, string $serviceId, array $input, int $revisionNo): ServiceVersion
    {
        $values = ServiceRules::normalize($input);
        ServiceRules::validate($values, strict: false);

        return DB::transaction(function () use ($owner, $serviceId, $input, $values, $revisionNo) {
            $service = $this->owned($owner, $serviceId, lock: true);
            $this->ensureVersions($service);
            $v = $this->working($service) ?? throw new ServiceStateConflict('Aucune version en cours de rédaction : démarrez une nouvelle version.');
            $this->assertEditable($v, $revisionNo);

            // Légendes et textes alternatifs des images déjà jointes (les images elles-mêmes passent par Media\*).
            $images = collect($v->images)->map(function ($img) use ($input) {
                $id = $img['id'] ?? null;
                if ($id !== null) {
                    $img['alt'] = trim((string) ($input['image_alt'][$id] ?? $img['alt'] ?? ''));
                    $img['caption'] = trim((string) ($input['image_caption'][$id] ?? $img['caption'] ?? ''));
                }

                return $img;
            })->all();
            // La première image alimente déjà la carte du catalogue et la galerie.
            // Seule une image appartenant à cette version peut devenir principale.
            $cover = $input['cover_image'] ?? null;
            if ($cover !== null && $cover !== '') {
                $index = collect($images)->search(fn ($img) => ($img['id'] ?? null) === $cover);
                if ($index === false) {
                    throw ValidationException::withMessages(['cover_image' => 'Choisissez une image de ce service.']);
                }
                $primary = $images[$index];
                array_splice($images, $index, 1);
                array_unshift($images, $primary);
            }
            $v->forceFill($values + ['images' => $images, 'revision_no' => $v->revision_no + 1])->save();

            return $v;
        });
    }

    public function submit(User $owner, string $serviceId, int $revisionNo): ServiceVersion
    {
        AccountStanding::assertCanStartNew($owner);

        return DB::transaction(function () use ($owner, $serviceId, $revisionNo) {
            $service = $this->owned($owner, $serviceId, lock: true);
            $this->ensureVersions($service);
            $v = $this->working($service) ?? throw new ServiceStateConflict('Aucune version à soumettre.');
            $this->assertEditable($v, $revisionNo);

            $profile = $service->freelanceProfile;
            if ($profile->published_at === null) {
                throw ValidationException::withMessages(['profile' => 'Publiez d’abord votre profil : il s’affiche avec vos services.']);
            }
            ServiceRules::validate(collect(ServiceRules::FIELDS)->mapWithKeys(fn ($f) => [$f => $v->{$f}])->all(), strict: true);
            if (count($v->images) < 1 && ImageProcessor::available()) {
                throw ValidationException::withMessages(['images' => 'Ajoutez une image de couverture : elle s’affiche sur la carte de votre service dans le catalogue.']);
            }
            foreach ($v->images as $img) {
                if (isset($img['id']) && mb_strlen(trim((string) ($img['alt'] ?? ''))) < 3) {
                    throw ValidationException::withMessages(['images' => 'Décrivez chaque image (texte alternatif d’au moins 3 caractères) : il est lu par les lecteurs d’écran.']);
                }
            }

            $v->forceFill(['state' => 'in_review', 'submitted_at' => now(), 'decided_at' => null, 'decided_by' => null, 'decision_note' => null])->save();
            if ($service->status === ServiceStatus::Draft) {
                $service->forceFill(['status' => ServiceStatus::InReview->value])->save();
            }
            ServiceHistory::log($service->getKey(), $v->getKey(), 'submitted', $owner, self::OWNER, null, ['number' => $v->number]);

            return $v;
        });
    }

    /** Le propriétaire retire sa soumission avant décision : retour en brouillon, la version publiée n'est pas touchée. */
    public function withdrawSubmission(User $owner, string $serviceId): void
    {
        DB::transaction(function () use ($owner, $serviceId) {
            $service = $this->owned($owner, $serviceId, lock: true);
            $v = ServiceVersion::query()->where('service_id', $service->getKey())->where('state', 'in_review')->lockForUpdate()->first()
                ?? throw new ServiceStateConflict('Cette version n’est plus en contrôle (elle a peut-être été traitée entre-temps).');
            $v->forceFill(['state' => 'draft', 'submitted_at' => null])->save();
            if ($service->status === ServiceStatus::InReview) {
                $service->forceFill(['status' => ServiceStatus::Draft->value])->save();
            }
            ServiceHistory::log($service->getKey(), $v->getKey(), 'submission_withdrawn', $owner, self::OWNER);
        });
    }

    /** Nouvelle version à partir de la version publiée (contenu et images copiés) ; la version publiée reste en ligne. */
    public function startRevision(User $owner, string $serviceId): ServiceVersion
    {
        return DB::transaction(function () use ($owner, $serviceId) {
            $service = $this->owned($owner, $serviceId, lock: true);
            $this->ensureVersions($service);
            if ($this->working($service) !== null) {
                throw new ServiceStateConflict('Une version est déjà en cours de rédaction ou en contrôle.');
            }
            $live = ServiceVersion::query()->where('service_id', $service->getKey())->where('state', 'published')->first()
                ?? throw new ServiceStateConflict('Ce service n’a pas encore de version publiée.');
            $n = (int) ServiceVersion::query()->where('service_id', $service->getKey())->max('number') + 1;
            $v = ServiceVersion::create($live->only(['category_id', 'title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'images', 'brief_requires_files', 'delivery_requires_files'])
                + ['service_id' => $service->getKey(), 'number' => $n, 'state' => 'draft', 'created_by' => $owner->getKey()]);
            ServiceHistory::log($service->getKey(), $v->getKey(), 'revision_started', $owner, self::OWNER, null, ['from_number' => $live->number]);

            return $v;
        });
    }

    /** Retrait du catalogue par le propriétaire : le service n'est plus proposé ; les commandes et leurs accords ne bougent pas. */
    public function withdrawFromCatalog(User $owner, string $serviceId, ?string $note = null): void
    {
        DB::transaction(function () use ($owner, $serviceId, $note) {
            $service = $this->owned($owner, $serviceId, lock: true);
            if ($service->status !== ServiceStatus::Published) {
                throw new ServiceStateConflict('Ce service n’est pas en ligne.');
            }
            $service->forceFill(['status' => ServiceStatus::Archived->value])->save();
            ServiceHistory::log($service->getKey(), null, 'withdrawn_by_owner', $owner, self::OWNER, $note === null ? null : mb_substr(trim($note), 0, 500));
        });
    }

    /** Remise en ligne d'un service que le propriétaire a retiré lui-même (jamais d'un service suspendu par la modération). */
    public function restoreToCatalog(User $owner, string $serviceId): void
    {
        DB::transaction(function () use ($owner, $serviceId) {
            $service = $this->owned($owner, $serviceId, lock: true);
            if ($service->status !== ServiceStatus::Archived) {
                throw new ServiceStateConflict('Seul un service que vous avez retiré peut être remis en ligne.');
            }
            if ($service->freelanceProfile->published_at === null) {
                throw ValidationException::withMessages(['profile' => 'Publiez d’abord votre profil.']);
            }
            $service->forceFill(['status' => ServiceStatus::Published->value])->save();
            ServiceHistory::log($service->getKey(), null, 'restored_by_owner', $owner, self::OWNER);
        });
    }

    private function assertEditable(ServiceVersion $v, int $revisionNo): void
    {
        if (! $v->isEditable()) {
            throw new ServiceStateConflict('Cette version est en contrôle : retirez la soumission pour la modifier.');
        }
        if ($v->revision_no !== $revisionNo) {
            throw new ServiceStateConflict('Ce service a été modifié depuis un autre écran. Rechargez la page pour voir la dernière version avant de continuer ; rien n’a été écrasé.');
        }
    }
}
