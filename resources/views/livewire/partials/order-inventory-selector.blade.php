<flux:modal wire:model="showInventoryVariationSelector" class="w-full max-w-lg">
    @php $selectionProduct = $this->inventorySelectionProduct(); @endphp
    @if($selectionProduct)
        <div class="space-y-6">
            <div><flux:heading size="lg">{{ __('Choose Variation') }}</flux:heading><p class="mt-2 font-semibold">{{ $selectionProduct->name }}</p><flux:text>{{ __('Select the exact variation for this item.') }}</flux:text></div>
            @php $chosenBefore = []; @endphp
            @foreach($selectionProduct->options->where('is_active', true)->sortBy('sort_order') as $option)
                <fieldset><legend class="mb-2 font-semibold">{{ $option->name }}</legend><div class="flex flex-wrap gap-2">
                @foreach($option->values->where('is_active', true)->sortBy('sort_order') as $value)
                    @php
                        $offered = $selectionProduct->variants->filter(fn($v) => collect([...$chosenBefore, $value->id])->diff($v->selectedValues->pluck('id'))->isEmpty());
                        $ready = $offered->filter(fn($v) => $v->is_active && $v->stockUnit?->is_active && $v->stockUnit?->allocation_status === 'ready');
                        $selected = (int)($inventoryOptionChoices['option_'.$option->id] ?? 0) === $value->id;
                    @endphp
                    <button type="button" wire:click="chooseInventoryOption({{ $option->id }}, {{ $value->id }})" @disabled($ready->isEmpty()) aria-pressed="{{ $selected ? 'true' : 'false' }}" class="min-h-11 rounded-lg border px-4 py-2 text-sm disabled:opacity-50 {{ $selected ? 'border-[var(--tm-accent)] bg-[var(--tm-accent)] text-[var(--tm-accent-foreground)]' : 'border-zinc-300 dark:border-zinc-600' }}">{{ $value->name }} @if($offered->isEmpty())<span class="block text-xs">{{ __('Not offered') }}</span>@elseif($ready->isEmpty())<span class="block text-xs">{{ __('Unavailable') }}</span>@endif</button>
                @endforeach
                </div></fieldset>
                @php
                    if (isset($inventoryOptionChoices['option_'.$option->id])) $chosenBefore[] = (int)$inventoryOptionChoices['option_'.$option->id];
                @endphp
            @endforeach
            @php
                $selectedIds = collect($inventoryOptionChoices)->map(fn($v)=>(int)$v)->sort()->values()->all();
                $exact = $selectionProduct->variants->first(fn($v) => $selectedIds !== [] && $v->selectedValues->pluck('id')->sort()->values()->all() === $selectedIds);
            @endphp
            @if($exact?->stockUnit)
                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-white/5"><p class="font-semibold">{{ $exact->display_name }}</p><p class="mt-2 text-sm">{{ __('SKU') }}: {{ $exact->stockUnit->sku }}</p><p class="text-sm">{{ __('Available') }}: {{ (float)($exact->stockUnit->stock?->qty_on_hand ?? 0) - (float)($exact->stockUnit->stock?->qty_reserved ?? 0) }}</p><p class="mt-2 font-semibold">@if($inventorySelectionCostContext ?? false){{ __('Reference purchase cost') }}: {{ money_currency($exact->stockUnit->reference_cost) }}@else{{ money_currency($exact->stockUnit->selling_price) }}@endif</p></div>
            @endif
            @error('inventory')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3"><flux:button wire:click="$set('showInventoryVariationSelector', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="confirmInventoryVariation" :disabled="!$exact">{{ __('Use Variation') }}</flux:button></div>
        </div>
    @endif
</flux:modal>
