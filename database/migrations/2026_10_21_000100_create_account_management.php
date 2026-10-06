<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 13 : gestion du compte (changement d'adresse, demande de fermeture), suivi des tâches planifiées.
 * Ajouts uniquement : aucune donnée existante n'est modifiée. La fermeture ANONYMISE la ligne `users` en place (les clés étrangères des
 * commandes, messages, écritures financières et dossiers restent valides) ; elle ne supprime jamais ce qui doit être conservé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn ($t) => $t->timestampTz('closed_at')->nullable());

        DB::unprepared(<<<'SQL'
CREATE TABLE account_closure_requests (
    id uuid PRIMARY KEY,
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    state varchar(12) NOT NULL CHECK (state IN ('requested', 'cancelled', 'completed')),
    requested_at timestamptz NOT NULL,
    due_at timestamptz NOT NULL,
    cancelled_at timestamptz NULL,
    completed_at timestamptz NULL,
    last_blockers jsonb NULL,
    last_checked_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX account_closure_one_open ON account_closure_requests (user_id) WHERE state = 'requested';
CREATE INDEX account_closure_due ON account_closure_requests (due_at) WHERE state = 'requested';

CREATE TABLE email_change_requests (
    id uuid PRIMARY KEY,
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    new_email varchar(254) NOT NULL,
    token_hash char(64) NOT NULL,
    state varchar(12) NOT NULL CHECK (state IN ('pending', 'confirmed', 'superseded')),
    expires_at timestamptz NOT NULL,
    confirmed_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX email_change_one_pending ON email_change_requests (user_id) WHERE state = 'pending';
CREATE INDEX email_change_token ON email_change_requests (token_hash);

CREATE TABLE system_task_runs (
    task varchar(60) PRIMARY KEY,
    last_ok_at timestamptz NULL,
    last_failed_at timestamptz NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS system_task_runs, email_change_requests, account_closure_requests');
        Schema::table('users', fn ($t) => $t->dropColumn('closed_at'));
    }
};
