<?php

namespace App\Modules\Accounts\Referrals;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Commission\CommissionGrants;
use App\Modules\Finance\Commission\CommissionTerms;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Parrainage (F-14). Rattachement UNIQUE à l'inscription ; récompense seulement à la première commande RÉELLE du filleul, payée, livrée et validée par le client. La récompense est une
 * commission offerte (FreeCI renonce à sa part) pour le filleul ET le parrain ; le prix payé par le client ne change jamais.
 */
final class Referrals
{
    public function __construct(private ReferralCodes $codes, private CommissionGrants $grants, private Notify $notify) {}

    /** Adresse normalisée : minuscules, sans « +étiquette », points ignorés chez Gmail (deux écritures d'une même boîte ne se parrainent pas). */
    public static function normalizeEmail(string $email): string
    {
        $e = mb_strtolower(trim($email));
        [$local, $domain] = array_pad(explode('@', $e, 2), 2, '');
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public function hasCampaign(string $userId): bool
    {
        return DB::table('commission_grants')->where('user_id', $userId)->where('source', 'campaign')->exists();
    }

    /** Contrôle d'un code saisi à l'inscription. @return object le propriétaire @throws ValidationException */
    public function check(?string $input, string $newEmail): object
    {
        $owner = $this->codes->ownerOf($input);
        if ($owner === null) {
            throw ValidationException::withMessages(['referral_code' => 'Code de parrainage inconnu : vérifiez-le ou laissez le champ vide.']);
        }
        if (self::normalizeEmail((string) $owner->email) === self::normalizeEmail($newEmail)) {
            throw ValidationException::withMessages(['referral_code' => 'Ce code ne peut pas être utilisé avec cette adresse e-mail.']);
        }

        return $owner;
    }

    /** Rattache le nouveau compte à son parrain (une seule fois). Ne lève rien : le parrainage ne bloque jamais l'inscription déjà acceptée. */
    public function attach(User $referee, ?string $input): bool
    {
        $owner = $this->codes->ownerOf($input);
        if ($owner === null || (string) $owner->user_id === (string) $referee->getKey() || self::normalizeEmail((string) $owner->email) === self::normalizeEmail($referee->email)) {
            return false;
        }

        return DB::table('referrals')->insertOrIgnore(['id' => (string) Str::uuid(), 'referrer_id' => $owner->user_id, 'referee_id' => $referee->getKey(), 'code' => $owner->code, 'state' => 'registered', 'created_at' => now()]) === 1;
    }

    /**
     * Commande validée EXPLICITEMENT par le client (dans la transaction de validation) : qualifie le parrainage de l'une ou l'autre des parties si c'est sa première commande réelle.
     * Jamais pour une commande de test, jamais si le parrain est l'autre partie de la même commande, jamais pour un compte suspendu.
     */
    public function onOrderValidated(string $orderId): void
    {
        $o = DB::table('orders')->where('id', $orderId)->first(['id', 'client_id', 'freelancer_id', 'environment']);
        if ($o === null || $o->environment !== 'live' || ! config('freeci.referral.enabled')) {
            return;
        }
        foreach ([[$o->client_id, $o->freelancer_id], [$o->freelancer_id, $o->client_id]] as [$party, $other]) {
            $ref = DB::table('referrals')->where('referee_id', $party)->where('state', 'registered')->lockForUpdate()->first();
            if ($ref === null || (string) $ref->referrer_id === (string) $other) {
                continue;
            }
            if (DB::table('users')->whereIn('id', [$ref->referrer_id, $ref->referee_id])->whereNotNull('suspended_at')->exists()) {
                continue;
            }
            $this->qualify($ref, $o->id);
        }
    }

    private function qualify(object $ref, string $orderId): void
    {
        $n = (int) config('freeci.referral.free_orders');
        $rate = min((int) config('freeci.referral.rate_bp'), max(0, CommissionTerms::baseBp() - 1));
        $rewardsReferrer = DB::table('referrals')->where('referrer_id', $ref->referrer_id)->where('referrer_rewarded', true)->count() < (int) config('freeci.referral.max_qualified');
        DB::table('referrals')->where('id', $ref->id)->update(['state' => 'qualified', 'qualified_at' => now(), 'qualifying_order_id' => $orderId, 'referrer_rewarded' => $rewardsReferrer]);
        $this->grants->create((string) $ref->referee_id, 'referral_referee', ['referral_id' => $ref->id], $rate, $n);
        $label = $rate === 0 ? '0 %' : ($rate / 100).' %';
        ($this->notify)((string) $ref->referee_id, 'referral_reward', 'referral_reward:'.$ref->id.':referee', 'Récompense de parrainage', "{$n} commandes à {$label} de commission vous sont attribuées.", 'account.referral');
        if ($rewardsReferrer) {
            $this->grants->create((string) $ref->referrer_id, 'referral_referrer', ['referral_id' => $ref->id], $rate, $n);
            ($this->notify)((string) $ref->referrer_id, 'referral_reward', 'referral_reward:'.$ref->id.':referrer', 'Un filleul est qualifié', "{$n} commandes à {$label} de commission vous sont attribuées.", 'account.referral');
        }
    }

    /**
     * Page « Parrainage » d'un compte.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $uid = (string) $user->getKey();
        $code = $this->codes->forUser($uid);
        $max = (int) config('freeci.referral.max_qualified');
        $rows = DB::table('referrals as r')->join('users as u', 'u.id', '=', 'r.referee_id')->where('r.referrer_id', $uid)->orderByDesc('r.created_at')->orderBy('r.id')->limit(200)
            ->get(['r.id', 'r.state', 'r.qualified_at', 'r.referrer_rewarded', 'r.created_at', 'u.id as uid', 'u.name']);
        $items = $rows->map(function ($r) {
            $paid = DB::table('orders as o')->where('o.environment', 'live')->where(fn ($q) => $q->where('o.client_id', $r->uid)->orWhere('o.freelancer_id', $r->uid))
                ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments')->whereColumn('payments.order_id', 'o.id')->where('payments.state', 'confirmed'))->exists();
            $isF = DB::table('account_roles')->where('user_id', $r->uid)->where('role', 'freelance')->exists();
            [$state, $label, $tone] = $r->state === 'qualified' ? ['qualified', 'Qualifié', 'success'] : ($paid ? ['progress', 'Première commande en cours', 'info'] : ['registered', 'Inscrit', 'neutral']);

            return ['name' => ReferralCodes::shortName((string) $r->name), 'since' => Carbon::parse($r->created_at)->translatedFormat('j M Y'), 'state' => $state, 'label' => $label, 'tone' => $tone,
                'freelance' => $isF, 'rewarded' => $r->state === 'qualified' ? Carbon::parse($r->qualified_at)->translatedFormat('j M') : null, 'capped' => $r->state === 'qualified' && ! $r->referrer_rewarded];
        })->all();

        return [
            'code' => $code, 'link' => url('/inscription').'?parrain='.$code, 'enabled' => (bool) config('freeci.referral.enabled'),
            'available' => CommissionTerms::available($uid), 'qualified' => (int) DB::table('referrals')->where('referrer_id', $uid)->where('referrer_rewarded', true)->count(), 'max' => $max,
            'registered' => count($items), 'items' => $items, 'isFreelance' => $user->hasRole('freelance'), 'freeOrders' => (int) config('freeci.referral.free_orders'),
            'rate' => (int) config('freeci.referral.rate_bp') / 100, 'hasCampaign' => DB::table('commission_grants')->where('user_id', $uid)->where('source', 'campaign')->exists(),
        ];
    }
}
