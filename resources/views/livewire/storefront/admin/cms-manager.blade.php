<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('CMS') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Storefront CMS Manager') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Manage published pages, homepage sections, announcement banners, and storefront menus.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['pages' => 'Pages', 'sections' => 'Sections', 'banners' => 'Banners', 'menus' => 'Menus'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === $key ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'pages')
            <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">
                        {{ $editingPageId ? __('Edit Page') : __('Create Page') }}
                    </flux:heading>

                    <flux:input wire:model.blur="pageTitle" label="{{ __('Title') }}" required />
                    <flux:input wire:model.blur="pageSlug" label="{{ __('Slug') }}" readonly />
                    <flux:textarea wire:model.blur="pageExcerpt" label="{{ __('Excerpt') }}" rows="2" readonly />
                    <flux:textarea wire:model.blur="pageBody" label="{{ __('Body') }}" rows="8" />
                    <flux:input wire:model.blur="pageMetaTitle" label="{{ __('Meta Title') }}" />
                    <flux:textarea wire:model.blur="pageMetaDescription" label="{{ __('Meta Description') }}" rows="2" />
                    <flux:input wire:model.blur="pageSocialImagePath" label="{{ __('Social Image Path') }}" />
                    <flux:input wire:model.blur="pageSort" type="number" min="0" label="{{ __('Sort Order') }}" />

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ __('Published') }}</span>
                            <input type="checkbox" wire:model="pagePublished" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ __('Header') }}</span>
                            <input type="checkbox" wire:model="pageHeader" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ __('Footer') }}</span>
                            <input type="checkbox" wire:model="pageFooter" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if ($editingPageId)
                            <flux:button type="button" variant="ghost" wire:click="resetPageForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="savePage">
                            {{ $editingPageId ? __('Update Page') : __('Create Page') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Page') }}</th>
                                    <th class="px-4 py-3">{{ __('Slug') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($pages as $page)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $page->title }}</div>
                                            <div class="mt-1 flex gap-1">
                                                @if ($page->show_in_header)
                                                    <flux:badge size="sm">{{ __('Header') }}</flux:badge>
                                                @endif
                                                @if ($page->show_in_footer)
                                                    <flux:badge size="sm">{{ __('Footer') }}</flux:badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-500">{{ $page->slug }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$page->is_published ? 'green' : 'zinc'">
                                                {{ $page->is_published ? __('Published') : __('Draft') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editPage({{ $page->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deletePage({{ $page->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No CMS pages yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif

        @if ($tab === 'sections')
            <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">
                        {{ $editingSectionId ? __('Edit Section') : __('Create Section') }}
                    </flux:heading>

                    <flux:input wire:model.blur="sectionKey" label="{{ __('Section Key') }}" placeholder="{{ __('hero / featured_products') }}" required />
                    <flux:input wire:model.blur="sectionTitle" label="{{ __('Section Title') }}" />
                    <flux:textarea wire:model.blur="sectionContent" label="{{ __('Section Content') }}" rows="5" />
                    <flux:textarea wire:model.blur="sectionDataJson" label="{{ __('Section Data JSON') }}" rows="8" />
                    <flux:input wire:model.blur="sectionSort" type="number" min="0" label="{{ __('Sort Order') }}" />
                    <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span>{{ __('Published') }}</span>
                        <input type="checkbox" wire:model="sectionPublished" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                    </label>

                    <div class="flex justify-end gap-2">
                        @if ($editingSectionId)
                            <flux:button type="button" variant="ghost" wire:click="resetSectionForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveSection">
                            {{ $editingSectionId ? __('Update Section') : __('Create Section') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Key') }}</th>
                                    <th class="px-4 py-3">{{ __('Title') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($sections as $section)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $section->key }}</td>
                                        <td class="px-4 py-3 text-zinc-500">{{ $section->title ?: 'â€”' }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$section->is_published ? 'green' : 'zinc'">
                                                {{ $section->is_published ? __('Published') : __('Draft') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editSection({{ $section->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteSection({{ $section->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No sections yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif

        @if ($tab === 'banners')
            <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">
                        {{ $editingBannerId ? __('Edit Banner') : __('Create Banner') }}
                    </flux:heading>

                    <flux:input wire:model.blur="bannerTitle" label="{{ __('Title') }}" required />
                    <flux:textarea wire:model.blur="bannerMessage" label="{{ __('Message') }}" rows="3" />
                    <flux:input wire:model.blur="bannerLinkText" label="{{ __('Link Text') }}" />
                    <flux:input wire:model.blur="bannerLinkUrl" label="{{ __('Link URL') }}" />
                    <flux:input wire:model.blur="bannerStartsAt" type="datetime-local" label="{{ __('Starts At') }}" />
                    <flux:input wire:model.blur="bannerEndsAt" type="datetime-local" label="{{ __('Ends At') }}" />
                    <flux:input wire:model.blur="bannerSort" type="number" min="0" label="{{ __('Sort Order') }}" />
                    <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span>{{ __('Active') }}</span>
                        <input type="checkbox" wire:model="bannerActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                    </label>

                    <div class="flex justify-end gap-2">
                        @if ($editingBannerId)
                            <flux:button type="button" variant="ghost" wire:click="resetBannerForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveBanner">
                            {{ $editingBannerId ? __('Update Banner') : __('Create Banner') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Banner') }}</th>
                                    <th class="px-4 py-3">{{ __('Window') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($banners as $banner)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $banner->title }}</div>
                                            @if ($banner->message)
                                                <div class="mt-1 text-xs text-zinc-500">{{ $banner->message }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-zinc-500">
                                            <div>{{ $banner->starts_at?->format('M d, Y H:i') ?: 'â€”' }}</div>
                                            <div>{{ $banner->ends_at?->format('M d, Y H:i') ?: 'â€”' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$banner->is_active ? 'green' : 'zinc'">
                                                {{ $banner->is_active ? __('Active') : __('Inactive') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editBanner({{ $banner->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteBanner({{ $banner->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No banners yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif

        @if ($tab === 'menus')
            <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">
                        {{ $editingMenuItemId ? __('Edit Menu Item') : __('Create Menu Item') }}
                    </flux:heading>

                    <flux:select wire:model="menuLocation" label="{{ __('Menu Location') }}">
                        <flux:select.option value="header">{{ __('Header') }}</flux:select.option>
                        <flux:select.option value="footer">{{ __('Footer') }}</flux:select.option>
                    </flux:select>

                    <flux:input wire:model.blur="menuLabel" label="{{ __('Label') }}" required />
                    <flux:select wire:model="menuPageId" label="{{ __('Link CMS Page (optional)') }}">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($pages as $page)
                            <flux:select.option value="{{ $page->id }}">{{ $page->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.blur="menuUrl" label="{{ __('External URL (optional)') }}" placeholder="https://example.com/page" />
                    <flux:input wire:model.blur="menuRouteName" label="{{ __('Route Name (optional)') }}" placeholder="storefront.catalog.index" />
                    <flux:select wire:model="menuTarget" label="{{ __('Target') }}">
                        <flux:select.option value="">{{ __('Default') }}</flux:select.option>
                        <flux:select.option value="_self">{{ __('Same tab') }}</flux:select.option>
                        <flux:select.option value="_blank">{{ __('New tab') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.blur="menuSort" type="number" min="0" label="{{ __('Sort Order') }}" />
                    <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span>{{ __('Active') }}</span>
                        <input type="checkbox" wire:model="menuActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                    </label>

                    <div class="flex justify-end gap-2">
                        @if ($editingMenuItemId)
                            <flux:button type="button" variant="ghost" wire:click="resetMenuForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveMenuItem">
                            {{ $editingMenuItemId ? __('Update Item') : __('Create Item') }}
                        </flux:button>
                    </div>
                </flux:card>

                <div class="space-y-6">
                    <flux:card>
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Header Menu') }}</flux:heading>
                            <flux:badge size="sm">{{ $headerMenuItems->count() }}</flux:badge>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        <th class="px-4 py-3">{{ __('Label') }}</th>
                                        <th class="px-4 py-3">{{ __('Destination') }}</th>
                                        <th class="px-4 py-3">{{ __('Status') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @forelse ($headerMenuItems as $item)
                                        <tr class="text-sm">
                                            <td class="px-4 py-3 font-medium">{{ $item->label }}</td>
                                            <td class="px-4 py-3 text-zinc-500">
                                                {{ $item->cmsPage?->title ?: ($item->route_name ?: $item->url ?: 'â€”') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'">
                                                    {{ $item->is_active ? __('Active') : __('Inactive') }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex justify-end gap-2">
                                                    <flux:button type="button" size="sm" variant="ghost" wire:click="editMenuItem({{ $item->id }})">{{ __('Edit') }}</flux:button>
                                                    <flux:button type="button" size="sm" variant="ghost" wire:click="deleteMenuItem({{ $item->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('No header menu items yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </flux:card>

                    <flux:card>
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Footer Menu') }}</flux:heading>
                            <flux:badge size="sm">{{ $footerMenuItems->count() }}</flux:badge>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        <th class="px-4 py-3">{{ __('Label') }}</th>
                                        <th class="px-4 py-3">{{ __('Destination') }}</th>
                                        <th class="px-4 py-3">{{ __('Status') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @forelse ($footerMenuItems as $item)
                                        <tr class="text-sm">
                                            <td class="px-4 py-3 font-medium">{{ $item->label }}</td>
                                            <td class="px-4 py-3 text-zinc-500">
                                                {{ $item->cmsPage?->title ?: ($item->route_name ?: $item->url ?: 'â€”') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'">
                                                    {{ $item->is_active ? __('Active') : __('Inactive') }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex justify-end gap-2">
                                                    <flux:button type="button" size="sm" variant="ghost" wire:click="editMenuItem({{ $item->id }})">{{ __('Edit') }}</flux:button>
                                                    <flux:button type="button" size="sm" variant="ghost" wire:click="deleteMenuItem({{ $item->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('No footer menu items yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </flux:card>
                </div>
            </div>
        @endif
    </flux:main>
</div>
