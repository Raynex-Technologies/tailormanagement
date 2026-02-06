<?php

namespace App\Livewire\Messages;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\ConversationService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Messages')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $userSearch = '';

    public bool $showNewGroupModal = false;

    public string $groupTitle = '';

    public array $selectedUserIds = [];

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('viewAny', Conversation::class);
    }

    #[Computed]
    public function conversations()
    {
        return app(ConversationService::class)
            ->getConversationsForUser(auth()->user(), $this->search ?: null);
    }

    #[Computed]
    public function searchableUsers(): Collection
    {
        if (strlen($this->userSearch) < 2) {
            return collect();
        }

        return User::where('branch_id', BranchContext::id())
            ->where('id', '!=', auth()->id())
            ->where('name', 'like', "%{$this->userSearch}%")
            ->limit(10)
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function branchUsers(): Collection
    {
        return User::where('branch_id', BranchContext::id())
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function startDirectChat(int $userId): void
    {
        try {
            $service = app(ConversationService::class);
            $conversation = $service->getOrCreateDirect(auth()->user(), $userId);

            $this->userSearch = '';
            $this->redirect(route('messages.show', $conversation), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openNewGroupModal(): void
    {
        $this->authorize('createGroup', Conversation::class);
        $this->reset(['groupTitle', 'selectedUserIds']);
        $this->showNewGroupModal = true;
    }

    public function toggleUserSelection(int $userId): void
    {
        if (in_array($userId, $this->selectedUserIds)) {
            $this->selectedUserIds = array_values(array_diff($this->selectedUserIds, [$userId]));
        } else {
            $this->selectedUserIds[] = $userId;
        }
    }

    public function createGroup(): void
    {
        $this->authorize('createGroup', Conversation::class);

        $this->validate([
            'groupTitle' => 'required|string|max:100',
            'selectedUserIds' => 'required|array|min:1',
        ], [
            'groupTitle.required' => 'Please enter a group name.',
            'selectedUserIds.required' => 'Please select at least one participant.',
            'selectedUserIds.min' => 'Please select at least one participant.',
        ]);

        try {
            $service = app(ConversationService::class);
            $conversation = $service->createGroup(
                auth()->user(),
                $this->groupTitle,
                $this->selectedUserIds
            );

            $this->showNewGroupModal = false;
            $this->reset(['groupTitle', 'selectedUserIds']);

            session()->flash('success', 'Group created successfully.');
            $this->redirect(route('messages.show', $conversation), navigate: true);
        } catch (\Exception $e) {
            $this->addError('groupTitle', $e->getMessage());
        }
    }

    public function getUnreadCountFor(Conversation $conversation): int
    {
        return $conversation->getUnreadCountFor(auth()->id());
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.messages.index');
    }
}
