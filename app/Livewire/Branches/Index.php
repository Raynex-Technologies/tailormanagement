<?php

namespace App\Livewire\Branches;

use App\Concerns\PasswordValidationRules;
use App\Models\Branch;
use App\Services\Branches\BranchDeletionService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Branches')]
class Index extends Component
{
    use PasswordValidationRules, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public int $perPage = 15;

    public bool $showFormModal = false;

    public bool $isEditing = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public bool $is_active = true;

    public bool $showDeletePasswordModal = false;

    public bool $showDeleteConfirmModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $deletePassword = '';

    public bool $deletePasswordConfirmed = false;

    /**
     * @var array<string, int>
     */
    public array $deleteImpact = [];

    /**
     * @var array<string, int>
     */
    public array $deleteBlockers = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Branch::class);
    }

    protected function rules(): array
    {
        $nameRule = Rule::unique('branches', 'name');
        $codeRule = Rule::unique('branches', 'code');

        if ($this->editingId !== null) {
            $nameRule = $nameRule->ignore($this->editingId);
            $codeRule = $codeRule->ignore($this->editingId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => [$this->isEditing ? 'required' : 'nullable', 'string', 'max:50', $codeRule],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Branch::class);

        $this->resetForm();
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEditModal(int $branchId): void
    {
        $branch = $this->findAccessibleBranch($branchId);

        $this->authorize('update', $branch);

        $this->editingId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->phone = (string) ($branch->phone ?? '');
        $this->address = (string) ($branch->address ?? '');
        $this->is_active = $branch->is_active;
        $this->isEditing = true;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $branch = $this->editingId !== null
            ? $this->findAccessibleBranch($this->editingId)
            : new Branch();

        if ($this->editingId !== null) {
            $this->authorize('update', $branch);
        } else {
            $this->authorize('create', Branch::class);
        }

        $this->validate();

        $code = strtoupper(trim($this->code));

        $payload = [
            'code' => $code !== '' ? $code : null,
            'name' => trim($this->name),
            'phone' => trim($this->phone) !== '' ? trim($this->phone) : null,
            'address' => trim($this->address) !== '' ? trim($this->address) : null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId !== null) {
            $branch->update($payload);
            session()->flash('success', 'Branch updated successfully.');
        } else {
            Branch::create($payload);
            session()->flash('success', 'Branch created successfully.');
        }

        $this->closeFormModal();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDeletePasswordModal(int $branchId): void
    {
        $branch = $this->findAccessibleBranch($branchId);

        $this->authorize('delete', $branch);

        $this->resetDeleteState();
        $this->deletingId = $branch->id;
        $this->deletingName = $branch->name;
        $this->showDeletePasswordModal = true;
    }

    public function closeDeletePasswordModal(): void
    {
        $this->showDeletePasswordModal = false;
        $this->resetValidation('deletePassword');
        $this->deletePassword = '';
    }

    public function closeDeleteConfirmModal(): void
    {
        $this->showDeleteConfirmModal = false;
        $this->resetDeleteState();
    }

    public function verifyDeletePassword(BranchDeletionService $deletionService): void
    {
        $branch = $this->deletingBranch();

        $this->authorize('delete', $branch);

        $this->validate([
            'deletePassword' => $this->currentPasswordRules(),
        ]);

        $inspection = $deletionService->inspect($branch);

        $this->deletePasswordConfirmed = true;
        $this->deleteImpact = $inspection['soft_deleteable'];
        $this->deleteBlockers = $inspection['blocking'];
        $this->showDeletePasswordModal = false;
        $this->showDeleteConfirmModal = true;
    }

    public function deleteBranch(BranchDeletionService $deletionService): void
    {
        $branch = $this->deletingBranch();

        $this->authorize('delete', $branch);

        if (! $this->deletePasswordConfirmed) {
            session()->flash('error', 'Confirm your password before continuing.');

            return;
        }

        try {
            $deletionService->delete($branch, auth()->user());
        } catch (\DomainException $e) {
            $inspection = $deletionService->inspect($branch->fresh());
            $this->deleteImpact = $inspection['soft_deleteable'];
            $this->deleteBlockers = $inspection['blocking'];
            session()->flash('error', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to delete branch.');

            return;
        }

        $message = $this->deleteImpact === []
            ? 'Branch archived successfully.'
            : 'Branch archived successfully. Supported order-related data was soft-deleted.';

        $this->showDeleteConfirmModal = false;
        $this->resetDeleteState();
        session()->flash('success', $message);
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $query = Branch::query()
            ->withCount(['users', 'orders'])
            ->when(! $user->isGlobalAdmin(), fn ($builder) => $builder->whereKey($user->branch_id))
            ->when($this->search !== '', function ($builder) {
                $term = '%' . $this->search . '%';

                $builder->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->orderBy('name');

        $branches = $query->paginate($this->perPage);

        $statsQuery = Branch::query()
            ->when(! $user->isGlobalAdmin(), fn ($builder) => $builder->whereKey($user->branch_id));

        return view('livewire.branches.index', [
            'branches' => $branches,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('is_active', true)->count(),
                'inactive' => (clone $statsQuery)->where('is_active', false)->count(),
            ],
            'canManage' => $user->can('create', Branch::class),
            'canDelete' => $user->hasRole('superadmin'),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId',
            'code',
            'name',
            'phone',
            'address',
        ]);

        $this->isEditing = false;
        $this->is_active = true;
        $this->resetValidation();
    }

    protected function resetDeleteState(): void
    {
        $this->reset([
            'deletingId',
            'deletingName',
            'deletePassword',
        ]);

        $this->deletePasswordConfirmed = false;
        $this->deleteImpact = [];
        $this->deleteBlockers = [];
        $this->resetValidation('deletePassword');
    }

    protected function findAccessibleBranch(int $branchId): Branch
    {
        return Branch::query()
            ->when(! auth()->user()->isGlobalAdmin(), fn ($builder) => $builder->whereKey(auth()->user()->branch_id))
            ->findOrFail($branchId);
    }

    protected function deletingBranch(): Branch
    {
        abort_if($this->deletingId === null, 404);

        return $this->findAccessibleBranch($this->deletingId);
    }
}
