<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Commission\CommissionGrants;
use App\Modules\Finance\Commission\CommissionTerms;
use App\Modules\Missions\Exceptions\MissionConflict;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Codes promotionnels de campagne (F-14) : commission offerte (ou réduite) pour N commandes d'un freelance qui saisit le code. Créés et gérés par un administrateur (audit),
 * utilisés par un compte au plus une fois. Les attributions déjà faites et les accords déjà figés ne changent jamais.
 */
final class PromoCampaigns
{
    public function __construct(private AdminAudit $audit, private CommissionGrants $grants) {}

    public static function normalize(?string $code): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $code));
    }

    /** @param  array<string, mixed>  $in @return array<string, mixed> */
    private function validate(array $in): array
    {
        $e = [];
        $code = self::normalize($in['code'] ?? '');
        if (! preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
            $e['code'] = 'Le code compte de 4 à 20 lettres ou chiffres, sans accent ni espace.';
        }
        $pct = str_replace(',', '.', trim((string) ($in['rate'] ?? '')));
        if (! is_numeric($pct) || (float) $pct < 0 || (float) $pct * 100 >= CommissionTerms::baseBp()) {
            $e['rate'] = 'Le taux appliqué doit être inférieur à la commission normale ('.(CommissionTerms::baseBp() / 100).' %).';
        }
        $n = (int) ($in['free_orders'] ?? 0);
        if ($n < 1 || $n > 50) {
            $e['free_orders'] = 'De 1 à 50 commandes.';
        }
        $max = (int) ($in['max_uses'] ?? 0);
        if ($max < 1 || $max > 100000) {
            $e['max_uses'] = 'De 1 à 100 000 utilisations.';
        }
        $d = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && ($c = Carbon::createFromFormat('!Y-m-d', $v)) && $c->format('Y-m-d') === $v ? $v : null;
        $from = $d($in['starts_on'] ?? null);
        $to = $d($in['ends_on'] ?? null);
        if ($from === null || $to === null || $to < $from) {
            $e['starts_on'] = 'Dates invalides : la fin ne peut pas précéder le début.';
        }
        $note = trim((string) ($in['note'] ?? ''));
        if (mb_strlen($note) > 200) {
            $e['note'] = 'Au plus 200 caractères.';
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }

        return ['code' => $code, 'rate_bp' => (int) round((float) $pct * 100), 'free_orders' => $n, 'starts_on' => $from, 'ends_on' => $to, 'max_uses' => $max, 'note' => $note === '' ? null : $note];
    }

    /** @param  array<string, mixed>  $in */
    public function create(User $admin, array $in): string
    {
        $v = $this->validate($in);

        return $this->audit->run($admin, 'campaign.create', 'campaign', null, $v['code'], null, function () use ($admin, $v) {
            if (DB::table('promo_campaigns')->where('code', $v['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Ce code existe déjà.']);
            }
            $id = (string) Str::uuid();
            DB::table('promo_campaigns')->insert($v + ['id' => $id, 'uses' => 0, 'state' => 'active', 'created_by' => $admin->getKey(), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
    }

    /** Modification possible tant que le code n'a jamais servi. @param  array<string, mixed>  $in */
    public function update(User $admin, string $id, array $in): void
    {
        $v = $this->validate($in);
        $this->audit->run($admin, 'campaign.update', 'campaign', $id, $v['code'], null, function () use ($id, $v) {
            $c = DB::table('promo_campaigns')->where('id', $id)->lockForUpdate()->first() ?? throw new MissionConflict('Campagne introuvable.');
            if ((int) $c->uses > 0) {
                throw new MissionConflict('Ce code a déjà servi : il ne se modifie plus (suspendez-le et créez-en un autre).');
            }
            if (DB::table('promo_campaigns')->where('code', $v['code'])->where('id', '<>', $id)->exists()) {
                throw ValidationException::withMessages(['code' => 'Ce code existe déjà.']);
            }
            DB::table('promo_campaigns')->where('id', $id)->update($v + ['updated_at' => now()]);
        });
    }

    public function setState(User $admin, string $id, bool $active): void
    {
        $code = (string) DB::table('promo_campaigns')->where('id', $id)->value('code');
        $this->audit->run($admin, $active ? 'campaign.resume' : 'campaign.suspend', 'campaign', $id, $code, null, function () use ($id, $active) {
            if (DB::table('promo_campaigns')->where('id', $id)->update(['state' => $active ? 'active' : 'suspended', 'updated_at' => now()]) === 0) {
                throw new MissionConflict('Campagne introuvable.');
            }
        });
    }

    /** Saisie du code par un freelance : un seul code par compte, dans les dates, sous le plafond, campagne active. Message neutre en cas d'échec. */
    public function redeem(User $user, ?string $input): void
    {
        $code = self::normalize($input);
        $fail = fn () => ValidationException::withMessages(['promo_code' => 'Code inconnu, expiré ou épuisé.']);
        if ($code === '') {
            throw $fail();
        }
        DB::transaction(function () use ($user, $code, $fail) {
            DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first();            // sérialise les saisies d'un même compte
            if (DB::table('commission_grants')->where('user_id', $user->getKey())->where('source', 'campaign')->exists()) {
                throw ValidationException::withMessages(['promo_code' => 'Un seul code promotionnel par compte : vous en avez déjà utilisé un.']);
            }
            $today = Carbon::now('Africa/Abidjan')->format('Y-m-d');
            $c = DB::table('promo_campaigns')->where('code', $code)->lockForUpdate()->first();
            if ($c === null || $c->state !== 'active' || $c->starts_on > $today || $c->ends_on < $today || (int) $c->uses >= (int) $c->max_uses) {
                throw $fail();
            }
            DB::table('promo_campaigns')->where('id', $c->id)->update(['uses' => (int) $c->uses + 1, 'updated_at' => now()]);
            $this->grants->create((string) $user->getKey(), 'campaign', ['campaign_id' => $c->id], (int) $c->rate_bp, (int) $c->free_orders);
        });
    }
}
