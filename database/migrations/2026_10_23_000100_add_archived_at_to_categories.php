<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les catégories se gèrent depuis l'administration : une catégorie archivée n'est plus proposée, sans toucher aux services et missions qui l'utilisent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
