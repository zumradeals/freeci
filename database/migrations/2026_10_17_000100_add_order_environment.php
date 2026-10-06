<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 10.1 — passerelle unique (Genius Pay), deux modes (sandbox | live). Séparation des COMMANDES, pas des personnes.
 *
 *  - `orders.environment` : « test » (créée en mode sandbox), « live » (créée en mode live) ou « legacy » (antérieure à ce marquage).
 *    Valeur par défaut « legacy » : les commandes existantes ne sont NI réécrites NI rendues payables en silence. Fixée à la création, IMMUABLE
 *    (déclencheur) : aucun changement de configuration ne convertit une commande engagée.
 *  - Un nouveau paiement doit concorder avec sa commande (déclencheur) : sandbox ↔ test, live ↔ live ; un paiement du simulateur retiré ne peut plus être créé.
 *  - Tentatives OUVERTES du simulateur retiré : closes (« expired », code `legacy_simulator_retired`) avec une ligne d'historique de commande. Aucune
 *    confirmation n'est fabriquée ; les tentatives déjà confirmées/échouées et le registre sont conservés tels quels.
 * Les tables `sandbox_transactions`, la colonne `users.sandbox_payments` et `ledger_batches.is_simulated` sont conservées (provenance), non utilisées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('environment', 10)->default('legacy');
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_environment_chk CHECK (environment IN ('test','live','legacy'))");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_order_environment_fixed() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.environment IS DISTINCT FROM OLD.environment THEN
                    RAISE EXCEPTION 'L''environnement d''une commande est fixé à sa création' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER orders_environment_fixed BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION freeci_order_environment_fixed()');

        // Fermeture des tentatives ouvertes du simulateur retiré (avant l'activation du déclencheur de concordance).
        $open = DB::table('payments')->where('provider', 'sandbox')->whereIn('state', ['created', 'pending', 'unknown'])->get(['id', 'order_id', 'provider_reference']);
        foreach ($open as $p) {
            DB::table('payments')->where('id', $p->id)->update([
                'state' => 'expired', 'failure_code' => 'legacy_simulator_retired', 'failed_at' => now(), 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now(),
            ]);
            DB::table('order_events')->insert([
                'order_id' => $p->order_id, 'type' => 'payment_failed', 'actor_id' => null, 'occurred_at' => now(),
                'note' => 'Tentative de paiement du simulateur retiré, close sans effet ('.$p->provider_reference.') : aucun paiement n\'a été confirmé.',
                'meta' => json_encode(['reason' => 'legacy_simulator_retired']),
            ]);
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION freeci_payment_matches_order() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE order_env text;
            BEGIN
                SELECT environment INTO order_env FROM orders WHERE id = NEW.order_id;
                IF NEW.provider <> 'genius_pay'
                   OR NOT ((NEW.environment = 'sandbox' AND order_env = 'test') OR (NEW.environment = 'live' AND order_env = 'live')) THEN
                    RAISE EXCEPTION 'Paiement incompatible avec l''environnement de la commande' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END $$
        SQL);
        DB::statement('CREATE TRIGGER payments_match_order BEFORE INSERT ON payments FOR EACH ROW EXECUTE FUNCTION freeci_payment_matches_order()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS payments_match_order ON payments');
        DB::statement('DROP FUNCTION IF EXISTS freeci_payment_matches_order()');
        DB::statement('DROP TRIGGER IF EXISTS orders_environment_fixed ON orders');
        DB::statement('DROP FUNCTION IF EXISTS freeci_order_environment_fixed()');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_environment_chk');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('environment'));
        // Les tentatives du simulateur closes par `up()` ne sont pas rouvertes : aucune confirmation n'est fabriquée.
    }
};
