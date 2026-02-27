<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockRequestStatus;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderExpense;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Models\OrderPayment;
use App\Models\OrderStockRequest;
use App\Models\OrderStockRequestItem;
use App\Models\OrderWatcher;
use App\Services\Inventory\StockMovementService;
use App\Services\Orders\OrderDeletionService;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDeleteFlowTest extends TestCase
{
    public function test_order_delete_soft_deletes_order_and_related_records(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $watcherUser = $this->createUserWithRole('sales', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailor->id,
            'status' => OrderStatus::InProgress,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $manager->id,
        ]);

        $line = OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Wedding Suit',
            'qty' => 1,
            'unit_price' => 120000,
            'line_total' => 120000,
        ]);

        $measurement = OrderMeasurement::create([
            'order_line_id' => $line->id,
            'measurements' => ['chest' => 42, 'waist' => 36],
        ]);

        $payment = OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'amount' => 50000,
            'paid_at' => now(),
            'received_by' => $manager->id,
            'payment_method_id' => null,
        ]);

        $comment = OrderComment::create([
            'order_id' => $order->id,
            'user_id' => $manager->id,
            'comment' => 'Customer requested pickup on Friday.',
        ]);

        $watcher = OrderWatcher::create([
            'order_id' => $order->id,
            'user_id' => $watcherUser->id,
            'notify_on_comment' => true,
            'notify_on_status_change' => true,
        ]);

        $expense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 15000,
            'notes' => 'Tailor labor cost',
        ]);

        $stockRequest = OrderStockRequest::create([
            'order_id' => $order->id,
            'requested_by' => $manager->id,
            'status' => StockRequestStatus::Requested,
            'note' => 'Fabric and lining',
        ]);

        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $inventoryItem = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);

        $stockRequestItem = OrderStockRequestItem::create([
            'order_stock_request_id' => $stockRequest->id,
            'inventory_item_id' => $inventoryItem->id,
            'qty_requested' => 3,
            'qty_approved' => 3,
            'qty_issued' => 0,
        ]);

        $deliveryNote = DeliveryNote::create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'delivery_note_no' => 'DN-DELETE-001',
            'delivered_at' => now(),
            'delivered_by' => $manager->id,
        ]);

        $order->recalculateTotals();
        $invoice = $order->invoice()->with('lines')->firstOrFail();
        $invoiceLine = $invoice->lines->firstOrFail();

        Livewire::actingAs($manager)
            ->test(OrdersShow::class, ['order' => $order])
            ->call('deleteOrder')
            ->assertRedirect(route('orders.index'));

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSoftDeleted('order_lines', ['id' => $line->id]);
        $this->assertSoftDeleted('order_measurements', ['id' => $measurement->id]);
        $this->assertSoftDeleted('order_payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('order_comments', ['id' => $comment->id]);
        $this->assertSoftDeleted('order_watchers', ['id' => $watcher->id]);
        $this->assertSoftDeleted('order_expenses', ['id' => $expense->id]);
        $this->assertSoftDeleted('order_stock_requests', ['id' => $stockRequest->id]);
        $this->assertSoftDeleted('order_stock_request_items', ['id' => $stockRequestItem->id]);
        $this->assertSoftDeleted('delivery_notes', ['id' => $deliveryNote->id]);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('invoice_lines', ['id' => $invoiceLine->id]);
    }

    public function test_order_delete_reinstates_issued_inventory(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $inventoryItem = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);
        $inventoryItem->stock()->update(['qty_on_hand' => 30, 'qty_reserved' => 0]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Blazer',
            'qty' => 1,
            'unit_price' => 90000,
            'line_total' => 90000,
        ]);
        $order->recalculateTotals();

        $stockRequest = OrderStockRequest::create([
            'order_id' => $order->id,
            'requested_by' => $manager->id,
            'status' => StockRequestStatus::Fulfilled,
            'note' => 'Issued for production',
        ]);

        $requestItem = OrderStockRequestItem::create([
            'order_stock_request_id' => $stockRequest->id,
            'inventory_item_id' => $inventoryItem->id,
            'qty_requested' => 5,
            'qty_approved' => 5,
            'qty_issued' => 5,
        ]);

        app(StockMovementService::class)->issue(
            item: $inventoryItem,
            qty: 5,
            note: 'Issue for order production',
            actor: $manager,
            reference: $stockRequest
        );

        $inventoryItem->stock->refresh();
        $this->assertEquals(25, (float) $inventoryItem->stock->qty_on_hand);

        app(OrderDeletionService::class)->delete($order, $manager);

        $inventoryItem->stock->refresh();
        $this->assertEquals(30, (float) $inventoryItem->stock->qty_on_hand);

        $returnTransaction = InventoryTransaction::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('type', InventoryTransactionType::Return)
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($returnTransaction);
        $this->assertEquals(5.0, (float) $returnTransaction->qty);
        $this->assertSoftDeleted('order_stock_request_items', ['id' => $requestItem->id]);
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }
}
