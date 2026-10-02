<?php

namespace App\Livewire\OrderCatalog;

use App\Models\InventoryItem;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use App\Models\OrderPackageTemplateItem;
use App\Services\Media\ImageUploadService;
use App\Services\Orders\OrderCatalogAdministrationService;
use App\Services\Orders\OrderPackagePricingService;
use App\Support\Livewire\NormalizesMoneyInputs;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
#[Title('Package Builder')]
class PackageForm extends Component
{
    use \App\Livewire\Concerns\SelectsOrderInventory;
    use NormalizesMoneyInputs;
    use WithFileUploads;

    public ?int $templateId = null;

    public string $code = '';

    public int $revision = 1;

    public string $name = '';

    public string $description = '';

    public bool $availableAllBranches = true;

    /** @var array<int, int|string> */
    public array $branchIds = [];

    public mixed $coverImageUpload = null;

    public ?string $existingCoverImagePath = null;

    public bool $removeCoverImage = false;

    public string $catalogSearch = '';

    public string $inventorySearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $components = [];

    public function mount(?OrderPackageTemplate $package = null): void
    {
        $this->authorize('order_catalog.packages.manage');
        $user = auth()->user();

        if ($package?->exists) {
            $this->assertManageable($package);
            $package->load(['branches:id', 'items.catalogItem', 'items.inventoryItem']);
            $this->templateId = $package->id;
            $this->code = $package->code;
            $this->revision = $package->revision;
            $this->name = $package->name;
            $this->description = (string) $package->description;
            $this->availableAllBranches = $package->available_all_branches;
            $this->branchIds = $package->branches->pluck('id')->all();
            $this->existingCoverImagePath = $package->cover_image_path;
            $this->components = $package->items->map(fn (OrderPackageTemplateItem $item) => $this->componentPayload($item))->all();
        } elseif (! $user->isGlobalAdmin()) {
            $this->availableAllBranches = false;
            $this->branchIds = [(int) $user->branch_id];
        }
    }

    public function addCatalogItem(int $itemId): void
    {
        $this->authorize('order_catalog.packages.manage');
        $item = OrderCatalogItem::query()->active()->findOrFail($itemId);
        $this->components[] = [
            'id' => null,
            'source_type' => 'catalog_item',
            'source_id' => $item->id,
            'name' => $item->name,
            'source_code' => $item->code,
            'image_url' => $item->image_url,
            'source_archived' => false,
            'source_branch_id' => null,
            'standard_unit_price' => (string) $item->default_selling_price,
            'minimum_quantity' => '1.00',
            'default_quantity' => '1.00',
            'maximum_quantity' => null,
            'package_unit_price' => (string) $item->default_selling_price,
            'sort_order' => count($this->components) + 1,
        ];
        $this->catalogSearch = '';
    }

    public function addInventoryItem(int $itemId): void
    {
        $this->authorize('order_catalog.packages.manage');
        $query = auth()->user()->isGlobalAdmin() ? InventoryItem::withoutBranchScope() : InventoryItem::query();
        $item = $query->where('is_active', true)->findOrFail($itemId);

        if (! auth()->user()->canAccessBranch((int) $item->branch_id)) {
            abort(403);
        }

        $this->components[] = [
            'id' => null,
            'source_type' => 'inventory_item',
            'source_id' => $item->id,
            'name' => $item->name,
            'source_code' => $item->sku,
            'image_url' => $item->featured_image_url,
            'source_archived' => ! $item->is_active,
            'source_branch_id' => $item->branch_id,
            'has_variations' => $item->variant_mode === 'variants',
            'variation_selection' => $item->variant_mode === 'variants' ? 'deferred' : 'simple',
            'inventory_stock_unit_id' => null,
            'inventory_item_variant_id' => null,
            'standard_unit_price' => (string) ($item->default_sell_price ?? '0.00'),
            'minimum_quantity' => '1.00',
            'default_quantity' => '1.00',
            'maximum_quantity' => null,
            'package_unit_price' => (string) ($item->default_sell_price ?? '0.00'),
            'sort_order' => count($this->components) + 1,
        ];
        $this->inventorySearch = '';
    }

