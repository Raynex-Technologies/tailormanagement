<div class="mx-auto max-w-5xl space-y-5">
    <x-orders.workspace-header
        class="!mb-0"
        :title="$itemId ? __('Edit Catalog Item') : __('New Catalog Item')"
        :subtitle="__('Define how this garment or service behaves when it is later added to an order.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs class="text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('order-catalog.index', ['tab' => 'items'])" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ $itemId ? __('Edit Item') : __('New Item') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

    </x-orders.workspace-header>

    <form id="catalog-item-form" wire:submit="save" class="space-y-5">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e] sm:p-6">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Basic information') }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><flux:input wire:model="name" label="{{ __('Name') }}" required />@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><flux:textarea wire:model="description" label="{{ __('Description') }}" rows="4" />@error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><flux:select wire:model.live="type" label="{{ __('Type') }}" required><flux:select.option value="garment">{{ __('Garment') }}</flux:select.option><flux:select.option value="service">{{ __('Service') }}</flux:select.option></flux:select>@error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><x-money-input wire:model.blur="defaultSellingPrice" min="0" step="0.01" label="{{ __('Default selling price (TZS)') }}" required />@error('defaultSellingPrice')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                @if ($type === 'garment')
                    <div class="md:col-span-2">
                        <flux:select wire:model="garmentCategoryId" label="{{ __('Garment Category') }}">
                            <flux:select.option value="">{{ __('Select category...') }}</flux:select.option>
                            @foreach ($garmentCategories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Optional for now. The category determines which measurements can be suggested during later order entry.') }}</p>
                        @error('garmentCategoryId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Image') }}</label>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/5">
                            @if ($imageUpload)<img src="{{ $imageUpload->temporaryUrl() }}" alt="" class="size-full object-cover">@elseif ($existingImagePath && ! $removeImage)<img src="{{ app(\App\Services\Media\ImageUploadService::class)->publicUrl($existingImagePath) }}" alt="" class="size-full object-cover">@else<i class="fa-duotone fa-image text-2xl text-zinc-400"></i>@endif
                        </div>
                        <div class="flex-1"><input type="file" wire:model="imageUpload" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-zinc-200 p-2 text-sm dark:border-white/10"><p class="mt-1 text-xs text-zinc-500">{{ __('JPG, PNG or WebP, up to 2 MB.') }}</p>@if ($existingImagePath)<label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="removeImage" class="rounded border-zinc-300">{{ __('Remove current image') }}</label>@endif @error('imageUpload')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e] sm:p-6">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Order behavior') }}</h2>
            <div class="mt-5 space-y-5">
                <div>
                    <label class="flex items-start gap-3"><input type="checkbox" wire:model="requiresMeasurements" class="mt-1 rounded border-zinc-300"><span><strong class="block text-sm text-zinc-800 dark:text-zinc-100">{{ __('Requires measurements') }}</strong><span class="text-xs text-zinc-500">{{ __('Marks this item for measurement-aware order entry in the upcoming catalog integration.') }}</span></span></label>
                </div>
                <fieldset>
                    <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Quantity behavior') }}</legend>
                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border p-4 transition {{ $quantityBehavior === 'individual' ? 'border-lime-400 bg-lime-50 dark:bg-lime-400/10' : 'border-zinc-200 dark:border-white/10' }}"><input type="radio" wire:model.live="quantityBehavior" value="individual" class="sr-only"><strong class="text-sm">{{ __('Individual') }}</strong><p class="mt-1 text-xs text-zinc-500">{{ __('Each quantity becomes separately configurable when added to an order. Recommended for tailored garments.') }}</p></label>
                        <label class="cursor-pointer rounded-xl border p-4 transition {{ $quantityBehavior === 'bulk' ? 'border-lime-400 bg-lime-50 dark:bg-lime-400/10' : 'border-zinc-200 dark:border-white/10' }}"><input type="radio" wire:model.live="quantityBehavior" value="bulk" class="sr-only"><strong class="text-sm">{{ __('Bulk') }}</strong><p class="mt-1 text-xs text-zinc-500">{{ __('Multiple units may remain on one order line. Recommended for services or identical items.') }}</p></label>
                    </div>
                    @error('quantityBehavior')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e] sm:p-6">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Availability') }}</h2>
            @if ($isGlobalAdmin)
                <label class="mt-4 flex items-start gap-3"><input type="checkbox" wire:model.live="availableAllBranches" class="mt-1 rounded border-zinc-300"><span><strong class="block text-sm">{{ __('Available at all branches') }}</strong><span class="text-xs text-zinc-500">{{ __('New and existing branches may use this catalog item.') }}</span></span></label>
            @else
                <p class="mt-2 text-sm text-zinc-500">{{ __('Availability is limited to your assigned branch.') }}</p>
            @endif
            @if (! $availableAllBranches)
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-white/10"><input type="checkbox" wire:model="branchIds" value="{{ $branch->id }}" class="rounded border-zinc-300" @disabled(! $isGlobalAdmin)>{{ $branch->name }}</label>
                    @endforeach
                </div>
            @endif
            @error('branchIds')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]" data-form-actions="catalog-item">
            <div class="grid gap-2 sm:grid-flow-col sm:auto-cols-max sm:justify-end">
                <flux:button :href="route('order-catalog.index', ['tab' => 'items'])" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $itemId ? __('Save Changes') : __('Create Item') }}</span>
                    <span wire:loading>{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </section>

    </form>
</div>
