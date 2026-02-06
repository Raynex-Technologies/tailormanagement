<?php

namespace App\Livewire\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ConversationService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public Conversation $conversation;

    public string $messageBody = '';

    public int $messagesLimit = 30;

    public bool $showParticipantsModal = false;

    public bool $showAddParticipantsModal = false;

    public array $selectedUserIds = [];

    public function mount(Conversation $conversation): void
    {
        $this->authorize('view', $conversation);

        $this->conversation = $conversation->load(['users', 'creator']);

        // Mark conversation as read
        $this->markAsRead();
    }

    public function getTitle(): string
    {
        return $this->conversation->getDisplayNameFor(auth()->user()) . ' - Messages';
    }

    #[Computed]
    public function messages(): Collection
    {
        return Message::where('conversation_id', $this->conversation->id)
            ->with('sender')
            ->orderByDesc('created_at')
            ->limit($this->messagesLimit)
            ->get()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function hasMoreMessages(): bool
    {
        return Message::where('conversation_id', $this->conversation->id)->count() > $this->messagesLimit;
    }

    #[Computed]
    public function participants(): Collection
    {
        return $this->conversation->users;
    }

    #[Computed]
    public function availableUsersToAdd(): Collection
    {
        $existingParticipantIds = $this->conversation->users->pluck('id')->toArray();

        return User::where('branch_id', BranchContext::id())
            ->whereNotIn('id', $existingParticipantIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function loadMoreMessages(): void
    {
        $this->messagesLimit += 30;
    }

    public function sendMessage(): void
    {
        $this->authorize('sendMessage', $this->conversation);

        $this->validate([
            'messageBody' => 'required|string|max:2000',
        ]);

        try {
            $service = app(ConversationService::class);
            $service->sendMessage($this->conversation, auth()->user(), $this->messageBody);

            $this->messageBody = '';
            $this->dispatch('message-sent');
        } catch (\Exception $e) {
            $this->addError('messageBody', $e->getMessage());
        }
    }

    public function markAsRead(): void
    {
        app(ConversationService::class)->markAsRead($this->conversation, auth()->user());
    }

    public function openAddParticipantsModal(): void
    {
        $this->authorize('addParticipants', $this->conversation);
        $this->selectedUserIds = [];
        $this->showAddParticipantsModal = true;
    }

    public function toggleUserSelection(int $userId): void
    {
        if (in_array($userId, $this->selectedUserIds)) {
            $this->selectedUserIds = array_values(array_diff($this->selectedUserIds, [$userId]));
        } else {
            $this->selectedUserIds[] = $userId;
        }
    }

    public function addParticipants(): void
    {
        $this->authorize('addParticipants', $this->conversation);

        if (empty($this->selectedUserIds)) {
            $this->addError('selectedUserIds', 'Please select at least one user.');

            return;
        }

        try {
            $service = app(ConversationService::class);
            $service->addParticipants($this->conversation, $this->selectedUserIds, auth()->user());

            $this->showAddParticipantsModal = false;
            $this->selectedUserIds = [];
            $this->conversation->refresh();

            session()->flash('success', 'Participants added successfully.');
        } catch (\Exception $e) {
            $this->addError('selectedUserIds', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.messages.show')
            ->title($this->getTitle());
    }
}
