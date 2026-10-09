<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 76 — offres à niveaux et options payantes (F-08). Un service propose soit une offre unique (comme avant), soit 2 ou 3 formules, et jusqu'à 5 options payantes.
 * Formules et options font partie du contenu CONTRÔLÉ : colonnes de la version et du service publié, et gelées avec la version soumise (déclencheur élargi).
 * L'accord de commande fige la formule choisie, les options retenues, le prix et le délai de base. Aucune donnée existante n'est modifiée : tout reste « offre unique ».
 */
return new class extends Migration
{
    private const CONTENT = ['category_id', 'title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'images', 'brief_requires_files', 'delivery_requires_files', 'tiers', 'options'];

    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->jsonb('tiers')->nullable();
            $t->jsonb('options')->nullable();
        });
        Schema::table('service_versions', function (Blueprint $t) {
            $t->jsonb('tiers')->nullable();
            $t->jsonb('options')->nullable();
        });
        Schema::table('order_agreements', function (Blueprint $t) {
            $t->string('tier_name', 30)->nullable();
            $t->unsignedBigInteger('base_price_xof')->nullable();           // prix de la formule seule ; price_xof = prix TOTAL (formule + options)
            $t->unsignedSmallInteger('base_delivery_days')->nullable();
            $t->jsonb('selected_options')->nullable();
        });
        $this->frozenFunction(self::CONTENT);
    }

    public function down(): void
    {
        $this->frozenFunction(array_values(array_diff(self::CONTENT, ['tiers', 'options'])));
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn(['tier_name', 'base_price_xof', 'base_delivery_days', 'selected_options']));
        Schema::table('service_versions', fn (Blueprint $t) => $t->dropColumn(['tiers', 'options']));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['tiers', 'options']));
    }

    /** @param  list<string>  $content */
    private function frozenFunction(array $content): void
    {
        $cols = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS DISTINCT FROM OLD.{$c}", $content));
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION freeci_service_version_frozen() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state <> 'draft' THEN RAISE EXCEPTION 'Une version soumise ou publiée est conservée' USING ERRCODE = 'integrity_constraint_violation'; END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state IN ('in_review','published','superseded') AND ({$cols}) THEN
                    RAISE EXCEPTION 'Une version soumise, publiée ou remplacée ne se modifie pas : créez une nouvelle version' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.state <> OLD.state AND NOT (
                    (OLD.state = 'draft' AND NEW.state = 'in_review') OR (OLD.state = 'changes_requested' AND NEW.state = 'in_review')
                    OR (OLD.state = 'in_review' AND NEW.state IN ('published','changes_requested','draft'))
                    OR (OLD.state = 'published' AND NEW.state = 'superseded')) THEN
                    RAISE EXCEPTION 'Transition de version impossible : % vers %', OLD.state, NEW.state USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END \$\$
        SQL);
    }
};
