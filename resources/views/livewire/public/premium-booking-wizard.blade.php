<div class="min-h-screen bg-[#f4f1eb] px-4 py-8 text-zinc-900 sm:px-6 lg:py-12">
    <div class="mx-auto max-w-6xl">
        <header class="mb-8 flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-700">{{ config('app.name') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Book your tailoring experience') }}</h1>
            </div>
            @if (! in_array($stage, ['service', 'complete'], true))
                <button wire:click="back" class="rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-zinc-50">{{ __('Back') }}</button>
            @endif
        </header>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <main class="overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-[0_24px_80px_rgba(39,32,22,0.12)]">
            @if ($stage === 'service')
                <section class="p-6 sm:p-10">
                    <div class="max-w-2xl"><p class="text-sm font-semibold text-amber-700">{{ __('Step 1') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('How can we help?') }}</h2><p class="mt-2 text-zinc-500">{{ __('Choose the tailoring service you need today.') }}</p></div>
                    <div class="mt-8 grid gap-4 md:grid-cols-2">
                        @foreach ($this->services() as $key => $meta)
                            <button wire:click="chooseService('{{ $key }}')" class="group rounded-3xl border border-zinc-200 p-6 text-left transition hover:-translate-y-1 hover:border-amber-400 hover:shadow-xl">
                                <span class="flex size-12 items-center justify-center rounded-2xl bg-zinc-950 text-white"><i class="fa-duotone {{ $meta['icon'] }}"></i></span>
                                <span class="mt-5 block text-lg font-semibold">{{ $meta['name'] }}</span><span class="mt-2 block text-sm leading-6 text-zinc-500">{{ $meta['description'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @elseif ($stage === 'contact')
                <section class="mx-auto max-w-3xl p-6 sm:p-10">
                    <p class="text-sm font-semibold text-amber-700">{{ __('Your details') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('Let us know how to reach you') }}</h2>
                    <div class="mt-8 grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="fullName" label="{{ __('Full name') }}" required />
                        <flux:input wire:model="phone" label="{{ __('Mobile number') }}" placeholder="0712 345 678" required />
                        <flux:input wire:model="email" type="email" label="{{ __('Email (optional)') }}" />
                        <flux:input wire:model="location" label="{{ __('Location (optional)') }}" />
                    </div>
                    <div class="mt-8 flex justify-end"><flux:button wire:click="sendContactCode" variant="primary">{{ __('Send verification code') }}</flux:button></div>
                </section>
            @elseif ($stage === 'contact_verification')
                <section class="mx-auto max-w-xl p-6 text-center sm:p-12">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-800"><i class="fa-duotone fa-message-dots text-xl"></i></div>
                    <h2 class="mt-5 text-2xl font-semibold">{{ __('Verify your mobile number') }}</h2><p class="mt-2 text-zinc-500">{{ __('Enter the six-digit code sent to :phone.', ['phone' => $phone]) }}</p>
                    <flux:input wire:model="verificationCode" inputmode="numeric" maxlength="6" class="mx-auto mt-7 max-w-xs text-center text-2xl tracking-[0.4em]" />
                    <div class="mt-6 flex justify-center gap-3"><flux:button wire:click="verifyContact" variant="primary">{{ __('Verify and continue') }}</flux:button><flux:button wire:click="resendContactCode" variant="ghost">{{ __('Resend') }}</flux:button></div>
                </section>
            @elseif ($stage === 'order_lookup')
                <section class="mx-auto max-w-xl p-6 sm:p-12">
                    <p class="text-sm font-semibold text-amber-700">{{ __('Existing order') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('Enter your order number') }}</h2><p class="mt-2 text-zinc-500">{{ __('We will send another code to the mobile number recorded on that order.') }}</p>
                    <flux:input wire:model="orderNumber" class="mt-7" label="{{ __('Order number') }}" placeholder="ORD-000001" />
                    <div class="mt-7 flex justify-end"><flux:button wire:click="confirmOrder" variant="primary">{{ __('Confirm order') }}</flux:button></div>
                </section>
            @elseif ($stage === 'order_verification')
                <section class="mx-auto max-w-xl p-6 text-center sm:p-12">
                    <h2 class="text-2xl font-semibold">{{ __('Confirm this order belongs to you') }}</h2><p class="mt-2 text-zinc-500">{{ __('We sent a code to :phone.', ['phone' => $orderPhoneMasked]) }}</p>
                    <flux:input wire:model="orderVerificationCode" inputmode="numeric" maxlength="6" class="mx-auto mt-7 max-w-xs text-center text-2xl tracking-[0.4em]" />
                    <div class="mt-6"><flux:button wire:click="verifyOrder" variant="primary">{{ __('Verify order') }}</flux:button></div>
                </section>
            @elseif ($stage === 'customize')
                <section class="p-6 sm:p-10">
                    <p class="text-sm font-semibold text-amber-700">{{ __('Create your garment') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('Choose a garment') }}</h2>
                    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                        @foreach ($categories as $category)
                            <button wire:click="$set('garmentCategoryId', {{ $category->id }})" class="overflow-hidden rounded-2xl border text-left {{ $garmentCategoryId === $category->id ? 'border-amber-500 ring-2 ring-amber-200' : 'border-zinc-200' }}">
                                <div class="aspect-square bg-zinc-100">@if($category->image_url)<img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="size-full object-cover">@else<div class="flex size-full items-center justify-center text-3xl text-zinc-300"><i class="fa-duotone fa-shirt"></i></div>@endif</div>
                                <span class="block p-3 text-sm font-semibold">{{ $category->name }}</span>
                            </button>
                        @endforeach
                    </div>
                    @if ($garmentCategoryId)
                        <h3 class="mt-10 text-lg font-semibold">{{ __('Choose your fabric') }}</h3>
                        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                            @forelse ($fabricVariants as $variant)
                                <button wire:click="$set('fabricVariantId', {{ $variant->id }})" class="rounded-2xl border p-3 text-left {{ $fabricVariantId === $variant->id ? 'border-amber-500 ring-2 ring-amber-200' : 'border-zinc-200' }}">
                                    <div class="aspect-square overflow-hidden rounded-xl bg-zinc-100">@if($variant->image_url)<img src="{{ $variant->image_url }}" alt="{{ $variant->name }}" class="size-full object-cover">@else<div class="size-full" style="background: {{ $variant->swatch_hex ?: '#e4e4e7' }}"></div>@endif</div>
                                    <span class="mt-3 block font-semibold">{{ $variant->fabric?->name }}</span><span class="text-sm text-zinc-500">{{ $variant->name }}</span>
                                </button>
                            @empty
                                <div class="col-span-full rounded-2xl bg-amber-50 p-5 text-sm text-amber-800">{{ __('No fabrics have been configured for this garment yet.') }}</div>
                            @endforelse
                        </div>
                        @foreach ($groups as $group)
                            <div class="mt-9"><h3 class="font-semibold">{{ $group->name }} @if($group->is_required)<span class="text-red-500">*</span>@endif</h3><div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                                @foreach ($group->options as $choice)
                                    <button wire:click="$set('selectedChoices.{{ $group->id }}', {{ $choice->id }})" class="overflow-hidden rounded-2xl border text-left {{ ($selectedChoices[$group->id] ?? null) === $choice->id ? 'border-amber-500 ring-2 ring-amber-200' : 'border-zinc-200' }}">
                                        <div class="aspect-[4/3] bg-zinc-100">@if($choice->image_url)<img src="{{ $choice->image_url }}" alt="{{ $choice->label }}" class="size-full object-cover">@else<div class="flex size-full items-center justify-center text-zinc-300"><i class="fa-duotone fa-swatchbook text-2xl"></i></div>@endif</div><span class="block p-3 text-sm font-semibold">{{ $choice->label }}</span>
                                    </button>
                                @endforeach
                            </div></div>
                        @endforeach
                        <div class="mt-9 grid gap-5 sm:grid-cols-2"><flux:input wire:model="quantity" type="number" min="1" label="{{ __('Quantity') }}" /><flux:input wire:model="uploads" type="file" multiple accept="image/jpeg,image/png,image/webp" label="{{ __('Inspiration images (optional)') }}" /></div>
                        <div class="mt-8 flex justify-end"><flux:button wire:click="continueCustomization" variant="primary">{{ __('Review booking') }}</flux:button></div>
                    @endif
                </section>
            @elseif ($stage === 'schedule')
                <section class="mx-auto max-w-3xl p-6 sm:p-10"><p class="text-sm font-semibold text-amber-700">{{ __('Appointment') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('Choose a convenient time') }}</h2><flux:input wire:model.live="appointmentDate" type="date" class="mt-7" label="{{ __('Appointment date') }}" />
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach($availableSlots as $slot)<button @disabled(!$slot['available']) wire:click="$set('selectedSlot', '{{ $slot['start_at'] }}')" class="rounded-xl border px-4 py-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40 {{ $selectedSlot === $slot['start_at'] ? 'border-amber-500 bg-amber-50' : 'border-zinc-200' }}">{{ $slot['label'] }}</button>@endforeach</div>
                    <flux:textarea wire:model="notes" class="mt-6" label="{{ __('Notes (optional)') }}" /><div class="mt-8 flex justify-end"><flux:button wire:click="continueSchedule" variant="primary">{{ __('Review appointment') }}</flux:button></div>
                </section>
            @elseif ($stage === 'review')
                <section class="mx-auto max-w-3xl p-6 sm:p-10"><p class="text-sm font-semibold text-amber-700">{{ __('Final review') }}</p><h2 class="mt-2 text-2xl font-semibold">{{ __('Everything look right?') }}</h2>
                    <dl class="mt-7 grid gap-3 sm:grid-cols-2">@foreach(['Service' => ($this->services()[$service]['name'] ?? ''), 'Customer' => $fullName, 'Mobile' => $phone, 'Order' => $orderNumber ?: 'Not required', 'Appointment' => $selectedSlotLabel ?: 'Not required'] as $label => $value)<div class="rounded-2xl bg-zinc-50 p-4"><dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __($label) }}</dt><dd class="mt-1 font-semibold">{{ $value }}</dd></div>@endforeach</dl>
                    <div class="mt-8 flex justify-end"><flux:button wire:click="submit" variant="primary">{{ __('Confirm booking') }}</flux:button></div>
                </section>
            @elseif ($stage === 'complete')
                <section class="mx-auto max-w-xl p-8 text-center sm:p-14"><div class="mx-auto flex size-16 items-center justify-center rounded-full bg-lime-100 text-lime-700"><i class="fa-duotone fa-check text-2xl"></i></div><h2 class="mt-6 text-2xl font-semibold">{{ __('Your booking is received') }}</h2><p class="mt-3 text-zinc-500">{{ __('Keep this reference number for future communication.') }}</p><div class="mx-auto mt-6 w-fit rounded-2xl bg-zinc-950 px-6 py-3 font-mono text-lg font-semibold text-white">{{ $confirmationNumber }}</div></section>
            @endif
        </main>
    </div>
</div>
