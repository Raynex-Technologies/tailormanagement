<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\PosTerminal;
use App\Models\InventoryItem;
use App\Models\PosSale;
use App\Services\Inventory\StockUnitBarcodeService;
use App\Services\Inventory\VariantAdministrationService;
use App\Services\Pos\PosSaleService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PosVariantsTest extends TestCase
{
    private InventoryItem $product;

    private array $values = [];

    private array $variants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('superadmin', $this->branch);
        $s = app(VariantAdministrationService::class);
        $this->product = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Plain T-Shirt', 'sku' => 'TSHIRT', 'default_sell_price' => 35000, 'is_active' => true]);
        $this->product->stock->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);
        $color = $s->option($this->product->id, null, 'Color');
        $size = $s->option($this->product->id, null, 'Size', 1);
        foreach (['Black', 'White'] as $n) {
            $this->values[$n] = $s->value($this->product->id, $color->id, null, $n);
        }foreach (['Small', 'Medium', 'Large'] as $n) {
            $this->values[$n] = $s->value($this->product->id, $size->id, null, $n);
        }
        $alloc = [];
        foreach ([['Black', 'Small', 2, 35000], ['Black', 'Medium', 3, 35000], ['Black', 'Large', 1, 40000], ['White', 'Small', 1, 36000], ['White', 'Large', 3, 42000]] as [$c,$z,$q,$price]) {
            $v = $s->combination($this->product->id, [$this->values[$c]->id, $this->values[$z]->id], $c.'-'.$z);
            $s->identity($this->product->id, $v->id, $v->sku, (string) $price, '20000', 'SCAN-'.$v->id);
            $this->variants[$c.' '.$z] = $v;
            $alloc[$v->id] = (string) $q;
        }$s->convert($this->product->id, $alloc, '10');
    }

    private function sale($unit, $qty = 1, $extra = [])
    {
        return app(PosSaleService::class)->complete([['inventory_item_id' => $unit->inventory_item_id, 'inventory_stock_unit_id' => $unit->id, 'quantity' => $qty, ...$extra]], auth()->user(), null, 0, 0, 1000000, 'cash');
    }

    private function reject(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected rejection');
        } catch (ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_selector_sparse_choices_and_exact_cart_merging(): void
    {
        $a = $this->variants['Black Medium']->stockUnit;
        $b = $this->variants['Black Small']->stockUnit;
        $c = Livewire::test(PosTerminal::class)->call('addItem', $this->product->id)->assertSet('cart', [])->assertSet('showVariationModal', true)
            ->call('chooseVariationValue', $this->values['White']->inventory_item_option_id, $this->values['White']->id)->assertSee('Not offered')
            ->call('chooseVariationValue', $this->values['Black']->inventory_item_option_id, $this->values['Black']->id)->call('chooseVariationValue', $this->values['Medium']->inventory_item_option_id, $this->values['Medium']->id)->assertSee('Black / Medium')->call('addSelectedVariation')->assertHasNoErrors();
        $c->assertSet('cart.unit_'.$a->id.'.quantity', 1)->call('addStockUnit', $a->id)->assertSet('cart.unit_'.$a->id.'.quantity', 2)->call('addStockUnit', $b->id)->assertSet('cart.unit_'.$b->id.'.quantity', 1)->call('addItem', $this->product->id)->assertSet('variationChoices', [])->assertSet('showVariationModal', true);
    }

    public function test_barcode_sku_unknown_and_unavailable(): void
    {
        $v = $this->variants['White Large'];
        $unit = $v->stockUnit;
        $c = Livewire::test(PosTerminal::class)->set('itemSearch', 'SCAN-'.$v->id)->call('addFirstSearchMatch')->assertSet('cart.unit_'.$unit->id.'.quantity', 1)->assertSet('itemSearch', '')->assertSet('showVariationModal', false);
        $c->set('itemSearch', $v->sku)->call('addFirstSearchMatch')->assertSet('cart.unit_'.$unit->id.'.quantity', 2)->set('itemSearch', 'White')->call('addFirstSearchMatch')->assertHasErrors('itemSearch');
        $unit->update(['is_active' => false]);
        $c->set('itemSearch', 'SCAN-'.$v->id)->call('addFirstSearchMatch')->assertHasErrors('itemSearch')->assertSet('cart.unit_'.$unit->id.'.quantity', 2);
    }

    public function test_checkout_exact_identity_snapshot_receipt_and_aggregate(): void
    {
        $v = $this->variants['Black Medium'];
        $u = $v->stockUnit;
        $sale = $this->sale($u);
        $line = $sale->items->first();
        $this->assertSame($u->id, $line->inventory_stock_unit_id);
        $this->assertSame($v->id, $line->inventory_item_variant_id);
        $this->assertSame('Black / Medium', $line->variation_description);
        $this->assertSame('35000.00', $line->unit_price);
        $this->assertSame('2.00', $u->stock->fresh()->qty_on_hand);
        $this->assertSame('9.00', $this->product->fresh()->physical_on_hand);
        $this->assertDatabaseHas('inventory_transactions', ['inventory_stock_unit_id' => $u->id, 'qty' => -1]);
        app(VariantAdministrationService::class)->value($this->product->id, $this->values['Black']->inventory_item_option_id, $this->values['Black']->id, 'Jet Black');
        $this->get(route('pos.sales.show', $sale))->assertOk()->assertSee('Black / Medium')->assertDontSee('Jet Black / Medium');
    }

    public function test_canonical_price_stale_cart_and_last_unit_fail_closed(): void
    {
        $u = $this->variants['Black Large']->stockUnit;
        $this->product->refresh()->update(['default_sell_price' => 99999]);
        $this->reject(fn () => $this->sale($u, 1, ['expected_price' => 35000]));
        $this->assertSame(0, PosSale::count());
        $sale = $this->sale($u);
        $this->assertSame('40000.00', $sale->items->first()->unit_price);
        $this->reject(fn () => $this->sale($u));
        $this->assertEquals(0, $u->stock->fresh()->qty_on_hand);
        $this->assertSame(1, PosSale::count());
    }

    public function test_branch_retired_unallocated_and_duplicate_payload_cannot_oversell(): void
    {
        $u = $this->variants['Black Small']->stockUnit;
        $this->actingAsRole('sales', $this->otherBranch);
        $this->reject(fn () => $this->sale($u));
        $this->actingAsRole('sales', $this->branch);
        $u->update(['allocation_status' => 'allocation_required']);
        $this->reject(fn () => $this->sale($u));
        $u->update(['allocation_status' => 'ready', 'is_active' => false]);
        $this->reject(fn () => $this->sale($u));
        $u->update(['is_active' => true]);
        $this->reject(fn () => app(PosSaleService::class)->complete([['inventory_item_id' => $u->inventory_item_id, 'inventory_stock_unit_id' => $u->id, 'quantity' => 2], ['inventory_item_id' => $u->inventory_item_id, 'inventory_stock_unit_id' => $u->id, 'quantity' => 1]], auth()->user(), null, 0, 0, 1000000, 'cash'));
        $this->assertEquals(2, $u->stock->fresh()->qty_on_hand);
        $this->assertSame(0, PosSale::count());
    }

    public function test_simple_scanning_and_pos_permissions(): void
    {
        $item = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true, 'default_sell_price' => 5000, 'track_stock' => false]);
        $item->stock->update(['qty_on_hand' => 2, 'qty_reserved' => 0]);
        $u = $item->simpleStockUnit;
        app(StockUnitBarcodeService::class)->assign($u, 'NEEDLES-SCAN');
        $this->actingAsRole('sales', $this->branch);
        $c = Livewire::test(PosTerminal::class)->call('addItem', $item->id)->assertSet('showVariationModal', false)->set('itemSearch', 'NEEDLES-SCAN')->call('addFirstSearchMatch')->assertSet('cart.unit_'.$u->id.'.quantity',2);
        $this->sale($u,2);
        $this->assertEquals(0,$u->stock->fresh()->qty_on_hand);
        $this->actingAsRole('tailor',$this->branch);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->sale($u);
    }
}
