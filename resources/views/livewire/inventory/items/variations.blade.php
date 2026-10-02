<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('inventory.items.index')" wire:navigate>{{ __('Inventory') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $item->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <section data-theme-hero style="background: linear-gradient(135deg, var(--tm-hero), color-mix(in srgb, var(--tm-hero) 88%, white));" class="rounded-2xl p-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
        <div><h1 class="text-2xl font-semibold">{{ $item->name }}</h1><p class="mt-2">{{ $item->variant_mode === 'simple' ? __('Simple product') : __('Product with variations') }}</p></div>
        @can('inventory.items.manage')
            <flux:button variant="primary" wire:click="show('options')">{{ $item->variant_mode === 'simple' ? __('Configure Variations') : __('Manage Variations') }}</flux:button>
        @endcan
    </section>
    @if(session('success'))<flux:text role="status">{{ session('success') }}</flux:text>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-300 p-4 text-red-700 dark:text-red-300">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <nav aria-label="{{ __('Product sections') }}" class="flex flex-wrap gap-2">
        @foreach(['overview'=>__('Overview'),'options'=>__('Options'),'combinations'=>__('Combinations'),'pricing'=>__('Pricing & Identity'),'stock'=>__('Stock'),'movements'=>__('Movements')] as $key=>$label)
            @if($item->variant_mode !== 'simple' || in_array($key,['overview','stock','movements']) || in_array($section,['options','combinations','pricing']))
                <flux:button variant="ghost" :style="$section === $key ? 'background: var(--tm-accent); color: var(--tm-accent-foreground);' : ''" wire:click="show('{{ $key }}')" :aria-current="$section === $key ? 'page' : null">{{ $label }}</flux:button>
            @endif
        @endforeach
    </nav>
    @if($section === 'overview')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card><flux:text>{{ __('In stock') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $total }}</flux:heading></flux:card>
            @if($item->variant_mode === 'variants')
                <flux:card><flux:text>{{ __('Active variations') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $activeVariants->count() }}</flux:heading></flux:card>
                <flux:card><flux:text>{{ __('Selling price range') }}</flux:text><flux:heading class="mt-2">{{ $prices->isEmpty() ? __('Not set') : money_tzs($prices->min()).' to '.money_tzs($prices->max()) }}</flux:heading></flux:card>
                <flux:card><flux:text>{{ __('Option types') }}</flux:text><flux:heading class="mt-2">{{ $item->options->count() }}</flux:heading><flux:text class="mt-2">{{ $low }} {{ __('low stock variations') }}</flux:text></flux:card>
            @else
                <flux:card><flux:text>{{ __('SKU') }}</flux:text><flux:heading class="mt-2">{{ $item->sku }}</flux:heading></flux:card>
                <flux:card><flux:text>{{ __('Selling price') }}</flux:text><flux:heading class="mt-2">{{ $item->simpleStockUnit?->selling_price ?? __('Not set') }}</flux:heading></flux:card>
            @endif
        </div>
        @if($item->variant_mode === 'variants')
            <flux:card><flux:heading>{{ __('Stock by :option', ['option'=>$groupOption?->name ?? __('variation')]) }}</flux:heading>
                @forelse($groups as $group)
                    <details class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><summary class="cursor-pointer font-medium">{{ $group['name'] }} <span class="float-right">{{ $group['total'] }}</span></summary>
                        @foreach($group['rows'] as $v)<div class="mt-3 flex justify-between gap-3"><span>{{ $v->display_name }}</span><span>{{ $v->stockUnit?->stock?->qty_on_hand ?? 0 }}</span></div>@endforeach
                    </details>
                @empty<flux:text class="mt-4">{{ __('No variations yet. Add options, then choose the combinations you actually stock.') }}</flux:text>@endforelse
            </flux:card>
        @endif
    @elseif($section === 'options')
        <div class="flex items-center justify-between gap-4"><div><flux:heading>{{ __('Variation types') }}</flux:heading><flux:text>{{ __('Add product-specific choices such as Color, Size or Material. Adding values does not create variations.') }}</flux:text></div>
        @can('inventory.items.manage')<flux:button wire:click="open('option')" icon="plus">{{ __('Add option') }}</flux:button>@endcan</div>
        @forelse($item->options as $option)
            <flux:card wire:key="option-{{ $option->id }}"><div class="flex flex-wrap justify-between gap-3"><flux:heading>{{ $option->name }} {{ $option->is_active ? '' : __('(Inactive)') }}</flux:heading>
                @can('inventory.items.manage')<div class="flex flex-wrap gap-2"><flux:button size="sm" wire:click="open('option',{{ $option->id }})">{{ __('Edit / Reorder') }}</flux:button><flux:button size="sm" wire:click="deleteOption({{ $option->id }})" wire:confirm="Delete this unused option and its values?">{{ __('Delete unused') }}</flux:button></div>@endcan</div>
                <div class="mt-4 flex flex-wrap gap-2">@foreach($option->values as $value)
                    <div class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700"><span>{{ $value->name }} {{ $value->is_active ? '' : __('(Inactive)') }}</span>
                    @can('inventory.items.manage')<flux:button size="sm" variant="ghost" wire:click="open('value',{{ $value->id }},{{ $option->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="ghost" wire:click="deleteValue({{ $value->id }})" wire:confirm="Delete this value if unused?">{{ __('Delete') }}</flux:button>@endcan</div>
                @endforeach</div>
                @if($option->values->isEmpty())<flux:text class="mt-3">{{ __(':name has no values yet.', ['name'=>$option->name]) }}</flux:text>@endif
                @can('inventory.items.manage')<flux:button class="mt-4" size="sm" wire:click="open('value',null,{{ $option->id }})">{{ __('Add :name Value', ['name'=>$option->name]) }}</flux:button>@endcan
            </flux:card>
        @empty<flux:card><flux:heading>{{ __('Start with an option') }}</flux:heading><flux:text class="mt-2">{{ __('For a T-shirt, add Color and Size. Then add values such as Black, White, Small and Large.') }}</flux:text></flux:card>@endforelse
        <flux:button wire:click="show('combinations')">{{ __('Choose real combinations') }}</flux:button>
    @elseif(in_array($section,['combinations','pricing','stock']))
        <div class="flex flex-wrap justify-between gap-3"><div><flux:heading>{{ $section === 'pricing' ? __('Pricing & Identity') : ($section === 'stock' ? __('Variation stock') : __('Real combinations')) }}</flux:heading><flux:text>{{ __('Only combinations you explicitly add exist. Prices and stock are managed separately.') }}</flux:text></div>
        @can('inventory.items.manage')@if($section === 'combinations')<flux:button variant="primary" wire:click="open('combination')">{{ __('Add combination') }}</flux:button>@endif
