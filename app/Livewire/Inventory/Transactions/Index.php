<?php

namespace App\Livewire\Inventory\Transactions;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Transactions')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $itemFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public int $perPage = 25;

    public function mount(): void
    {
        // Default to last 30 days if no date range set
        if (empty($this->dateFrom) && empty($this->dateTo)) {
            $this->dateFrom = now()->subDays(30)->format('Y-m-d');
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedItemFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'itemFilter']);
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $transactions = InventoryTransaction::query()
            ->with(['item', 'creator'])
            ->when($this->search, fn ($q) => $q->whereHas('item', function ($iq) {
                $iq->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%");
            }))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->itemFilter, fn ($q) => $q->where('inventory_item_id', $this->itemFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $transactionTypes = collect(InventoryTransactionType::cases())
            ->mapWithKeys(fn ($type) => [$type->value => $type->label()]);

        $items = InventoryItem::orderBy('name')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->id => "{$item->sku} - {$item->name}"]);

        // Calculate summary stats for the filtered period
        $stats = $this->getStats();

        return view('livewire.inventory.transactions.index', [
            'transactions' => $transactions,
            'transactionTypes' => $transactionTypes,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    protected function getStats(): array
    {
        $baseQuery = InventoryTransaction::query()
            ->when($this->itemFilter, fn ($q) => $q->where('inventory_item_id', $this->itemFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));

        $received = (clone $baseQuery)
            ->where('type', InventoryTransactionType::Receive)
            ->sum('qty');

        $issued = (clone $baseQuery)
            ->where('type', InventoryTransactionType::Issue)
            ->sum('qty');

        $adjusted = (clone $baseQuery)
            ->where('type', InventoryTransactionType::Adjust)
            ->sum('qty');

        $totalTransactions = (clone $baseQuery)->count();

        return [
            'received' => abs($received),
            'issued' => abs($issued),
            'adjusted' => $adjusted,
            'total_transactions' => $totalTransactions,
        ];
    }
}
