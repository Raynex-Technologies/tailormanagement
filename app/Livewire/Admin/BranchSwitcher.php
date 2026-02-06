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
    public function updatedSelectedBranchId(?int $value): void
    {
        if ($value === null || $value === 0) {
            // Clear selection
            $this->clearSelection();

            return;
        }

        // Validate branch exists and is active
        $branch = Branch::where('id', $value)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            session()->flash('error', 'Invalid branch selection.');
            $this->selectedBranchId = BranchContext::id();

            return;
        }

        // Set active branch in session and context
        BranchContext::setActiveBranch($value);

        // Refresh the page to apply the new branch context
        $this->redirect(request()->header('Referer', route('dashboard')), navigate: true);
    }

    /**
     * Clear the branch selection.
     */
    public function clearSelection(): void
    {
        BranchContext::clearActiveBranch();
        $this->selectedBranchId = null;

        // Refresh to apply change
        $this->redirect(request()->header('Referer', route('dashboard')), navigate: true);
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
