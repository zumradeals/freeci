<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 12 — avis, réponses, historique de modération, favoris. Tables NOUVELLES : aucune donnée existante n'est modifiée.
 *  - `reviews` : un avis par commande (unique), déposé par le CLIENT de la commande ; note 1–5 et commentaire IMMUABLES (déclencheur) : ni l'auteur, ni un administrateur ne les réécrivent.
 *    `counts_public` n'est vrai que pour une commande RÉELLE (environnement « live ») : les commandes de test ne produisent aucun avis public ni effet sur la réputation.
 *    `visible_at` est figée au dépôt. Le masquage ne supprime rien : colonne `hidden_at` + historique append-only.
 *  - `review_responses` : une réponse publique du freelance par avis, immuable.
 *  - `review_moderations` : masquages et rétablissements (motif, catégorie, auteur), append-only.
 *  - `favorites` : privés, sans doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $t->foreignUuid('author_id')->constrained('users')->restrictOnDelete();             // le client
            $t->foreignUuid('subject_id')->constrained('users')->restrictOnDelete();            // le freelance
            $t->string('origin', 8);                                                              // service | mission : jamais de rattachement artificiel à un service
            $t->foreignUuid('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $t->foreignUuid('mission_id')->nullable()->constrained('missions')->restrictOnDelete();
            $t->unsignedSmallInteger('rating');
            $t->text('comment');
            $t->boolean('counts_public');                                                         // commande réelle seulement
            $t->timestampTz('visible_at');                                                        // publication différée, figée au dépôt
            $t->timestampTz('hidden_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['subject_id', 'created_at']);
            $t->index('service_id');
        });
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_origin_chk CHECK ((origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL) OR (origin = 'mission' AND mission_id IS NOT NULL AND service_id IS NULL))");
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_chk CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_comment_chk CHECK (char_length(comment) BETWEEN 10 AND 1500)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_parties_chk CHECK (author_id <> subject_id)');

        Schema::create('review_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('review_id')->unique()->constrained('reviews')->restrictOnDelete();
            $t->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $t->text('body');
            $t->timestampTz('hidden_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE review_responses ADD CONSTRAINT review_responses_body_chk CHECK (char_length(body) BETWEEN 10 AND 1000)');

        Schema::create('review_moderations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('review_id')->constrained('reviews')->restrictOnDelete();
            $t->string('target', 8);                                                              // review | reply
            $t->string('action', 10);                                                             // hidden | restored
            $t->string('category', 20)->nullable();
            $t->text('reason');
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['review_id', 'id']);
        });
        DB::statement("ALTER TABLE review_moderations ADD CONSTRAINT review_moderations_chk CHECK (target IN ('review','reply') AND action IN ('hidden','restored'))");
        DB::statement('CREATE TRIGGER review_moderations_append_only BEFORE UPDATE OR DELETE ON review_moderations FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        // Immutabilité : seule la colonne `hidden_at` peut changer (masquage / rétablissement) ; jamais la note, le commentaire, l'auteur, la cible ni la date de visibilité.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_review_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Un avis ou une réponse ne se supprime pas' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF TG_TABLE_NAME = 'reviews' THEN
                    IF to_jsonb(NEW) - 'hidden_at' IS DISTINCT FROM to_jsonb(OLD) - 'hidden_at' THEN
                        RAISE EXCEPTION 'La note et le commentaire d''un avis ne peuvent pas être modifiés' USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                ELSE
                    IF to_jsonb(NEW) - 'hidden_at' IS DISTINCT FROM to_jsonb(OLD) - 'hidden_at' THEN
                        RAISE EXCEPTION 'Une réponse ne peut pas être modifiée' USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER reviews_guard BEFORE UPDATE OR DELETE ON reviews FOR EACH ROW EXECUTE FUNCTION freeci_review_guard()');
        DB::statement('CREATE TRIGGER review_responses_guard BEFORE UPDATE OR DELETE ON review_responses FOR EACH ROW EXECUTE FUNCTION freeci_review_guard()');

        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('kind', 10);                                                               // service | freelance
            $t->uuid('target_id');                                                                // services.id | freelance_profiles.id
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['user_id', 'kind', 'target_id']);
            $t->index(['user_id', 'id']);
        });
        DB::statement("ALTER TABLE favorites ADD CONSTRAINT favorites_kind_chk CHECK (kind IN ('service','freelance'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        DB::statement('DROP TRIGGER IF EXISTS review_responses_guard ON review_responses');
        DB::statement('DROP TRIGGER IF EXISTS reviews_guard ON reviews');
        DB::statement('DROP FUNCTION IF EXISTS freeci_review_guard()');
        DB::statement('DROP TRIGGER IF EXISTS review_moderations_append_only ON review_moderations');
        Schema::dropIfExists('review_moderations');
        Schema::dropIfExists('review_responses');
        Schema::dropIfExists('reviews');
    }
};
