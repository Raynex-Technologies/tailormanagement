<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public User $user;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);
        $this->user = $user->load(['roles', 'branch']);
    }

    public function render()
    {
        $actor = auth()->user();

        // Get recent activity (simple version - just counts)
        $activity = [
            'orders_created' => $this->user->createdOrders()->count(),
            'orders_assigned' => $this->user->assignedOrders()->count(),
            'payments_received' => $this->user->receivedPayments()->count(),
            'expenses_created' => $this->user->expenses()->count(),
        ];

        return view('livewire.users.show', [
            'activity' => $activity,
            'canEdit' => $actor->can('update', $this->user),
        ])->title($this->user->name);
    }
}
