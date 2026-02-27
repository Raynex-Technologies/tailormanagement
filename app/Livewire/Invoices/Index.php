<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $allowed = [10, 15, 25, 50];
        if (! in_array($this->perPage, $allowed, true)) {
            $this->perPage = 15;
        }
        $this->resetPage();
    }

    public function render()
    {
        $query = Invoice::query()
            ->with(['order.customer', 'branch'])
            ->search($this->search);

        $user = auth()->user();
        if ($user && $user->hasRole('tailor')) {
            $query->whereHas('order', fn ($q) => $q->forTailor($user->id));
        }

        $invoices = $query
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
        ]);
    }
}
