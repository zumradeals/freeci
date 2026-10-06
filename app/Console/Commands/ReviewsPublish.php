<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Actions\Notify;
use App\Modules\Orders\Support\ReviewVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prévient le freelance quand un avis devient PUBLIC (la publication elle-même est calculée à la lecture : aucune tâche n'est nécessaire à son affichage).
 * Idempotent (clé de dédoublonnage par avis) ; ne concerne jamais une commande de test ni un contenu masqué.
 */
class ReviewsPublish extends Command
{
    protected $signature = 'freeci:reviews:publish {--days=30 : fenêtre de rattrapage}';

    protected $description = 'Notifie les freelances des avis devenus publics (idempotent).';

    public function handle(Notify $notify): int
    {
        $n = 0;
        ReviewVisibility::published(DB::table('reviews'))->where('visible_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))->orderBy('visible_at')->get(['id', 'subject_id', 'order_id'])
            ->each(function ($r) use ($notify, &$n) {
                $ref = DB::table('orders')->where('id', $r->order_id)->value('reference');
                $n += $notify($r->subject_id, 'review_published', 'review_published:'.$r->id, 'Un avis sur votre prestation est publié', 'Commande '.$ref.' : vous pouvez y répondre publiquement.', 'orders.review', ['reference' => $ref]) ? 1 : 0;
            });
        $this->info("Notifications créées : {$n}.");

        return self::SUCCESS;
    }
}
