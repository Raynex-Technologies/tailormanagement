<?php

namespace Tests\Feature\Procurement;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventorySelectionService;
use App\Services\Inventory\ProcurementReconciliationService;
use App\Services\Inventory\VariantAdministrationService;
use App\Services\Orders\StockRequestService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Procurement\ReceivingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementVariantsTest extends TestCase
{
    private InventoryItem $product;

    private array $variants = [];

    private array $values = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->actingAsRole('superadmin', $this->branch);
        $s = app(VariantAdministrationService::class);
        $this->product = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Plain T-Shirt', 'is_active' => true]);
        $this->product->stock->update(['qty_on_hand' => 3, 'qty_reserved' => 0]);
        $color = $s->option($this->product->id, null, 'Color');
        $size = $s->option($this->product->id, null, 'Size', 1);
        foreach (['Black', 'White'] as $label) {
            $this->values[$label] = $s->value($this->product->id, $color->id, null, $label);
        }
        foreach (['Medium', 'Large'] as $label) {
            $this->values[$label] = $s->value($this->product->id, $size->id, null, $label);
        }
        $allocations = [];
        foreach ([['Black', 'Medium', 2], ['White', 'Large', 1]] as [$c, $z, $qty]) {
            $v = $s->combination($this->product->id, [$this->values[$c]->id, $this->values[$z]->id], $c.'-'.$z);
            $s->identity($this->product->id, $v->id, $v->sku, '30000', '20000', null);
            $this->variants[] = $v;
            $allocations[$v->id] = (string) $qty;
        }
        $s->convert($this->product->id, $allocations, '3');
    }

    private function input(int $index, string $qty = '5.00'): array
    {
        return [...app(InventorySelectionService::class)->snapshot($this->variants[$index]->stockUnit->load('item', 'variant.selectedValues.option')), 'qty' => $qty, 'unit_price_est' => '12000.25'];
    }

    private function po(array $items): PurchaseOrder
    {
        $service = app(PurchaseRequestService::class);
        $pr = $service->createDraft(auth()->user(), ['items' => $items]);
        $service->submit($pr, auth()->user());
        $service->approve($pr->refresh(), auth()->user(), []);

        return $service->convertToPo($pr->refresh(), auth()->user(), ['supplier_id' => Supplier::factory()->create(['branch_id' => $this->branch->id])->id]);
    }

    private function rejects(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected validation failure');
        } catch (\Illuminate\Validation\ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_exact_partial_receipts_retry_cost_and_sibling_preservation(): void
    {
        $po = $this->po([$this->input(0), $this->input(1)]);
        $line = $po->items[0];
        $service = app(ReceivingService::class);
        $payload = [['purchase_order_item_id' => $line->id, 'qty_received' => '1.25', 'unit_cost' => '100.10']];
        $grn = $service->receive($po, $payload, auth()->user(), null, 'delivery-one');
        $repeat = $service->receive($po, $payload, auth()->user(), null, 'delivery-one');
        $this->assertSame($grn->id, $repeat->id);
        $this->assertSame('3.25', $this->variants[0]->stockUnit->stock->fresh()->qty_on_hand);
        $this->assertSame('1.00', $this->variants[1]->stockUnit->stock->fresh()->qty_on_hand);
        $this->assertSame('125.13', $grn->items[0]->line_total);
        $this->assertSame('100.10', $grn->items[0]->unit_cost);
        $this->assertSame('20000.00', $this->variants[0]->stockUnit->fresh()->reference_cost);
        $this->assertSame('1.25', $line->fresh()->qty_received);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $po->fresh()->status);
        $movement = InventoryTransaction::where('reference_type', $grn->getMorphClass())->where('reference_id', $grn->id)->firstOrFail();
        $this->assertSame($grn->items[0]->inventory_stock_unit_id, $movement->inventory_stock_unit_id);
        $this->assertSame('125.13', $movement->total_cost);
        $this->rejects(fn () => $service->receive($po, [['purchase_order_item_id' => $line->id, 'qty_received' => '1.50']], auth()->user(), null, 'delivery-one'));
        $this->rejects(fn () => $service->receive($po, [['purchase_order_item_id' => $line->id, 'qty_received' => '4.00']], auth()->user(), null, 'too-much'));
        $this->assertSame(1, GoodsReceipt::count());
        $this->assertSame(0, app(ProcurementReconciliationService::class)->inspect()['summary']['receipt_movement_errors']);
    }

    public function test_receipt_failure_rolls_back_every_line_and_stale_outstanding_is_rechecked(): void
    {
        $po = $this->po([$this->input(0, '1'), $this->input(1, '1')]);
        $service = app(ReceivingService::class);
        $payload = $po->items->map(fn ($line) => ['purchase_order_item_id' => $line->id, 'qty_received' => '1'])->all();
        $this->variants[1]->stockUnit->update(['is_active' => false]);
        $this->rejects(fn () => $service->receive($po, $payload, auth()->user(), null, 'atomic'));
        $this->assertSame(0, GoodsReceipt::count());
        $this->assertSame('2.00', $this->variants[0]->stockUnit->stock->fresh()->qty_on_hand);
        $this->assertSame('0.00', $po->items[0]->fresh()->qty_received);
        $this->variants[1]->stockUnit->update(['is_active' => true]);
        $grn = $service->receive($po, $payload, auth()->user(), null, 'atomic');
        $this->assertSame($grn->id, $service->receive($po->fresh(), $payload, auth()->user(), null, 'atomic')->id);
        $this->rejects(fn () => $service->receive($po, $payload, auth()->user(), null, 'stale-new-key'));
        $this->assertSame(PurchaseOrderStatus::Received, $po->fresh()->status);
    }

    public function test_identity_validation_permissions_and_unallocated_selection(): void
    {
        $service = app(PurchaseRequestService::class);
        $this->rejects(fn () => $service->createDraft(auth()->user(), ['items' => [['inventory_item_id' => $this->product->id, 'qty' => 1]]]));
        $data = $this->input(0);
        $data['inventory_item_variant_id'] = $this->variants[1]->id;
        $this->rejects(fn () => $service->createDraft(auth()->user(), ['items' => [$data]]));
        $this->variants[0]->stockUnit->update(['allocation_status' => 'pending']);
        $this->rejects(fn () => $service->createDraft(auth()->user(), ['items' => [$this->input(0)]]));
        $this->variants[0]->stockUnit->update(['allocation_status' => 'ready']);
        $po = $this->po([$this->input(0)]);
        $actor = User::factory()->create(['branch_id' => $this->branch->id]);
        $actor->givePermissionTo('procurement.receive');
        $this->actingAs($actor);
        $this->assertFalse($actor->can('inventory.items.manage'));
        app(ReceivingService::class)->receive($po, [['purchase_order_item_id' => $po->items[0]->id, 'qty_received' => '1']], $actor, null, 'receiver-only');
        $this->assertSame('3.00', $this->variants[0]->stockUnit->stock->fresh()->qty_on_hand);
        $actor->revokePermissionTo('procurement.receive');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ReceivingService::class)->receive($po, [['purchase_order_item_id' => $po->items[0]->id, 'qty_received' => '1']], $actor, null, 'denied');
    }

    public function test_request_snapshot_renames_stock_issue_and_original_return_identity(): void
    {
        $service = app(StockRequestService::class);
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => OrderStatus::New]);
        $request = $service->createRequest($order, [[...$this->input(0), 'qty_requested' => '1.25']], null, auth()->user());
        $line = $request->items[0];
        $description = $line->variation_description;
        $this->values['Black']->update(['name' => 'Jet Black']);
        $service->reviewRequest($request, 'approve', [['id' => $line->id, 'qty_approved' => '1.25']], null, auth()->user());
        $service->fulfillRequest($request->refresh(), [['id' => $line->id, 'qty_to_issue' => '1.25']], null, auth()->user());
        $this->assertSame('0.75', $this->variants[0]->stockUnit->stock->fresh()->qty_on_hand);
        $this->assertSame('1.00', $this->variants[1]->stockUnit->stock->fresh()->qty_on_hand);
        $this->assertSame($description, $line->fresh()->variation_description);
        $this->assertSame(0, app(ProcurementReconciliationService::class)->inspect()['summary']['request_movement_errors']);
        app(\App\Services\Orders\OrderInventoryRestorationService::class)->restoreReference($request, auth()->user());
        $this->assertSame('2.00', $this->variants[0]->stockUnit->stock->fresh()->qty_on_hand);
    }

    public function test_sparse_livewire_selection_and_two_variations_remain_distinct(): void
    {
        $form = Livewire::test(\App\Livewire\Procurement\Requests\Form::class);
        $form->call('selectProduct', $this->product->id)
            ->call('chooseInventoryOption', $this->values['White']->inventory_item_option_id, $this->values['White']->id)
            ->assertSee('Not offered')
            ->set('inventoryOptionChoices.option_'.$this->values['Medium']->inventory_item_option_id, $this->values['Medium']->id)
            ->call('confirmInventoryVariation')->assertHasErrors('inventory');
        foreach ($this->variants as $v) {
            $form->call('selectProduct', $this->product->id);
            foreach ($v->selectedValues as $value) {
                $form->call('chooseInventoryOption', $value->inventory_item_option_id, $value->id);
            }
            $form->call('confirmInventoryVariation')->assertHasNoErrors();
        }
        $this->assertCount(2, $form->get('items'));
        $this->assertNotSame($form->get('items')[0]['inventory_stock_unit_id'], $form->get('items')[1]['inventory_stock_unit_id']);
        $form->call('save')->assertHasNoErrors();
        $this->assertSame(2, PurchaseRequest::latest('id')->first()->items()->count());
    }

    public function test_simple_and_legacy_receipts_keep_historical_rows_and_snapshot_labels(): void
    {
        $item = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $po = $this->po([['inventory_item_id' => $item->id, 'qty' => '2', 'unit_price_est' => '0']]);
        $line = $po->items[0];
        $this->assertNotNull($line->inventory_stock_unit_id);
        $line->update(['inventory_stock_unit_id' => null, 'sku' => null]);
        $item->update(['name' => 'Renamed product']);
        $grn = app(ReceivingService::class)->receive($po, [['purchase_order_item_id' => $line->id, 'qty_received' => '1', 'unit_cost' => '0']], auth()->user(), null, 'legacy-simple');
        $this->assertSame($line->item_name, $grn->items[0]->item_name);
        $this->assertNull($line->fresh()->inventory_stock_unit_id);
        $this->assertSame('0.00', $grn->items[0]->line_total);
        $this->assertNotNull($grn->items[0]->inventory_stock_unit_id);
        DB::table('goods_receipts')->where('id', $grn->id)->update(['operation_key' => null, 'payload_hash' => null]);
        DB::table('goods_receipt_items')->where('id', $grn->items[0]->id)->update(['inventory_stock_unit_id' => null, 'purchase_order_item_id' => null]);
        $this->assertSame(0, app(ProcurementReconciliationService::class)->inspect()['summary']['receipt_movement_errors']);
    }

    public function test_receipt_rejects_missing_retry_identity_foreign_lines_and_wrong_branch(): void
    {
        $po = $this->po([$this->input(0)]);
        $other = $this->po([$this->input(1)]);
        $svc = app(ReceivingService::class);
        $payload = [['purchase_order_item_id' => $po->items[0]->id, 'qty_received' => '1']];
        $this->rejects(fn () => $svc->receive($po, $payload, auth()->user()));
        $this->rejects(fn () => $svc->receive($po, [...$payload, ...$payload], auth()->user(), null, 'duplicate-lines'));
        $this->rejects(fn () => $svc->receive($po, [['purchase_order_item_id' => $other->items[0]->id, 'qty_received' => '1']], auth()->user(), null, 'foreign-line'));
        $this->rejects(fn () => app(InventorySelectionService::class)->resolve($this->product->id, $this->variants[0]->stockUnit->id, $this->otherBranch->id));
        $this->assertSame(0, GoodsReceipt::count());
        $actor = User::factory()->create(['branch_id' => $this->otherBranch->id]);
        $actor->givePermissionTo('procurement.receive');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->receive($po, $payload, $actor, null, 'foreign-branch');
    }

    public function test_retired_draft_snapshot_stays_readable_and_receipt_remains_blocked(): void
    {
        $svc = app(PurchaseRequestService::class);
        $pr = $svc->createDraft(auth()->user(), ['items' => [$this->input(0)]]);
        $line = $pr->items[0];
        $this->values['Black']->update(['name' => 'Jet Black']);
        $this->variants[0]->stockUnit->update(['is_active' => false]);
        $pr = $svc->updateDraft($pr, auth()->user(), ['items' => [$line->only(['id', 'inventory_item_id', 'inventory_stock_unit_id', 'inventory_item_variant_id', 'qty', 'unit_price_est'])]]);
        $this->assertSame($line->variation_description, $pr->items[0]->variation_description);
        $this->assertSame('Requires attention', $pr->items[0]->selection_warning);
        $svc->submit($pr, auth()->user());
        $svc->approve($pr->refresh(), auth()->user(), []);
        $po = $svc->convertToPo($pr->refresh(), auth()->user(), ['supplier_id' => Supplier::factory()->create(['branch_id' => $this->branch->id])->id]);
        $this->rejects(fn () => app(ReceivingService::class)->receive($po, [['purchase_order_item_id' => $po->items[0]->id, 'qty_received' => '1']], auth()->user(), null, 'retired'));
        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_receiving_livewire_preserves_retry_key_and_receiver_can_view_po_without_management(): void
    {
        $po = $this->po([$this->input(0)]);
        $actor = User::factory()->create(['branch_id' => $this->branch->id]);
        $actor->givePermissionTo(['procurement.view', 'procurement.receive']);
        $this->actingAs($actor);
        $this->get(route('procurement.pos.show', $po))->assertOk();
        $component = Livewire::test(\App\Livewire\Procurement\Receiving\Show::class, ['purchaseOrder' => $po]);
        $key = $component->get('receiptOperationKey');
        $component->set('receivingItems.'.$po->items[0]->id.'.qty_received', '1.25')->call('receive')->assertHasNoErrors();
        $this->assertSame($key, $component->get('receiptOperationKey'));
        $component->call('receive')->assertHasNoErrors();
        $this->assertSame(1, GoodsReceipt::count());
        $this->assertSame('1.25', $po->items[0]->fresh()->qty_received);
    }
}
