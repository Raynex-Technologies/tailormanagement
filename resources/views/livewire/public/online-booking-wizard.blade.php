@php
    $fieldBase = 'peer block w-full rounded-2xl border bg-white px-4 pb-3 pt-5 text-sm text-zinc-900 outline-none transition focus:ring-4';
    $labelBase = 'pointer-events-none absolute left-4 top-1.5 bg-white px-1 text-xs text-zinc-500 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:text-zinc-400 peer-focus:top-1.5 peer-focus:text-xs';
    $errorClass = 'border-red-300 focus:border-red-500 focus:ring-red-100';
    $normalClass = 'border-zinc-300 focus:border-blue-600 focus:ring-blue-100';
    $stepLabels = collect($this->steps())->pluck('label', 'number');
    $bookingLabel = $this->bookingTypes()[$booking_type]['label'] ?? str($booking_type)->replace('_', ' ')->headline();
    $selectedSlotLabel = collect($availableSlots)->firstWhere('start_at', $selectedSlot)['label'] ?? null;
@endphp

<div
    class="flex min-h-screen items-center justify-center px-4 py-8 text-zinc-900 sm:px-6 lg:px-8"
    style="background: radial-gradient(circle at top left, color-mix(in srgb, var(--tailorpro-secondary, #2563EB) 24%, transparent), transparent 34%), radial-gradient(circle at bottom right, color-mix(in srgb, var(--tailorpro-secondary-2, #F59E0B) 18%, transparent), transparent 32%), var(--tailorpro-primary, #111827);"
