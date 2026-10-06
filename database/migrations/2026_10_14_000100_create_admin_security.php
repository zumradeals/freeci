<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 8 — administration sécurisée : double authentification (secret chiffré, codes de récupération hachés), journal des événements de
 * sécurité, journal des actions administratives, historique des suspensions de comptes, statut « suspendu » des missions.
 * Données existantes inchangées : colonnes ajoutées nulles, aucune ligne modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('two_factor_secret')->nullable();                 // chiffré par l'application (jamais en clair)
            $t->timestampTz('two_factor_confirmed_at')->nullable();
            $t->bigInteger('two_factor_last_step')->nullable();        // anti-rejeu d'un code TOTP déjà accepté
            $t->timestampTz('suspended_at')->nullable();
        });

        Schema::create('two_factor_recovery_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->char('code_hash', 64);                                  // HMAC-SHA256 : le code n'est affiché qu'une fois
            $t->timestampTz('used_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['user_id', 'code_hash']);
        });

        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('user_id')->nullable()->index();
            $t->string('type', 60)->index();
            $t->string('ip', 45)->nullable();
            $t->jsonb('meta')->nullable();                              // liste blanche de clés : jamais de secret ni de code
            $t->timestampTz('created_at')->useCurrent()->index();
        });
        DB::statement('CREATE TRIGGER security_events_append_only BEFORE UPDATE OR DELETE ON security_events FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        Schema::create('admin_actions', function (Blueprint $t) {
            $t->id();
            $t->uuid('actor_id')->index();
            $t->string('action', 60)->index();
            $t->string('target_type', 30);
            $t->string('target_id', 64)->nullable();
            $t->string('target_label', 200)->nullable();
            $t->text('reason')->nullable();
            $t->string('result', 20);                                   // done | refused
            $t->string('detail', 300)->nullable();                      // message d'une règle métier, jamais de contenu privé
            $t->timestampTz('created_at')->useCurrent()->index();
        });
        DB::statement("ALTER TABLE admin_actions ADD CONSTRAINT admin_actions_result_chk CHECK (result IN ('done','refused'))");
        DB::statement('CREATE TRIGGER admin_actions_append_only BEFORE UPDATE OR DELETE ON admin_actions FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        Schema::create('account_restrictions', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 12);                                   // suspended | reactivated
            $t->text('reason');
            $t->uuid('actor_id');
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['user_id', 'id']);
        });
        DB::statement("ALTER TABLE account_restrictions ADD CONSTRAINT account_restrictions_action_chk CHECK (action IN ('suspended','reactivated'))");
        DB::statement('CREATE TRIGGER account_restrictions_append_only BEFORE UPDATE OR DELETE ON account_restrictions FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');

        DB::statement('ALTER TABLE missions DROP CONSTRAINT missions_status_chk');
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_status_chk CHECK (status IN ('draft','in_review','open','reserved','awarded','selection_ended','closed','cancelled','expired','suspended'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE missions SET status = 'open' WHERE status = 'suspended'");
        DB::statement('ALTER TABLE missions DROP CONSTRAINT missions_status_chk');
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_status_chk CHECK (status IN ('draft','in_review','open','reserved','awarded','selection_ended','closed','cancelled','expired'))");
        foreach (['account_restrictions', 'admin_actions', 'security_events'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
        }
        Schema::dropIfExists('account_restrictions');
        Schema::dropIfExists('admin_actions');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('two_factor_recovery_codes');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step', 'suspended_at']));
    }
};
