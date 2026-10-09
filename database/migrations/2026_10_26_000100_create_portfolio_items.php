<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Réalisations (portfolio) d'un freelance : publiques une fois le profil publié. Table NOUVELLE : aucune donnée existante n'est modifiée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('state', 12);                                   // active | deleted | removed | closed
            $t->string('title', 80);
            $t->string('description', 300)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('key_large', 120)->nullable();                  // null une fois le fichier effacé
            $t->string('key_card', 120)->nullable();
            $t->string('mime', 40);
            $t->string('sha256', 64);
            $t->timestampTz('created_at');
            $t->timestampTz('updated_at')->nullable();
            $t->timestampTz('ended_at')->nullable();
            $t->foreignUuid('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('removal_reason')->nullable();
            $t->timestampTz('purge_after')->nullable();
            $t->index(['user_id', 'state', 'created_at']);
            $t->index(['state', 'purge_after']);
        });
        DB::statement("ALTER TABLE portfolio_items ADD CONSTRAINT portfolio_items_state_chk CHECK (state IN ('active','deleted','removed','closed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};
