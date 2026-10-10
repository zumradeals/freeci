<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-14 — parrainage et codes promotionnels : la récompense est une COMMISSION OFFERTE (FreeCI renonce à sa part). Le prix payé par le client ne change jamais ; le taux appliqué est figé
 * dans l'accord à la création de la commande (`order_agreements.commission_bp`, inchangé). Tables nouvelles seulement ; deux colonnes facultatives sur l'accord pour expliquer le taux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('code', 8)->unique();
            $t->timestampTz('created_at')->useCurrent();
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('referrer_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('referee_id')->unique()->constrained('users')->restrictOnDelete();     // un compte n'est parrainé qu'une fois, à l'inscription
            $t->string('code', 8);
            $t->string('state', 12)->default('registered');                                       // registered qualified
            $t->boolean('referrer_rewarded')->default(false);                                     // faux si le plafond de filleuls qualifiés était atteint
            $t->uuid('qualifying_order_id')->nullable();
            $t->timestampTz('qualified_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['referrer_id', 'state']);
        });
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_state_chk CHECK (state IN ('registered','qualified'))");
        DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrals_not_self_chk CHECK (referrer_id <> referee_id)');
        Schema::table('referrals', fn (Blueprint $t) => $t->foreign('qualifying_order_id')->references('id')->on('orders')->restrictOnDelete());

        Schema::create('promo_campaigns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 20)->unique();                                                     // majuscules, sans espace
            $t->unsignedSmallInteger('rate_bp');                                                  // taux de commission appliqué (0 = offerte)
            $t->unsignedSmallInteger('free_orders');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->unsignedInteger('max_uses');
            $t->unsignedInteger('uses')->default(0);
            $t->string('state', 10)->default('active');                                           // active suspended
            $t->string('note', 200)->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE promo_campaigns ADD CONSTRAINT promo_campaigns_state_chk CHECK (state IN ('active','suspended'))");
        DB::statement('ALTER TABLE promo_campaigns ADD CONSTRAINT promo_campaigns_range_chk CHECK (free_orders BETWEEN 1 AND 50 AND max_uses >= 1 AND ends_on >= starts_on AND uses <= max_uses)');

        Schema::create('commission_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $t->string('source', 20);                                                             // referral_referee referral_referrer campaign
            $t->foreignUuid('referral_id')->nullable()->constrained('referrals')->restrictOnDelete();
            $t->foreignUuid('campaign_id')->nullable()->constrained('promo_campaigns')->restrictOnDelete();
            $t->unsignedSmallInteger('rate_bp');
            $t->unsignedSmallInteger('total');
            $t->unsignedSmallInteger('consumed')->default(0);
            $t->string('state', 10)->default('active');                                           // active revoked
            $t->string('revoked_reason', 1000)->nullable();
            $t->foreignUuid('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'state']);
        });
        DB::statement("ALTER TABLE commission_grants ADD CONSTRAINT commission_grants_source_chk CHECK (source IN ('referral_referee','referral_referrer','campaign'))");
        DB::statement("ALTER TABLE commission_grants ADD CONSTRAINT commission_grants_state_chk CHECK (state IN ('active','revoked'))");
        DB::statement('ALTER TABLE commission_grants ADD CONSTRAINT commission_grants_count_chk CHECK (total >= 1 AND consumed >= 0 AND consumed <= total)');
        DB::statement("ALTER TABLE commission_grants ADD CONSTRAINT commission_grants_origin_chk CHECK ((source = 'campaign' AND campaign_id IS NOT NULL AND referral_id IS NULL) OR (source <> 'campaign' AND referral_id IS NOT NULL AND campaign_id IS NULL))");
        DB::statement('CREATE UNIQUE INDEX commission_grants_one_per_referral_side_uq ON commission_grants (referral_id, source) WHERE referral_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX commission_grants_one_campaign_per_user_uq ON commission_grants (user_id) WHERE source = 'campaign'");

        Schema::create('commission_grant_uses', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('grant_id')->constrained('commission_grants')->restrictOnDelete();
            $t->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();     // une commande n'utilise qu'une unité, d'une seule attribution
            $t->string('state', 10)->default('reserved');                                         // reserved consumed returned
            $t->timestampsTz();
            $t->index(['grant_id', 'state']);
        });
        DB::statement("ALTER TABLE commission_grant_uses ADD CONSTRAINT commission_grant_uses_state_chk CHECK (state IN ('reserved','consumed','returned'))");

        Schema::table('order_agreements', function (Blueprint $t) {
            $t->unsignedSmallInteger('commission_base_bp')->nullable();                           // taux normal au moment de l'accord, quand un taux offert s'applique
            $t->string('commission_reason', 80)->nullable();                                      // « Parrainage », « Code LANCEMENT »
        });
    }

    public function down(): void
    {
        Schema::table('order_agreements', fn (Blueprint $t) => $t->dropColumn(['commission_base_bp', 'commission_reason']));
        Schema::dropIfExists('commission_grant_uses');
        Schema::dropIfExists('commission_grants');
        Schema::dropIfExists('promo_campaigns');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
    }
};
