<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Liste initiale des catégories, validée par le porteur (lot 32) : posée UNIQUEMENT en production et si la table est vide (aucune donnée existante n'est touchée),
 * puis gérée depuis l'administration. Ajoute aussi la « mise en avant » d'une catégorie sur l'accueil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->after('archived_at');
        });
        // Production seulement : les environnements de test et de développement gardent leurs propres jeux de données.
        if (! app()->environment('production') || DB::table('categories')->count() > 0) {
            return;
        }
        $rows = [
            ['BTP et Architecture', 'building'], ['Ingénierie et Industrie', 'cog'], ['Développement et Informatique', 'code'], ['Design et Graphisme', 'pen'],
            ['Photo et Vidéo', 'camera'], ['Marketing et Communication', 'megaphone'], ['Rédaction et Traduction', 'text'], ['Formation et Accompagnement', 'cap'],
        ];
        foreach ($rows as $i => [$name, $icon]) {
            DB::table('categories')->insert(['id' => (string) Str::uuid(), 'slug' => Str::slug($name), 'name' => $name, 'icon' => $icon, 'position' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('featured_at');
        });
    }
};
