<?php

namespace App\Livewire\Storefront\Admin;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemMedia;
use App\Support\BranchContext;
use App\Support\StorefrontMedia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
#[Title('Storefront Product Form')]
class ProductForm extends Component
{
    use WithFileUploads;

    public ?int $editingProductId = null;
    public string $productName = '';
    public string $productShortDescription = '';
    public string $productDescription = '';
    public string $productSku = '';
    public string $productStatus = 'draft';
    public ?int $productCategoryId = null;
    public ?float $productPrice = null;
    public ?float $productCompareAtPrice = null;
    public bool $productTrackStock = true;
    public ?float $productStockQuantity = 0;
    public ?float $productLowStockThreshold = 0;
    public bool $productAllowBackorders = false;
    public bool $productVisible = true;
    public bool $productFeatured = false;
    public bool $productTaxable = false;
    public ?float $productWeight = null;
    public ?float $productLength = null;
    public ?float $productWidth = null;
    public ?float $productHeight = null;
    public string $productSizes = '';
    public string $productColors = '';
    public array $productColorOptions = [];
    public $productFeaturedImageUpload = null;
    public ?string $productFeaturedImagePath = null;
    public array $productGalleryUploads = [];
    public array $existingProductGallery = [];

    public function mount(?InventoryItem $product = null): void
    {
        abort_unless(auth()->user()?->can('storefront.catalog.manage'), 403);

        if (! $product || ! $product->exists) {
            return;
        }

        $this->editingProductId = $product->id;
        $this->loadProduct($product->id);
    }