@endcan</div>
        @if($item->variant_mode === 'simple')
            <flux:card><flux:heading>{{ __('Prepare variations, then review allocation') }}</flux:heading><flux:text class="mt-2">{{ __('Your simple product remains operational until you confirm conversion. Allocate its full stock; resolve reservations and outstanding issues first.') }}</flux:text>
            @can('inventory.items.manage')@can('inventory.stock.adjust')<flux:button class="mt-4" wire:click="open('allocation')">{{ __('Review & Enable Variations') }}</flux:button>@endcan
@endcan
            @if($section==='stock')<flux:button class="mt-4" :href="route('inventory.items.index')" wire:navigate>{{ __('Simple product stock actions') }}</flux:button>@endif</flux:card>
        @endif
        <div class="space-y-3">@forelse($variants as $v)
            <flux:card wire:key="variant-{{ $v->id }}"><div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0"><flux:heading>{{ $v->display_name }}</flux:heading><flux:text class="mt-1 break-all">{{ $v->stockUnit?->sku }} / {{ $v->is_active ? __('Active') : __('Retired') }}</flux:text></div>
                @if($section==='pricing')<div><flux:text>{{ __('Reference purchase cost') }}: {{ $v->stockUnit?->reference_cost ?? __('Not set') }}</flux:text><flux:text>{{ __('Selling price') }}: {{ $v->stockUnit?->selling_price ?? __('Not set') }}</flux:text><flux:text>{{ __('Barcode') }}: {{ $v->stockUnit?->primaryBarcode?->barcode ?? __('Not set') }}</flux:text></div>
                    @can('inventory.items.manage')<flux:button wire:click="open('identity',{{ $v->id }})">{{ __('Edit pricing & identity') }}</flux:button>@endcan
                @elseif($section==='stock')<flux:button size="sm" wire:click="viewMovements({{ $v->id }})">{{ __('View Movements') }}</flux:button><div><flux:text>{{ __('On hand') }}: {{ $v->stockUnit?->stock?->qty_on_hand ?? 0 }}</flux:text><flux:text>{{ __('Reserved') }}: {{ $v->stockUnit?->stock?->qty_reserved ?? 0 }}</flux:text></div>
                    @if($v->stockUnit?->allocation_status==='ready' && $v->is_active)<div class="flex flex-wrap gap-2">@can('inventory.stock.receive')<flux:button wire:click="open('receive',{{ $v->id }})">{{ __('Receive Stock') }}</flux:button>@endcan @can('inventory.stock.adjust')<flux:button wire:click="open('adjust',{{ $v->id }})">{{ __('Adjust Stock') }}</flux:button>@endcan</div>@else<flux:text>{{ __('Allocation required or retired') }}</flux:text>@endif
                @else @can('inventory.items.manage')@if(! $v->combination_key)<flux:button wire:click="open('combination',{{ $v->id }})">{{ __('Map existing values') }}</flux:button>@endif
