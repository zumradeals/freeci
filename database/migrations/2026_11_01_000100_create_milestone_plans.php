<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-13 — jalons de paiement des missions. Un jalon est une commande ORDINAIRE (accord, paiement, livraison, litige, reversement inchangés) : le plan ne fait que les enchaîner.
 * Aucune donnée existante n'est réécrite. Seul l'index « une commande vivante par mission » est précisé : il continue de s'appliquer aux commandes SANS jalon ;
 * pour les commandes à jalons, une seule commande de jalon en cours à la fois par mission (les jalons clôturés ne comptent plus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_versions', fn (Blueprint $t) => $t->jsonb('milestones')->nullable());          // plan proposé : [{title, scope, price_xof, days}] ; la table reste en ajout seul

        Schema::create('mission_plans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('mission_id')->constrained('missions')->restrictOnDelete();
            $t->foreignUuid('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $t->string('state', 12);                                       // active paused stopped completed discarded
            $t->unsignedBigInteger('total_xof');
            $t->timestampTz('paused_at')->nullable();
            $t->timestampTz('ended_at')->nullable();
            $t->string('stop_reason', 12)->nullable();                     // client unpaid support
            $t->timestampsTz();
            $t->index(['client_id', 'state']);
            $t->index(['freelancer_id', 'state']);
        });
        DB::statement("ALTER TABLE mission_plans ADD CONSTRAINT mission_plans_state_chk CHECK (state IN ('active','paused','stopped','completed','discarded'))");
        DB::statement("ALTER TABLE mission_plans ADD CONSTRAINT mission_plans_reason_chk CHECK (stop_reason IS NULL OR stop_reason IN ('client','unpaid','support'))");
        DB::statement("CREATE UNIQUE INDEX mission_plans_one_live_uq ON mission_plans (mission_id) WHERE state <> 'discarded'");

        Schema::create('mission_plan_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('plan_id')->constrained('mission_plans')->restrictOnDelete();
            $t->unsignedSmallInteger('rank');
            $t->string('title', 80);
            $t->text('scope');
            $t->unsignedBigInteger('price_xof');
            $t->unsignedSmallInteger('delivery_days');
            $t->string('state', 12)->default('upcoming');                  // upcoming open validated cancelled
            $t->uuid('order_id')->nullable();                              // commande COURANTE du jalon (la précédente, expirée, reste dans l'historique de la commande)
            $t->timestampsTz();
            $t->unique(['plan_id', 'rank']);
            $t->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE mission_plan_items ADD CONSTRAINT mission_plan_items_state_chk CHECK (state IN ('upcoming','open','validated','cancelled'))");
        DB::statement('ALTER TABLE mission_plan_items ADD CONSTRAINT mission_plan_items_amount_chk CHECK (price_xof > 0 AND delivery_days > 0 AND rank >= 1)');
        DB::statement("CREATE UNIQUE INDEX mission_plan_items_one_open_uq ON mission_plan_items (plan_id) WHERE state = 'open'");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_plan_item_frozen() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Un jalon est conservé' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                IF NEW.plan_id <> OLD.plan_id OR NEW.rank <> OLD.rank OR NEW.title <> OLD.title OR NEW.scope <> OLD.scope OR NEW.price_xof <> OLD.price_xof OR NEW.delivery_days <> OLD.delivery_days THEN
                    RAISE EXCEPTION 'Le plan de jalons est figé à la sélection : un jalon ne se modifie pas' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER mission_plan_items_frozen BEFORE UPDATE OR DELETE ON mission_plan_items FOR EACH ROW EXECUTE FUNCTION freeci_plan_item_frozen()');

        Schema::table('orders', function (Blueprint $t) {
            $t->uuid('milestone_item_id')->nullable();
            $t->foreign('milestone_item_id')->references('id')->on('mission_plan_items')->restrictOnDelete();
            $t->index('milestone_item_id');
        });
        DB::statement('DROP INDEX IF EXISTS order_mission_live_uq');
        DB::statement("CREATE UNIQUE INDEX order_mission_live_uq ON orders (mission_id) WHERE mission_id IS NOT NULL AND milestone_item_id IS NULL AND state NOT IN ('cancelled','expired')");
        DB::statement("CREATE UNIQUE INDEX order_milestone_live_uq ON orders (mission_id) WHERE milestone_item_id IS NOT NULL AND state NOT IN ('cancelled','expired','closed')");
        // La proposition retenue n'a qu'une commande SANS jalon ; avec jalons, chaque jalon a la sienne (unicité portée par le jalon).
        DB::statement('DROP INDEX IF EXISTS order_proposal_version_uq');
        DB::statement('CREATE UNIQUE INDEX order_proposal_version_uq ON orders (proposal_version_id) WHERE proposal_version_id IS NOT NULL AND milestone_item_id IS NULL');
        // Un jalon n'a jamais plus d'une commande vivante.
        DB::statement("CREATE UNIQUE INDEX order_milestone_item_live_uq ON orders (milestone_item_id) WHERE milestone_item_id IS NOT NULL AND state NOT IN ('cancelled','expired')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS order_milestone_item_live_uq');
        DB::statement('DROP INDEX IF EXISTS order_milestone_live_uq');
        DB::statement('DROP INDEX IF EXISTS order_proposal_version_uq');
        DB::statement('CREATE UNIQUE INDEX order_proposal_version_uq ON orders (proposal_version_id) WHERE proposal_version_id IS NOT NULL');
        DB::statement('DROP INDEX IF EXISTS order_mission_live_uq');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['milestone_item_id']);
            $t->dropColumn('milestone_item_id');
        });
        DB::statement("CREATE UNIQUE INDEX order_mission_live_uq ON orders (mission_id) WHERE mission_id IS NOT NULL AND state NOT IN ('cancelled','expired')");
        Schema::dropIfExists('mission_plan_items');
        Schema::dropIfExists('mission_plans');
        Schema::table('proposal_versions', fn (Blueprint $t) => $t->dropColumn('milestones'));
    }
};