    protected function authorizeInventorySelection(): void
    {
        $this->authorize('order_catalog.packages.manage');
    }

    protected function inventorySelectionBranch(): int
    {
        abort_unless(! $this->availableAllBranches && count($this->branchIds) === 1, 422);

        return (int) $this->branchIds[0];
    }

    public function chooseFixedVariation(int $index): void
    {
        $this->authorizeInventorySelection();
        abort_unless(isset($this->components[$index]) && $this->components[$index]['source_type'] === 'inventory_item', 422);
        $this->beginInventorySelection((int) $this->components[$index]['source_id'], 'component:'.$index);
    }

    protected function acceptInventoryUnit(\App\Models\InventoryStockUnit $unit, string $target): void
    {
        $index = (int) substr($target, 10);
        abort_unless(isset($this->components[$index]) && (int) $this->components[$index]['source_id'] === (int) $unit->inventory_item_id, 422);
        $this->components[$index] = [...$this->components[$index], 'variation_selection' => $unit->inventory_item_variant_id ? 'fixed' : 'simple',
            'inventory_stock_unit_id' => $unit->id, 'inventory_item_variant_id' => $unit->inventory_item_variant_id,
            'variation_description' => $unit->variant?->display_name, 'variation_warning' => null];
    }

    private function normalizeInventoryComponents(): void
    {
        foreach ($this->components as &$component) {
            if ($component['source_type'] !== 'inventory_item') {
                continue;
            }
            $item = InventoryItem::withoutBranchScope()->where('branch_id', $this->inventorySelectionBranch())->findOrFail($component['source_id']);
            if ($item->variant_mode === 'variants' && ($component['variation_selection'] ?? null) === 'deferred') {
                $component['inventory_stock_unit_id'] = null;
                $component['inventory_item_variant_id'] = null;

                continue;
            }
            $old = ! empty($component['id']) && $this->templateId ? OrderPackageTemplateItem::where('order_package_template_id', $this->templateId)->findOrFail($component['id']) : null;
            if ($old && $old->inventory_stock_unit_id && (int) $old->inventory_stock_unit_id === (int) ($component['inventory_stock_unit_id'] ?? 0) && (int) $old->inventory_item_id === (int) $item->id) {
                $component['inventory_item_variant_id'] = $old->inventory_item_variant_id;
                $component['variation_selection'] = $old->variation_selection;

                continue;
            }
            $unit = app(\App\Services\Orders\OrderInventorySelectionService::class)->resolve($item->id, ($component['inventory_stock_unit_id'] ?? null) ?: null, $this->inventorySelectionBranch());
            $component['inventory_stock_unit_id'] = $unit->id;
            $component['inventory_item_variant_id'] = $unit->inventory_item_variant_id;
            $component['variation_selection'] = $unit->inventory_item_variant_id ? 'fixed' : 'simple';
        }
    }

    public function removeComponent(int $index): void
    {
        unset($this->components[$index]);
        $this->components = array_values($this->components);
        $this->normalizeSortOrder();
    }

