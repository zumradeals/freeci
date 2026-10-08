<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Exceptions\AccountConflict;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use App\Modules\Accounts\Support\AccountMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fermeture de compte en DEUX temps : demande (réversible pendant un délai de réflexion PROVISOIRE), puis traitement par `freeci:accounts:close`.
 * La fermeture n'est exécutée que si AUCUNE obligation n'est en cours (commande active, litige, opération financière, reversement ou remboursement
 * à finaliser, mission ou proposition ouverte, habilitation du personnel, suspension). Sinon la demande reste en attente et les obstacles sont listés.
 * Exécutée, elle ANONYMISE le compte en place : les commandes, messages, écritures financières et dossiers conservés restent liés à un « Compte fermé ».
 * Les durées de conservation de ces éléments NE SONT PAS décidées ici (voir docs/21).
 */
final class AccountClosure
{
    public const TERMINAL_ORDERS = ['closed', 'cancelled', 'expired'];

    public function __construct(private AccountSettings $settings) {}

    public function open(User $user): ?object
    {
        return DB::table('account_closure_requests')->where('user_id', $user->getKey())->where('state', 'requested')->first();
    }

    public function request(User $user, string $password): void
    {
        $this->settings->assertPassword($user, $password);
        if ($this->open($user) !== null) {
            throw new AccountConflict('Une demande de fermeture est déjà en cours.');
        }
        DB::table('account_closure_requests')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->getKey(), 'state' => 'requested', 'requested_at' => now(),
            'due_at' => now()->addDays((int) config('freeci.account.closure_grace_days'))]);
        SecurityLog::record('account_closure_requested', $user->getKey());
        AccountMail::send($user->email, 'demande de fermeture de compte', ['Vous avez demandé la fermeture de votre compte FreeCI. Elle ne sera exécutée qu’après un délai de réflexion et seulement si aucune obligation n’est en cours. Vous pouvez annuler depuis votre espace, rubrique Compte.']);
    }

    public function cancel(User $user): void
    {
        DB::table('account_closure_requests')->where('user_id', $user->getKey())->where('state', 'requested')->update(['state' => 'cancelled', 'cancelled_at' => now()]);
        SecurityLog::record('account_closure_cancelled', $user->getKey());
    }

    /** @return list<string> obstacles à la fermeture, formulés pour l'utilisateur */
    public function blockers(string $userId): array
    {
        $b = [];
        $orders = DB::table('orders')->where(fn ($q) => $q->where('client_id', $userId)->orWhere('freelancer_id', $userId));
        if ((clone $orders)->whereNotIn('state', self::TERMINAL_ORDERS)->exists()) {
            $b[] = 'Des commandes ou demandes sont encore en cours (à terminer, annuler ou laisser expirer).';
        }
        $live = (clone $orders)->where('environment', 'live');
        if ((clone $live)->where('state', 'closed')->where('closure_reason', 'validated')->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments')->whereColumn('payments.order_id', 'orders.id')->where('payments.state', 'confirmed'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_operations')->whereColumn('financial_operations.order_id', 'orders.id')->where('kind', 'payout')->where('state', 'confirmed'))->exists()) {
            $b[] = 'Un reversement de commande réelle reste à finaliser.';
        }
        if ((clone $live)->where('closure_reason', 'cancelled_after_payment')->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments')->whereColumn('payments.order_id', 'orders.id')->where('payments.state', 'confirmed'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_operations')->whereColumn('financial_operations.order_id', 'orders.id')->where('kind', 'refund')->where('state', 'confirmed'))->exists()) {
            $b[] = 'Un remboursement de commande réelle reste à finaliser.';
        }
        if (DB::table('financial_operations')->whereIn('order_id', (clone $orders)->select('id'))->whereNotIn('state', ['confirmed', 'rejected', 'cancelled'])->exists()) {
            $b[] = 'Une opération financière est en cours de traitement.';
        }
        if (DB::table('support_cases')->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('counterparty_id', $userId))->whereIn('status', ['open', 'in_review', 'awaiting_requester', 'awaiting_party', 'decided'])->exists()) {
            $b[] = 'Un dossier d’assistance ou un litige n’est pas clos.';
        }
        if (DB::table('missions')->where('client_id', $userId)->whereIn('status', ['in_review', 'open', 'reserved', 'awarded', 'selection_ended', 'suspended'])->exists()) {
            $b[] = 'Une de vos missions est encore ouverte ou en examen : fermez-la d’abord.';
        }
        if (DB::table('proposals')->where('freelancer_id', $userId)->where('state', 'active')->exists()) {
            $b[] = 'Une de vos propositions est encore active : retirez-la d’abord.';
        }
        if (DB::table('staff_grants')->where('user_id', $userId)->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
            $b[] = 'Une habilitation du personnel est en vigueur : elle doit d’abord être révoquée.';
        }
        if (DB::table('users')->where('id', $userId)->whereNotNull('suspended_at')->exists()) {
            $b[] = 'Votre compte est suspendu : contactez l’assistance avant toute fermeture.';
        }

        return $b;
    }

    /** @return 'closed'|'blocked'|'none' */
    public function process(object $request): string
    {
        $blockers = $this->blockers($request->user_id);
        if ($blockers !== []) {
            DB::table('account_closure_requests')->where('id', $request->id)->update(['last_blockers' => json_encode($blockers, JSON_UNESCAPED_UNICODE), 'last_checked_at' => now()]);

            return 'blocked';
        }
        $user = User::query()->find($request->user_id);
        if ($user === null || $user->closed_at !== null) {
            return 'none';
        }
        $oldEmail = $user->email;
        DB::transaction(function () use ($request, $user) {
            $this->anonymize($user->getKey());
            DB::table('account_closure_requests')->where('id', $request->id)->update(['state' => 'completed', 'completed_at' => now(), 'last_blockers' => null, 'last_checked_at' => now()]);
        });
        SecurityLog::record('account_closed', $user->getKey());
        AccountMail::send($oldEmail, 'compte fermé', ['Votre compte FreeCI est fermé et ses informations personnelles sont anonymisées. Les éléments qui doivent être conservés (commandes, écritures financières, dossiers) restent rattachés à un compte anonyme.']);

        return 'closed';
    }

    private function anonymize(string $id): void
    {
        $profile = DB::table('freelance_profiles')->where('user_id', $id)->value('id');
        if ($profile !== null) {
            DB::table('services')->where('freelance_profile_id', $profile)->whereIn('status', ['draft', 'in_review', 'published', 'suspended'])->update(['status' => 'archived', 'updated_at' => now()]);
            DB::table('freelance_profiles')->where('id', $profile)->update(['display_name' => 'Ancien freelance', 'slug' => 'ferme-'.Str::lower(Str::random(12)), 'headline' => '', 'bio' => null, 'city' => null, 'skills' => '[]', 'published_at' => null, 'updated_at' => now()]);
            DB::table('favorites')->where('kind', 'freelance')->where('target_id', $profile)->delete();
        }
        DB::table('favorites')->where('user_id', $id)->delete();
        app(ProfilePhotos::class)->purgeAll($id);                // photo de profil : fichiers effacés
        DB::table('app_notifications')->where('user_id', $id)->delete();
        DB::table('notification_preferences')->where('user_id', $id)->delete();
        DB::table('contact_blocks')->where('blocker_id', $id)->orWhere('blocked_id', $id)->delete();
        DB::table('two_factor_recovery_codes')->where('user_id', $id)->delete();
        DB::table('email_change_requests')->where('user_id', $id)->delete();
        DB::table('sessions')->where('user_id', $id)->delete();
        DB::table('payout_beneficiaries')->where('user_id', $id)->whereIn('status', ['pending', 'verified'])->update(['status' => 'disabled', 'disabled_at' => now(), 'updated_at' => now()]);
        $email = (string) DB::table('users')->where('id', $id)->value('email');
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('users')->where('id', $id)->update([
            'name' => 'Compte fermé', 'email' => 'ferme-'.$id.'@compte-ferme.invalid', 'email_verified_at' => null, 'password' => Hash::make(Str::random(64)), 'remember_token' => null,
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null, 'closed_at' => now(), 'updated_at' => now(),
        ]);
    }
}
