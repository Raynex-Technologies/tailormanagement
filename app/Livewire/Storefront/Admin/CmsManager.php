<?php

namespace App\Livewire\Storefront\Admin;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\StorefrontBanner;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;

#[Layout('layouts.app.sidebar')]
#[Title('Storefront CMS')]
class CmsManager extends Component
{
    public string $tab = 'pages';

    public ?int $editingPageId = null;
    public string $pageSlug = '';
    public string $pageTitle = '';
    public string $pageExcerpt = '';
    public string $pageBody = '';
    public bool $pagePublished = false;
    public bool $pageHeader = false;
    public bool $pageFooter = false;
    public int $pageSort = 0;
    public string $pageMetaTitle = '';
    public string $pageMetaDescription = '';
    public string $pageSocialImagePath = '';

    public ?int $editingSectionId = null;
    public string $sectionKey = '';
    public string $sectionTitle = '';
    public string $sectionContent = '';
    public string $sectionDataJson = '';
    public bool $sectionPublished = false;
    public int $sectionSort = 0;

    public ?int $editingBannerId = null;
    public string $bannerTitle = '';
    public string $bannerMessage = '';
    public string $bannerLinkText = '';
    public string $bannerLinkUrl = '';
    public ?string $bannerStartsAt = null;
    public ?string $bannerEndsAt = null;
    public bool $bannerActive = true;
    public int $bannerSort = 0;

