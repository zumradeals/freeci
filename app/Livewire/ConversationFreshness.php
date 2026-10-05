<?php

namespace App\Livewire;

use App\Modules\Messaging\Queries\Inbox;
use Livewire\Component;

/** Signale les nouveaux messages d'une conversation sans rien afficher d'eux ni les marquer lus : un lien d'actualisation suffit. */
class ConversationFreshness extends Component
{
    public string $conversation = '';

    public int $after = 0;

    public function render()
    {
        $user = auth()->user();

        return view('livewire.conversation-freshness', ['n' => $user === null ? 0 : app(Inbox::class)->newerThan($user, $this->conversation, $this->after)]);
    }
}
