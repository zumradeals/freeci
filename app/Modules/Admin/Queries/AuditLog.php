<?php

namespace App\Modules\Admin\Queries;

use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Journal des actions administratives et des événements de sécurité (lecture seule, filtrable, paginé). */
final class AuditLog
{
    public const ACTIONS = [
        'service.approve' => 'Service approuvé', 'service.request_changes' => 'Service : correction demandée', 'service.suspend' => 'Service suspendu', 'service.reinstate' => 'Service remis en ligne',
        'mission.approve' => 'Mission approuvée', 'mission.request_changes' => 'Mission : correction demandée', 'mission.suspend' => 'Mission suspendue', 'mission.reinstate' => 'Mission remise en ligne',
        'payment.reconciliation_review' => 'Paiement : dossier de rapprochement examiné', 'user.suspend' => 'Compte suspendu', 'user.photo_remove' => 'Photo de profil retirée', 'user.portfolio_remove' => 'Réalisation retirée du portfolio', 'user.reactivate' => 'Compte réactivé',
        'case.claim' => 'Dossier : prise en charge', 'case.assign' => 'Dossier : affectation', 'case.release' => 'Dossier : fin d’affectation', 'case.open' => 'Dossier : ouverture motivée',
        'case.view' => 'Dossier : consultation du contenu', 'case.download' => 'Dossier : pièce consultée', 'case.status' => 'Dossier : changement d’état', 'case.priority' => 'Dossier : priorité',
        'category.create' => 'Catégorie créée', 'category.update' => 'Catégorie modifiée', 'category.move' => 'Catégorie déplacée', 'category.archive' => 'Catégorie archivée', 'category.restore' => 'Catégorie rétablie', 'category.delete' => 'Catégorie supprimée', 'menu.customize' => 'Menu personnalisé', 'menu.add' => 'Lien de menu ajouté', 'menu.update' => 'Lien de menu modifié', 'menu.move' => 'Lien de menu déplacé', 'menu.delete' => 'Lien de menu supprimé', 'menu.reset' => 'Menu d’origine rétabli', 'admin.grant' => 'Habilitation d’administrateur accordée', 'admin.revoke' => 'Habilitation d’administrateur retirée', 'support.grant' => 'Habilitation support accordée', 'support.revoke' => 'Habilitation support retirée', 'category.feature' => 'Catégorie mise en avant', 'category.unfeature' => 'Catégorie retirée de la mise en avant',
        'demo.install' => 'Démonstration installée', 'demo.purge' => 'Démonstration retirée',
        'case.close' => 'Dossier : clôture', 'case.decide' => 'Dossier : décision', 'case.from_follow_up' => 'Dossier ouvert depuis un besoin de suivi',
    ];

    public const SECURITY = [
        'login_failed' => 'Connexion échouée', 'login_locked' => 'Connexion bloquée (trop de tentatives)', 'admin_denied' => 'Accès administration refusé', 'email_verification_sent' => 'Lien de vérification envoyé',
        'email_verification_unavailable' => 'Vérification impossible (courrier non configuré)', 'email_verification_failed' => 'Échec d’envoi du lien de vérification', 'email_verified' => 'Adresse vérifiée', 'email_verification_rejected' => 'Lien de vérification rejeté',
        'mfa_enabled' => 'Double authentification activée', 'mfa_passed' => 'Double authentification franchie', 'mfa_failed' => 'Code de double authentification refusé', 'mfa_enroll_failed' => 'Code d’activation refusé',
        'mfa_locked' => 'Double authentification bloquée', 'mfa_recovery_used' => 'Code de récupération utilisé', 'mfa_codes_regenerated' => 'Codes de récupération régénérés', 'mfa_reset' => 'Double authentification réinitialisée (console)',
        'reauth_ok' => 'Identité reconfirmée', 'reauth_failed' => 'Reconfirmation refusée', 'admin_granted' => 'Habilitation accordée (console)', 'admin_revoked' => 'Habilitation révoquée (console)', 'support_granted' => 'Habilitation support accordée (console)', 'support_revoked' => 'Habilitation support révoquée (console)',
    ];