<flux:button wire:click="setActive({{ $v->id }},{{ $v->is_active ? 'false' : 'true' }})" wire:confirm="Change this variation's active status? Stocked variations cannot be retired.">{{ $v->is_active ? __('Retire') : __('Reactivate') }}</flux:button>@endcan @endif
            </div></flux:card>
        @empty<flux:card><flux:heading>{{ __('No variations yet') }}</flux:heading><flux:text class="mt-2">{{ __('Add options such as Color or Size, then choose only the combinations you actually sell.') }}</flux:text><flux:button class="mt-4" wire:click="show('options')">{{ __('Configure Variations') }}</flux:button></flux:card>@endforelse</div>
    @elseif($section==='movements')
        <flux:heading>{{ __('Recent movements') }}</flux:heading><flux:text>{{ __('Latest 100 movements for this product. Historical identity is preserved.') }}</flux:text>
        @forelse($movements as $movement)<flux:card><div class="flex flex-wrap justify-between gap-3"><span>{{ $movement->stockUnit?->variant?->display_name ?? __('Simple product') }} / {{ $movement->created_at }} / {{ $movement->type->value }}</span><span>{{ $movement->qty }}</span></div><flux:text>{{ $movement->note }}</flux:text></flux:card>@empty<flux:text>{{ __('No movements yet.') }}</flux:text>@endforelse
    @endif
    @if($editor !== '')
        <section role="region" aria-label="{{ __('Edit variation settings') }}" class="rounded-xl border border-zinc-300 bg-white p-6 dark:border-zinc-600 dark:bg-zinc-900" x-data x-init="$el.scrollIntoView({behavior:'smooth',block:'start'})">
            <form wire:submit="save" class="space-y-4 max-w-2xl">
                <div class="flex justify-between gap-4"><flux:heading>{{ match($editor) {'option'=>__('Variation type'),'value'=>__('Option value'),'combination'=>__('Add a combination you sell'),'identity'=>__('Pricing & Identity'),'receive'=>__('Receive Stock'),'adjust'=>__('Adjust Stock'),'allocation'=>__('Allocate Existing Stock'), default=>''} }}</flux:heading><flux:button type="button" variant="ghost" wire:click="$set('editor','')">{{ __('Close') }}</flux:button></div>
                @if(in_array($editor,['option','value']))
                    <flux:input label="Name" wire:model="name" required maxlength="100" />
                    <flux:input label="Display order" type="number" min="0" wire:model="position" />
                    <flux:checkbox label="Active" wire:model="active" />
                    <flux:text>{{ __('Renaming preserves existing variations. Deactivation hides this choice from new combinations.') }}</flux:text>
                @elseif($editor==='combination')
                    @foreach($item->options->where('is_active',true) as $option)
                        <flux:select :label="$option->name" wire:model="choices.option_{{ $option->id }}" required><flux:select.option value="">{{ __('Choose a value') }}</flux:select.option>@foreach($option->values->where('is_active',true) as $value)<flux:select.option :value="$value->id">{{ $value->name }}</flux:select.option>@endforeach</flux:select>
                    @endforeach
                    @if(! $editingId)<flux:input label="Variation SKU" wire:model="sku" required maxlength="100" />@else<flux:text>{{ __('Mapping preserves the existing SKU, prices, variant and historical references.') }}</flux:text>@endif
                    <flux:text>{{ __('This adds one real variation. Its initial prices use the product defaults; edit them under Pricing & Identity.') }}</flux:text>
                @elseif($editor==='identity')
                    <flux:text>{{ $name }}</flux:text><flux:input label="SKU" wire:model="sku" required />
                    <flux:input label="Primary barcode (optional)" wire:model="barcode" /><flux:text>{{ __('Leave blank to keep existing aliases. No barcode is generated automatically.') }}</flux:text>
                    <flux:input label="Reference purchase cost" type="number" min="0" step="0.01" wire:model="cost" />
                    <flux:input label="Selling price" type="number" min="0" step="0.01" wire:model="price" />
                @elseif(in_array($editor,['receive','adjust']))
                    <flux:heading>{{ $name }}</flux:heading><flux:text>{{ __('Current stock') }}: {{ $currentQuantity }}</flux:text><flux:input :label="$editor === 'receive' ? __('Quantity received') : __('Adjustment (+ / -)')" type="number" step="0.01" wire:model="quantity" required />
                    @if($editor==='receive')<flux:input label="Receipt unit cost (optional)" type="number" min="0" step="0.01" wire:model="cost" />@endif
                    <flux:textarea label="Reason / note" wire:model="note" />
                @elseif($editor==='allocation')
                    <flux:text>{{ __('Current SKU') }}: {{ $item->sku }} / {{ __('Current stock to allocate') }}: {{ $sourceQuantity }} / {{ __('Default price') }}: {{ $item->default_sell_price }}</flux:text>
                    <flux:text>{{ __('Confirm only when every quantity is reviewed. The total must equal current stock. Historical records stay unchanged.') }}</flux:text>
                    @foreach($activeVariants as $v)<flux:input :label="$v->display_name" type="number" min="0" step="0.01" wire:model.live="allocations.variant_{{ $v->id }}" />@endforeach
                    <flux:text>{{ __('Allocated') }}: {{ array_sum(array_map('floatval',$allocations)) }} / {{ __('Remaining') }}: {{ (float)$sourceQuantity-array_sum(array_map('floatval',$allocations)) }}</flux:text>
                @endif
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $editor === 'allocation' ? __('Confirm Allocation & Enable Variations') : __('Save') }}</flux:button>
            </form>
        </section>
    @endif
</div>