    public function moveComponent(int $index, string $direction): void
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($this->components[$index], $this->components[$target])) {
            return;
        }

        [$this->components[$index], $this->components[$target]] = [$this->components[$target], $this->components[$index]];
        $this->normalizeSortOrder();
    }

    public function save()
    {
        $this->normalizeMoneyInputs();
        $this->authorize('order_catalog.packages.manage');
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'availableAllBranches' => ['boolean'],
            'branchIds' => ['array'],
            'branchIds.*' => ['integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'coverImageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'removeCoverImage' => ['boolean'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.source_type' => ['required', Rule::in(['catalog_item', 'inventory_item'])],
            'components.*.source_id' => ['required', 'integer'],
            'components.*.minimum_quantity' => ['required', 'numeric', 'min:0'],
            'components.*.default_quantity' => ['required', 'numeric', 'min:0'],
            'components.*.maximum_quantity' => ['nullable', 'numeric', 'min:0'],
            'components.*.package_unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $administration = app(OrderCatalogAdministrationService::class);
        try {
            $branchIds = $administration->normalizeAvailability(auth()->user(), $this->availableAllBranches, $this->branchIds);
            $administration->assertPackageSourcesValid($this->availableAllBranches, $branchIds, $this->components);
            $this->normalizeInventoryComponents();
            $this->assertComponentConfigurations();
        } catch (\Throwable $exception) {
            $this->addError('components', $exception->getMessage());

            return null;
        }

        $template = $this->templateId ? OrderPackageTemplate::query()->findOrFail($this->templateId) : new OrderPackageTemplate;
        if ($template->exists) {
            $this->assertManageable($template);
        }

        $beforeSignature = $template->exists ? $administration->commercialSignature($template) : null;
        $images = app(ImageUploadService::class);
        $coverPath = $template->cover_image_path;
        if ($this->coverImageUpload) {
            $coverPath = $images->replacePublic($this->coverImageUpload, $coverPath, 'order-catalog/packages')->path;
        } elseif ($this->removeCoverImage && $coverPath) {
            $images->deletePublic($coverPath);
            $coverPath = null;
        }

        DB::transaction(function () use ($template, $validated, $branchIds, $coverPath): void {
            $template->fill([
                'name' => trim($validated['name']),
                'description' => filled($validated['description']) ? trim($validated['description']) : null,
                'cover_image_path' => $coverPath,
                'available_all_branches' => $this->availableAllBranches,
            ])->save();
            $template->branches()->sync($branchIds);

            $keptIds = [];
            foreach ($this->components as $index => $component) {
                $item = filled($component['id'] ?? null)
                    ? $template->items()->whereKey((int) $component['id'])->firstOrFail()
                    : new OrderPackageTemplateItem(['order_package_template_id' => $template->id]);

                $item->fill([
                    'order_package_template_id' => $template->id,
                    'order_catalog_item_id' => $component['source_type'] === 'catalog_item' ? $component['source_id'] : null,
                    'inventory_item_id' => $component['source_type'] === 'inventory_item' ? $component['source_id'] : null,
                    'inventory_stock_unit_id' => $component['inventory_stock_unit_id'] ?? null,
                    'inventory_item_variant_id' => $component['inventory_item_variant_id'] ?? null,
                    'variation_selection' => $component['variation_selection'] ?? null,
                    'minimum_quantity' => $component['minimum_quantity'],
                    'default_quantity' => $component['default_quantity'],
                    'maximum_quantity' => filled($component['maximum_quantity'] ?? null) ? $component['maximum_quantity'] : null,
                    'package_unit_price' => $component['package_unit_price'],
                    'sort_order' => $index + 1,
                ])->save();
                $keptIds[] = $item->id;
            }

            $template->items()->whereNotIn('id', $keptIds)->delete();
        });

        $template->unsetRelation('branches');
        $template->unsetRelation('items');
        $afterSignature = $administration->commercialSignature($template->refresh());
        if ($beforeSignature !== null && ! hash_equals($beforeSignature, $afterSignature)) {
            $template->bumpRevision();
        }

        session()->flash('success', $this->templateId ? __('Package updated.') : __('Package created.'));

        return $this->redirectRoute('order-catalog.index', ['tab' => 'packages'], navigate: true);
    }

    public function render()
    {
        $catalogItems = OrderCatalogItem::query()
            ->active()
            ->when($this->catalogSearch !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$this->catalogSearch.'%')
                ->orWhere('code', 'like', '%'.$this->catalogSearch.'%')))
            ->when($this->availableAllBranches, fn ($query) => $query->where('available_all_branches', true))
            ->when(! $this->availableAllBranches && count($this->branchIds) === 1, fn ($query) => $query->availableForBranch((int) $this->branchIds[0]))
            ->orderBy('name')->limit(12)->get();

        $inventoryItems = collect();
        if (! $this->availableAllBranches && count($this->branchIds) === 1) {
            $query = auth()->user()->isGlobalAdmin() ? InventoryItem::withoutBranchScope() : InventoryItem::query();
            $inventoryItems = $query->with('stock')
                ->where('branch_id', (int) $this->branchIds[0])
                ->where('is_active', true)
                ->when($this->inventorySearch !== '', fn ($query) => $query->where(fn ($search) => $search
                    ->where('name', 'like', '%'.$this->inventorySearch.'%')
                    ->orWhere('sku', 'like', '%'.$this->inventorySearch.'%')))
                ->orderBy('name')->limit(12)->get();
        }

        return view('livewire.order-catalog.package-form', [
            'branches' => app(OrderCatalogAdministrationService::class)->permittedBranches(auth()->user()),
            'isGlobalAdmin' => auth()->user()->isGlobalAdmin(),
            'catalogItems' => $catalogItems,
            'inventoryItems' => $inventoryItems,
            'pricingSummary' => app(OrderPackagePricingService::class)->summarizeComponents($this->components),
        ]);
    }

    private function assertComponentConfigurations(): void
    {
        foreach ($this->components as $component) {
            (new OrderPackageTemplateItem([
                'order_catalog_item_id' => $component['source_type'] === 'catalog_item' ? $component['source_id'] : null,
                'inventory_item_id' => $component['source_type'] === 'inventory_item' ? $component['source_id'] : null,
                'inventory_stock_unit_id' => $component['inventory_stock_unit_id'] ?? null,
                'inventory_item_variant_id' => $component['inventory_item_variant_id'] ?? null,
                'variation_selection' => $component['variation_selection'] ?? null,
                'minimum_quantity' => $component['minimum_quantity'],
                'default_quantity' => $component['default_quantity'],
                'maximum_quantity' => filled($component['maximum_quantity'] ?? null) ? $component['maximum_quantity'] : null,
                'package_unit_price' => $component['package_unit_price'],
            ]))->assertValidConfiguration();
        }
    }

    private function componentPayload(OrderPackageTemplateItem $item): array
    {
        $source = $item->source();

        return [
            'id' => $item->id,
            'source_type' => $item->sourceType(),
            'source_id' => $source->id,
            'name' => $source->name,
            'source_code' => $source instanceof OrderCatalogItem ? $source->code : $source->sku,
            'image_url' => $source instanceof OrderCatalogItem ? $source->image_url : $source->featured_image_url,
            'source_archived' => $source instanceof OrderCatalogItem ? $source->archived_at !== null : ! $source->is_active,
            'source_branch_id' => $source instanceof InventoryItem ? $source->branch_id : null,
            'has_variations' => $source instanceof InventoryItem && $source->variant_mode === 'variants',
            'variation_selection' => $item->variation_selection,
            'inventory_stock_unit_id' => $item->inventory_stock_unit_id,
            'inventory_item_variant_id' => $item->inventory_item_variant_id,
            'variation_description' => $item->stockUnit?->variant?->display_name,
            'variation_warning' => $item->variationWarning(),
            'standard_unit_price' => $item->standardUnitPrice(),
            'minimum_quantity' => (string) $item->minimum_quantity,
            'default_quantity' => (string) $item->default_quantity,
            'maximum_quantity' => $item->maximum_quantity === null ? null : (string) $item->maximum_quantity,
            'package_unit_price' => (string) $item->package_unit_price,
            'sort_order' => $item->sort_order,
        ];
    }

    private function normalizeSortOrder(): void
    {
        foreach ($this->components as $index => &$component) {
            $component['sort_order'] = $index + 1;
        }
    }

    private function assertManageable(OrderPackageTemplate $template): void
    {
        try {
            app(OrderCatalogAdministrationService::class)->assertManageable(auth()->user(), $template);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
