<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Support\StatsPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exports CSV de gestion (F-17). Administrateur seulement, confirmation récente d'identité (route), journal d'audit à chaque export.
 * Minimisation : jamais de message, de brief, de fichier, de coordonnée bancaire, d'e-mail, de téléphone ni d'adresse IP. 50 000 lignes au plus.
 * Format Excel français : séparateur « ; », UTF-8 avec BOM, montants en francs entiers, dates à l'heure d'Abidjan, cellules protégées contre l'injection de formules.
 */
final class CsvExports
{
    public const MAX_ROWS = 50000;

    public static function maxRows(): int
    {
        return (int) config('freeci.exports.max_rows', self::MAX_ROWS);
    }

    public function __construct(private AdminAudit $audit) {}

    /** @return array<string, array{label: string, sub: string, icon: string, cols: list<string>}> */
    public static function catalogue(): array
    {
        return [
            'commandes' => ['label' => 'Commandes', 'sub' => 'Une ligne par commande créée dans la période', 'icon' => 'clipboard', 'cols' => ['référence', 'origine', 'état', 'environnement', 'créée le', 'clôturée le', 'motif de clôture', 'jalon', 'prix (FCFA)', 'délai (jours)', 'taux de commission (points de base)', 'commission (FCFA)']],
            'paiements' => ['label' => 'Paiements', 'sub' => 'Une ligne par paiement créé dans la période', 'icon' => 'card', 'cols' => ['commande', 'montant (FCFA)', 'état', 'fournisseur', 'environnement du paiement', 'créé le', 'confirmé le']],
            'operations' => ['label' => 'Remboursements et reversements', 'sub' => 'Opérations financières créées dans la période et leur état de confirmation', 'icon' => 'card', 'cols' => ['opération', 'commande', 'type', 'portée', 'montant (FCFA)', 'état', 'mode d’exécution', 'preuve', 'créée le', 'confirmée le']],
            'commissions' => ['label' => 'Commissions', 'sub' => 'Une ligne par commande clôturée (livraison validée) dans la période', 'icon' => 'list', 'cols' => ['commande', 'clôturée le', 'prix (FCFA)', 'taux (points de base)', 'commission (FCFA)', 'part du freelance (FCFA)']],
            'services' => ['label' => 'Services', 'sub' => 'Services créés dans la période', 'icon' => 'briefcase', 'cols' => ['identifiant', 'titre', 'catégorie', 'prix (FCFA)', 'état', 'créé le', 'publié le']],
            'missions' => ['label' => 'Missions', 'sub' => 'Missions créées dans la période', 'icon' => 'search', 'cols' => ['identifiant', 'titre', 'catégorie', 'budget (FCFA)', 'état', 'créée le', 'publiée le', 'propositions reçues']],
            'avis' => ['label' => 'Avis', 'sub' => 'Avis déposés dans la période', 'icon' => 'flag', 'cols' => ['commande', 'origine', 'note', 'déposé le', 'visible le', 'masqué', 'réponse du freelance']],
            'comptes' => ['label' => 'Comptes', 'sub' => 'Comptes créés dans la période, sans coordonnées', 'icon' => 'user', 'cols' => ['identifiant', 'rôles', 'inscrit le', 'adresse vérifiée', 'suspendu']],
        ];
    }

