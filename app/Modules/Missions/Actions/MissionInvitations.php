<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\Availability;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invitation d'un freelance à une mission (F-12). Une invitation n'est ni une commande ni un droit : le freelance décide de proposer ou de décliner,
 * les autres freelances peuvent toujours proposer, et la proposition suit les règles habituelles. L'identité du client n'est jamais transmise.
 */
final class MissionInvitations
{
    public const REASONS = ['unavailable' => 'Pas disponible en ce moment', 'budget' => 'Budget trop bas', 'domain' => 'Hors de mon domaine', 'other' => 'Autre'];

    public function __construct(private Notify $notify) {}

    /** @return string identifiant de l'invitation */
    public function invite(User $client, string $profileSlug, string $missionId, ?string $message): string
    {
        AccountStanding::assertCanStartNew($client);
        $message = $message === null ? null : trim($message);
        $message = $message === '' ? null : $message;
        if ($message !== null && mb_strlen($message) > (int) config('freeci.missions.invitations.message_max')) {
            throw new MissionConflict('Le message est limité à '.config('freeci.missions.invitations.message_max').' caractères.');
        }
        if ($message !== null && PrivateContact::found($message)) {
            throw new MissionConflict('Le message ne peut contenir ni téléphone, ni adresse e-mail, ni lien de messagerie : les échanges restent dans FreeCI.');
        }

        return DB::transaction(function () use ($client, $profileSlug, $missionId, $message) {
            $p = DB::table('freelance_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.slug', $profileSlug)->whereNotNull('p.published_at')->whereNull('u.suspended_at')
                ->first(['p.user_id', 'p.display_name', 'p.unavailable_at', 'p.back_on', 'p.auto_reopen']) ?? throw new MissionForbidden;
            if ((string) $p->user_id === (string) $client->getKey()) {
                throw new MissionConflict('Vous ne pouvez pas vous inviter vous-même.');
            }
            if (Availability::unavailable($p)) {
                throw new MissionConflict('Ce freelance est indisponible pour le moment : il ne peut pas être invité.');
            }
            DB::table('users')->where('id', $client->getKey())->lockForUpdate()->first();                     // sérialise les plafonds d'un même client
            $m = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $missionId)->where('m.client_id', $client->getKey())
                ->where('m.status', 'open')->where('v.application_deadline', '>', now())->first(['m.id', 'v.title']) ?? throw new MissionConflict('Choisissez une de vos missions ouvertes, dont la date limite n’est pas passée.');
            if (DB::table('mission_invitations')->where('mission_id', $m->id)->where('freelancer_id', $p->user_id)->exists()) {
                throw new MissionConflict('Ce freelance a déjà été invité à cette mission.');
            }
            if (DB::table('mission_invitations')->where('mission_id', $m->id)->count() >= (int) config('freeci.missions.invitations.per_mission')) {
                throw new MissionConflict('Cette mission a déjà reçu le maximum de '.config('freeci.missions.invitations.per_mission').' invitations.');
            }
            if (DB::table('mission_invitations')->where('client_id', $client->getKey())->where('created_at', '>=', now()->subDay())->count() >= (int) config('freeci.missions.invitations.per_client_day')) {
                throw new MissionConflict('Vous avez atteint la limite de '.config('freeci.missions.invitations.per_client_day').' invitations par jour : réessayez demain.');
            }
            $id = (string) Str::uuid();
            DB::table('mission_invitations')->insert(['id' => $id, 'mission_id' => $m->id, 'client_id' => $client->getKey(), 'freelancer_id' => $p->user_id, 'state' => 'pending', 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
            ($this->notify)((string) $p->user_id, 'mission_invitation', 'mission_invitation:'.$id, 'Invitation : '.$m->title, 'Un client vous invite à proposer vos services pour cette mission.', 'freelance.invitations');

            return $id;
        });
    }

    /** Le client retire une invitation restée sans réponse. */
    public function withdraw(User $client, string $id): void
    {
        $n = DB::table('mission_invitations')->where('id', $id)->where('client_id', $client->getKey())->where('state', 'pending')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('proposals as pr')->whereColumn('pr.mission_id', 'mission_invitations.mission_id')->whereColumn('pr.freelancer_id', 'mission_invitations.freelancer_id')->whereIn('pr.state', ['active', 'selected']))
            ->update(['state' => 'withdrawn', 'responded_at' => now(), 'updated_at' => now()]);
        if ($n === 0) {
            throw new MissionConflict('Cette invitation ne peut plus être retirée (déjà traitée, ou le freelance a déjà proposé).');
        }
    }

    /** Le freelance décline, avec un motif prédéfini (jamais de texte libre). */
    public function decline(User $freelancer, string $id, string $reason): void
    {
        if (! array_key_exists($reason, self::REASONS)) {
            throw new MissionConflict('Choisissez un motif dans la liste.');
        }
        $inv = DB::table('mission_invitations as i')->join('mission_versions as v', fn ($j) => $j->on('v.mission_id', '=', 'i.mission_id')->where('v.state', 'published'))->where('i.id', $id)->where('i.freelancer_id', $freelancer->getKey())
            ->where('i.state', 'pending')->first(['i.client_id', 'v.title']);
        $n = DB::table('mission_invitations')->where('id', $id)->where('freelancer_id', $freelancer->getKey())->where('state', 'pending')
            ->update(['state' => 'declined', 'decline_reason' => $reason, 'responded_at' => now(), 'updated_at' => now()]);
        if ($n === 0) {
            throw new MissionConflict('Cette invitation n’est plus en attente.');
        }
        if ($inv !== null) {
            ($this->notify)((string) $inv->client_id, 'invitation_update', 'invitation_declined:'.$id, 'Invitation déclinée : '.$inv->title, 'Motif indiqué : « '.self::REASONS[$reason].' ».', 'client.missions.show', ['mission' => DB::table('mission_invitations')->where('id', $id)->value('mission_id')]);
        }
    }
}
