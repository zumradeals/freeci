<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Support\MissionHistory;
use App\Modules\Missions\Support\MissionRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rédaction et cycle de vie d'une mission par son CLIENT PROPRIÉTAIRE. Toute écriture : propriétaire vérifié, ligne verrouillée, état revérifié.
 * Le contenu public n'est jamais modifié ici : toute modification d'une mission publiée crée une NOUVELLE VERSION soumise à modération ;
 * les propositions faites sur une version précédente ne sont plus sélectionnables tant que leur auteur ne les a pas reconfirmées.
 */
final class MissionAuthoring
{
    private const OWNER = 'propriétaire';

    public function owned(User $client, string $missionId, bool $lock = false): Mission
    {
        $q = Mission::query()->whereKey($missionId)->where('client_id', $client->getKey());
        $m = ($lock ? $q->lockForUpdate() : $q)->first();
        if ($m === null) {
            throw new MissionForbidden;
        }

        return $m;
    }

    public function working(Mission $m): ?MissionVersion
    {
        return MissionVersion::query()->where('mission_id', $m->getKey())->whereIn('state', MissionVersion::OPEN)->first();
    }

    public function create(User $client, string $categoryId, string $title): Mission
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        [$min, $max] = config('freeci.missions.title');
        $errors = [];
        if (mb_strlen($title) < $min || mb_strlen($title) > $max) {
            $errors['title'] = "Le titre doit faire entre {$min} et {$max} caractères.";
        } elseif (PrivateContact::found($title)) {
            $errors['title'] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : une mission est publique.';
        }
        if (! Category::query()->active()->whereKey($categoryId)->exists()) {
            $errors['category_id'] = 'Choisissez une catégorie.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($client, $categoryId, $title) {
            $m = Mission::create(['client_id' => $client->getKey(), 'slug' => 'brouillon-'.substr((string) Str::uuid(), 0, 8), 'status' => 'draft', 'is_demo' => $client->is_demo]);
            $v = MissionVersion::create(['mission_id' => $m->getKey(), 'number' => 1, 'state' => 'draft', 'category_id' => $categoryId, 'title' => $title, 'created_by' => $client->getKey()]);
            MissionHistory::log($m->getKey(), 'created', $client, self::OWNER, null, null, $v->getKey());

            return $m;
        });
    }

    public function save(User $client, string $missionId, array $input, int $revisionNo): MissionVersion
    {
        $values = MissionRules::normalize($input);
        MissionRules::validate($values, strict: false);

        return DB::transaction(function () use ($client, $missionId, $values, $revisionNo) {
            $m = $this->owned($client, $missionId, lock: true);
            $v = $this->working($m) ?? throw new MissionConflict('Aucune version en cours de rédaction : démarrez une nouvelle version.');
            $this->assertEditable($v, $revisionNo);
            $v->forceFill($values + ['revision_no' => $v->revision_no + 1])->save();

            return $v;
        });
    }

    public function submit(User $client, string $missionId, int $revisionNo): MissionVersion
    {
        AccountStanding::assertCanStartNew($client);

        return DB::transaction(function () use ($client, $missionId, $revisionNo) {
            $m = $this->owned($client, $missionId, lock: true);
            $v = $this->working($m) ?? throw new MissionConflict('Aucune version à soumettre.');
            $this->assertEditable($v, $revisionNo);
            MissionRules::validate(collect(MissionRules::FIELDS)->mapWithKeys(fn ($f) => [$f => $v->{$f}])->all(), strict: true);
            $v->forceFill(['state' => 'in_review', 'submitted_at' => now(), 'decided_at' => null, 'decided_by' => null, 'decision_note' => null])->save();
            if ($m->status === 'draft') {
                $m->forceFill(['status' => 'in_review'])->save();
            }
            MissionHistory::log($m->getKey(), 'submitted', $client, self::OWNER, null, ['number' => $v->number], $v->getKey());

            return $v;
        });
    }

    public function withdrawSubmission(User $client, string $missionId): void
    {
        DB::transaction(function () use ($client, $missionId) {
            $m = $this->owned($client, $missionId, lock: true);
            $v = MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'in_review')->lockForUpdate()->first()
                ?? throw new MissionConflict('Cette version n’est plus en contrôle (elle a peut-être été traitée entre-temps).');
            $v->forceFill(['state' => 'draft', 'submitted_at' => null])->save();
            if ($m->status === 'in_review') {
                $m->forceFill(['status' => 'draft'])->save();
            }
            MissionHistory::log($m->getKey(), 'submission_withdrawn', $client, self::OWNER, null, null, $v->getKey());
        });
    }

    /** Modifier une mission publiée = nouvelle version (copie). Impossible tant qu'elle est réservée ou attribuée (une commande existe). */
    public function startRevision(User $client, string $missionId): MissionVersion
    {
        return DB::transaction(function () use ($client, $missionId) {
            $m = $this->owned($client, $missionId, lock: true);
            if (! in_array($m->status, ['open', 'selection_ended'], true)) {
                throw new MissionConflict('Cette mission ne peut pas être modifiée dans son état actuel.');
            }
            if ($this->working($m) !== null) {
                throw new MissionConflict('Une version est déjà en cours de rédaction ou en contrôle.');
            }
            $live = MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'published')->firstOrFail();
            $n = (int) MissionVersion::query()->where('mission_id', $m->getKey())->max('number') + 1;
            $v = MissionVersion::create($live->only(['category_id', 'title', 'description', 'budget_xof', 'application_deadline', 'client_inputs', 'brief_requires_files'])
                + ['mission_id' => $m->getKey(), 'number' => $n, 'state' => 'draft', 'created_by' => $client->getKey()]);
            $affected = Proposal::query()->where('mission_id', $m->getKey())->where('state', 'active')->count();
            MissionHistory::log($m->getKey(), 'revision_started', $client, self::OWNER, null, ['from_number' => $live->number, 'proposals_to_reconfirm' => $affected], $v->getKey());

            return $v;
        });
    }

    /** Annulation d'une mission JAMAIS publiée (brouillon, en contrôle, à corriger). */
    public function cancel(User $client, string $missionId): void
    {
        DB::transaction(function () use ($client, $missionId) {
            $m = $this->owned($client, $missionId, lock: true);
            if (! in_array($m->status, ['draft', 'in_review'], true)) {
                throw new MissionConflict('Seule une mission non publiée peut être annulée ; une mission publiée se ferme.');
            }
            $m->forceFill(['status' => 'cancelled', 'closed_at' => now(), 'row_version' => $m->row_version + 1])->save();
            MissionHistory::log($m->getKey(), 'cancelled', $client, self::OWNER);
        });
    }

    /** Fermeture d'une mission publiée, sans effacer l'historique : les propositions actives sont closes. Refusée tant qu'une commande est en cours. */
    public function close(User $client, string $missionId, ?string $note = null): void
    {
        DB::transaction(function () use ($client, $missionId, $note) {
            $m = $this->owned($client, $missionId, lock: true);
            if (! in_array($m->status, ['open', 'selection_ended'], true)) {
                throw new MissionConflict($m->status === 'reserved' ? 'Une proposition est retenue et la commande attend son paiement : annulez d’abord la commande pour libérer la mission.' : 'Cette mission ne peut pas être fermée dans son état actuel.');
            }
            Proposal::query()->where('mission_id', $m->getKey())->where('state', 'active')->update(['state' => 'closed', 'updated_at' => now()]);
            $m->forceFill(['status' => 'closed', 'closed_at' => now(), 'closure_note' => $note === null ? null : mb_substr(trim($note), 0, 500), 'row_version' => $m->row_version + 1])->save();
            MissionHistory::log($m->getKey(), 'closed', $client, self::OWNER, $note);
        });
    }

    /** Réouverture EXPLICITE après la fin d'une sélection (commande annulée ou expirée avant paiement). Jamais automatique. */
    public function reopen(User $client, string $missionId): void
    {
        AccountStanding::assertCanStartNew($client);
        DB::transaction(function () use ($client, $missionId) {
            $m = $this->owned($client, $missionId, lock: true);
            if ($m->status !== 'selection_ended') {
                throw new MissionConflict('Seule une mission dont la sélection est terminée peut être rouverte.');
            }
            $live = MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'published')->firstOrFail();
            if (MissionLifecycle::selectionEnd($live)->lte(now())) {
                throw new MissionConflict('La période de sélection est dépassée : modifiez la date limite de candidature (nouvelle version soumise au contrôle) ou fermez la mission.');
            }
            $m->forceFill(['status' => 'open', 'row_version' => $m->row_version + 1])->save();
            MissionHistory::log($m->getKey(), 'reopened', $client, self::OWNER);
        });
    }

    private function assertEditable(MissionVersion $v, int $revisionNo): void
    {
        if (! $v->isEditable()) {
            throw new MissionConflict('Cette version est en contrôle : retirez la soumission pour la modifier.');
        }
        if ($v->revision_no !== $revisionNo) {
            throw new MissionConflict('Cette mission a été modifiée depuis un autre écran. Rechargez la page avant de continuer ; rien n’a été écrasé.');
        }
    }
}
