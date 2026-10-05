<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1 — découverte des services : catégories, profils freelance publics, services.
 * Les versions immuables de service (contrat d'accord figé) viendront avec le lot « commande » ;
 * `row_version` est déjà là pour refuser une action sur une version périmée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // « unaccent » est une extension de confiance (PG ≥ 13) : pas besoin d'être superutilisateur.
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        // unaccent() n'est pas IMMUTABLE ; l'enveloppe ci-dessous permet de l'utiliser dans une colonne générée.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
        SQL);

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 120)->unique();
            $table->string('icon', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        // Profil public d'un freelance : projection distincte du compte (jamais d'e-mail ici).
        Schema::create('freelance_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('headline', 160);
            $table->string('city', 80)->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories');
            $table->foreignUuid('freelance_profile_id')->constrained('freelance_profiles');
            $table->string('slug', 160)->unique();
            $table->string('title', 160);
            $table->text('summary');
            $table->text('scope');
            $table->unsignedBigInteger('price_xof');      // francs entiers, jamais de flottant
            $table->unsignedSmallInteger('delivery_days');
            $table->unsignedSmallInteger('revisions_included');
            $table->jsonb('deliverables');                  // liste de textes
            $table->jsonb('exclusions');
            $table->jsonb('client_inputs');
            $table->jsonb('images');                        // [{src, alt, caption}]
            $table->string('status', 20)->default('draft'); // draft | in_review | published | suspended | archived
            $table->timestampTz('published_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
        });

        DB::statement("ALTER TABLE services ADD CONSTRAINT services_status_chk CHECK (status IN ('draft','in_review','published','suspended','archived'))");
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_chk CHECK (price_xof > 0 AND delivery_days > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE services ADD COLUMN search_document tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('french', freeci_unaccent(title)), 'A') ||
                setweight(to_tsvector('french', freeci_unaccent(summary)), 'B') ||
                setweight(to_tsvector('french', freeci_unaccent(scope)), 'C')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX services_search_idx ON services USING GIN (search_document)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('freelance_profiles');
        Schema::dropIfExists('categories');
        DB::statement('DROP FUNCTION IF EXISTS freeci_unaccent(text)');
    }
};
