<?php

namespace App\Livewire\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Models\OrderCatalogItem;
use App\Services\Media\ImageUploadService;
use App\Services\Orders\OrderCatalogAdministrationService;
use DomainException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
#[Title('Catalog Item')]
class ItemForm extends Component
{
    use WithFileUploads;

    public ?int $itemId = null;

    public string $name = '';

    public string $description = '';

    public string $type = 'garment';

    public string $defaultSellingPrice = '';

    public bool $requiresMeasurements = true;

    public string $quantityBehavior = 'individual';

    public bool $availableAllBranches = true;

    /** @var array<int, int|string> */
    public array $branchIds = [];

    public mixed $imageUpload = null;

    public ?string $existingImagePath = null;

    public bool $removeImage = false;

    public bool $quantityBehaviorExplicit = false;

    public function mount(?OrderCatalogItem $catalogItem = null): void
    {
        $this->authorize('order_catalog.items.manage');
        $user = auth()->user();

        if ($catalogItem?->exists) {
            $this->assertManageable($catalogItem);
            $catalogItem->load('branches:id');
            $this->itemId = $catalogItem->id;
            $this->name = $catalogItem->name;
            $this->description = (string) $catalogItem->description;
            $this->type = $catalogItem->type->value;
            $this->defaultSellingPrice = (string) $catalogItem->default_selling_price;
            $this->requiresMeasurements = $catalogItem->requires_measurements;
            $this->quantityBehavior = $catalogItem->quantity_behavior->value;
            $this->availableAllBranches = $catalogItem->available_all_branches;
            $this->branchIds = $catalogItem->branches->pluck('id')->all();
            $this->existingImagePath = $catalogItem->image_path;
            $this->quantityBehaviorExplicit = true;
        } elseif (! $user->isGlobalAdmin()) {
            $this->availableAllBranches = false;
            $this->branchIds = [(int) $user->branch_id];
        }
    }

    public function updatedType(string $type): void
    {
        if ($this->quantityBehaviorExplicit || ! OrderCatalogItemType::tryFrom($type)) {
            return;
        }

        $this->quantityBehavior = OrderCatalogQuantityBehavior::defaultFor(OrderCatalogItemType::from($type))->value;
        $this->requiresMeasurements = $type === OrderCatalogItemType::Garment->value;
    }

    public function updatedQuantityBehavior(): void
    {
        $this->quantityBehaviorExplicit = true;
    }

    public function save()
    {
        $this->authorize('order_catalog.items.manage');
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'type' => ['required', new Enum(OrderCatalogItemType::class)],
            'defaultSellingPrice' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'requiresMeasurements' => ['boolean'],
            'quantityBehavior' => ['required', new Enum(OrderCatalogQuantityBehavior::class)],
            'availableAllBranches' => ['boolean'],
            'branchIds' => ['array'],
            'branchIds.*' => ['integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'imageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'removeImage' => ['boolean'],
        ]);

        try {
            $branchIds = app(OrderCatalogAdministrationService::class)->normalizeAvailability(
                auth()->user(),
                $this->availableAllBranches,
                $this->branchIds
            );
        } catch (DomainException $exception) {
            $this->addError('branchIds', $exception->getMessage());

            return null;
        }

        $item = $this->itemId ? OrderCatalogItem::query()->findOrFail($this->itemId) : new OrderCatalogItem;
        if ($item->exists) {
            $this->assertManageable($item);
        }

        $imagePath = $item->image_path;
        $images = app(ImageUploadService::class);
        if ($this->imageUpload) {
            $imagePath = $images->replacePublic($this->imageUpload, $imagePath, 'order-catalog/items')->path;
        } elseif ($this->removeImage && $imagePath) {
            $images->deletePublic($imagePath);
            $imagePath = null;
        }

        $item->fill([
            'name' => trim($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'type' => $validated['type'],
            'default_selling_price' => $validated['defaultSellingPrice'],
            'requires_measurements' => $validated['requiresMeasurements'],
            'quantity_behavior' => $validated['quantityBehavior'],
            'available_all_branches' => $this->availableAllBranches,
            'image_path' => $imagePath,
        ])->save();
        $item->branches()->sync($branchIds);

        session()->flash('success', $this->itemId ? __('Catalog item updated.') : __('Catalog item created.'));

        return $this->redirectRoute('order-catalog.index', ['tab' => 'items'], navigate: true);
    }

    public function render()
    {
        return view('livewire.order-catalog.item-form', [
            'branches' => app(OrderCatalogAdministrationService::class)->permittedBranches(auth()->user()),
            'isGlobalAdmin' => auth()->user()->isGlobalAdmin(),
        ]);
    }

    private function assertManageable(OrderCatalogItem $item): void
    {
        try {
            app(OrderCatalogAdministrationService::class)->assertManageable(auth()->user(), $item);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
