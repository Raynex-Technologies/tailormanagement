<?php

namespace App\Reports;

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?int $categoryId;

    protected bool $lowStockOnly;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->categoryId = $filters['category_id'] ?? null;
        $this->lowStockOnly = $filters['low_stock_only'] ?? false;
        $this->search = $filters['search'] ?? null;
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $branchId = BranchContext::id();

        $stockQuery = InventoryStock::query()
            ->whereHas('item', function ($q) {
                if ($this->categoryId) {
                    $q->where('inventory_category_id', $this->categoryId);
                }
                if ($this->search) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%");
                }
            });

        $totalItems = $stockQuery->count();
        $totalOnHand = (clone $stockQuery)->sum('qty_on_hand');

        // Low stock count
        $lowStockCount = InventoryStock::query()
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_items.branch_id', $branchId)
            ->when($this->categoryId, fn ($q) => $q->where('inventory_items.inventory_category_id', $this->categoryId))
            ->whereColumn('inventory_stocks.qty_on_hand', '<=', 'inventory_items.reorder_level')
            ->count();

        // Movement summary for the date range
        $received = $this->getTransactionTotal('receive');
        $issued = $this->getTransactionTotal('issue');

        return [
            'total_items' => $totalItems,
            'low_stock_count' => $lowStockCount,
            'total_on_hand' => (float) $totalOnHand,
            'total_received' => (float) $received,
            'total_issued' => (float) $issued,
        ];
    }

    /**
     * Get current stock levels (paginated).
     */
    public function stockLevels(int $perPage = 15): LengthAwarePaginator
    {
        $query = InventoryStock::query()
            ->select([
                'inventory_stocks.*',
                'inventory_items.sku',
                'inventory_items.name as item_name',
                'inventory_items.reorder_level',
                'inventory_categories.name as category_name',
            ])
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoin('inventory_categories', 'inventory_items.inventory_category_id', '=', 'inventory_categories.id');

        // Category filter
        if ($this->categoryId) {
            $query->where('inventory_items.inventory_category_id', $this->categoryId);
        }

        // Low stock filter
        if ($this->lowStockOnly) {
            $query->whereColumn('inventory_stocks.qty_on_hand', '<=', 'inventory_items.reorder_level');
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('inventory_items.name', 'like', "%{$this->search}%")
                    ->orWhere('inventory_items.sku', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('inventory_items.name')->paginate($perPage);
    }

    /**
     * Get movement summary for items (paginated).
     */
    public function movementSummary(int $perPage = 15): LengthAwarePaginator
    {
        $branchId = BranchContext::id();

        $query = InventoryItem::query()
            ->select([
                'inventory_items.id',
                'inventory_items.sku',
                'inventory_items.name',
                DB::raw("(SELECT COALESCE(SUM(qty), 0) FROM inventory_transactions WHERE inventory_transactions.inventory_item_id = inventory_items.id AND inventory_transactions.type = 'receive' AND inventory_transactions.created_at >= '{$this->dateFrom}' AND inventory_transactions.created_at <= '{$this->dateTo} 23:59:59') as received_qty"),
                DB::raw("(SELECT COALESCE(SUM(qty), 0) FROM inventory_transactions WHERE inventory_transactions.inventory_item_id = inventory_items.id AND inventory_transactions.type = 'issue' AND inventory_transactions.created_at >= '{$this->dateFrom}' AND inventory_transactions.created_at <= '{$this->dateTo} 23:59:59') as issued_qty"),
                DB::raw("(SELECT COALESCE(SUM(CASE WHEN type = 'adjustment' THEN qty ELSE 0 END), 0) FROM inventory_transactions WHERE inventory_transactions.inventory_item_id = inventory_items.id AND inventory_transactions.created_at >= '{$this->dateFrom}' AND inventory_transactions.created_at <= '{$this->dateTo} 23:59:59') as adjusted_qty"),
                DB::raw("(SELECT MAX(created_at) FROM inventory_transactions WHERE inventory_transactions.inventory_item_id = inventory_items.id) as last_movement_at"),
            ]);

        // Category filter
        if ($this->categoryId) {
            $query->where('inventory_items.inventory_category_id', $this->categoryId);
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('inventory_items.name', 'like', "%{$this->search}%")
                    ->orWhere('inventory_items.sku', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('inventory_items.name')->paginate($perPage);
    }

    /**
     * Export stock levels for CSV.
     */
    public function exportStockLevels(): array
    {
        $rows = InventoryStock::query()
            ->select([
                'inventory_stocks.*',
                'inventory_items.sku',
                'inventory_items.name as item_name',
                'inventory_items.reorder_level',
                'inventory_categories.name as category_name',
            ])
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoin('inventory_categories', 'inventory_items.inventory_category_id', '=', 'inventory_categories.id');

        if ($this->categoryId) {
            $rows->where('inventory_items.inventory_category_id', $this->categoryId);
        }

        if ($this->lowStockOnly) {
            $rows->whereColumn('inventory_stocks.qty_on_hand', '<=', 'inventory_items.reorder_level');
        }

        if ($this->search) {
            $rows->where(function ($q) {
                $q->where('inventory_items.name', 'like', "%{$this->search}%")
                    ->orWhere('inventory_items.sku', 'like', "%{$this->search}%");
            });
        }

        $rows = $rows->orderBy('inventory_items.name')->get();

        $data = [];
        $data[] = ['SKU', 'Item', 'Category', 'On Hand', 'Reserved', 'Reorder Level', 'Low Stock'];

        foreach ($rows as $row) {
            $isLowStock = $row->qty_on_hand <= $row->reorder_level;
            $data[] = [
                $row->sku,
                $row->item_name,
                $row->category_name ?? '',
                number_format($row->qty_on_hand, 2),
                number_format($row->qty_reserved, 2),
                number_format($row->reorder_level, 2),
                $isLowStock ? 'Yes' : 'No',
            ];
        }

        return $data;
    }

    /**
     * Get transaction total for a type in the date range.
     */
    protected function getTransactionTotal(string $type): float
    {
        return InventoryTransaction::where('type', $type)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->sum('qty');
    }
}
