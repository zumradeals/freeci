<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use Illuminate\Support\Facades\DB;

/**
 * Export des données personnelles de l'utilisateur CONNECTÉ, construit à la demande (rien n'est stocké ni envoyé par courriel).
 * Périmètre : ce que la personne a elle-même fourni ou produit, et ce qui la concerne. N'y figurent JAMAIS : les messages ou données privées des
 * autres personnes, les identifiants de leurs comptes, la destination de reversement en clair, les secrets (mot de passe, double authentification),
 * les jetons de session, ni le contenu interne des dossiers du personnel (notes, décisions internes).
 */
final class ExportPersonalData
{
    public function build(User $user): array
    {
        $id = $user->getKey();
        $profile = DB::table('freelance_profiles')->where('user_id', $id)->first();
        $own = fn (string $table, string $col) => DB::table($table)->where($col, $id);

        $data = [
            'genere_le' => now()->toIso8601String(),
            'avertissement' => 'Export personnel : il ne contient que vos propres données. Les échanges et informations des autres personnes n’y figurent pas.',
            'compte' => [
                'identifiant' => $id, 'nom' => $user->name, 'adresse_email' => $user->email, 'adresse_verifiee_le' => $user->email_verified_at?->toIso8601String(),
                'cree_le' => $user->created_at?->toIso8601String(), 'roles' => DB::table('account_roles')->where('user_id', $id)->pluck('role')->all(),
                'double_authentification_active' => $user->hasTwoFactor(), 'compte_suspendu' => $user->isSuspended(),
            ],
            'profil_freelance' => $profile === null ? null : [
                'nom_affiche' => $profile->display_name, 'accroche' => $profile->headline, 'presentation' => $profile->bio, 'ville' => $profile->city,
                'competences' => json_decode((string) $profile->skills, true), 'publie_le' => $profile->published_at, 'adresse_publique' => $profile->slug,
            ],
            'services' => $profile === null ? [] : DB::table('services')->where('freelance_profile_id', $profile->id)->get(['title', 'status', 'price_xof', 'delivery_days', 'summary', 'created_at'])->all(),
            'missions' => $own('missions', 'client_id')->orderBy('created_at')->get(['slug', 'status', 'created_at', 'closed_at'])->all(),
            'propositions' => $own('proposals', 'freelancer_id')->orderBy('created_at')->get(['id', 'state', 'created_at'])->map(fn ($p) => [
                'etat' => $p->state, 'cree_le' => $p->created_at,
                'versions' => DB::table('proposal_versions')->where('proposal_id', $p->id)->orderBy('number')->get(['number', 'price_xof', 'delivery_days', 'message', 'submitted_at'])->all(),
            ])->all(),
            'commandes' => DB::table('orders')->where(fn ($q) => $q->where('client_id', $id)->orWhere('freelancer_id', $id))->orderBy('created_at')
                ->get(['id', 'reference', 'client_id', 'state', 'origin', 'environment', 'requested_at', 'accepted_at', 'started_at', 'due_at', 'closed_at', 'closure_reason'])
                ->map(fn ($o) => [
                    'reference' => $o->reference, 'votre_role' => $o->client_id === $id ? 'client' : 'freelance', 'etat' => $o->state, 'origine' => $o->origin,
                    'environnement' => $o->environment === 'live' ? 'reel' : 'test', 'demandee_le' => $o->requested_at, 'acceptee_le' => $o->accepted_at,
                    'demarree_le' => $o->started_at, 'echeance' => $o->due_at, 'cloturee_le' => $o->closed_at, 'motif_de_cloture' => $o->closure_reason,
                    'paiements' => $o->client_id === $id ? DB::table('payments')->where('order_id', $o->id)->get(['amount_xof', 'currency', 'state', 'environment', 'created_at', 'confirmed_at'])->all() : [],
                ])->all(),
            'messages_envoyes' => DB::table('messages')->join('conversations as c', 'c.id', '=', 'messages.conversation_id')->where('messages.sender_id', $id)->orderBy('messages.created_at')
                ->get(['c.context_title as conversation', 'messages.body', 'messages.created_at'])->all(),
            'avis_ecrits' => $own('reviews', 'author_id')->get(['rating', 'comment', 'created_at', 'visible_at', 'counts_public'])->all(),
            'avis_recus' => DB::table('reviews')->where('subject_id', $id)->where('counts_public', true)->get(['rating', 'comment', 'visible_at'])->all(),
            'reponses_aux_avis' => $own('review_responses', 'author_id')->get(['body', 'created_at'])->all(),
            'photos_de_profil' => $own('profile_photos', 'user_id')->orderBy('created_at')->get(['state', 'mime', 'created_at', 'ended_at', 'removal_reason'])->map(fn ($p) => ['etat' => $p->state, 'format' => $p->mime, 'ajoutee_le' => $p->created_at, 'terminee_le' => $p->ended_at, 'motif_de_retrait' => $p->removal_reason])->all(),
            'realisations' => $own('portfolio_items', 'user_id')->orderBy('created_at')->get(['state', 'title', 'description', 'year', 'created_at', 'ended_at', 'removal_reason'])->map(fn ($r) => ['etat' => $r->state, 'titre' => $r->title, 'description' => $r->description, 'annee' => $r->year, 'ajoutee_le' => $r->created_at, 'terminee_le' => $r->ended_at, 'motif_de_retrait' => $r->removal_reason])->all(),
            'favoris' => $own('favorites', 'user_id')->get(['kind', 'created_at'])->all(),
            'dossiers_assistance' => DB::table('support_cases')->where('requester_id', $id)->orderBy('opened_at')->get(['id', 'reference', 'kind', 'status', 'subject', 'opened_at', 'closed_at'])
                ->map(fn ($c) => [
                    'reference' => $c->reference, 'type' => $c->kind, 'statut' => $c->status, 'objet' => $c->subject, 'ouvert_le' => $c->opened_at, 'clos_le' => $c->closed_at,
                    'vos_messages' => DB::table('support_messages')->where('case_id', $c->id)->where('author_id', $id)->where('visibility', '<>', 'internal')->orderBy('created_at')->get(['body', 'created_at'])->all(),
                ])->all(),
            'notifications' => $own('app_notifications', 'user_id')->orderBy('created_at')->get(['type', 'title', 'body', 'created_at', 'read_at'])->all(),
            'preferences_de_notification' => $own('notification_preferences', 'user_id')->get(['type', 'in_app', 'email'])->all(),
            'destination_de_reversement' => $own('payout_beneficiaries', 'user_id')->get(['method', 'holder_name', 'status', 'created_at'])->map(fn ($b) => (array) $b + ['destination' => 'non exportée (donnée chiffrée)'])->all(),
            'sessions_ouvertes' => $own('sessions', 'user_id')->get(['ip_address', 'user_agent', 'last_activity'])->all(),
            'evenements_de_securite' => $own('security_events', 'user_id')->orderByDesc('created_at')->limit(200)->get(['type', 'ip', 'created_at'])->all(),
            'contacts_bloques' => ['nombre' => $own('contact_blocks', 'blocker_id')->count()],
        ];
        SecurityLog::record('data_exported', $id);

        return $data;
    }
}
