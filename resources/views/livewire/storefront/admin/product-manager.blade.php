<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Products') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Storefront Products Manager') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Manage products, combos, and coupons used in storefront checkout.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout class="mt-4" variant="danger" icon="x-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['products' => 'Products', 'combos' => 'Combos', 'coupons' => 'Coupons'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === $key ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'products')
            <flux:card class="mt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Products') }}</flux:heading>
                    <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                        <div class="w-full sm:w-72">
                            <flux:input wire:model.blur="productSearch" placeholder="{{ __('Search name, SKU, slug') }}" />
                        </div>
                        <flux:button :href="route('administration.storefront.products.create')" wire:navigate variant="primary">
                            <x-icon name="add" class="mr-1 size-4" />
                            {{ __('New Product') }}
                        </flux:button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Product') }}</th>
                                <th class="px-4 py-3">{{ __('Category') }}</th>
                                <th class="px-4 py-3">{{ __('Price') }}</th>
                                <th class="px-4 py-3">{{ __('Stock') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($products as $product)
                                <tr class="text-sm">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($product->featured_image_url)
                                                <img src="{{ $product->featured_image_url }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                                            @else
                                                <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-xs text-zinc-500 dark:border-zinc-700">{{ __('N/A') }}</div>
                                            @endif
                                            <div>
                                                <div class="font-medium">{{ $product->name }}</div>
                                                <div class="text-xs text-zinc-500">{{ $product->sku ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $product->category?->name ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ money_currency($product->default_sell_price, config('app.currency', 'TZS')) }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ number_format((float) ($product->stock?->qty_on_hand ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <flux:badge size="sm" :color="$product->status === 'active' ? 'green' : 'zinc'">
                                                {{ str($product->status)->title() }}
                                            </flux:badge>
                                            @if ($product->storefront_is_visible)
                                                <flux:badge size="sm" color="blue">{{ __('Visible') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" size="sm" variant="ghost" :href="route('administration.storefront.products.edit', $product->id)" wire:navigate>{{ __('Edit') }}</flux:button>
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="deleteProduct({{ $product->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No products found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </flux:card>
        @endif

        @if ($tab === 'combos')
            <flux:card class="mt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Combos') }}</flux:heading>
                    <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                        <div class="w-full sm:w-72">
                            <flux:input wire:model.blur="comboSearch" placeholder="{{ __('Search combo') }}" />
                        </div>
                        <flux:button type="button" variant="primary" wire:click="openCreateComboModal">
                            <x-icon name="add" class="mr-1 size-4" />
                            {{ __('New Combo') }}
                        </flux:button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Combo') }}</th>
                                <th class="px-4 py-3">{{ __('Items') }}</th>
                                <th class="px-4 py-3">{{ __('Price') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($combos as $combo)
                                <tr class="text-sm">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $combo->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $combo->slug }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $combo->items_count }}</td>
                                    <td class="px-4 py-3">{{ money_currency($combo->price, config('app.currency', 'TZS')) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <flux:badge size="sm" :color="$combo->is_active ? 'green' : 'zinc'">
                                                {{ $combo->is_active ? __('Active') : __('Inactive') }}
                                            </flux:badge>
                                            @if ($combo->storefront_is_visible)
                                                <flux:badge size="sm" color="blue">{{ __('Visible') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="editCombo({{ $combo->id }})">{{ __('Edit') }}</flux:button>
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="deleteCombo({{ $combo->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No combos found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $combos->links() }}
                </div>
            </flux:card>
        @endif

        @if ($tab === 'coupons')
            <flux:card class="mt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Coupons') }}</flux:heading>
                    <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                        <div class="w-full sm:w-72">
                            <flux:input wire:model.blur="couponSearch" placeholder="{{ __('Search coupon') }}" />
                        </div>
                        <flux:button type="button" variant="primary" wire:click="openCreateCouponModal">
                            <x-icon name="add" class="mr-1 size-4" />
                            {{ __('New Coupon') }}
                        </flux:button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Coupon') }}</th>
                                <th class="px-4 py-3">{{ __('Discount') }}</th>
                                <th class="px-4 py-3">{{ __('Usage') }}</th>
                                <th class="px-4 py-3">{{ __('Window') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($coupons as $coupon)
                                <tr class="text-sm">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $coupon->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $coupon->code }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($coupon->discount_type === 'percent')
                                            {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2, '.', ''), '0'), '.') }}%
                                        @else
                                            {{ money_currency($coupon->discount_value, config('app.currency', 'TZS')) }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500">
                                        {{ $coupon->used_count }}
                                        @if ($coupon->usage_limit)
                                            / {{ $coupon->usage_limit }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-zinc-500">
                                        <div>{{ $coupon->starts_at?->format('M d, Y H:i') ?: '-' }}</div>
                                        <div>{{ $coupon->ends_at?->format('M d, Y H:i') ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="sm" :color="$coupon->is_active ? 'green' : 'zinc'">
                                            {{ $coupon->is_active ? __('Active') : __('Inactive') }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="editCoupon({{ $coupon->id }})">{{ __('Edit') }}</flux:button>
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="deleteCoupon({{ $coupon->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No coupons found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $coupons->links() }}
                </div>
            </flux:card>
        @endif
    </flux:main>

    <flux:modal wire:model="showComboModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingComboId ? __('Edit Combo') : __('Create Combo') }}</flux:heading>
            <flux:input wire:model.blur="comboName" label="{{ __('Combo Name') }}" required />
            <flux:textarea wire:model.blur="comboDescription" label="{{ __('Description') }}" rows="3" />
            <x-money-input wire:model.blur="comboPrice" step="0.01" min="0" label="{{ __('Combo Price') }}" />
            <flux:input wire:model.blur="comboSortOrder" type="number" min="0" label="{{ __('Sort Order') }}" />

            <div>
                <flux:label>{{ __('Products In Combo') }}</flux:label>
                <select wire:model.blur="comboProductIds" multiple size="8" class="mt-2 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950">
                    @foreach ($productOptions as $productOption)
                        <option value="{{ $productOption->id }}">{{ $productOption->name }} ({{ $productOption->sku ?: 'NO-SKU' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:label>{{ __('Combo Image') }}</flux:label>
                @if ($comboFeaturedImagePath && ! $comboFeaturedImageUpload)
                    <img src="{{ \App\Support\StorefrontMedia::url($comboFeaturedImagePath) }}" alt="{{ __('Combo image') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                @if ($comboFeaturedImageUpload)
                    <img src="{{ $comboFeaturedImageUpload->temporaryUrl() }}" alt="{{ __('Preview') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                <input type="file" wire:model="comboFeaturedImageUpload" accept="image/*" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Active') }}</span>
                    <input type="checkbox" wire:model="comboActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Visible') }}</span>
                    <input type="checkbox" wire:model="comboVisible" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeComboModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="saveCombo">
                    {{ $editingComboId ? __('Update Combo') : __('Create Combo') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showCouponModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingCouponId ? __('Edit Coupon') : __('Create Coupon') }}</flux:heading>
            <flux:input wire:model.blur="couponName" label="{{ __('Coupon Name') }}" required />

            <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                <flux:input wire:model.blur="couponCode" label="{{ __('Coupon Code') }}" required />
                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" wire:click="generateCouponCode">{{ __('Generate') }}</flux:button>
                </div>
            </div>

            <flux:textarea wire:model.blur="couponDescription" label="{{ __('Description') }}" rows="3" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.blur="couponDiscountType" label="{{ __('Discount Type') }}">
                    <flux:select.option value="fixed">{{ __('Fixed') }}</flux:select.option>
                    <flux:select.option value="percent">{{ __('Percent') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model.blur="couponDiscountValue" type="number" step="0.01" min="0" label="{{ __('Discount Value') }}" />
                <x-money-input wire:model.blur="couponMinSubtotal" step="0.01" min="0" label="{{ __('Minimum Subtotal') }}" />
                <x-money-input wire:model.blur="couponMaxDiscountAmount" step="0.01" min="0" label="{{ __('Maximum Discount') }}" />
                <flux:input wire:model.blur="couponUsageLimit" type="number" min="1" label="{{ __('Usage Limit') }}" />
                <flux:input wire:model.blur="couponPerCustomerLimit" type="number" min="1" label="{{ __('Per Customer Limit') }}" />
                <flux:input wire:model.blur="couponStartsAt" type="datetime-local" label="{{ __('Starts At') }}" />
                <flux:input wire:model.blur="couponEndsAt" type="datetime-local" label="{{ __('Ends At') }}" />
            </div>

            <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                <span>{{ __('Active') }}</span>
                <input type="checkbox" wire:model="couponActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
            </label>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeCouponModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="saveCoupon">
                    {{ $editingCouponId ? __('Update Coupon') : __('Create Coupon') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