    public function save()
    {
        $this->authorize('storefront.catalog.manage');
        $this->productColorOptions = $this->normalizeProductColors($this->productColorOptions);
        $this->syncProductColorsString();

        $validated = $this->validate([
            'productName' => ['required', 'string', 'max:191'],
            'productShortDescription' => ['nullable', 'string', 'max:2000'],
            'productDescription' => ['nullable', 'string', 'max:20000'],
            'productSku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_items', 'sku')->ignore($this->editingProductId),
            ],
            'productStatus' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'productCategoryId' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'productPrice' => ['required', 'numeric', 'min:0'],
            'productCompareAtPrice' => ['nullable', 'numeric', 'min:0'],
            'productTrackStock' => ['boolean'],
            'productStockQuantity' => ['nullable', 'numeric', 'min:0'],
            'productLowStockThreshold' => ['nullable', 'numeric', 'min:0'],
            'productAllowBackorders' => ['boolean'],
            'productVisible' => ['boolean'],
            'productFeatured' => ['boolean'],
            'productTaxable' => ['boolean'],
            'productWeight' => ['nullable', 'numeric', 'min:0'],
            'productLength' => ['nullable', 'numeric', 'min:0'],
            'productWidth' => ['nullable', 'numeric', 'min:0'],
            'productHeight' => ['nullable', 'numeric', 'min:0'],
            'productSizes' => ['nullable', 'string', 'max:2000'],
            'productColors' => ['nullable', 'string', 'max:2000'],
            'productFeaturedImageUpload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
            'productGalleryUploads' => ['nullable', 'array', 'max:12'],
            'productGalleryUploads.*' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ]);

        $branchId = $this->resolveBranchId();
        $name = $this->sanitizeText($validated['productName']) ?? 'Product';
        $status = strtolower($validated['productStatus']);
        $isActive = $status === 'active';

        $payload = [
            'branch_id' => $branchId,
            'inventory_category_id' => $validated['productCategoryId'] ?: null,
            'inventory_unit_id' => null,
            'sku' => $this->prepareSku($validated['productSku'] ?? null),
            'name' => $name,
            'slug' => $this->generateUniqueProductSlug($name, $branchId, $this->editingProductId),
            'unit' => 'pcs',
            'short_description' => $this->sanitizeText($validated['productShortDescription'] ?? null),
            'full_description' => $this->sanitizeText($validated['productDescription'] ?? null),
            'status' => $status,
            'default_buy_price' => null,
            'default_sell_price' => round((float) $validated['productPrice'], 2),
            'compare_at_price' => $validated['productCompareAtPrice'] !== null
                ? round((float) $validated['productCompareAtPrice'], 2)
                : null,
            'track_stock' => (bool) $validated['productTrackStock'],
            'reorder_level' => round((float) ($validated['productLowStockThreshold'] ?? 0), 2),
            'low_stock_threshold' => round((float) ($validated['productLowStockThreshold'] ?? 0), 2),
            'allow_backorders' => (bool) $validated['productAllowBackorders'],
            'storefront_is_visible' => (bool) $validated['productVisible'] && $isActive,
            'is_featured' => (bool) $validated['productFeatured'],
            'weight' => $validated['productWeight'],
            'dimensions' => $this->dimensionsPayload(
                $validated['productLength'] ?? null,
                $validated['productWidth'] ?? null,
                $validated['productHeight'] ?? null
            ),
            'shipping_profile_id' => null,
            'is_taxable' => (bool) $validated['productTaxable'],
            'meta_title' => $name,
            'meta_description' => $this->sanitizeText($validated['productShortDescription'] ?? null),
            'is_active' => $isActive,
        ];

        $product = $this->editingProductId
            ? InventoryItem::query()->with(['media', 'stock'])->findOrFail($this->editingProductId)
            : new InventoryItem();

        if ($this->productFeaturedImageUpload) {
            if ($product->exists && $product->featured_image_path) {
                StorefrontMedia::delete($product->featured_image_path);
            }

            $payload['featured_image_path'] = StorefrontMedia::store($this->productFeaturedImageUpload, 'storefront/products/featured');
        }

        $product->fill($payload);
        $product->save();

        $product->stock()->updateOrCreate(
            ['inventory_item_id' => $product->id],
            [
                'branch_id' => $product->branch_id,
                'qty_on_hand' => round((float) ($validated['productStockQuantity'] ?? 0), 2),
                'qty_reserved' => (float) ($product->stock?->qty_reserved ?? 0),
            ]
        );

        $this->syncProductVariants(
            $product,
            $this->splitCsv($validated['productSizes'] ?? ''),
            $this->splitCsv($validated['productColors'] ?? '')
        );

        $this->appendGalleryUploads($product);

        return redirect()
            ->route('administration.storefront.products')
            ->with('success', $this->editingProductId ? 'Product updated.' : 'Product created.');
    }

    public function addProductColor(string $value): void
    {
        $normalized = $this->normalizeColorValue($value);
        if ($normalized === null) {
            return;
        }

        $this->productColorOptions = collect([...$this->productColorOptions, $normalized])
            ->unique()
            ->values()
            ->all();
        $this->syncProductColorsString();
    }

    public function removeProductColor(string $value): void
    {
        $normalized = $this->normalizeColorValue($value);
        if ($normalized === null) {
            return;
        }

        $this->productColorOptions = collect($this->productColorOptions)
            ->reject(fn (string $color) => $color === $normalized)
            ->values()
            ->all();
        $this->syncProductColorsString();
    }

    public function removeProductMedia(int $mediaId): void
    {
        $this->authorize('storefront.catalog.manage');

        $media = InventoryItemMedia::query()->findOrFail($mediaId);
        $product = InventoryItem::query()->findOrFail($media->inventory_item_id);

        StorefrontMedia::delete($media->path);
        $media->delete();

        $this->syncGalleryImagesColumn($product->fresh('media'));

        $this->existingProductGallery = collect($this->existingProductGallery)
            ->reject(fn (array $image) => (int) $image['id'] === $mediaId)
            ->values()
            ->all();
    }

    protected function loadProduct(int $productId): void
    {
        $product = InventoryItem::query()
            ->with(['media', 'variants', 'stock'])
            ->findOrFail($productId);

        $this->productName = $product->name;
        $this->productShortDescription = (string) ($product->short_description ?? '');
        $this->productDescription = (string) ($product->full_description ?? '');
        $this->productSku = (string) ($product->sku ?? '');
        $this->productStatus = (string) ($product->status ?: 'draft');
        $this->productCategoryId = $product->inventory_category_id;
        $this->productPrice = $product->default_sell_price !== null ? (float) $product->default_sell_price : null;
        $this->productCompareAtPrice = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
        $this->productTrackStock = (bool) $product->track_stock;
        $this->productStockQuantity = $product->stock ? (float) $product->stock->qty_on_hand : 0;
        $this->productLowStockThreshold = $product->low_stock_threshold !== null ? (float) $product->low_stock_threshold : 0;
        $this->productAllowBackorders = (bool) $product->allow_backorders;
        $this->productVisible = (bool) $product->storefront_is_visible;
        $this->productFeatured = (bool) $product->is_featured;
        $this->productTaxable = (bool) $product->is_taxable;
        $this->productWeight = $product->weight !== null ? (float) $product->weight : null;
        $this->productLength = data_get($product->dimensions, 'length');
        $this->productWidth = data_get($product->dimensions, 'width');
        $this->productHeight = data_get($product->dimensions, 'height');
        $this->productSizes = $product->variants->pluck('size')->filter()->unique()->implode(', ');
        $this->productColorOptions = $this->normalizeProductColors(
            $product->variants->pluck('color')->filter()->all()
        );
        $this->syncProductColorsString();
        $this->existingProductGallery = $product->media->map(fn (InventoryItemMedia $media) => [
            'id' => $media->id,
            'path' => $media->path,
            'image_url' => $media->image_url,
            'alt_text' => $media->alt_text,
        ])->all();
        $this->productFeaturedImagePath = $product->featured_image_path;
        $this->productFeaturedImageUpload = null;
        $this->productGalleryUploads = [];
    }

    protected function syncProductVariants(InventoryItem $product, array $sizes, array $colors): void
    {
        $variants = [];
        $index = 1;

        if ($sizes !== [] && $colors !== []) {
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    $variants[] = $this->variantPayload($product, $size, $color, $index++);
                }
            }
        } elseif ($sizes !== []) {
            foreach ($sizes as $size) {
                $variants[] = $this->variantPayload($product, $size, null, $index++);
            }
        } elseif ($colors !== []) {
            foreach ($colors as $color) {
                $variants[] = $this->variantPayload($product, null, $color, $index++);
            }
        }

        $product->variants()->delete();

        if ($variants !== []) {
            $product->variants()->createMany($variants);
        }
    }

    protected function variantPayload(InventoryItem $product, ?string $size, ?string $color, int $index): array
    {
        $sizeLabel = $size ? strtoupper($size) : null;
        $colorLabel = $color ? $this->normalizeColorValue($color) : null;

        $name = trim(implode(' / ', array_filter([$sizeLabel, $colorLabel])));
        if ($name === '') {
            $name = 'Variant '.$index;
        }

        return [
            'name' => $name,
            'size' => $sizeLabel,
            'color' => $colorLabel,
            'sku' => $product->sku.'-V'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'price_delta' => 0,
            'stock_qty' => null,
            'option_values' => [
                'size' => $sizeLabel,
                'color' => $colorLabel,
            ],
            'is_active' => true,
        ];
    }

    protected function appendGalleryUploads(InventoryItem $product): void
    {
        $uploads = array_filter($this->productGalleryUploads);
        if ($uploads === []) {
            $this->syncGalleryImagesColumn($product->fresh('media'));

            return;
        }

        $maxSort = (int) ($product->media()->max('sort_order') ?? 0);
        foreach ($uploads as $upload) {
            $path = StorefrontMedia::store($upload, 'storefront/products/gallery');
            $maxSort++;

            $product->media()->create([
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $maxSort,
            ]);
        }

        $this->syncGalleryImagesColumn($product->fresh('media'));
        $this->productGalleryUploads = [];
    }

    protected function syncGalleryImagesColumn(InventoryItem $product): void
    {
        $paths = $product->media->pluck('path')->values()->all();
        $product->update(['gallery_images' => $paths]);
    }

    protected function resolveBranchId(): int
    {
        $user = auth()->user();
        if (! $user) {
            throw ValidationException::withMessages(['productName' => 'Authenticated user is required.']);
        }

        if (! $user->isGlobalAdmin()) {
            if (! $user->branch_id) {
                throw ValidationException::withMessages(['productName' => 'Your account is not assigned to a branch.']);
            }

            return (int) $user->branch_id;
        }

        $branchId = BranchContext::id() ?: $user->branch_id;
        if (! $branchId) {
            throw ValidationException::withMessages([
                'productName' => 'Select a branch from the branch switcher before creating storefront records.',
            ]);
        }

        return (int) $branchId;
    }

    protected function generateUniqueProductSlug(string $name, int $branchId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $candidate = $baseSlug;
        $suffix = 2;

        while (InventoryItem::query()
            ->withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function prepareSku(?string $provided): string
    {
        $candidate = strtoupper(trim((string) $provided));
        if ($candidate !== '') {
            return $candidate;
        }

        do {
            $candidate = 'PRD-'.Str::upper(Str::random(8));
        } while (InventoryItem::query()->withoutBranchScope()->where('sku', $candidate)->exists());

        return $candidate;
    }

    protected function dimensionsPayload(?float $length, ?float $width, ?float $height): ?array
    {
        $payload = array_filter([
            'length' => $length,
            'width' => $width,
            'height' => $height,
        ], fn ($value) => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    protected function splitCsv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => trim(strip_tags($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function syncProductColorsString(): void
    {
        $this->productColors = implode(', ', $this->productColorOptions);
    }

    /**
     * @param  array<int, string>  $colors
     * @return array<int, string>
     */
    protected function normalizeProductColors(array $colors): array
    {
        return collect($colors)
            ->map(fn (string $color) => $this->normalizeColorValue($color))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeColorValue(string $value): ?string
    {
        $candidate = trim(strip_tags($value));
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $candidate, $matches)) {
            $hex = strtoupper($matches[1]);

            return '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $candidate)) {
            return '#'.strtoupper(substr($candidate, 1));
        }

        return Str::title(Str::lower($candidate));
    }

    protected function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = trim(strip_tags($value));

        return $sanitized === '' ? null : $sanitized;
    }

    public function render()
    {
        return view('livewire.storefront.admin.product-form', [
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
