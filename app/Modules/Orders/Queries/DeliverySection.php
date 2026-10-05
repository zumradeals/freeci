<?php

namespace App\Modules\Orders\Queries;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Actions\DownloadBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Actions\DeliveryDraft;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\CorrectionRequest;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecture de la partie « livraisons » d'un dossier, bornée aux parties. Le client ne voit JAMAIS un brouillon ;
 * seules les versions soumises, leurs fichiers contrôlés, les corrections, les reports et le délai d'examen.
 */
final class DeliverySection
{
    public function __construct(private DownloadBriefFile $downloads, private FileScanner $scanner) {}

    /** @return array<string, mixed> */
    public function __invoke(Order $order, User $viewer, bool $isFreelancer): array
    {
        $order->loadMissing('agreement');
        $submitted = Delivery::query()->where('order_id', $order->getKey())->where('state', 'submitted')->with('author')->orderBy('version')->get();
        $corrections = CorrectionRequest::query()->where('order_id', $order->getKey())->orderBy('number')->get()->keyBy('delivery_id');
        $answered = $corrections->keyBy('id');
        $latest = $submitted->last();
        $included = (int) $order->agreement->revisions_included;
        $used = $corrections->count();

        $list = $submitted->map(function (Delivery $d) use ($submitted, $corrections, $answered, $order, $viewer, $latest) {
            $c = $corrections->get($d->getKey());
            $next = $submitted->firstWhere('version', $d->version + 1);
            [$label, $tone, $icon] = match (true) {
                $next !== null => ['Remplacée par la v'.$next->version, 'neutral', 'minus-circle'],
                $order->validated_delivery_id === $d->getKey() => ['Validée', 'success', 'check-circle'],
                $c !== null => ['Correction demandée', 'warning', 'warn'],
                default => [$order->state === OrderState::Delivered ? 'À examiner' : 'Livrée', 'info', 'info'],
            };
            $answers = $d->correction_request_id ? $answered->get($d->correction_request_id) : null;

            return [
                'id' => $d->getKey(), 'version' => $d->version, 'isLatest' => $latest?->getKey() === $d->getKey(),
                'when' => Dates::format($d->submitted_at), 'author' => $d->author->name, 'message' => $d->message,
                'label' => $label, 'tone' => $tone, 'icon' => $icon,
                'answers' => $answers ? 'Répond à la correction n° '.$answers->number : null,
                'correction' => $c ? ['number' => $c->number, 'reason' => $c->reason, 'when' => Dates::format($c->created_at)] : null,
                'files' => $this->files($d, $viewer, $order),
            ];
        })->reverse()->values()->all();

        $isWork = in_array($order->state, [OrderState::InProgress, OrderState::RevisionRequested], true);
        $draft = null;
        if ($isFreelancer && $isWork) {
            $d = Delivery::query()->where('order_id', $order->getKey())->where('state', 'draft')->first();
            $blockers = DeliveryDraft::blockers($order, $d);
            $draft = [
                'id' => $d?->getKey(), 'message' => $d?->message ?? '', 'files' => $d ? $this->files($d, $viewer, $order, true) : [], 'blockers' => $blockers, 'canSubmit' => $blockers === [],
                'scannerOperational' => $this->scanner->isOperational(), 'requiresFiles' => $order->agreement->deliveryMode() === 'files', 'deliveryMode' => $order->agreement->deliveryMode(),
            ];
        }

        $extensions = $order->extensionRequests()->get();
        $pending = $extensions->firstWhere('state', 'pending');
        $accepted = $extensions->where('state', 'accepted');
        $fmt = fn ($e) => [
            'id' => $e->id, 'state' => $e->state, 'previous' => Dates::format($e->previous_due_at), 'proposed' => Dates::format($e->proposed_due_at),
            'reason' => $e->reason, 'when' => Dates::format($e->created_at), 'decidedAt' => $e->decided_at ? Dates::format($e->decided_at) : null, 'note' => $e->decision_note,
            'label' => ['pending' => 'En attente de réponse', 'accepted' => 'Acceptée', 'declined' => 'Refusée', 'withdrawn' => 'Retirée'][$e->state],
        ];

        $review = null;
        if ($latest !== null && $order->state === OrderState::Delivered) {
            $review = [
                'deadline' => $latest->review_deadline_at, 'overdue' => $latest->review_deadline_at->lte(now()),
                'followUp' => DB::table('order_follow_ups')->where('delivery_id', $latest->getKey())->exists(),
            ];
        }

        $disagreement = $latest ? DB::table('order_follow_ups')->where('delivery_id', $latest->getKey())->where('kind', 'client_disagreement')->first(['note', 'recorded_at']) : null;

        return [
            'deliveries' => $list, 'latestId' => $latest?->getKey(), 'latestVersion' => $latest?->version,
            'corrections' => ['included' => $included, 'used' => $used, 'remaining' => max(0, $included - $used)],
            'draft' => $draft, 'canDeliver' => $isFreelancer && $isWork,
            'extension' => $pending ? $fmt($pending) : null,
            'extensionHistory' => $extensions->where('state', '!=', 'pending')->reverse()->map($fmt)->values()->all(),
            'initialDue' => $accepted->isNotEmpty() ? Dates::format($accepted->first()->previous_due_at) : null,
            'canRequestExtension' => $isFreelancer && $isWork && $order->due_at !== null && $pending === null,
            'canAnswerExtension' => ! $isFreelancer && $isWork && $pending !== null,
            'canWithdrawExtension' => $isFreelancer && $isWork && $pending !== null,
            'late' => $order->due_at !== null && $isWork && $order->due_at->lte(now()),
            'canDecide' => ! $isFreelancer && $order->state === OrderState::Delivered && $latest !== null,
            'review' => $review,
            'exhausted' => $latest !== null && $used >= $included,
            'disagreement' => $disagreement ? ['note' => $disagreement->note, 'when' => Dates::format(Carbon::parse($disagreement->recorded_at))] : null,
            'canSignalDisagreement' => ! $isFreelancer && $order->state === OrderState::Delivered && $latest !== null && $used >= $included
                && ! DB::table('order_follow_ups')->where('delivery_id', $latest->getKey())->where('kind', 'client_disagreement')->exists(),
            'requiresFiles' => $order->agreement->deliveryMode() === 'files', 'deliveryMode' => $order->agreement->deliveryMode(),
            'validatedAt' => $order->validated_at ? Dates::format($order->validated_at) : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function files(Delivery $d, User $viewer, Order $order, bool $draft = false): array
    {
        /** @var Collection<int, FileAsset> $files */
        $files = $d->files()->whereNotIn('state', [FileState::Removed->value])->get();

        return $files->map(function (FileAsset $f) use ($viewer, $order, $draft) {
            [$tone, $icon] = match ($f->state) {
                FileState::Clean => ['success', 'shield'], FileState::Rejected => ['error', 'error'], default => ['warning', 'clock']
            };
            $kb = $f->size_bytes / 1024;
            $url = $f->state->downloadable() ? $this->downloads->link($viewer, $order->reference, $f->id) : null;

            return [
                'id' => $f->id, 'name' => $f->original_name, 'ext' => strtoupper($f->extension), 'size' => $kb >= 1024 ? number_format($kb / 1024, 1, ',', ' ').' Mo' : number_format($kb, 0, ',', ' ').' Ko',
                'label' => $f->state->label(), 'tone' => $tone, 'icon' => $icon, 'url' => $url, 'canRemove' => $draft && $f->state !== FileState::Removed,
                'note' => $f->state === FileState::Rejected ? 'Refusé par le contrôle de sécurité : non téléchargeable.' : (! $f->state->downloadable() ? 'Non téléchargeable avant la fin du contrôle de sécurité.' : null),
            ];
        })->values()->all();
    }
}
