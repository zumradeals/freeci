<?php

namespace App\Livewire;

use App\Modules\Messaging\Queries\Inbox;
use App\Modules\Notifications\Actions\NotificationCenter;
use Livewire\Component;

/** Compteur de non-lus (messages ou notifications), actualisé légèrement par interrogation : pas de temps réel. */
class UnreadBadge extends Component
{
    public string $kind = 'messages';

    public function render()
    {
        $user = auth()->user();
        $n = $user === null ? 0 : ($this->kind === 'messages' ? app(Inbox::class)->totalUnread($user) : app(NotificationCenter::class)->unread($user));

        return view('livewire.unread-badge', ['n' => $n]);
    }
}
