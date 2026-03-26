<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StorefrontProductCombo;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request, StorefrontContext $context)
    {
        $search = trim((string) $request->query('q', ''));
        $categorySlug = $request->query('category');
        $sort = $request->query('sort', 'latest');
        $featuredOnly = $request->boolean('featured');

        $query = InventoryItem::query()
            ->storefrontVisible()
            ->with(['category', 'stock', 'media', 'variants'])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('full_description', 'like', "%{$search}%");
                });
            })
            ->when($categorySlug, function (Builder $builder) use ($categorySlug) {
                $builder->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('slug', $categorySlug));
            })
            ->when($featuredOnly, function (Builder $builder) {
                $builder->where('is_featured', true);
            });

        $query = match ($sort) {
            'price_asc' => $query->orderBy('default_sell_price'),
            'price_desc' => $query->orderByDesc('default_sell_price'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->latest('id'),
        };

        return view('storefront.catalog.index', [
            'settings' => $context->settings(),
            'products' => $query->paginate(16)->withQueryString(),
            'categories' => InventoryCategory::query()
                ->where('storefront_is_visible', true)
                ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->storefrontVisible())
                ->orderBy('name')
                ->get(),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'search' => $search,
            'selectedCategory' => $categorySlug,
            'sort' => $sort,
            'featuredOnly' => $featuredOnly,
            'currency' => $context->currency(),
        ]);
    }

    public function show(string $slug, StorefrontContext $context)
    {
        $product = $this->resolveProduct($slug);

        $related = InventoryItem::query()
            ->storefrontVisible()
            ->with(['category', 'stock', 'variants'])
            ->where('id', '!=', $product->id)
            ->where(function (Builder $builder) use ($product) {
                $builder
                    ->where('inventory_category_id', $product->inventory_category_id)
                    ->orWhereHas('tags', function (Builder $tagQuery) use ($product) {
                        $tagIds = $product->tags->pluck('id');
                        if ($tagIds->isNotEmpty()) {
                            $tagQuery->whereIn('product_tags.id', $tagIds);
                        }
                    });
            })
            ->take(8)
            ->get();

        return view('storefront.catalog.show', [
            'settings' => $context->settings(),
            'product' => $product,
            'relatedProducts' => $related,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $context->currency(),
        ]);
    }

    public function showCombo(string $slug, StorefrontContext $context)
    {
        $combo = $this->resolveCombo($slug);

        $comboItems = $combo->items
            ->filter(fn ($comboItem) => $comboItem->product && (float) $comboItem->quantity > 0)
            ->values();

        $componentsRegularTotal = (float) $comboItems->sum(
            fn ($comboItem) => (float) $comboItem->product->default_sell_price * (float) $comboItem->quantity
        );
        $comboPrice = (float) $combo->price;
        $comboSavings = max(0, round($componentsRegularTotal - $comboPrice, 2));

        $relatedProducts = $comboItems
            ->pluck('product')
            ->filter()
            ->unique('id')
            ->values()
            ->take(8);

        return view('storefront.catalog.combo-show', [
            'settings' => $context->settings(),
            'combo' => $combo,
            'comboItems' => $comboItems,
            'componentsRegularTotal' => $componentsRegularTotal,
            'comboPrice' => $comboPrice,
            'comboSavings' => $comboSavings,
            'relatedProducts' => $relatedProducts,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $context->currency(),
        ]);
    }

    protected function resolveProduct(string $slugOrId): InventoryItem
    {
        $query = InventoryItem::query()
            ->storefrontVisible()
            ->with(['category', 'stock', 'media', 'variants', 'tags'])
            ->where(function (Builder $builder) use ($slugOrId) {
                $builder->where('slug', $slugOrId);

                if (ctype_digit($slugOrId)) {
                    $builder->orWhere('id', (int) $slugOrId);
                }
            });

        $product = $query->first();

        if (! $product) {
            throw (new ModelNotFoundException())->setModel(InventoryItem::class, [$slugOrId]);
        }

        return $product;
    }

    protected function resolveCombo(string $slugOrId): StorefrontProductCombo
    {
        $query = StorefrontProductCombo::query()
            ->where('is_active', true)
            ->where('storefront_is_visible', true)
            ->with([
                'items' => fn (Builder $builder) => $builder
                    ->orderBy('id')
                    ->with([
                        'product' => fn (Builder $productQuery) => $productQuery
                            ->storefrontVisible()
                            ->with(['category', 'stock', 'media', 'variants']),
                    ]),
            ])
            ->where(function (Builder $builder) use ($slugOrId) {
                $builder->where('slug', $slugOrId);

                if (ctype_digit($slugOrId)) {
                    $builder->orWhere('id', (int) $slugOrId);
                }
            });

        $combo = $query->first();

        if (! $combo) {
            throw (new ModelNotFoundException())->setModel(StorefrontProductCombo::class, [$slugOrId]);
        }

        $hasVisibleProducts = $combo->items->contains(fn ($comboItem) => $comboItem->product && (float) $comboItem->quantity > 0);

        if (! $hasVisibleProducts) {
            throw (new ModelNotFoundException())->setModel(StorefrontProductCombo::class, [$slugOrId]);
        }

        return $combo;
    }
}