    /** Protège une cellule contre l'injection de formules (tableurs) et normalise les types. */
    public static function cell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'oui' : 'non';
        }
        $s = str_replace(["\r\n", "\r", "\n"], ' ', (string) $v);

        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t"], true) && ! preg_match('/^-?\d+([.,]\d+)?$/', $s) ? "'".$s : $s;
    }

    private static function date(mixed $v): ?string
    {
        return $v === null ? null : Carbon::parse($v)->timezone(StatsPeriod::TZ)->format('Y-m-d H:i');
    }

    private function envOrders(Builder $q, bool $live, string $col = 'orders.environment'): Builder
    {
        return $live ? $q->where($col, 'live') : $q->where($col, '<>', 'live');
    }

    /** @return array{0: Builder, 1: callable(object): list<mixed>} */
    private function dataset(string $name, StatsPeriod $p, bool $live): array
    {
        $in = fn (Builder $q, string $col) => $q->where($col, '>=', $p->from)->where($col, '<', $p->to);
        $demo = fn (Builder $q, string $t) => $live ? $q->where("{$t}.is_demo", false) : $q;

        return match ($name) {
            'commandes' => [
                $in($this->envOrders(DB::table('orders')->join('order_agreements as a', 'a.order_id', '=', 'orders.id')->leftJoin('mission_plan_items as mi', 'mi.id', '=', 'orders.milestone_item_id'), $live), 'orders.created_at')
                    ->select('orders.id', 'orders.reference', 'orders.origin', 'orders.state', 'orders.environment', 'orders.created_at', 'orders.closed_at', 'orders.closure_reason', 'mi.rank', 'a.price_xof', 'a.delivery_days', 'a.commission_bp')->orderBy('orders.created_at')->orderBy('orders.id'),
                fn ($r) => [$r->reference, $r->origin, $r->state, $r->environment, self::date($r->created_at), self::date($r->closed_at), $r->closure_reason, $r->rank, (int) $r->price_xof, (int) $r->delivery_days, $r->commission_bp, $r->commission_bp === null ? null : intdiv((int) $r->price_xof * (int) $r->commission_bp + 5000, 10000)],
            ],
            'paiements' => [
                $in($this->envOrders(DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id'), $live), 'payments.created_at')
                    ->select('payments.id', 'orders.reference', 'payments.amount_xof', 'payments.state', 'payments.provider', 'payments.environment', 'payments.created_at', 'payments.confirmed_at')->orderBy('payments.created_at')->orderBy('payments.id'),
                fn ($r) => [$r->reference, (int) $r->amount_xof, $r->state, $r->provider, $r->environment, self::date($r->created_at), self::date($r->confirmed_at)],
            ],
            'operations' => [
                $in($this->envOrders(DB::table('financial_operations as f')->join('orders', 'orders.id', '=', 'f.order_id'), $live), 'f.created_at')
                    ->select('f.id', 'f.reference as op', 'orders.reference', 'f.kind', 'f.scope', 'f.amount_xof', 'f.state', 'f.execution_mode', 'f.external_reference', 'f.provider_refund_reference', 'f.provider_proof_at', 'f.created_at', 'f.confirmed_at')->orderBy('f.created_at')->orderBy('f.id'),
                fn ($r) => [$r->op, $r->reference, $r->kind, $r->scope, (int) $r->amount_xof, $r->state, $r->execution_mode, ($r->external_reference || $r->provider_refund_reference || $r->provider_proof_at) ? 'oui' : 'non', self::date($r->created_at), self::date($r->confirmed_at)],
            ],
            'commissions' => [
                $in($this->envOrders(DB::table('orders')->join('order_agreements as a', 'a.order_id', '=', 'orders.id')->where('orders.closure_reason', 'validated'), $live), 'orders.closed_at')
                    ->select('orders.id', 'orders.reference', 'orders.closed_at', 'a.price_xof', 'a.commission_bp')->orderBy('orders.closed_at')->orderBy('orders.id'),
                function ($r) {
                    $c = intdiv((int) $r->price_xof * (int) $r->commission_bp + 5000, 10000);

                    return [$r->reference, self::date($r->closed_at), (int) $r->price_xof, (int) $r->commission_bp, $c, (int) $r->price_xof - $c];
                },
            ],
            'services' => [
                $in($demo(DB::table('services')->join('categories as c', 'c.id', '=', 'services.category_id'), 'services'), 'services.created_at')
                    ->select('services.id', 'services.title', 'c.name as category', 'services.price_xof', 'services.status', 'services.created_at', 'services.published_at')->orderBy('services.created_at')->orderBy('services.id'),
                fn ($r) => [$r->id, $r->title, $r->category, (int) $r->price_xof, $r->status, self::date($r->created_at), self::date($r->published_at)],
            ],
            'missions' => [
                $in($demo(DB::table('missions')->leftJoin('mission_versions as v', 'v.id', '=', 'missions.published_version_id')->leftJoin('categories as c', 'c.id', '=', 'v.category_id'), 'missions'), 'missions.created_at')
                    ->select('missions.id', 'v.title', 'c.name as category', 'v.budget_xof', 'missions.status', 'missions.created_at', 'missions.published_at')->selectRaw('(select count(*) from proposals pr where pr.mission_id = missions.id) as proposals')->orderBy('missions.created_at')->orderBy('missions.id'),
                fn ($r) => [$r->id, $r->title, $r->category, $r->budget_xof === null ? null : (int) $r->budget_xof, $r->status, self::date($r->created_at), self::date($r->published_at), (int) $r->proposals],
            ],
            'avis' => [
                $in($this->envOrders(DB::table('reviews')->join('orders', 'orders.id', '=', 'reviews.order_id'), $live), 'reviews.created_at')
                    ->select('reviews.id', 'orders.reference', 'reviews.origin', 'reviews.rating', 'reviews.created_at', 'reviews.visible_at', 'reviews.hidden_at')->selectRaw('exists(select 1 from review_responses rr where rr.review_id = reviews.id and rr.hidden_at is null) as replied')->orderBy('reviews.created_at')->orderBy('reviews.id'),
                fn ($r) => [$r->reference, $r->origin, (int) $r->rating, self::date($r->created_at), self::date($r->visible_at), $r->hidden_at !== null, (bool) $r->replied],
            ],
            'comptes' => [
                $in($demo(DB::table('users'), 'users'), 'users.created_at')->select('users.id', 'users.created_at', 'users.email_verified_at', 'users.suspended_at')
                    ->selectRaw("coalesce((select string_agg(ur.role, ',' order by ur.role) from account_roles ur where ur.user_id = users.id), '') as roles")->orderBy('users.created_at')->orderBy('users.id'),
                fn ($r) => [$r->id, $r->roles, self::date($r->created_at), $r->email_verified_at !== null, $r->suspended_at !== null],
            ],
            default => throw ValidationException::withMessages(['dataset' => 'Jeu de données inconnu.']),
        };
    }

    /** Vérifie les bornes, journalise l'export, puis fournit le flux CSV. @return array{0: string, 1: callable(): void, 2: int} [nom de fichier, écriture, nombre de lignes] */
    public function prepare(User $admin, string $dataset, StatsPeriod $p, bool $live): array
    {
        $cat = self::catalogue()[$dataset] ?? throw ValidationException::withMessages(['dataset' => 'Jeu de données inconnu.']);
        [$query, $map] = $this->dataset($dataset, $p, $live);
        $env = $live ? 'reel' : 'test';
        $label = sprintf('%s · %s · %s', $cat['label'], $p->label(), $live ? 'réel' : 'test et anciennes');

        try {
            $this->audit->assertActor($admin);
            $n = (clone $query)->count();
            if ($n > self::maxRows()) {
                throw new \DomainException('Cet export dépasse '.number_format(self::maxRows(), 0, ',', ' ').' lignes ('.number_format($n, 0, ',', ' ').') : réduisez la période.');
            }
        } catch (\Throwable $e) {
            $this->audit->record($admin, 'export.csv', 'export', $dataset, $label, null, 'refused', $e->getMessage());
            throw $e;
        }
        $this->audit->record($admin, 'export.csv', 'export', $dataset, $label, null, 'done', $n.' ligne'.($n > 1 ? 's' : ''));
        $file = sprintf('freeci-%s-%s-%s.csv', $dataset, $p->from->format('Y-m-d').'_'.$p->lastDay()->format('Y-m-d'), $env);

        $write = function () use ($query, $map, $cat) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'cell'], $cat['cols']), ';');
            foreach ($query->cursor() as $r) {
                fputcsv($out, array_map([self::class, 'cell'], $map($r)), ';');
            }
            fclose($out);
        };

        return [$file, $write, $n];
    }
}
