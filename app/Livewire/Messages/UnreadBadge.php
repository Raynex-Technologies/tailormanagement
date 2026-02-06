<?php

namespace App\Livewire\Messages;

use App\Services\Messaging\ConversationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UnreadBadge extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        if (! auth()->user()->can('messages.use')) {
            return 0;
        }

        return app(ConversationService::class)->getUnreadConversationsCount(auth()->user());
    }

    public function render()
    {
        return view('livewire.messages.unread-badge');
    }
}
