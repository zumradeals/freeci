<?php

namespace App\Modules\Admin\Queries;

use App\Modules\Catalog\Actions\AvailabilityManager;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comptes : seulement les informations nécessaires à leur gestion (identité du compte, rôles, état, volumes). Jamais de mot de passe,
 * de secret MFA, de message, de brief, de fichier ni de détail de commande.
 */
final class UsersQuery
{
    private const FINAL = ['cancelled', 'expired', 'closed'];

    /** Compteurs des onglets (comptes réels, sans filtre de recherche). @return array{all: int, active: int, suspended: int, unverified: int} */
    public function counts(): array
    {
        $r = DB::table('users')->selectRaw('count(*) as a, count(*) filter (where suspended_at is null) as b, count(*) filter (where suspended_at is not null) as c, count(*) filter (where email_verified_at is null) as d')->first();

        return ['all' => (int) $r->a, 'active' => (int) $r->b, 'suspended' => (int) $r->c, 'unverified' => (int) $r->d];
    }

    /** @param array{q?: ?string, status?: ?string, role?: ?string} $f */
    public function search(array $f, int $perPage): LengthAwarePaginator
    {
        $like = isset($f['q']) && trim($f['q']) !== '' ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($f['q']), 0, 80)).'%' : null;
        $q = DB::table('users')->orderBy('name')->orderBy('id')
            ->when($like, fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)))
            ->when(($f['status'] ?? null) === 'suspended', fn ($w) => $w->whereNotNull('suspended_at'))
            ->when(($f['status'] ?? null) === 'unverified', fn ($w) => $w->whereNull('email_verified_at'))
            ->when(($f['status'] ?? null) === 'active', fn ($w) => $w->whereNull('suspended_at'))
            ->when(in_array($f['role'] ?? null, ['client', 'freelance'], true), fn ($w) => $w->whereExists(fn ($x) => $x->select(DB::raw(1))->from('account_roles')->whereColumn('account_roles.user_id', 'users.id')->where('account_roles.role', $f['role'])))
            ->when(($f['role'] ?? null) === 'admin', fn ($w) => $w->whereExists(fn ($x) => $x->select(DB::raw(1))->from('staff_grants')->whereColumn('staff_grants.user_id', 'users.id')->whereNull('revoked_at')->where(fn ($y) => $y->whereNull('expires_at')->orWhere('expires_at', '>', now()))));

        return $q->paginate($perPage, ['id', 'name', 'email', 'email_verified_at', 'suspended_at', 'created_at', 'is_demo'])->withQueryString()->through(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'verified' => $u->email_verified_at !== null, 'suspended' => $u->suspended_at !== null,
            'since' => Dates::format(Carbon::parse($u->created_at)), 'demo' => (bool) $u->is_demo,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function detail(string $id): ?array
    {
        $u = DB::table('users')->where('id', $id)->first();
        if ($u === null) {
            return null;
        }
        $orders = fn (string $col) => DB::table('orders')->where($col, $id);
        $history = DB::table('account_restrictions')->join('users as a', 'a.id', '=', 'account_restrictions.actor_id')->where('account_restrictions.user_id', $id)->orderByDesc('account_restrictions.id')
            ->get(['account_restrictions.action', 'account_restrictions.reason', 'account_restrictions.created_at', 'a.name as actor'])
            ->map(fn ($h) => ['action' => $h->action === 'suspended' ? 'Suspendu' : 'Réactivé', 'reason' => $h->reason, 'actor' => $h->actor, 'when' => Dates::format(Carbon::parse($h->created_at))])->all();
        $admin = DB::table('staff_grants')->where('user_id', $id)->where('capability', 'administrator')->whereNull('revoked_at')->where(fn ($y) => $y->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();

        $photos = DB::table('profile_photos')->leftJoin('users as a', 'a.id', '=', 'profile_photos.removed_by')->where('profile_photos.user_id', $id)->orderByDesc('profile_photos.created_at')->limit(20)
            ->get(['profile_photos.id', 'profile_photos.state', 'profile_photos.created_at', 'profile_photos.ended_at', 'profile_photos.removal_reason', 'a.name as remover']);
        $photoLabels = ['active' => 'Photo en ligne', 'replaced' => 'Photo remplacée', 'deleted' => 'Photo supprimée par la personne', 'removed' => 'Photo retirée par l’administration', 'closed' => 'Photo effacée (compte fermé)'];
        $photoHistory = $photos->map(fn ($p) => ['what' => $photoLabels[$p->state] ?? $p->state, 'when' => Dates::format(Carbon::parse($p->state === 'active' ? $p->created_at : ($p->ended_at ?? $p->created_at))),
            'reason' => $p->removal_reason, 'by' => $p->remover])->all();
        $active = $photos->firstWhere('state', 'active');
        $items = DB::table('portfolio_items')->leftJoin('users as a', 'a.id', '=', 'portfolio_items.removed_by')->where('portfolio_items.user_id', $id)->whereIn('portfolio_items.state', ['active', 'removed'])
            ->orderByDesc('portfolio_items.created_at')->limit(30)->get(['portfolio_items.id', 'portfolio_items.state', 'portfolio_items.title', 'portfolio_items.description', 'portfolio_items.ended_at', 'portfolio_items.removal_reason', 'a.name as remover']);
        $portfolio = $items->where('state', 'active')->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'description' => $r->description])->values()->all();
        $portfolioRemoved = $items->where('state', 'removed')->map(fn ($r) => ['title' => $r->title, 'when' => Dates::format(Carbon::parse($r->ended_at)), 'reason' => $r->removal_reason, 'by' => $r->remover])->values()->all();

        return [
            'photo' => $active === null ? null : ['id' => $active->id, 'since' => Dates::format(Carbon::parse($active->created_at))], 'photoHistory' => $photoHistory, 'portfolio' => $portfolio, 'portfolioRemoved' => $portfolioRemoved,
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'verifiedAt' => $u->email_verified_at === null ? null : Dates::format(Carbon::parse($u->email_verified_at)),
            'suspended' => $u->suspended_at !== null, 'suspendedAt' => $u->suspended_at === null ? null : Dates::format(Carbon::parse($u->suspended_at)),
            'since' => Dates::format(Carbon::parse($u->created_at)), 'demo' => (bool) $u->is_demo, 'admin' => $admin, 'staff' => DB::table('staff_grants')->where('user_id', $id)->whereNull('revoked_at')->where(fn ($y) => $y->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists(), 'mfa' => $u->two_factor_confirmed_at !== null,
            'availability' => app(AvailabilityManager::class)->stateFor((string) $id), 'roles' => DB::table('account_roles')->where('user_id', $id)->pluck('role')->all(),
            'ordersAsClient' => [$orders('client_id')->count(), $orders('client_id')->whereNotIn('state', self::FINAL)->count()],
            'ordersAsFreelancer' => [$orders('freelancer_id')->count(), $orders('freelancer_id')->whereNotIn('state', self::FINAL)->count()],
            'services' => DB::table('services')->join('freelance_profiles as p', 'p.id', '=', 'services.freelance_profile_id')->where('p.user_id', $id)->count(),
            'missions' => DB::table('missions')->where('client_id', $id)->count(),
            'history' => $history,
        ];
    }
}
