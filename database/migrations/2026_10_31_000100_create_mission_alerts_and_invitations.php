<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-11 / F-12 — alertes de missions (catégorie + budget minimum) et invitations d'un freelance à une mission.
 * Tables NOUVELLES uniquement : aucune donnée existante n'est lue ni réécrite. Une invitation n'est pas une commande et ne donne aucun droit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_alerts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $t->unsignedBigInteger('min_budget_xof')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->index(['category_id', 'active']);
        });
        DB::statement('ALTER TABLE mission_alerts ADD CONSTRAINT mission_alerts_budget_chk CHECK (min_budget_xof IS NULL OR min_budget_xof > 0)');
        DB::statement('CREATE UNIQUE INDEX mission_alerts_unique_uq ON mission_alerts (user_id, category_id, COALESCE(min_budget_xof, 0))');

        Schema::create('mission_invitations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('mission_id')->constrained('missions')->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $t->string('state', 12)->default('pending');                 // pending declined withdrawn ; « proposition reçue » et « expirée » se déduisent
            $t->string('message', 500)->nullable();
            $t->string('decline_reason', 20)->nullable();
            $t->timestampTz('responded_at')->nullable();
            $t->timestampsTz();
            $t->unique(['mission_id', 'freelancer_id']);                 // une invitation par mission et par freelance
            $t->index(['freelancer_id', 'state']);
            $t->index(['client_id', 'created_at']);
        });
        DB::statement("ALTER TABLE mission_invitations ADD CONSTRAINT mission_invitations_state_chk CHECK (state IN ('pending','declined','withdrawn'))");
        DB::statement("ALTER TABLE mission_invitations ADD CONSTRAINT mission_invitations_reason_chk CHECK (decline_reason IS NULL OR decline_reason IN ('unavailable','budget','domain','other'))");
        DB::statement('ALTER TABLE mission_invitations ADD CONSTRAINT mission_invitations_not_self_chk CHECK (client_id <> freelancer_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_invitations');
        Schema::dropIfExists('mission_alerts');
    }
};
