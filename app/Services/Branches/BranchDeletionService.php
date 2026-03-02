<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\GoodsReceipt;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStockRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Scopes\BranchScope;
use App\Models\SmsLog;
use App\Models\Supplier;
use App\Models\Todo;
use App\Models\User;
use App\Services\Orders\OrderDeletionService;
use App\Support\BranchContext;
use DomainException;
use Illuminate\Support\Facades\DB;

class BranchDeletionService
{
    /**
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected const SOFT_DELETEABLE_MODELS = [
        'Orders' => Order::class,
        'Invoices' => Invoice::class,
        'Delivery notes' => DeliveryNote::class,
        'Payments' => OrderPayment::class,
        'Stock requests' => OrderStockRequest::class,
    ];

    /**
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected const BLOCKING_MODELS = [
        'Users' => User::class,
        'Customers' => Customer::class,
        'Suppliers' => Supplier::class,
        'Inventory categories' => InventoryCategory::class,
        'Inventory items' => InventoryItem::class,
        'Inventory units' => InventoryUnit::class,
        'Inventory stock rows' => InventoryStock::class,
        'Inventory transactions' => InventoryTransaction::class,
        'Purchase requests' => PurchaseRequest::class,
        'Purchase orders' => PurchaseOrder::class,
        'Goods receipts' => GoodsReceipt::class,
        'Capital allocations' => CapitalAllocation::class,
        'Capital transactions' => CapitalTransaction::class,
        'Expenses' => Expense::class,
        'Expense categories' => ExpenseCategory::class,
        'Expense subcategories' => ExpenseSubcategory::class,
        'Conversations' => Conversation::class,
        'SMS logs' => SmsLog::class,
        'Todos' => Todo::class,
    ];

    public function __construct(
        protected OrderDeletionService $orderDeletionService
    ) {}

    /**
     * @return array{
     *     soft_deleteable: array<string, int>,
     *     blocking: array<string, int>
     * }
     */
    public function inspect(Branch $branch): array
    {
        $softDeleteable = [];
        foreach (self::SOFT_DELETEABLE_MODELS as $label => $modelClass) {
            $count = $this->countRecords($modelClass, $branch->id);
            if ($count > 0) {
                $softDeleteable[$label] = $count;
            }
        }

        $blocking = [];
        foreach (self::BLOCKING_MODELS as $label => $modelClass) {
            $count = $this->countRecords($modelClass, $branch->id);
            if ($count > 0) {
                $blocking[$label] = $count;
            }
        }

        return [
            'soft_deleteable' => $softDeleteable,
            'blocking' => $blocking,
        ];
    }

    public function delete(Branch $branch, User $actor): void
    {
        if (! $actor->hasRole('superadmin')) {
            throw new DomainException('Only superadmins can delete branches.');
        }

        $inspection = $this->inspect($branch);

        if ($inspection['blocking'] !== []) {
            throw new DomainException($this->blockingMessage($inspection['blocking']));
        }

        DB::transaction(function () use ($branch, $actor) {
            $lockedBranch = Branch::query()
                ->lockForUpdate()
                ->findOrFail($branch->id);

            if (! $lockedBranch->is_active) {
                throw new DomainException('This branch is already inactive.');
            }

            $orders = Order::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $lockedBranch->id)
                ->orderBy('id')
                ->get();

            foreach ($orders as $order) {
                $this->orderDeletionService->delete($order, $actor);
            }

            $this->softDeleteOrphanedRecords($lockedBranch->id);

            $lockedBranch->forceFill(['is_active' => false])->save();
        });

        if (BranchContext::id() === $branch->id) {
            BranchContext::clearActiveBranch();
        }
    }

    protected function softDeleteOrphanedRecords(int $branchId): void
    {
        $stockRequests = OrderStockRequest::query()
            ->withoutGlobalScope(BranchScope::class)
            ->with('items')
            ->where('branch_id', $branchId)
            ->get();

        foreach ($stockRequests as $stockRequest) {
            $stockRequest->items->each->delete();
            $stockRequest->delete();
        }

        $invoices = Invoice::query()
            ->withoutGlobalScope(BranchScope::class)
            ->with('lines')
            ->where('branch_id', $branchId)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->lines->each->delete();
            $invoice->delete();
        }

        OrderPayment::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->get()
            ->each
            ->delete();

        DeliveryNote::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->get()
            ->each
            ->delete();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function countRecords(string $modelClass, int $branchId): int
    {
        return $modelClass::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->count();
    }

    /**
     * @param  array<string, int>  $blocking
     */
    protected function blockingMessage(array $blocking): string
    {
        $summary = collect($blocking)
            ->map(fn (int $count, string $label) => "{$count} {$label}")
            ->take(4)
            ->implode(', ');

        return "Branch deletion is blocked because this branch still has records without a soft-delete path in the current schema: {$summary}. Move or clear that data first.";
    }
}
