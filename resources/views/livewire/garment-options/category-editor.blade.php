    @if ($panelOpen)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-label="{{ __('Garment type details') }}" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closePanel()">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:max-w-2xl sm:rounded-3xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $readOnly ? __('View Garment Type') : ($categoryId ? __('Edit Garment Type') : __('New Garment Type')) }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">{{ __('The illustration is shown in the customer booking experience.') }}</flux:text>
                    </div>
                    <flux:button wire:click="closePanel" variant="ghost" icon="x-mark" aria-label="{{ __('Close') }}" />
                </div>
                <form wire:submit="save" class="mt-6 space-y-4">
                    <flux:input wire:model="name" label="{{ __('Garment type name') }}" placeholder="{{ __('e.g. Suit') }}" :disabled="$readOnly" />
                    @error('slug')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" :disabled="$readOnly" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="genderScope" label="{{ __('Audience') }}" :disabled="$readOnly">
                            <flux:select.option value="">{{ __('Not specified') }}</flux:select.option>
                            <flux:select.option value="male">{{ __('Men') }}</flux:select.option>
                            <flux:select.option value="female">{{ __('Women') }}</flux:select.option>
                            <flux:select.option value="unisex">{{ __('Unisex') }}</flux:select.option>
                            <flux:select.option value="children">{{ __('Children') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="sortOrder" type="number" min="0" label="{{ __('Sort order') }}" :disabled="$readOnly" />
                    </div>
                    @if (! $readOnly)
                        <flux:input wire:model="imageUpload" type="file" accept="image/jpeg,image/png,image/webp" label="{{ __('Illustration') }}" />
                        @if ($existingImagePath)
                            <flux:checkbox wire:model="removeImage" label="{{ __('Remove current illustration') }}" />
                        @endif
                    @endif
                    <flux:checkbox wire:model="isActive" label="{{ __('Active') }}" :disabled="$readOnly" />
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-white/10">
                        <flux:button type="button" wire:click="closePanel" variant="ghost">{{ $readOnly ? __('Close') : __('Cancel') }}</flux:button>
                        @if ($readOnly)
                            @can('garment-options.manage')<flux:button type="button" wire:click="edit({{ $categoryId }})" variant="primary">{{ __('Edit') }}</flux:button>@endcan
                        @else
                            <flux:button type="submit" variant="primary">{{ $categoryId ? __('Save Changes') : __('Create Garment Type') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
