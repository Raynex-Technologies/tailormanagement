<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('administration.storefront.products')" wire:navigate>{{ __('Products') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $editingProductId ? __('Edit Product') : __('New Product') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ $editingProductId ? __('Edit Product') : __('Create Product') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Use this dedicated form for complete product setup including media, pricing, stock, variants, and storefront visibility.') }}
                </flux:text>
            </div>

            <flux:button :href="route('administration.storefront.products')" wire:navigate variant="ghost">
                <x-icon name="arrow_back" class="mr-1 size-4" />
                {{ __('Back to Products') }}
            </flux:button>
        </div>

        <flux:card class="mt-6 space-y-4">
            @error('save')
                <div class="rounded-xl border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/40 dark:text-red-300">
                    {{ $message }}
                </div>
            @enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.blur="productName" label="{{ __('Name') }}" required />
                <flux:input wire:model.blur="productSku" label="{{ __('SKU') }}" placeholder="{{ __('Auto-generated if empty') }}" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.blur="productCategoryId" label="{{ __('Category') }}">
                    <flux:select.option value="">{{ __('Select category') }}</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.blur="productStatus" label="{{ __('Status') }}">
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model.blur="productShortDescription" label="{{ __('Short Description') }}" rows="2" />
            <flux:textarea wire:model.blur="productDescription" label="{{ __('Description') }}" rows="4" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-money-input wire:model.blur="productPrice" step="0.01" min="0" label="{{ __('Price') }}" required />
                <x-money-input wire:model.blur="productCompareAtPrice" step="0.01" min="0" label="{{ __('Compare At Price') }}" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.blur="productSizes" label="{{ __('Sizes (CSV)') }}" placeholder="S, M, L, XL" />

                <div>
                    <flux:label>{{ __('Colors') }}</flux:label>
                    <div class="mt-2 space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            @forelse ($productColorOptions as $color)
                                @php
                                    $isHexColor = preg_match('/^#([0-9A-Fa-f]{6})$/', $color) === 1;
                                    $swatchColor = $isHexColor ? $color : '#94A3B8';
                                @endphp
                                <span class="inline-flex items-center gap-2 rounded-full border border-zinc-300 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200">
                                    <span class="h-4 w-4 rounded-full border border-zinc-300 dark:border-zinc-600" style="background-color: {{ $swatchColor }};"></span>
                                    <span>{{ $color }}</span>
                                    <button type="button" wire:click='removeProductColor(@js($color))' class="inline-flex h-5 w-5 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-red-600 dark:hover:bg-zinc-800" aria-label="{{ __('Remove color') }}">
                                        <i class="fa-light fa-xmark text-xs"></i>
                                    </button>
                                </span>
                            @empty
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('No colors selected yet.') }}</span>
                            @endforelse

                            <button
                                type="button"
                                onclick="document.getElementById('storefront-product-color-picker').click();"
                                class="inline-flex items-center gap-2 rounded-full border border-dashed border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:border-lime-500 hover:text-lime-600 dark:border-zinc-600 dark:text-zinc-200"
                            >
                                <i class="fa-light fa-plus"></i>
                                <span>{{ __('Add Color') }}</span>
                            </button>
                        </div>

                        <input
                            id="storefront-product-color-picker"
                            type="color"
                            value="#000000"
                            class="sr-only"
                            wire:change="addProductColor($event.target.value)"
                        />
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Use the plus button to pick one or more colors.') }}
                    </p>
                    @error('productColors')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.blur="productWeight" type="number" step="0.001" min="0" label="{{ __('Weight') }}" />
                <flux:input wire:model.blur="productLowStockThreshold" type="number" step="0.01" min="0" label="{{ __('Low Stock Threshold') }}" />
                <flux:input wire:model.blur="productLength" type="number" step="0.01" min="0" label="{{ __('Length') }}" />
                <flux:input wire:model.blur="productWidth" type="number" step="0.01" min="0" label="{{ __('Width') }}" />
                <flux:input wire:model.blur="productHeight" type="number" step="0.01" min="0" label="{{ __('Height') }}" />
                <flux:input wire:model.blur="productStockQuantity" type="number" step="0.01" min="0" label="{{ __('Stock Quantity') }}" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Track Stock') }}</span>
                    <input type="checkbox" wire:model="productTrackStock" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Allow Backorders') }}</span>
                    <input type="checkbox" wire:model="productAllowBackorders" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Visible on Storefront') }}</span>
                    <input type="checkbox" wire:model="productVisible" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Featured') }}</span>
                    <input type="checkbox" wire:model="productFeatured" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 sm:col-span-2">
                    <span>{{ __('Taxable') }}</span>
                    <input type="checkbox" wire:model="productTaxable" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
            </div>

            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:label>{{ __('Featured Image') }}</flux:label>
                @if ($productFeaturedImagePath && ! $productFeaturedImageUpload)
                    <img src="{{ \App\Support\StorefrontMedia::url($productFeaturedImagePath) }}" alt="{{ __('Featured image') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                @if ($productFeaturedImageUpload)
                    <img src="{{ $productFeaturedImageUpload->temporaryUrl() }}" alt="{{ __('Preview') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                <input type="file" wire:model="productFeaturedImageUpload" accept="image/*" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                @error('productFeaturedImageUpload')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:label>{{ __('Gallery Images') }}</flux:label>
                <input type="file" wire:model="productGalleryUploads" multiple accept="image/*" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                <p wire:loading wire:target="productGalleryUploads" class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Uploading gallery images...') }}
                </p>
                @error('productGalleryUploads')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                @php
                    $galleryErrors = collect($errors->get('productGalleryUploads.*'))->flatten();
                @endphp
                @foreach ($galleryErrors as $galleryError)
                    <p class="text-xs text-red-600">{{ $galleryError }}</p>
                @endforeach

                @if (! empty($existingProductGallery))
                    <div class="overflow-x-auto pb-1">
                        <div class="flex min-w-max gap-3">
                            @foreach ($existingProductGallery as $image)
                                <div class="w-24 flex-shrink-0">
                                    <img src="{{ $image['image_url'] ?? \App\Support\StorefrontMedia::url($image['path']) }}" alt="{{ $image['alt_text'] ?: 'Image' }}" class="h-24 w-full rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="removeProductMedia({{ $image['id'] }})" class="mt-1 w-full text-red-600">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button :href="route('administration.storefront.products')" wire:navigate variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary" wire:click="save">
                    {{ $editingProductId ? __('Update Product') : __('Create Product') }}
                </flux:button>
            </div>
        </flux:card>
    </flux:main>
</div>
