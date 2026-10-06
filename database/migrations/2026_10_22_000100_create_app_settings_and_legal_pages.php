<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 16 : paramètres administrables et textes légaux éditables. Ajouts uniquement : aucune donnée existante n'est modifiée.
 * Sans ligne en base, la valeur reste celle du code / du .env (valeur par défaut) : le comportement actuel est inchangé tant que rien n'est saisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE app_settings (
    key varchar(80) PRIMARY KEY,
    value jsonb NOT NULL,
    status varchar(12) NOT NULL DEFAULT 'provisional' CHECK (status IN ('provisional', 'approved')),
    updated_by uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    updated_at timestamptz NOT NULL DEFAULT now(),
    approved_by uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    approved_at timestamptz NULL
);

CREATE TABLE app_setting_changes (
    id bigserial PRIMARY KEY,
    key varchar(80) NOT NULL,
    old_value jsonb NULL,
    new_value jsonb NULL,
    status_after varchar(12) NOT NULL,
    reason text NOT NULL,
    actor_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX app_setting_changes_recent ON app_setting_changes (created_at DESC);
CREATE TRIGGER app_setting_changes_append_only BEFORE UPDATE OR DELETE ON app_setting_changes FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change();

CREATE TABLE legal_pages (
    slug varchar(40) PRIMARY KEY,
    draft_body text NULL,
    draft_updated_by uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    draft_updated_at timestamptz NULL,
    published_body text NULL,
    published_version integer NOT NULL DEFAULT 0,
    published_by uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    published_at timestamptz NULL
);

CREATE TABLE legal_page_history (
    id bigserial PRIMARY KEY,
    slug varchar(40) NOT NULL,
    version integer NOT NULL,
    action varchar(12) NOT NULL CHECK (action IN ('published', 'withdrawn')),
    body text NOT NULL,
    reason text NOT NULL,
    actor_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX legal_page_history_slug ON legal_page_history (slug, created_at DESC);
CREATE TRIGGER legal_page_history_append_only BEFORE UPDATE OR DELETE ON legal_page_history FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS legal_page_history, legal_pages, app_setting_changes, app_settings');
    }
};
