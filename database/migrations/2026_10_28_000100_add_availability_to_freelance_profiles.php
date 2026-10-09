<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 74 — disponibilité du freelance (F-10). « Indisponible » = aucune NOUVELLE demande de prestation ; services visibles, commandes en cours inchangées.
 * La date de retour est facultative ; avec `auto_reopen`, la disponibilité revient d'elle-même ce jour-là (évaluée à la lecture, la tâche planifiée ne fait que le constater et notifier).
 * Aucune donnée existante n'est modifiée : tous les profils restent disponibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freelance_profiles', function (Blueprint $t) {
            $t->timestampTz('unavailable_at')->nullable();
            $t->date('back_on')->nullable();
            $t->boolean('auto_reopen')->default(false);
        });
        DB::statement('ALTER TABLE freelance_profiles ADD CONSTRAINT freelance_profiles_availability_chk CHECK ((back_on IS NULL OR unavailable_at IS NOT NULL) AND (NOT auto_reopen OR back_on IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE freelance_profiles DROP CONSTRAINT IF EXISTS freelance_profiles_availability_chk');
        Schema::table('freelance_profiles', fn (Blueprint $t) => $t->dropColumn(['unavailable_at', 'back_on', 'auto_reopen']));
    }
};
