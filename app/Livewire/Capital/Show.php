<?php

namespace App\Livewire\Capital;

use App\Models\CapitalAllocation;
use App\Services\Capital\CapitalAllocationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public CapitalAllocation $allocation;
    public bool $showCloseModal = false;
    public ?string $closeNote = null;

    public function mount(CapitalAllocation $allocation): void
    {
        $this->authorize('view', $allocation);
        $this->allocation = $allocation->load(['accountant', 'creator', 'transactions.creator', 'transactions.reference']);
    }

    public function openCloseModal(): void
    {
        $this->authorize('close', $this->allocation);
        $this->closeNote = null;
        $this->showCloseModal = true;
    }

    public function closeAllocation(CapitalAllocationService $service): void
    {
        $this->authorize('close', $this->allocation);

        try {
            $service->close($this->allocation, auth()->user(), $this->closeNote);

            $this->allocation->refresh();
            $this->allocation->load(['accountant', 'creator', 'transactions.creator', 'transactions.reference']);
            $this->showCloseModal = false;

            session()->flash('success', 'Allocation closed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to close allocation: ' . $e->getMessage());
        }
    }

    public function getAvailableBalanceProperty(): float
    {
        return app(CapitalAllocationService::class)->availableBalance($this->allocation);
    }

    public function render()
    {
        return view('livewire.capital.show', [
            'availableBalance' => $this->availableBalance,
        ])->title("Capital: {$this->allocation->allocation_no}");
    }
}
