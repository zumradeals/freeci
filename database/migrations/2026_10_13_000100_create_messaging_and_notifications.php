<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 7 — messagerie privée et notifications. Tables NOUVELLES uniquement ; seule modification d'une table existante :
 * `file_assets` (pièces jointes de message : `order_id` devient nullable, `message_id` s'ajoute). Aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 10);                                          // contexte d'origine : service | proposal | order
            $t->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('freelancer_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $t->foreignUuid('proposal_id')->nullable()->constrained('proposals')->restrictOnDelete();
            $t->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();   // posé à la sélection / à la demande : le fil se poursuit dans la commande
            $t->string('context_title', 160);
            $t->unsignedBigInteger('last_message_id')->nullable();
            $t->timestampTz('last_message_at')->nullable();
            $t->unsignedBigInteger('client_read_id')->nullable();           // dernier message lu par chaque participant
            $t->unsignedBigInteger('freelancer_read_id')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['client_id', 'last_message_at']);
            $t->index(['freelancer_id', 'last_message_at']);
        });
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_kind_chk CHECK (kind IN ('service','proposal','order'))");
        DB::statement('ALTER TABLE conversations ADD CONSTRAINT conversations_parties_chk CHECK (client_id <> freelancer_id)');
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_context_chk CHECK ((kind = 'service' AND service_id IS NOT NULL) OR (kind = 'proposal' AND proposal_id IS NOT NULL) OR (kind = 'order' AND order_id IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX conversations_service_uq ON conversations (service_id, client_id) WHERE kind = 'service'");
        DB::statement('CREATE UNIQUE INDEX conversations_proposal_uq ON conversations (proposal_id) WHERE proposal_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX conversations_order_uq ON conversations (order_id) WHERE order_id IS NOT NULL');

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('conversation_id')->constrained('conversations')->restrictOnDelete();
            $t->foreignUuid('sender_id')->constrained('users')->restrictOnDelete();
            $t->text('body');
            $t->string('client_key', 80);                                    // une soumission = un message (double clic, rechargement)
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['conversation_id', 'sender_id', 'client_key']);
            $t->index(['conversation_id', 'id']);
        });
        DB::statement('CREATE TRIGGER messages_append_only BEFORE UPDATE OR DELETE ON messages FOR EACH ROW EXECUTE FUNCTION freeci_forbid_change()');        // l'historique ne se réécrit pas
        Schema::table('conversations', fn (Blueprint $t) => $t->foreign('last_message_id')->references('id')->on('messages')->restrictOnDelete());

        Schema::create('contact_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('blocker_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['blocker_id', 'blocked_id']);
        });
        DB::statement('ALTER TABLE contact_blocks ADD CONSTRAINT contact_blocks_not_self_chk CHECK (blocker_id <> blocked_id)');

        // Pièces jointes de message : même table et même chaîne de contrôle que le brief et les livraisons.
        DB::statement('ALTER TABLE file_assets ALTER COLUMN order_id DROP NOT NULL');
        Schema::table('file_assets', function (Blueprint $t) {
            $t->unsignedBigInteger('message_id')->nullable();
            $t->foreign('message_id')->references('id')->on('messages')->restrictOnDelete();
            $t->index('message_id');
        });
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_owner_chk CHECK (order_id IS NOT NULL OR message_id IS NOT NULL)');
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_message_exclusive_chk CHECK (message_id IS NULL OR (order_id IS NULL AND delivery_id IS NULL))');   // un fichier de message n'est jamais un fichier de brief ou de livraison

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('category', 10);                                      // essential | optional
            $t->string('dedupe_key', 120);                                   // un événement métier = une notification, même répété
            $t->string('title', 200);
            $t->string('body', 400)->nullable();                             // jamais le contenu d'un message
            $t->string('route', 60);
            $t->jsonb('route_params')->default('{}');
            $t->unsignedInteger('count')->default(1);                        // messages regroupés tant que non lus
            $t->timestampTz('read_at')->nullable();
            $t->string('email_state', 12)->default('none');                  // none | unavailable | pending | sent | failed
            $t->unsignedSmallInteger('email_attempts')->default(0);
            $t->string('email_error', 40)->nullable();                       // code technique, jamais de message du serveur ni de secret
            $t->timestampTz('email_sent_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('updated_at')->useCurrent();
            $t->unique(['user_id', 'dedupe_key']);
            $t->index(['user_id', 'read_at', 'id']);
        });
        DB::statement("ALTER TABLE app_notifications ADD CONSTRAINT app_notifications_email_state_chk CHECK (email_state IN ('none','unavailable','pending','sent','failed'))");

        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 40);
            $t->boolean('in_app')->default(true);
            $t->boolean('email')->default(false);
            $t->timestampsTz();
            $t->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_message_exclusive_chk');
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT file_assets_owner_chk');
        Schema::table('file_assets', fn (Blueprint $t) => $t->dropConstrainedForeignId('message_id'));
        DB::statement('DELETE FROM file_assets WHERE order_id IS NULL');
        DB::statement('ALTER TABLE file_assets ALTER COLUMN order_id SET NOT NULL');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('contact_blocks');
        Schema::table('conversations', fn (Blueprint $t) => $t->dropConstrainedForeignId('last_message_id'));
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