>
    <div class="w-full max-w-6xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/10">
        <div class="border-b border-zinc-200 bg-white px-5 py-5 sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em]" style="color: var(--tailorpro-secondary, #2563EB);">TailorPro Booking</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl">Book Tailoring Service</h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600">Choose a service, share your details, and we will confirm your request.</p>
                </div>

                @if ($step < 6)
                    <div class="overflow-x-auto pb-1">
                        <div class="flex min-w-max items-center gap-2">
                            @foreach ($this->steps() as $wizardStep)
                                @php
                                    $number = $wizardStep['number'];
                                    $isComplete = $step > $number;
                                    $isActive = $step === $number;
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-2 rounded-full px-2 py-1 {{ $isActive ? 'bg-zinc-100' : '' }}">
                                        <span
                                            class="flex size-8 items-center justify-center rounded-full text-xs font-bold ring-1 ring-inset {{ $isComplete || $isActive ? '' : 'bg-zinc-100 text-zinc-500 ring-zinc-200' }}"
                                            @if ($isActive)
                                                style="background-color: var(--tailorpro-secondary, #2563EB); color: var(--tailorpro-secondary-foreground, #ffffff);"
                                            @elseif ($isComplete)
                                                style="background-color: var(--tailorpro-secondary-2, #F59E0B); color: var(--tailorpro-secondary-2-foreground, #111827);"
                                            @endif
                                        >
                                            {{ $isComplete ? '✓' : $number }}
                                        </span>
                                        <span class="text-sm font-medium {{ $isActive ? 'text-zinc-950' : 'text-zinc-500' }}">{{ $wizardStep['label'] }}</span>
                                    </div>
                                    @if (! $loop->last)
                                        <span class="h-px w-6 {{ $step > $number ? '' : 'bg-zinc-200' }}" @if ($step > $number) style="background-color: var(--tailorpro-secondary, #2563EB);" @endif></span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="px-5 py-6 sm:px-8 sm:py-8">
            @if ($errors->any() && $step < 6)
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Please check the highlighted fields before continuing.
                </div>
            @endif

            @if ($step === 1)
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->bookingTypes() as $type => $meta)
                        @php($selected = $booking_type === $type)
                        <button
                            type="button"
                            wire:click="chooseType('{{ $type }}')"
                            class="group relative rounded-2xl border p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg {{ $selected ? 'bg-blue-50/70' : 'border-zinc-200 bg-white hover:border-zinc-300' }}"
                            @if ($selected) style="border-color: var(--tailorpro-secondary, #2563EB);" @endif
                        >
                            <div class="flex items-start gap-4">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl text-xs font-bold {{ $selected ? '' : 'bg-zinc-100 text-zinc-600' }}" @if ($selected) style="background-color: var(--tailorpro-secondary, #2563EB); color: var(--tailorpro-secondary-foreground, #ffffff);" @endif>{{ $meta['icon'] }}</span>
                                <span class="min-w-0">
                                    <span class="block text-base font-semibold text-zinc-950">{{ $meta['label'] }}</span>
                                    <span class="mt-2 block text-sm leading-6 text-zinc-600">{{ $meta['description'] }}</span>
                                </span>
                            </div>
                            @if ($selected)
                                <span class="absolute right-4 top-4 flex size-6 items-center justify-center rounded-full text-xs font-bold" style="background-color: var(--tailorpro-secondary-2, #F59E0B); color: var(--tailorpro-secondary-2-foreground, #111827);">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                @error('booking_type') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
            @elseif ($step === 2)
                <section>
                    <h2 class="text-xl font-semibold text-zinc-950">Customer Information</h2>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div class="relative">
                            <input id="customer_name" wire:model.blur="customer_name" placeholder=" " class="{{ $fieldBase }} @error('customer_name') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                            <label for="customer_name" class="{{ $labelBase }}">Full name</label>
                            @error('customer_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative">
                            <input id="customer_phone" wire:model.blur="customer_phone" placeholder=" " class="{{ $fieldBase }} @error('customer_phone') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                            <label for="customer_phone" class="{{ $labelBase }}">Phone number</label>
                            @error('customer_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative">
                            <input id="customer_whatsapp" wire:model.blur="customer_whatsapp" placeholder=" " class="{{ $fieldBase }} @error('customer_whatsapp') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                            <label for="customer_whatsapp" class="{{ $labelBase }}">WhatsApp number</label>
                            @error('customer_whatsapp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative">
                            <input id="customer_email" type="email" wire:model.blur="customer_email" placeholder=" " class="{{ $fieldBase }} @error('customer_email') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                            <label for="customer_email" class="{{ $labelBase }}">Email address</label>
                            @error('customer_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative md:col-span-2">
                            <input id="customer_location" wire:model.blur="customer_location" placeholder=" " class="{{ $fieldBase }} @error('customer_location') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                            <label for="customer_location" class="{{ $labelBase }}">Location</label>
                            @error('customer_location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative">
                            <select id="preferred_contact_method" wire:model.blur="preferred_contact_method" class="{{ $fieldBase }} @error('preferred_contact_method') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                <option value="phone">Phone</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                            </select>
                            <label for="preferred_contact_method" class="{{ $labelBase }}">Preferred contact</label>
                            @error('preferred_contact_method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative">
                            <select id="preferred_language" wire:model.blur="preferred_language" class="{{ $fieldBase }} @error('preferred_language') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                <option value="en">English</option>
                                <option value="sw">Swahili</option>
                            </select>
                            <label for="preferred_language" class="{{ $labelBase }}">Language</label>
                            @error('preferred_language') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            @elseif ($step === 3)
                <section>
                    <div class="flex flex-col gap-1">
                        <h2 class="text-xl font-semibold text-zinc-950">{{ $bookingLabel }}</h2>
                        <p class="text-sm text-zinc-500">Tell us enough to prepare the right next step.</p>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        @if ($booking_type === 'new_custom_order')
                            <div class="relative">
                                <select id="garment_category_id" wire:model.live="garment_category_id" class="{{ $fieldBase }} @error('garment_category_id') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                    <option value="">Choose category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <label for="garment_category_id" class="{{ $labelBase }}">Garment category</label>
                                @error('garment_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <input id="quantity" type="number" min="1" wire:model.blur="quantity" placeholder=" " class="{{ $fieldBase }} @error('quantity') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="quantity" class="{{ $labelBase }}">Quantity</label>
                                @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <select id="fabric_source" wire:model.blur="fabric_source" class="{{ $fieldBase }} @error('fabric_source') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                    <option value="customer_provided">Customer provided</option>
                                    <option value="tailor_supplied">Tailor supplied</option>
                                    <option value="undecided">Undecided</option>
                                </select>
                                <label for="fabric_source" class="{{ $labelBase }}">Fabric source</label>
                            </div>
                            @foreach ([
                                'fabric_type' => 'Fabric type',
                                'primary_color' => 'Primary color',
                                'secondary_color' => 'Secondary color',
                                'occasion' => 'Occasion',
                            ] as $property => $label)
                                <div class="relative">
                                    <input id="{{ $property }}" wire:model.blur="{{ $property }}" placeholder=" " class="{{ $fieldBase }} @error($property) {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                    <label for="{{ $property }}" class="{{ $labelBase }}">{{ $label }}</label>
                                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                            <div class="relative">
                                <select id="preferred_fit" wire:model.blur="preferred_fit" class="{{ $fieldBase }} @error('preferred_fit') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                    <option value="">Not sure</option>
                                    <option value="slim">Slim</option>
                                    <option value="regular">Regular</option>
                                    <option value="loose">Loose</option>
                                    <option value="traditional">Traditional</option>
                                </select>
                                <label for="preferred_fit" class="{{ $labelBase }}">Preferred fit</label>
                            </div>
                            @foreach ($optionGroups as $group)
                                <div class="relative">
                                    <select id="option_{{ $group->id }}" wire:model.blur="selectedOptions.{{ $group->id }}" class="{{ $fieldBase }} {{ $normalClass }}">
                                        <option value="">Choose</option>
                                        @foreach ($group->options as $option)
                                            <option value="{{ $option->id }}">{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                    <label for="option_{{ $group->id }}" class="{{ $labelBase }}">{{ $group->name }}</label>
                                </div>
                            @endforeach
                            <div class="relative md:col-span-2">
                                <textarea id="style_description" wire:model.blur="style_description" rows="4" placeholder=" " class="{{ $fieldBase }} @error('style_description') {{ $errorClass }} @else {{ $normalClass }} @enderror"></textarea>
                                <label for="style_description" class="{{ $labelBase }}">Style description</label>
                                @error('style_description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <select id="measurement_option" wire:model.live="measurement_option" class="{{ $fieldBase }} @error('measurement_option') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                    <option value="enter_measurements_now">Enter now</option>
                                    <option value="use_saved_measurements">Use saved</option>
                                    <option value="book_measurement_appointment">Book appointment</option>
                                    <option value="measurements_not_sure">Not sure</option>
                                </select>
                                <label for="measurement_option" class="{{ $labelBase }}">Measurements</label>
                                @error('measurement_option') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            @if ($measurement_option === 'enter_measurements_now')
                                <div class="grid gap-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 md:col-span-2 md:grid-cols-3">
                                    @forelse ($measurementFields as $field)
                                        <div class="relative">
                                            <input id="measurement_{{ $field->slug }}" wire:model.blur="measurements.{{ $field->slug }}" placeholder=" " class="{{ $fieldBase }} {{ $normalClass }}" />
                                            <label for="measurement_{{ $field->slug }}" class="{{ $labelBase }}">{{ $field->name }}</label>
                                        </div>
                                    @empty
                                        <p class="text-sm text-zinc-500 md:col-span-3">No measurement fields are configured for this garment yet.</p>
                                    @endforelse
                                </div>
                            @endif
                        @elseif ($booking_type === 'alteration_repair')
                            <div class="relative">
                                <input id="garment_name" wire:model.blur="garment_name" placeholder=" " class="{{ $fieldBase }} {{ $normalClass }}" />
                                <label for="garment_name" class="{{ $labelBase }}">Garment type</label>
                            </div>
                            <div class="relative">
                                <select id="alteration_type" wire:model.blur="alteration_type" class="{{ $fieldBase }} {{ $normalClass }}">
                                    <option value="resize">Resize</option>
                                    <option value="shorten_length">Shorten length</option>
                                    <option value="repair_tear">Repair tear</option>
                                    <option value="replace_zip">Replace zip</option>
                                    <option value="replace_buttons">Replace buttons</option>
                                    <option value="adjust_waist">Adjust waist</option>
                                    <option value="adjust_sleeves">Adjust sleeves</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="alteration_type" class="{{ $labelBase }}">Alteration type</label>
                            </div>
                            <div class="relative">
                                <select id="dropoff_method" wire:model.live="dropoff_method" class="{{ $fieldBase }} {{ $normalClass }}">
                                    <option value="customer_visits_branch">Customer visits branch</option>
                                    <option value="request_pickup">Request pickup</option>
                                    <option value="customer_sends_by_delivery">Customer sends by delivery</option>
                                </select>
                                <label for="dropoff_method" class="{{ $labelBase }}">Drop-off method</label>
                            </div>
                            <div class="relative md:col-span-2">
                                <textarea id="alteration_description" wire:model.blur="style_description" rows="4" placeholder=" " class="{{ $fieldBase }} {{ $normalClass }}"></textarea>
                                <label for="alteration_description" class="{{ $labelBase }}">Problem description</label>
                            </div>
                        @elseif ($booking_type === 'bulk_uniform_order')
                            <div class="relative">
                                <input id="organization_name" wire:model.blur="organization_name" placeholder=" " class="{{ $fieldBase }} @error('organization_name') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="organization_name" class="{{ $labelBase }}">Organization name</label>
                                @error('organization_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <select id="order_type" wire:model.blur="order_type" class="{{ $fieldBase }} {{ $normalClass }}">
                                    <option value="school_uniform">School uniform</option>
                                    <option value="corporate_uniform">Corporate uniform</option>
                                    <option value="hotel_restaurant_uniform">Hotel/restaurant uniform</option>
                                    <option value="security_uniform">Security uniform</option>
                                    <option value="event_outfit">Event outfit</option>
                                    <option value="team_outfit">Team outfit</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="order_type" class="{{ $labelBase }}">Order type</label>
                            </div>
                            @foreach ([
                                'estimated_quantity' => ['Estimated quantity', 'number'],
                                'needed_by_date' => ['Deadline', 'date'],
                                'brand_colors' => ['Brand colors', 'text'],
                                'logo_position' => ['Logo position', 'text'],
                            ] as $property => [$label, $type])
                                <div class="relative">
                                    <input id="{{ $property }}" type="{{ $type }}" wire:model.blur="{{ $property }}" placeholder=" " class="{{ $fieldBase }} @error($property) {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                    <label for="{{ $property }}" class="{{ $labelBase }}">{{ $label }}</label>
                                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                            <label class="flex min-h-14 items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 text-sm font-medium text-zinc-700">
                                <input type="checkbox" wire:model.live="measurement_appointment_needed" class="rounded border-zinc-300" />
                                Consultation or measurement appointment needed
                            </label>
                        @else
                            <div class="relative">
                                <input id="previous_order_no" wire:model.blur="previous_order_no" placeholder=" " class="{{ $fieldBase }} {{ $normalClass }}" />
                                <label for="previous_order_no" class="{{ $labelBase }}">Previous order number</label>
                            </div>
                            <div class="relative">
                                <input id="consultation_purpose" wire:model.blur="consultation_purpose" placeholder=" " class="{{ $fieldBase }} {{ $normalClass }}" />
                                <label for="consultation_purpose" class="{{ $labelBase }}">Purpose or type</label>
                            </div>
                            @if ($booking_type === 'style_consultation')
                                <div class="relative">
                                    <select id="consultation_mode" wire:model.live="consultation_mode" class="{{ $fieldBase }} {{ $normalClass }}">
                                        <option value="physical">Physical</option>
                                        <option value="online">Online</option>
                                    </select>
                                    <label for="consultation_mode" class="{{ $labelBase }}">Consultation mode</label>
                                </div>
                            @endif
                        @endif

                        <div class="md:col-span-2">
                            <label for="uploads" class="block rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-sm text-zinc-600 transition hover:border-zinc-400">
                                <span class="block font-semibold text-zinc-900">Reference images</span>
                                <span class="mt-1 block">Upload design, fabric, or garment photos. JPG, PNG, or WebP up to 2MB each.</span>
                                <input id="uploads" type="file" wire:model="uploads" multiple class="mt-4 block w-full text-sm text-zinc-600 file:mr-4 file:rounded-xl file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" />
                            </label>
                            @error('uploads.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="relative md:col-span-2">
                            <textarea id="notes" wire:model.blur="notes" rows="4" placeholder=" " class="{{ $fieldBase }} @error('notes') {{ $errorClass }} @else {{ $normalClass }} @enderror"></textarea>
                            <label for="notes" class="{{ $labelBase }}">Notes</label>
                            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            @elseif ($step === 4)
                <section>
                    <h2 class="text-xl font-semibold text-zinc-950">{{ $requiresAppointment ? 'Choose Appointment Slot' : 'Dates and Notes' }}</h2>
                    @if ($requiresAppointment)
                        <div class="mt-5 grid gap-5 md:grid-cols-3">
                            <div class="relative">
                                <select id="branch_id" wire:model.live="branch_id" class="{{ $fieldBase }} @error('branch_id') {{ $errorClass }} @else {{ $normalClass }} @enderror">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <label for="branch_id" class="{{ $labelBase }}">Branch</label>
                                @error('branch_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <input id="appointment_date" type="date" wire:model.live="appointment_date" placeholder=" " class="{{ $fieldBase }} @error('appointment_date') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="appointment_date" class="{{ $labelBase }}">Appointment date</label>
                                @error('appointment_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <input id="needed_by_date" type="date" wire:model.blur="needed_by_date" placeholder=" " class="{{ $fieldBase }} @error('needed_by_date') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="needed_by_date" class="{{ $labelBase }}">Needed by</label>
                                @error('needed_by_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-zinc-900">Available times</h3>
                                <span wire:loading wire:target="appointment_date,branch_id" class="text-xs text-zinc-500">Loading...</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @forelse ($availableSlots as $slot)
                                    @php($slotSelected = $selectedSlot === $slot['start_at'])
                                    @php($slotAvailable = (bool) ($slot['available'] ?? false))
                                    @if ($slotAvailable)
                                        <label
                                            class="cursor-pointer rounded-2xl border p-4 text-sm font-semibold transition {{ $slotSelected ? '' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:shadow-sm' }}"
                                            @if ($slotSelected) style="background-color: var(--tailorpro-secondary, #2563EB); color: var(--tailorpro-secondary-foreground, #ffffff); border-color: var(--tailorpro-secondary, #2563EB);" @endif
                                        >
                                            <input type="radio" wire:model.live="selectedSlot" value="{{ $slot['start_at'] }}" class="sr-only" />
                                            <span class="block">{{ $slot['label'] }}</span>
                                        </label>
                                    @else
                                        <div class="pointer-events-none select-none rounded-2xl border border-zinc-200 bg-zinc-100 p-4 text-sm font-semibold text-zinc-500 opacity-50 grayscale">
                                            <span class="block">{{ $slot['label'] }}</span>
                                            <span class="mt-1 block text-xs font-medium">{{ str($slot['reason'] ?? 'unavailable')->replace('_', ' ')->headline() }}</span>
                                        </div>
                                    @endif
                                @empty
                                    <div class="col-span-full rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                        Sorry, there are no available times for this date. Please choose another date.
                                    </div>
                                @endforelse
                            </div>
                            @error('selectedSlot') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div class="relative">
                                <input id="needed_by_date" type="date" wire:model.blur="needed_by_date" placeholder=" " class="{{ $fieldBase }} @error('needed_by_date') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="needed_by_date" class="{{ $labelBase }}">Needed by</label>
                                @error('needed_by_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="relative">
                                <input id="event_date" type="date" wire:model.blur="event_date" placeholder=" " class="{{ $fieldBase }} @error('event_date') {{ $errorClass }} @else {{ $normalClass }} @enderror" />
                                <label for="event_date" class="{{ $labelBase }}">Event date</label>
                                @error('event_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </section>
            @elseif ($step === 5)
                <section>
                    <h2 class="text-xl font-semibold text-zinc-950">Review and Submit</h2>
                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        @foreach ([
                            'Booking Type' => $bookingLabel,
                            'Customer Details' => trim($customer_name.' | '.$customer_phone, ' |'),
                            'Contact Preference' => str($preferred_contact_method)->headline().' | '.strtoupper($preferred_language),
                            'Garment / Request Details' => $garment_name ?: $organization_name ?: $consultation_purpose ?: str($booking_type)->replace('_', ' ')->headline(),
                            'Appointment Details' => $requiresAppointment ? (($appointment_date ?: 'Date not selected').' | '.($selectedSlotLabel ?: 'Time not selected')) : 'Not required',
                            'Measurements' => $measurement_option ?: 'Not provided',
                            'Notes' => $notes ?: 'Not provided',
                            'Uploaded Images' => count($uploads) > 0 ? count($uploads).' file(s)' : 'Not provided',
                        ] as $label => $value)
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-900">{{ $value ?: 'Not provided' }}</dd>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90 disabled:opacity-50"
                            style="background-color: var(--tailorpro-secondary, #2563EB); color: var(--tailorpro-secondary-foreground, #ffffff);"
                        >
                            <span wire:loading.remove wire:target="submit">Submit Booking Request</span>
                            <span wire:loading wire:target="submit">Submitting...</span>
                        </button>
                    </div>
                </section>
            @else
                <section class="mx-auto max-w-xl py-8 text-center">
                    <div class="mx-auto flex size-16 items-center justify-center rounded-full text-2xl font-bold" style="background-color: var(--tailorpro-secondary-2, #F59E0B); color: var(--tailorpro-secondary-2-foreground, #111827);">✓</div>
                    <h2 class="mt-5 text-2xl font-semibold text-zinc-950">Booking received</h2>
                    <p class="mt-3 text-zinc-600">{{ $confirmationMessage }}</p>
                    <div class="mx-auto mt-5 w-fit rounded-2xl bg-zinc-100 px-5 py-3 font-mono text-lg font-semibold text-zinc-950">{{ $confirmationNumber }}</div>
                    <p class="mt-4 text-sm text-zinc-500">Please keep this reference number. Bring your fabric or reference item where relevant.</p>
                </section>
            @endif

            @if ($step > 1 && $step < 6)
                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-zinc-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" wire:click="back" class="inline-flex items-center justify-center rounded-2xl border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                        Back
                    </button>
                    @if ($step < 5)
                        <button
                            type="button"
                            wire:click="next"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90 disabled:opacity-50"
                            style="background-color: var(--tailorpro-secondary, #2563EB); color: var(--tailorpro-secondary-foreground, #ffffff);"
                        >
                            <span wire:loading.remove wire:target="next">Continue</span>
                            <span wire:loading wire:target="next">Checking...</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
