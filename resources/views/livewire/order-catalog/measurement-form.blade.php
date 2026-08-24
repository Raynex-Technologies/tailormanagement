<div class="mx-auto max-w-5xl space-y-5">
    <x-orders.workspace-header class="!mb-0" :title="$measurementId ? __('Edit Measurement') : __('New Measurement')" :subtitle="__('Define a reusable dimensional measurement and the garment categories that use it.')">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs class="text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('order-catalog.index', ['tab' => 'measurements'])" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ $measurementId ? __('Edit Measurement') : __('New Measurement') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>
    </x-orders.workspace-header>

    <form wire:submit="save" class="space-y-5">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e] sm:p-6">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Definition') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Measurement definitions remain numeric; fit and style choices belong to Garment Options.') }}</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><flux:input wire:model.live.blur="name" label="{{ __('Name') }}" placeholder="{{ __('e.g. Sleeve Length') }}" required />@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div>
                    <flux:input wire:model="code" label="{{ __('Stable code') }}" placeholder="{{ __('SLEEVE_LENGTH') }}" :disabled="$measurementId !== null" required />
                    <p class="mt-1 text-xs text-zinc-500">{{ $measurementId ? __('Codes are locked after creation to protect stable identity.') : __('Uppercase letters, numbers and underscores only.') }}</p>
                    @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <flux:select wire:model="defaultUnit" label="{{ __('Default unit') }}" required>
                        <flux:select.option value="cm">{{ __('Centimetres (cm)') }}</flux:select.option>
                        <flux:select.option value="in">{{ __('Inches (in)') }}</flux:select.option>
                        <flux:select.option value="kg">{{ __('Kilograms (kg)') }}</flux:select.option>
                    </flux:select>
                    @error('defaultUnit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col justify-end gap-3">
                    <label class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                        <input type="checkbox" wire:model="isGlobal" class="mt-1 rounded border-zinc-300">
                        <span><strong class="block text-sm">{{ __('Globally available') }}</strong><span class="text-xs text-zinc-500">{{ __('Show in online booking for every garment category.') }}</span></span>
                    </label>
                    <label class="flex items-center gap-3 px-1 text-sm"><input type="checkbox" wire:model="isActive" class="rounded border-zinc-300"><span>{{ __('Active for new configuration') }}</span></label>
                </div>
                <div class="md:col-span-2"><flux:textarea wire:model="instructions" label="{{ __('Measuring instruction') }}" rows="3" placeholder="{{ __('Optional guidance for taking this measurement consistently.') }}" />@error('instructions')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e] sm:p-6">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Garment applicability') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Required state and display order are specific to each garment category.') }}</p>
            <div class="mt-5 space-y-3">
                @forelse ($categories as $category)
                    @php($settings = $categoryApplicability[$category->id] ?? ['selected' => false, 'required' => false, 'sort_order' => 0])
                    <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10 sm:grid-cols-[minmax(0,1fr)_auto_8rem] sm:items-center">
                        <label class="flex items-center gap-3 font-medium text-zinc-800 dark:text-zinc-100"><input type="checkbox" wire:model.live="categoryApplicability.{{ $category->id }}.selected" class="rounded border-zinc-300">{{ $category->name }}</label>
                        <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300"><input type="checkbox" wire:model="categoryApplicability.{{ $category->id }}.required" class="rounded border-zinc-300" @disabled(! $settings['selected'])>{{ __('Required') }}</label>
                        <flux:input type="number" min="0" max="9999" wire:model="categoryApplicability.{{ $category->id }}.sort_order" aria-label="{{ __('Display order for :category', ['category' => $category->name]) }}" :disabled="!$settings['selected']" />
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 px-5 py-8 text-center text-sm text-zinc-500 dark:border-white/15">{{ __('No garment categories are configured yet.') }}</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
            <div class="grid gap-2 sm:grid-flow-col sm:auto-cols-max sm:justify-end">
                <flux:button :href="route('order-catalog.index', ['tab' => 'measurements'])" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" wire:loading.attr="disabled"><span wire:loading.remove>{{ $measurementId ? __('Save Changes') : __('Create Measurement') }}</span><span wire:loading>{{ __('Saving…') }}</span></flux:button>
            </div>
        </section>
    </form>
</div>
