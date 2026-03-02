<?php

namespace Tests\Feature\Procurement;

use App\Enums\CapitalAllocationStatus;
use App\Enums\CapitalTransactionType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\CapitalAllocation;
use App\Models\CapitalTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Procurement\ReceivingService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementFlowTest extends TestCase
{
    protected PurchaseRequestService $prService;

    protected ReceivingService $receivingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prService = app(PurchaseRequestService::class);
        $this->receivingService = app(ReceivingService::class);
    }

    public function test_approving_purchase_request_requires_active_allocation(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);

        // No active allocation for this accountant
        // Create purchase request
        $pr = PurchaseRequest::create([
            'branch_id' => $this->branch->id,
            'request_no' => 'PR-TEST-001',
            'requested_by' => $accountant->id,
            'status' => PurchaseRequestStatus::Submitted,
            'note' => 'Test PR',
        ]);

        $pr->items()->create([
            'item_name' => 'Test Item',
            'qty' => 10,
            'unit_price_est' => 5000,
            'line_total_est' => 50000,
        ]);

        $this->expectException(ValidationException::class);

        // Try to approve without any active allocation
        $this->prService->approve($pr, $accountant, []);
    }

    public function test_approving_purchase_request_deducts_via_capital_transactions(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);

        // Create open allocation for accountant
        $allocation = CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-TEST-002',
            'accountant_id' => $accountant->id,
            'initial_amount' => 500000,
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Open,
            'created_by' => $accountant->id,
        ]);

        // Create purchase request
        $pr = PurchaseRequest::create([
            'branch_id' => $this->branch->id,
            'request_no' => 'PR-TEST-002',
            'requested_by' => $accountant->id,
            'status' => PurchaseRequestStatus::Submitted,
            'estimated_total' => 50000,
            'note' => 'Test PR',
        ]);

        $prItem = $pr->items()->create([
            'item_name' => 'Test Item',
            'qty' => 10,
            'unit_price_est' => 5000,
            'line_total_est' => 50000,
        ]);

        $initialTransactionCount = CapitalTransaction::count();

        // Approve with allocation (accountant approves)
        $this->prService->approve($pr, $accountant, [
            ['id' => $prItem->id, 'qty' => 10, 'unit_price_est' => 5000],
        ]);

        $pr->refresh();
        $allocation->refresh();

        // Check PR is approved
        $this->assertEquals(PurchaseRequestStatus::Approved, $pr->status);

        // Check capital transaction created
        $this->assertEquals($initialTransactionCount + 1, CapitalTransaction::count());

        $transaction = CapitalTransaction::latest()->first();
        $this->assertEquals(CapitalTransactionType::Debit, $transaction->type);
        $this->assertEquals(50000, $transaction->amount);

        // Check allocation spent amount updated
        $this->assertEquals(50000, $allocation->spent_amount);
    }

    public function test_approving_purchase_request_blocks_if_insufficient_allocation_balance(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);

        // Create allocation with small balance
        $allocation = CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-TEST-003',
            'accountant_id' => $accountant->id,
            'initial_amount' => 30000, // Small amount
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Open,
            'created_by' => $accountant->id,
        ]);

        // Create purchase request for more than allocation
        $pr = PurchaseRequest::create([
            'branch_id' => $this->branch->id,
            'request_no' => 'PR-TEST-003',
            'requested_by' => $accountant->id,
            'status' => PurchaseRequestStatus::Submitted,
            'estimated_total' => 50000,
            'note' => 'Large PR',
        ]);

        $prItem = $pr->items()->create([
            'item_name' => 'Expensive Item',
            'qty' => 10,
            'unit_price_est' => 5000,
            'line_total_est' => 50000, // More than 30000 allocation
        ]);

        $this->expectException(ValidationException::class);

        // Try to approve - should fail due to insufficient balance
        $this->prService->approve($pr, $accountant, [
            ['id' => $prItem->id, 'qty' => 10, 'unit_price_est' => 5000],
        ]);
    }

    public function test_approving_purchase_request_rejects_non_positive_review_quantities(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);

        CapitalAllocation::create([
            'branch_id' => $this->branch->id,
            'allocation_no' => 'CA-TEST-004',
            'accountant_id' => $accountant->id,
            'initial_amount' => 500000,
            'spent_amount' => 0,
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
            'status' => CapitalAllocationStatus::Open,
            'created_by' => $accountant->id,
        ]);

        $pr = PurchaseRequest::create([
            'branch_id' => $this->branch->id,
            'request_no' => 'PR-TEST-004',
            'requested_by' => $accountant->id,
            'status' => PurchaseRequestStatus::Submitted,
            'estimated_total' => 50000,
            'note' => 'Invalid reviewed qty',
        ]);

        $prItem = $pr->items()->create([
            'item_name' => 'Test Item',
            'qty' => 10,
            'unit_price_est' => 5000,
            'line_total_est' => 50000,
        ]);

        $this->expectException(ValidationException::class);

        $this->prService->approve($pr, $accountant, [
            ['id' => $prItem->id, 'qty' => 0, 'unit_price_est' => 5000],
        ]);
    }

    public function test_receiving_against_po_increases_inventory(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $supplier = Supplier::factory()->create(['branch_id' => $this->branch->id]);
        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);

        // Set initial stock
        $item->stock->update(['qty_on_hand' => 50]);
        $initialQty = 50;

        // Create PO
        $po = PurchaseOrder::create([
            'branch_id' => $this->branch->id,
            'po_no' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now(),
            'total_amount' => 100000,
            'created_by' => $user->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_cost' => 5000,
            'line_total' => 100000,
        ]);

        // Receive goods
        $this->receivingService->receive($po, [
            [
                'purchase_order_item_id' => $poItem->id,
                'qty_received' => 15,
                'unit_cost' => 5000,
            ],
        ], $user);

        $item->stock->refresh();
        $po->refresh();

        // Check inventory increased
        $this->assertEquals($initialQty + 15, $item->stock->qty_on_hand);
    }

    public function test_receiving_against_po_updates_po_status(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $supplier = Supplier::factory()->create(['branch_id' => $this->branch->id]);
        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);

        // Create PO
        $po = PurchaseOrder::create([
            'branch_id' => $this->branch->id,
            'po_no' => 'PO-TEST-002',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now(),
            'total_amount' => 100000,
            'created_by' => $user->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_cost' => 5000,
            'line_total' => 100000,
        ]);

        // Partial receive
        $this->receivingService->receive($po, [
            [
                'purchase_order_item_id' => $poItem->id,
                'qty_received' => 10,
                'unit_cost' => 5000,
            ],
        ], $user);

        $po->refresh();
        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $po->status);

        // Full receive
        $this->receivingService->receive($po, [
            [
                'purchase_order_item_id' => $poItem->id,
                'qty_received' => 10,
                'unit_cost' => 5000,
            ],
        ], $user);

        $po->refresh();
        $this->assertEquals(PurchaseOrderStatus::Received, $po->status);
    }
}
