<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Storefront\StorefrontContext;

class CmsPageController extends Controller
{
    public function show(string $slug, StorefrontContext $context)
    {
        $page = CmsPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('storefront.page', [
            'settings' => $context->settings(),
            'page' => $page,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
        ]);
    }
}
