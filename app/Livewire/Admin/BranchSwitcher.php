<?php

namespace App\Livewire\Admin;

use App\Models\Branch;
use App\Support\BranchContext;
use Livewire\Component;

/**
 * Branch Switcher component for global admin roles.
 * Allows admin/superadmin users to switch between branches.
 * Selection is stored in session and persists until logout.
 */
class BranchSwitcher extends Component
{
    public ?int $selectedBranchId = null;

    public function mount(): void
    {
        // Initialize from current context
        $this->selectedBranchId = BranchContext::id();
    }

    /**
     * Handle branch selection change.
     */
    public function updatedSelectedBranchId(mixed $value): void
    {
        $this->switchBranch($value);
    }

    /**
     * Explicit action handler for branch switch requests from the select.
     */
    public function switchBranch(mixed $value): void
    {
        $branchId = $this->normalizeBranchId($value);

        if ($branchId === null) {
            $this->clearSelection();

            return;
        }

        $branch = Branch::whereKey($branchId)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            session()->flash('error', 'Invalid branch selection.');
            $this->selectedBranchId = BranchContext::id();

            return;
        }

        BranchContext::setActiveBranch($branchId);
        $this->selectedBranchId = $branchId;

        $this->redirectToPreviousPage();
    }

    /**
     * Clear the branch selection.
     */
    public function clearSelection(): void
    {
        BranchContext::clearActiveBranch();
        $this->selectedBranchId = BranchContext::id();

        $this->redirectToPreviousPage();
    }

    /**
     * Normalize branch values coming from the select input.
     */
    protected function normalizeBranchId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Force a full reload so branch-scoped data refreshes immediately.
     */
    protected function redirectToPreviousPage(): void
    {
        $this->redirect(request()->header('Referer', route('dashboard')), navigate: false);
    }

    /**
     * Get all active branches for the dropdown.
     */
    public function getBranchesProperty()
    {
        return Branch::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * Get the current branch name for display.
     */
    public function getCurrentBranchNameProperty(): string
    {
        if ($this->selectedBranchId === null) {
            return 'Select Branch...';
        }

        $branch = Branch::find($this->selectedBranchId);

        return $branch?->name ?? 'Unknown Branch';
    }

    public function render()
    {
        // Only render for global admins
        $user = auth()->user();

        if (! $user || ! $user->isGlobalAdmin()) {
            return <<<'HTML'
            <div></div>
            HTML;
        }

        return view('livewire.admin.branch-switcher', [
            'branches' => $this->branches,
            'currentBranchName' => $this->currentBranchName,
        ]);
    }
}