    public ?int $editingMenuItemId = null;
    public string $menuLocation = 'header';
    public ?int $menuParentId = null;
    public ?int $menuPageId = null;
    public string $menuLabel = '';
    public string $menuUrl = '';
    public string $menuRouteName = '';
    public string $menuTarget = '';
    public bool $menuActive = true;
    public int $menuSort = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('storefront.cms.manage'), 403);
    }

    public function updatedPageTitle(string $value): void
    {
        $title = $this->sanitizeText($value) ?? '';
        $this->pageSlug = Str::slug($title);
        $this->pageExcerpt = $title === '' ? '' : Str::limit($title, 180, '');
    }

    public function savePage(): void
    {
        $this->authorize('storefront.cms.manage');

        $validated = $this->validate([
            'pageTitle' => ['required', 'string', 'max:191'],
            'pageBody' => ['nullable', 'string'],
            'pagePublished' => ['boolean'],
            'pageHeader' => ['boolean'],
            'pageFooter' => ['boolean'],
            'pageSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'pageMetaTitle' => ['nullable', 'string', 'max:191'],
            'pageMetaDescription' => ['nullable', 'string', 'max:2000'],
            'pageSocialImagePath' => ['nullable', 'string', 'max:255'],
        ]);

        $title = $this->sanitizeText($validated['pageTitle']);
        if ($title === null) {
            throw ValidationException::withMessages([
                'pageTitle' => 'Provide a valid page title.',
            ]);
        }

        $slugBase = Str::slug($title);
        if ($slugBase === '') {
            $slugBase = 'page';
        }

        $slug = $this->generateUniquePageSlug($slugBase, $this->editingPageId);
        $excerpt = Str::limit($title, 180, '');

        $payload = [
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $this->sanitizeMultiline($validated['pageBody'] ?? null),
            'is_published' => (bool) $validated['pagePublished'],
            'published_at' => $validated['pagePublished'] ? now() : null,
            'show_in_header' => (bool) $validated['pageHeader'],
            'show_in_footer' => (bool) $validated['pageFooter'],
            'sort_order' => (int) ($validated['pageSort'] ?? 0),
            'meta_title' => $this->sanitizeText($validated['pageMetaTitle'] ?? null),
            'meta_description' => $this->sanitizeText($validated['pageMetaDescription'] ?? null),
            'social_image_path' => $this->sanitizeText($validated['pageSocialImagePath'] ?? null),
        ];

        if ($this->editingPageId) {
            CmsPage::query()->whereKey($this->editingPageId)->update($payload);
            session()->flash('success', 'CMS page updated.');
        } else {
            CmsPage::query()->create($payload);
            session()->flash('success', 'CMS page created.');
        }

        $this->resetPageForm();
    }

    public function editPage(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        $page = CmsPage::query()->findOrFail($id);

        $this->editingPageId = $page->id;
        $this->pageSlug = $page->slug;
        $this->pageTitle = $page->title;
        $this->pageExcerpt = (string) ($page->excerpt ?? '');
        $this->pageBody = (string) ($page->body ?? '');
        $this->pagePublished = (bool) $page->is_published;
        $this->pageHeader = (bool) $page->show_in_header;
        $this->pageFooter = (bool) $page->show_in_footer;
        $this->pageSort = (int) ($page->sort_order ?? 0);
        $this->pageMetaTitle = (string) ($page->meta_title ?? '');
        $this->pageMetaDescription = (string) ($page->meta_description ?? '');
        $this->pageSocialImagePath = (string) ($page->social_image_path ?? '');
        $this->updatedPageTitle($this->pageTitle);
        $this->tab = 'pages';
    }

    public function deletePage(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        CmsPage::query()->whereKey($id)->delete();

        if ($this->editingPageId === $id) {
            $this->resetPageForm();
        }

        session()->flash('success', 'CMS page deleted.');
    }

    public function resetPageForm(): void
    {
        $this->editingPageId = null;
        $this->pageSlug = '';
        $this->pageTitle = '';
        $this->pageExcerpt = '';
        $this->pageBody = '';
        $this->pagePublished = false;
        $this->pageHeader = false;
        $this->pageFooter = false;
        $this->pageSort = 0;
        $this->pageMetaTitle = '';
        $this->pageMetaDescription = '';
        $this->pageSocialImagePath = '';

        $this->resetErrorBag([
            'pageTitle',
            'pageSlug',
            'pageExcerpt',
            'pageBody',
            'pageSort',
            'pageMetaTitle',
            'pageMetaDescription',
            'pageSocialImagePath',
        ]);
    }

    public function saveSection(): void
    {
        $this->authorize('storefront.cms.manage');

        $keyRule = Rule::unique('cms_sections', 'key');
        if ($this->editingSectionId) {
            $keyRule = $keyRule->ignore($this->editingSectionId);
        }

        $validated = $this->validate([
            'sectionKey' => ['required', 'string', 'max:100', $keyRule],
            'sectionTitle' => ['nullable', 'string', 'max:191'],
            'sectionContent' => ['nullable', 'string'],
            'sectionDataJson' => ['nullable', 'string'],
            'sectionPublished' => ['boolean'],
            'sectionSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data = null;
        if (trim($validated['sectionDataJson']) !== '') {
            $decoded = json_decode($validated['sectionDataJson'], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'sectionDataJson' => 'Section JSON must be a valid JSON object or array.',
                ]);
            }
            $data = $this->sanitizeArrayStrings($decoded);
        }

        $sectionKey = Str::snake($this->sanitizeText($validated['sectionKey']) ?? '');
        if ($sectionKey === '') {
            throw ValidationException::withMessages([
                'sectionKey' => 'Provide a valid section key.',
            ]);
        }

        $sectionKeyExists = CmsSection::query()
            ->where('key', $sectionKey)
            ->when($this->editingSectionId !== null, fn ($query) => $query->where('id', '!=', $this->editingSectionId))
            ->exists();

        if ($sectionKeyExists) {
            throw ValidationException::withMessages([
                'sectionKey' => 'This section key already exists.',
            ]);
        }

        $payload = [
            'key' => $sectionKey,
            'title' => $this->sanitizeText($validated['sectionTitle'] ?? null),
            'content' => $this->sanitizeMultiline($validated['sectionContent'] ?? null),
            'data' => $data,
            'is_published' => (bool) $validated['sectionPublished'],
            'sort_order' => (int) ($validated['sectionSort'] ?? 0),
        ];

        if ($this->editingSectionId) {
            CmsSection::query()->whereKey($this->editingSectionId)->update($payload);
            session()->flash('success', 'CMS section updated.');
        } else {
            CmsSection::query()->create($payload);
            session()->flash('success', 'CMS section created.');
        }

        $this->resetSectionForm();
    }

    public function editSection(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        $section = CmsSection::query()->findOrFail($id);

        $this->editingSectionId = $section->id;
        $this->sectionKey = $section->key;
        $this->sectionTitle = (string) ($section->title ?? '');
        $this->sectionContent = (string) ($section->content ?? '');
        $this->sectionDataJson = $section->data ? json_encode($section->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        $this->sectionPublished = (bool) $section->is_published;
        $this->sectionSort = (int) ($section->sort_order ?? 0);
        $this->tab = 'sections';
    }

    public function deleteSection(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        CmsSection::query()->whereKey($id)->delete();

        if ($this->editingSectionId === $id) {
            $this->resetSectionForm();
        }

        session()->flash('success', 'CMS section deleted.');
    }

    public function resetSectionForm(): void
    {
        $this->editingSectionId = null;
        $this->sectionKey = '';
        $this->sectionTitle = '';
        $this->sectionContent = '';
        $this->sectionDataJson = '';
        $this->sectionPublished = false;
        $this->sectionSort = 0;

        $this->resetErrorBag([
            'sectionKey',
            'sectionTitle',
            'sectionContent',
            'sectionDataJson',
            'sectionSort',
        ]);
    }

    public function saveBanner(): void
    {
        $this->authorize('storefront.cms.manage');

        $validated = $this->validate([
            'bannerTitle' => ['required', 'string', 'max:191'],
            'bannerMessage' => ['nullable', 'string', 'max:2000'],
            'bannerLinkText' => ['nullable', 'string', 'max:100'],
            'bannerLinkUrl' => ['nullable', 'url', 'max:255'],
            'bannerStartsAt' => ['nullable', 'date'],
            'bannerEndsAt' => ['nullable', 'date', 'after_or_equal:bannerStartsAt'],
            'bannerActive' => ['boolean'],
            'bannerSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $bannerTitle = $this->sanitizeText($validated['bannerTitle']);
        if ($bannerTitle === null) {
            throw ValidationException::withMessages([
                'bannerTitle' => 'Provide a valid banner title.',
            ]);
        }

        $payload = [
            'title' => $bannerTitle,
            'message' => $this->sanitizeText($validated['bannerMessage'] ?? null),
            'link_text' => $this->sanitizeText($validated['bannerLinkText'] ?? null),
            'link_url' => $this->sanitizeText($validated['bannerLinkUrl'] ?? null),
            'starts_at' => $validated['bannerStartsAt'] ?: null,
            'ends_at' => $validated['bannerEndsAt'] ?: null,
            'is_active' => (bool) $validated['bannerActive'],
            'sort_order' => (int) ($validated['bannerSort'] ?? 0),
        ];

        if ($this->editingBannerId) {
            StorefrontBanner::query()->whereKey($this->editingBannerId)->update($payload);
            session()->flash('success', 'Storefront banner updated.');
        } else {
            StorefrontBanner::query()->create($payload);
            session()->flash('success', 'Storefront banner created.');
        }

        $this->resetBannerForm();
    }

    public function editBanner(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        $banner = StorefrontBanner::query()->findOrFail($id);

        $this->editingBannerId = $banner->id;
        $this->bannerTitle = $banner->title;
        $this->bannerMessage = (string) ($banner->message ?? '');
        $this->bannerLinkText = (string) ($banner->link_text ?? '');
        $this->bannerLinkUrl = (string) ($banner->link_url ?? '');
        $this->bannerStartsAt = $banner->starts_at?->format('Y-m-d\\TH:i');
        $this->bannerEndsAt = $banner->ends_at?->format('Y-m-d\\TH:i');
        $this->bannerActive = (bool) $banner->is_active;
        $this->bannerSort = (int) ($banner->sort_order ?? 0);
        $this->tab = 'banners';
    }

    public function deleteBanner(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        StorefrontBanner::query()->whereKey($id)->delete();

        if ($this->editingBannerId === $id) {
            $this->resetBannerForm();
        }

        session()->flash('success', 'Storefront banner deleted.');
    }

    public function resetBannerForm(): void
    {
        $this->editingBannerId = null;
        $this->bannerTitle = '';
        $this->bannerMessage = '';
        $this->bannerLinkText = '';
        $this->bannerLinkUrl = '';
        $this->bannerStartsAt = null;
        $this->bannerEndsAt = null;
        $this->bannerActive = true;
        $this->bannerSort = 0;

        $this->resetErrorBag([
            'bannerTitle',
            'bannerMessage',
            'bannerLinkText',
            'bannerLinkUrl',
            'bannerStartsAt',
            'bannerEndsAt',
            'bannerSort',
        ]);
    }

    public function saveMenuItem(): void
    {
        $this->authorize('storefront.cms.manage');

        $validated = $this->validate([
            'menuLocation' => ['required', Rule::in(['header', 'footer'])],
            'menuParentId' => ['nullable', 'integer', 'exists:menu_items,id'],
            'menuPageId' => ['nullable', 'integer', 'exists:cms_pages,id'],
            'menuLabel' => ['required', 'string', 'max:191'],
            'menuUrl' => ['nullable', 'url', 'max:255'],
            'menuRouteName' => ['nullable', 'string', 'max:191'],
            'menuTarget' => ['nullable', Rule::in(['_self', '_blank'])],
            'menuSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'menuActive' => ['boolean'],
        ]);

        if (
            blank($validated['menuUrl'] ?? null)
            && blank($validated['menuRouteName'] ?? null)
            && blank($validated['menuPageId'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'menuUrl' => 'Provide a URL, route name, or CMS page link for this menu item.',
            ]);
        }

        $menu = Menu::query()->firstOrCreate(
            ['location' => $validated['menuLocation']],
            [
                'name' => Str::title($validated['menuLocation']).' Menu',
                'is_active' => true,
            ]
        );

        $menuLabel = $this->sanitizeText($validated['menuLabel']);
        if ($menuLabel === null) {
            throw ValidationException::withMessages([
                'menuLabel' => 'Provide a valid menu label.',
            ]);
        }

        $payload = [
            'menu_id' => $menu->id,
            'parent_id' => $validated['menuParentId'] ?: null,
            'cms_page_id' => $validated['menuPageId'] ?: null,
            'label' => $menuLabel,
            'url' => $this->sanitizeText($validated['menuUrl'] ?? null),
            'route_name' => $this->sanitizeText($validated['menuRouteName'] ?? null),
            'target' => $validated['menuTarget'] ?: null,
            'sort_order' => (int) ($validated['menuSort'] ?? 0),
            'is_active' => (bool) $validated['menuActive'],
        ];

        if ($this->editingMenuItemId) {
            MenuItem::query()->whereKey($this->editingMenuItemId)->update($payload);
            session()->flash('success', 'Menu item updated.');
        } else {
            MenuItem::query()->create($payload);
            session()->flash('success', 'Menu item created.');
        }

        $this->resetMenuForm();
    }

    public function editMenuItem(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        $item = MenuItem::query()->with('menu')->findOrFail($id);

        $this->editingMenuItemId = $item->id;
        $this->menuLocation = $item->menu?->location ?: 'header';
        $this->menuParentId = $item->parent_id;
        $this->menuPageId = $item->cms_page_id;
        $this->menuLabel = $item->label;
        $this->menuUrl = (string) ($item->url ?? '');
        $this->menuRouteName = (string) ($item->route_name ?? '');
        $this->menuTarget = (string) ($item->target ?? '');
        $this->menuActive = (bool) $item->is_active;
        $this->menuSort = (int) ($item->sort_order ?? 0);
        $this->tab = 'menus';
    }

    public function deleteMenuItem(int $id): void
    {
        $this->authorize('storefront.cms.manage');

        MenuItem::query()->whereKey($id)->delete();

        if ($this->editingMenuItemId === $id) {
            $this->resetMenuForm();
        }

        session()->flash('success', 'Menu item deleted.');
    }

    public function resetMenuForm(): void
    {
        $this->editingMenuItemId = null;
        $this->menuLocation = 'header';
        $this->menuParentId = null;
        $this->menuPageId = null;
        $this->menuLabel = '';
        $this->menuUrl = '';
        $this->menuRouteName = '';
        $this->menuTarget = '';
        $this->menuActive = true;
        $this->menuSort = 0;

        $this->resetErrorBag([
            'menuLocation',
            'menuParentId',
            'menuPageId',
            'menuLabel',
            'menuUrl',
            'menuRouteName',
            'menuTarget',
            'menuSort',
        ]);
    }

    protected function generateUniquePageSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $candidate = $baseSlug;
        $suffix = 2;

        while (CmsPage::query()
            ->withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = trim(strip_tags($value));
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';
        $sanitized = trim($sanitized);

        return $sanitized === '' ? null : $sanitized;
    }

    protected function sanitizeMultiline(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = trim(strip_tags($value));
        if ($sanitized === '') {
            return null;
        }

        $sanitized = preg_replace("/\r\n|\r/u", "\n", $sanitized) ?? $sanitized;
        $sanitized = preg_replace("/\n{3,}/u", "\n\n", $sanitized) ?? $sanitized;

        return trim($sanitized);
    }

    protected function sanitizeArrayStrings(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizeArrayStrings($value);
                continue;
            }

            if (is_string($value)) {
                $payload[$key] = $this->sanitizeText($value) ?? '';
            }
        }

        return $payload;
    }

    public function render()
    {
        return view('livewire.storefront.admin.cms-manager', [
            'pages' => CmsPage::query()->orderBy('sort_order')->orderBy('title')->get(),
            'sections' => CmsSection::query()->orderBy('sort_order')->orderBy('key')->get(),
            'banners' => StorefrontBanner::query()->orderBy('sort_order')->orderByDesc('id')->get(),
            'headerMenuItems' => MenuItem::query()
                ->whereHas('menu', fn ($query) => $query->where('location', 'header'))
                ->with(['menu', 'cmsPage'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'footerMenuItems' => MenuItem::query()
                ->whereHas('menu', fn ($query) => $query->where('location', 'footer'))
                ->with(['menu', 'cmsPage'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
