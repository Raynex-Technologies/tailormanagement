<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StorefrontProductCombo;
use App\Models\StorefrontBanner;
use App\Services\Storefront\StorefrontContext;

class HomeController extends Controller
{
    public function __invoke(StorefrontContext $context)
    {
        $settings = $context->settings();

        $storefrontProducts = InventoryItem::query()
            ->storefrontVisible()
            ->with(['category', 'stock', 'variants'])
            ->latest('id');

        $featuredProducts = (clone $storefrontProducts)
            ->where('is_featured', true)
            ->take(8)
            ->get();

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = (clone $storefrontProducts)
                ->take(8)
                ->get();
        }

        $comboDeals = StorefrontProductCombo::query()
            ->where('is_active', true)
            ->where('storefront_is_visible', true)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        $featuredCategories = InventoryCategory::query()
            ->where('storefront_is_visible', true)
            ->where('storefront_featured', true)
            ->orderBy('name')
            ->take(8)
            ->get();

        $sections = CmsSection::query()
            ->published()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        return view('storefront.home', [
            'settings' => $settings,
            'featuredProducts' => $featuredProducts,
            'comboDeals' => $comboDeals,
            'featuredCategories' => $featuredCategories,
            'sections' => $sections,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'banners' => StorefrontBanner::query()->visible()->take(3)->get(),
        ]);
    }
}
