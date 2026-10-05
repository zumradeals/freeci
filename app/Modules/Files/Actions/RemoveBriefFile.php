<?php

namespace App\Modules\Files\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Retrait d'une pièce par le client, tant que le travail n'a pas démarré. */
final class RemoveBriefFile
{
    public function __invoke(User $client, string $reference, string $fileId): void
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        DB::transaction(function () use ($order, $fileId, $client) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->started_at !== null || $locked->state->isFinal()) {
                throw new InvalidTransition;
            }
            $f = FileAsset::query()->whereKey($fileId)->where('order_id', $locked->getKey())->whereNull('delivery_id')->lockForUpdate()->first();
            if ($f === null || in_array($f->state, [FileState::Removed, FileState::Rejected], true)) {
                throw new FileForbidden;
            }
            $f->forceFill(['state' => FileState::Removed, 'removed_at' => now()])->save();
            Storage::disk('private_files')->delete($f->storage_key);
            $locked->events()->create(['type' => 'brief_file_removed', 'actor_id' => $client->getKey(), 'note' => $f->original_name]);
        });
    }
}