    public function recent(int $n): array
    {
        return $this->base([])->limit($n)->get()->map(fn ($r) => $this->row($r))->all();
    }

    /** @param array{actor?: ?string, action?: ?string, result?: ?string, from?: ?string, to?: ?string, q?: ?string} $f */
    public function actions(array $f, int $perPage): LengthAwarePaginator
    {
        return $this->base($f)->paginate($perPage)->withQueryString()->through(fn ($r) => $this->row($r));
    }

    /** @param array{type?: ?string, from?: ?string, to?: ?string} $f */
    public function security(array $f, int $perPage): LengthAwarePaginator
    {
        $q = DB::table('security_events')->leftJoin('users', 'users.id', '=', 'security_events.user_id')->orderByDesc('security_events.id')
            ->when($f['type'] ?? null, fn ($w, $t) => $w->where('security_events.type', $t))
            ->when($f['from'] ?? null, fn ($w, $d) => $w->where('security_events.created_at', '>=', $this->day($d)?->startOfDay()))
            ->when($f['to'] ?? null, fn ($w, $d) => $w->where('security_events.created_at', '<=', $this->day($d)?->endOfDay()))
            ->select('security_events.*', 'users.name as user_name');

        return $q->paginate($perPage)->withQueryString()->through(fn ($r) => [
            'when' => Dates::format(Carbon::parse($r->created_at)), 'type' => self::SECURITY[$r->type] ?? $r->type, 'who' => $r->user_name ?? '—', 'ip' => $r->ip ?? 'console',
            'detail' => $this->detail($r->meta),
        ]);
    }

    private function base(array $f)
    {
        return DB::table('admin_actions')->join('users', 'users.id', '=', 'admin_actions.actor_id')->orderByDesc('admin_actions.id')
            ->when($f['actor'] ?? null, fn ($w, $a) => $w->where(fn ($x) => $x->where('users.name', 'ilike', '%'.$this->like($a).'%')->orWhere('users.email', 'ilike', '%'.$this->like($a).'%')))
            ->when($f['action'] ?? null, fn ($w, $a) => $w->where('admin_actions.action', $a))
            ->when($f['result'] ?? null, fn ($w, $r) => $w->where('admin_actions.result', $r))
            ->when($f['q'] ?? null, fn ($w, $q) => $w->where(fn ($x) => $x->where('admin_actions.target_label', 'ilike', '%'.$this->like($q).'%')->orWhere('admin_actions.target_id', $q)))
            ->when($f['from'] ?? null, fn ($w, $d) => $w->where('admin_actions.created_at', '>=', $this->day($d)?->startOfDay()))
            ->when($f['to'] ?? null, fn ($w, $d) => $w->where('admin_actions.created_at', '<=', $this->day($d)?->endOfDay()))
            ->select('admin_actions.*', 'users.name as actor_name');
    }

    private function row(object $r): array
    {
        return [
            'when' => Dates::format(Carbon::parse($r->created_at)), 'actor' => $r->actor_name, 'action' => self::ACTIONS[$r->action] ?? $r->action, 'target' => $r->target_label ?? $r->target_id ?? '—',
            'type' => $r->target_type, 'reason' => $r->reason, 'result' => $r->result, 'detail' => $r->detail,
        ];
    }

    private function detail(?string $meta): ?string
    {
        $m = $meta === null ? [] : (json_decode($meta, true) ?: []);
        $parts = [];
        foreach (['channel' => 'canal', 'reason' => 'motif', 'remaining' => 'codes restants', 'by' => 'par'] as $k => $l) {
            if (isset($m[$k])) {
                $parts[] = $l.' : '.$m[$k];
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function day(string $d): ?Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', $d, 'Africa/Abidjan') ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function like(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($s), 0, 80));
    }
}
