<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles commerciaux (client, freelance) = données ; habilitations du personnel (administrateur) = lignes
 * datées, motivées, révocables (docs/02 §8.2, `staff_grant`). Aucun indicateur « admin » sur la table users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['user_id', 'role']);
        });
        DB::statement("ALTER TABLE account_roles ADD CONSTRAINT account_roles_role_chk CHECK (role IN ('client','freelance'))");

        Schema::create('staff_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('capability', 30);
            $table->text('reason');
            $table->string('granted_by', 60);               // « console » : désignation par le porteur sur le serveur
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->index(['user_id', 'capability']);
        });
        DB::statement("ALTER TABLE staff_grants ADD CONSTRAINT staff_grants_capability_chk CHECK (capability IN ('administrator'))");
        // Une seule habilitation active par personne et capacité.
        DB::statement('CREATE UNIQUE INDEX staff_grants_active_uq ON staff_grants (user_id, capability) WHERE revoked_at IS NULL');

        // Les comptes déjà inscrits sont des clients (comportement antérieur : tout compte avait l'espace client).
        DB::statement("INSERT INTO account_roles (id, user_id, role) SELECT gen_random_uuid(), id, 'client' FROM users ON CONFLICT DO NOTHING");
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_grants');
        Schema::dropIfExists('account_roles');
    }
};
