<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 75 — offre personnalisée (F-09). Le freelance envoie, depuis une conversation née d'une question sur son service, une offre (prix, délai, périmètre, livrables, corrections, éléments à fournir) ;
 * son acceptation par le client crée une commande NORMALE en « attente de paiement », d'origine « offre » (comme la proposition retenue d'une mission : pas de service, pas de demande à accepter).
 * Le contenu d'une offre est INMODIFIABLE (déclencheur) : pour changer, on la retire et on en envoie une autre. Une seule offre en attente par conversation.
 * Aucune donnée existante n'est modifiée : les commandes, accords et avis existants gardent leur origine ; les contraintes sont élargies, jamais restreintes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_offers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('conversation_id')->constrained('conversations')->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $t->string('title', 160);
            $t->text('scope');
            $t->jsonb('deliverables');
            $t->jsonb('client_inputs');
            $t->unsignedBigInteger('price_xof');
            $t->unsignedSmallInteger('delivery_days');
            $t->unsignedSmallInteger('revisions_included');
            $t->string('delivery_mode', 8);                                   // files | message : choix explicite de l'auteur, figé dans l'accord
            $t->timestampTz('valid_until');
            $t->string('state', 10)->default('pending');                      // pending | accepted | declined | withdrawn | expired
            $t->text('decline_note')->nullable();
            $t->timestampTz('responded_at')->nullable();
            $t->uuid('order_id')->nullable();
            $t->unsignedInteger('row_version')->default(1);
            $t->timestampsTz();
            $t->index(['client_id', 'state']);
            $t->index(['freelancer_id', 'state']);
            $t->index(['conversation_id', 'created_at']);
        });
        DB::statement("ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_state_chk CHECK (state IN ('pending','accepted','declined','withdrawn','expired'))");
        DB::statement("ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_delivery_mode_chk CHECK (delivery_mode IN ('files','message'))");
        DB::statement('ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_parties_chk CHECK (client_id <> freelancer_id)');
        DB::statement('ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_price_chk CHECK (price_xof > 0)');
        DB::statement("ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_accepted_order_chk CHECK ((state = 'accepted') = (order_id IS NOT NULL))");
        // Une seule offre en attente par conversation (rempart contre l'envoi simultané de deux offres).
        DB::statement("CREATE UNIQUE INDEX custom_offers_one_pending_uq ON custom_offers (conversation_id) WHERE state = 'pending'");
        // Le contenu d'une offre ne change jamais ; seul l'état évolue, et une offre terminée ne bouge plus.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_custom_offer_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Une offre ne se supprime pas' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.conversation_id IS DISTINCT FROM OLD.conversation_id OR NEW.client_id IS DISTINCT FROM OLD.client_id OR NEW.freelancer_id IS DISTINCT FROM OLD.freelancer_id
                   OR NEW.title IS DISTINCT FROM OLD.title OR NEW.scope IS DISTINCT FROM OLD.scope OR NEW.deliverables IS DISTINCT FROM OLD.deliverables OR NEW.client_inputs IS DISTINCT FROM OLD.client_inputs
                   OR NEW.price_xof IS DISTINCT FROM OLD.price_xof OR NEW.delivery_days IS DISTINCT FROM OLD.delivery_days OR NEW.revisions_included IS DISTINCT FROM OLD.revisions_included
                   OR NEW.delivery_mode IS DISTINCT FROM OLD.delivery_mode OR NEW.valid_until IS DISTINCT FROM OLD.valid_until THEN
                    RAISE EXCEPTION 'Le contenu d''une offre ne peut pas être modifié' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF OLD.state <> 'pending' THEN
                    RAISE EXCEPTION 'Une offre terminée ne se modifie plus' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER custom_offers_guard BEFORE UPDATE OR DELETE ON custom_offers FOR EACH ROW EXECUTE FUNCTION freeci_custom_offer_guard()');

        // Commande et accord d'origine « offre » : ni service, ni mission, ni proposition.
        Schema::table('orders', fn (Blueprint $t) => $t->uuid('offer_id')->nullable());
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_offer_fk FOREIGN KEY (offer_id) REFERENCES custom_offers(id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX order_offer_uq ON orders (offer_id) WHERE offer_id IS NOT NULL');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_origin_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL AND proposal_version_id IS NULL AND offer_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND proposal_version_id IS NOT NULL AND offer_id IS NULL)
            OR (origin = 'offer' AND service_id IS NULL AND mission_id IS NULL AND proposal_version_id IS NULL AND offer_id IS NOT NULL))");
        DB::statement('ALTER TABLE custom_offers ADD CONSTRAINT custom_offers_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT');

        Schema::table('order_agreements', fn (Blueprint $t) => $t->uuid('offer_id')->nullable());
        DB::statement('ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_offer_fk FOREIGN KEY (offer_id) REFERENCES custom_offers(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT order_agreements_origin_chk');
        DB::statement("ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND service_row_version IS NOT NULL AND proposal_version_id IS NULL AND offer_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND mission_version_id IS NOT NULL AND proposal_version_id IS NOT NULL AND offer_id IS NULL)
            OR (origin = 'offer' AND service_id IS NULL AND mission_id IS NULL AND proposal_version_id IS NULL AND offer_id IS NOT NULL))");

        // Avis : une commande issue d'une offre donne un avis d'origine « offre » (ni service ni mission : jamais de rattachement artificiel).
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_origin_chk');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_origin_chk CHECK ((origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL) OR (origin = 'mission' AND mission_id IS NOT NULL AND service_id IS NULL) OR (origin = 'offer' AND service_id IS NULL AND mission_id IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_origin_chk');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_origin_chk CHECK ((origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL) OR (origin = 'mission' AND mission_id IS NOT NULL AND service_id IS NULL))");
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT order_agreements_origin_chk');
        DB::statement("ALTER TABLE order_agreements ADD CONSTRAINT order_agreements_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND service_row_version IS NOT NULL AND proposal_version_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND mission_version_id IS NOT NULL AND proposal_version_id IS NOT NULL))");
        DB::statement('ALTER TABLE order_agreements DROP CONSTRAINT order_agreements_offer_fk');
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn('offer_id'));
        DB::statement('ALTER TABLE custom_offers DROP CONSTRAINT custom_offers_order_fk');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_origin_chk');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_origin_chk CHECK (
            (origin = 'service' AND service_id IS NOT NULL AND mission_id IS NULL AND proposal_version_id IS NULL)
            OR (origin = 'mission' AND service_id IS NULL AND mission_id IS NOT NULL AND proposal_version_id IS NOT NULL))");
        DB::statement('DROP INDEX IF EXISTS order_offer_uq');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_offer_fk');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('offer_id'));
        DB::statement('DROP TRIGGER IF EXISTS custom_offers_guard ON custom_offers');
        DB::statement('DROP FUNCTION IF EXISTS freeci_custom_offer_guard()');
        Schema::dropIfExists('custom_offers');
    }
};
