<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Services\Inventory\StockMovementService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    protected InventoryItem $item;

    protected InventoryStock $stock;

    protected StockMovementService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $category = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $this->item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $category->id,
        ]);

        // Get the stock created by factory
        $this->stock = $this->item->stock;
        $this->stock->update(['qty_on_hand' => 50, 'qty_reserved' => 0]);

        $this->stockService = app(StockMovementService::class);
    }

    public function test_receive_stock_increases_qty_on_hand(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $initialQty = $this->stock->qty_on_hand;

        $transaction = $this->stockService->receive(
            $this->item,
            qty: 25,
            unitCost: 1000,
            note: 'Test receive',
            actor: $user
        );

        $this->stock->refresh();

        // Verify qty increased
        $this->assertEquals($initialQty + 25, $this->stock->qty_on_hand);

        // Verify transaction created
        $this->assertInstanceOf(InventoryTransaction::class, $transaction);
        $this->assertEquals(InventoryTransactionType::Receive, $transaction->type);
        $this->assertEquals(25, $transaction->qty);
        $this->assertEquals(1000, $transaction->unit_cost);
        $this->assertEquals($this->branch->id, $transaction->branch_id);
    }

    public function test_receive_stock_creates_inventory_transaction(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);

        $initialTransactionCount = InventoryTransaction::count();

        $this->stockService->receive(
            $this->item,
            qty: 10,
            unitCost: 500,
            note: 'Test receive transaction',
            actor: $user
        );

        $this->assertEquals($initialTransactionCount + 1, InventoryTransaction::count());

        $transaction = InventoryTransaction::latest()->first();
        $this->assertEquals(InventoryTransactionType::Receive, $transaction->type);
        $this->assertEquals($this->item->id, $transaction->inventory_item_id);
    }

    public function test_adjust_negative_blocks_when_would_go_below_zero(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $this->stock->update(['qty_on_hand' => 10]);

        $this->expectException(ValidationException::class);

        // Try to adjust more than available (would go negative)
        $this->stockService->adjust(
            $this->item,
            qtyDelta: -15, // More than 10 available
            note: 'Test negative adjustment',
            actor: $user
        );
    }

    public function test_adjust_allows_valid_negative_adjustment(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $this->stock->update(['qty_on_hand' => 20]);

        $transaction = $this->stockService->adjust(
            $this->item,
            qtyDelta: -5,
            note: 'Valid negative adjustment',
            actor: $user
        );

        $this->stock->refresh();

        $this->assertEquals(15, $this->stock->qty_on_hand);
        $this->assertEquals(InventoryTransactionType::Adjust, $transaction->type);
        $this->assertEquals(-5, $transaction->qty);
    }

    public function test_issue_stock_blocks_if_insufficient(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $this->stock->update(['qty_on_hand' => 5]);

        $this->expectException(ValidationException::class);

        // Try to issue more than available
        $this->stockService->issue(
            $this->item,
            qty: 10,
            note: 'Test insufficient issue',
            actor: $user
        );
    }

    public function test_issue_stock_succeeds_with_sufficient_quantity(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $this->stock->update(['qty_on_hand' => 50]);

        $transaction = $this->stockService->issue(
            $this->item,
            qty: 20,
            note: 'Valid issue',
            actor: $user
        );

        $this->stock->refresh();

        $this->assertEquals(30, $this->stock->qty_on_hand);
        $this->assertEquals(InventoryTransactionType::Issue, $transaction->type);
        // Issue transactions store qty as negative
        $this->assertEquals(-20, $transaction->qty);
    }

    public function test_issue_stock_records_transaction_with_reference(): void
    {
        $user = $this->actingAsRole('storekeeper', $this->branch);
        $this->stock->update(['qty_on_hand' => 100]);

        // Create a mock reference object
        $mockReference = $this->item; // Use item as a reference for testing

        $transaction = $this->stockService->issue(
            $this->item,
            qty: 15,
            note: 'Issue with reference',
            actor: $user,
            reference: $mockReference
        );

        $this->assertNotNull($transaction->reference_type);
        $this->assertNotNull($transaction->reference_id);
    }
}
