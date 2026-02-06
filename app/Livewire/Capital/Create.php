<?php

namespace App\Livewire\Capital;

use App\Models\Branch;
use App\Models\User;
use App\Services\Capital\CapitalAllocationService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Create extends Component
{
    use AuthorizesRequests;

    public ?int $accountantId = null;
    public ?string $startsOn = null;
    public ?string $endsOn = null;
    public ?float $initialAmount = null;
    public ?string $note = null;

    // Branch selection for global admins
    public ?int $branchId = null;
    public bool $showBranchSelector = false;

    protected function rules(): array
    {
        $rules = [
            'accountantId' => ['required', 'exists:users,id'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'initialAmount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        // Branch_id required for global admins if no branch context
        if ($this->showBranchSelector) {
            $rules['branchId'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    public function mount(): void
    {
        $this->authorize('capital.assign');
        $this->startsOn = now()->format('Y-m-d');

        $user = auth()->user();
        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($this->showBranchSelector) {
            // Pre-fill with active branch context if available
            $this->branchId = BranchContext::id();
        } else {
            // Branch-tied users use their own branch
            if (! $user->branch_id) {
                abort(403, 'You must be assigned to a branch to create capital allocations.');
            }
            $this->branchId = $user->branch_id;
        }
    }

    public function save(CapitalAllocationService $service): void
    {
        $this->authorize('capital.assign');
        $this->validate();

        try {
            $allocation = $service->assign([
                'accountant_id' => $this->accountantId,
                'starts_on' => $this->startsOn,
                'ends_on' => $this->endsOn,
                'initial_amount' => $this->initialAmount,
                'note' => $this->note,
                'branch_id' => $this->branchId,
            ], auth()->user());

            session()->flash('success', "Capital allocation {$allocation->allocation_no} created successfully.");
            $this->redirect(route('capital.show', $allocation), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create allocation: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Use selected branch_id if available, otherwise current context
        $effectiveBranchId = $this->branchId ?? BranchContext::id();

        // Get accountants - scoped to selected branch
        $accountants = User::role('accountant')
            ->when($effectiveBranchId, fn ($q) => $q->where('branch_id', $effectiveBranchId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get()
            : collect();

        return view('livewire.capital.create', [
            'accountants' => $accountants,
            'branches' => $branches,
        ])->title(__('Create Capital Allocation'));
    }
}
