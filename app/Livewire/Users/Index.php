<?php

namespace App\Livewire\Users;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app.sidebar')]
#[Title('Users')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $roleFilter = '';

    #[Url]
    public string $branchFilter = '';

    public int $perPage = 15;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('users.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'branchFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $query = User::with(['roles', 'branch']);

        // Branch scoping for non-global admins
        if (! $user->isGlobalAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } else {
            // Admin can filter by branch
            if ($this->branchFilter) {
                $query->where('branch_id', $this->branchFilter);
            }
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        // Role filter
        if ($this->roleFilter) {
            $query->role($this->roleFilter);
        }

        $users = $query->orderBy('name')->paginate($this->perPage);

        // Get available roles and branches for filters
        $roles = Role::orderBy('name')->pluck('name');
        $branches = $user->isGlobalAdmin()
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        // Stats
        $stats = [
            'total' => User::when(! $user->isGlobalAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))->count(),
            'active_today' => 0, // Placeholder - would need last_login tracking
        ];

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'stats' => $stats,
            'canManage' => $user->can('users.manage'),
            'isGlobalAdmin' => $user->isGlobalAdmin(),
        ]);
    }
}
