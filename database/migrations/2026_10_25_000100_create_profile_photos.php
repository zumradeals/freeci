<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photos de profil : une photo ACTIVE par personne (index unique partiel), l'historique des changements est conservé sans fichier
 * (sauf photo retirée par l'administration, conservée jusqu'à `purge_after`). Table NOUVELLE : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_photos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('state', 12);                                   // active | replaced | deleted | removed | closed
            $t->string('key_large', 120)->nullable();                  // null une fois le fichier effacé
            $t->string('key_small', 120)->nullable();
            $t->string('mime', 40);
            $t->string('sha256', 64);
            $t->timestampTz('created_at');
            $t->timestampTz('ended_at')->nullable();
            $t->foreignUuid('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('removal_reason')->nullable();
            $t->timestampTz('purge_after')->nullable();                // photo retirée : fichier effacé à cette date
            $t->index(['user_id', 'created_at']);
            $t->index(['state', 'purge_after']);
        });
        DB::statement("ALTER TABLE profile_photos ADD CONSTRAINT profile_photos_state_chk CHECK (state IN ('active','replaced','deleted','removed','closed'))");
        DB::statement("CREATE UNIQUE INDEX profile_photos_one_active ON profile_photos (user_id) WHERE state = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_photos');
    }
};
