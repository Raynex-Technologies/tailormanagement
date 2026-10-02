<details class="mt-3 text-sm">
    <summary class="cursor-pointer py-2 font-medium text-[var(--tm-accent)]">{{ __('View received items') }}</summary>
    <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
        @foreach($grn->items as $receiptLine)
            <li class="flex flex-wrap justify-between gap-3 py-3">
                <div><p class="font-medium">{{ $receiptLine->item_name ?? $receiptLine->inventoryItem?->name ?? __('Historical item') }}</p>
                    @if($receiptLine->variation_description)<p>{{ $receiptLine->variation_description }}</p>@endif
                    @if($receiptLine->sku)<p class="text-xs text-zinc-500">{{ $receiptLine->sku }}</p>@endif
                </div>
                <div class="text-right"><p>{{ number_format($receiptLine->qty_received, 2) }} ? {{ number_format($receiptLine->unit_cost, 2) }}</p><p class="font-medium">{{ __('Total cost') }}: {{ number_format($receiptLine->line_total, 2) }}</p></div>
            </li>
        @endforeach
    </ul>
</details>
