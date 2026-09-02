<?php

namespace App\Livewire\Users;

use App\Models\Branch;
use App\Models\User;
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

    #[Url]
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

    public function updatingPerPage($value): void
    {
        if (! in_array((int) $value, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'branchFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $actor = auth()->user();
        $query = User::query()->with(['roles:id,name', 'branch:id,name']);
        $scopedUsers = User::query();

        if (! $actor->isGlobalAdmin()) {
            $query->where('branch_id', $actor->branch_id);
            $scopedUsers->where('branch_id', $actor->branch_id);
        } elseif ($this->branchFilter) {
            $query->where('branch_id', $this->branchFilter);
        }

        $query
            ->when($this->search, fn ($users) => $users->where(
                fn ($search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when($this->roleFilter, fn ($users) => $users->role($this->roleFilter));

        $users = $query->orderBy('name')->paginate($this->perPage);
        $roles = Role::query()->orderBy('name')->pluck('name');
        $branches = $actor->isGlobalAdmin()
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        // These non-sensitive directory counts are inherent to user administration.
        $stats = [
            'total' => (clone $scopedUsers)->count(),
            'with_roles' => (clone $scopedUsers)->whereHas('roles')->count(),
            'without_roles' => (clone $scopedUsers)->whereDoesntHave('roles')->count(),
            'roles_in_use' => Role::query()
                ->whereHas('users', fn ($users) => $users->when(
                    ! $actor->isGlobalAdmin(),
                    fn ($users) => $users->where('branch_id', $actor->branch_id)
                ))
                ->count(),
        ];

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'stats' => $stats,
            'canCreate' => $actor->can('create', User::class),
            'isGlobalAdmin' => $actor->isGlobalAdmin(),
        ]);
    }
}
