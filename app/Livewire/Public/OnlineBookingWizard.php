<?php

namespace App\Livewire\Public;

use App\Actions\Bookings\CreateOnlineBookingAction;
use App\Events\BookingSubmitted;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\GarmentCategory;
use App\Models\GarmentOptionGroup;
use App\Models\MeasurementField;
use App\Models\OnlineBooking;
use App\Models\Order;
use App\Services\Appointments\AppointmentAvailabilityService;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.public')]
#[Title('Book Appointment')]
class OnlineBookingWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?string $confirmationNumber = null;

    public ?string $confirmationMessage = null;

    public string $booking_type = 'new_custom_order';

    public ?int $branch_id = null;

    public string $customer_name = '';

    public string $customer_phone = '';

    public string $customer_whatsapp = '';

    public string $customer_email = '';

    public string $customer_location = '';

    public string $preferred_contact_method = 'phone';

    public string $preferred_language = 'en';

    public string $notes = '';

    public string $needed_by_date = '';

    public string $event_date = '';

    public bool $is_urgent = false;

    public string $previous_order_no = '';

    public string $repeat_mode = 'repeat_exactly';

    public array $matchingOrders = [];

    public ?int $garment_category_id = null;

    public string $garment_name = '';

    public int $quantity = 1;

    public string $fabric_source = 'undecided';

    public string $fabric_type = '';

    public string $primary_color = '';

    public string $secondary_color = '';

    public string $preferred_fit = '';

    public string $occasion = '';

    public string $style_description = '';

    public string $special_instructions = '';

    public string $budget_min = '';

    public string $budget_max = '';

    public string $measurement_option = 'measurements_not_sure';

    public array $selectedOptions = [];

    public array $measurements = [];

    public array $uploads = [];

    public string $alteration_type = 'resize';

    public string $dropoff_method = 'customer_visits_branch';

    public string $measurement_purpose = 'not_sure';

    public string $fitting_type = 'first_fitting';

    public string $consultation_purpose = 'not_sure';

    public string $consultation_mode = 'physical';

    public string $organization_name = '';

    public string $order_type = 'corporate_uniform';

    public string $estimated_quantity = '';

    public string $male_quantity = '';

    public string $female_quantity = '';

    public string $children_quantity = '';

    public bool $size_list_available = false;

    public bool $measurement_appointment_needed = false;

    public bool $embroidery_needed = false;

    public bool $printing_needed = false;

    public string $brand_colors = '';

    public string $logo_position = '';

    public string $delivery_location = '';

    public string $appointment_date = '';

    public string $selectedSlot = '';

    public array $availableSlots = [];

    public string $verification_code = '';

    public bool $verificationSent = false;

    public bool $verificationVerified = false;

    public ?int $verificationExpiresAt = null;

    public ?int $verificationRetryAt = null;

    public string $verificationChannel = 'sms';

    public string $verificationTarget = '';

    public function mount(): void
    {
        $this->branch_id = Branch::query()->active()->orderBy('name')->value('id');
        $this->appointment_date = now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->addDay()->toDateString();
    }

    public function chooseType(string $type): void
    {
        $this->booking_type = $type;
        $this->step = 2;
    }

    public function next(): void
    {
        $rules = $this->rulesForStep();

        if ($rules !== []) {
            $this->validate($rules);
        }

        if ($this->step === 2) {
            $this->findMatchingOrders();
        }

        if ($this->step === 4) {
            $this->sendVerificationCode();
        }

        $this->step = min(6, $this->step + 1);
        $this->refreshSlots();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function sendVerificationCode(): void
    {
        $this->validate(array_merge($this->rulesForStep(), [
            'customer_email' => ['nullable', 'email', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:40'],
        ]));

        if ($this->requiresAppointment() && ! $this->selectedSlotData()) {
            throw ValidationException::withMessages([
                'selectedSlot' => __('That appointment slot is no longer available. Please choose another time.'),
            ]);
        }

        $state = $this->verificationSessionState();
        $now = now()->timestamp;

        if ($state && ($state['retry_at'] ?? 0) > $now) {
            $this->hydrateVerificationState($state);

            return;
        }

        $pin = (string) random_int(100000, 999999);
        $target = $this->verificationDestination();
        $message = __('Your booking verification Code is :pin. This code is valid for 10 Minutes', ['pin' => $pin]);

        if ($target['channel'] === 'email') {
            Mail::raw($message, function ($mail) use ($target): void {
                $mail->to($target['value'])->subject('Booking verification code');
            });
        } else {
            $smsReference = new OnlineBooking(['branch_id' => $this->branch_id]);

            app(SmsService::class)->sendTemplate(
                'booking_verification',
                $target['value'],
                ['verification_code' => $pin],
                $smsReference
            );
        }

        $state = [
            'hash' => Hash::make($pin),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'retry_at' => now()->addMinutes(10)->timestamp,
            'channel' => $target['channel'],
            'target' => $target['value'],
            'verified' => false,
        ];

        session()->put($this->verificationSessionKey(), $state);
        $this->hydrateVerificationState($state);
        $this->verification_code = '';
    }

    public function retryVerificationCode(): void
    {
        $state = $this->verificationSessionState();

        if ($state && ($state['retry_at'] ?? 0) > now()->timestamp) {
            $this->hydrateVerificationState($state);

            return;
        }

        session()->forget($this->verificationSessionKey());
        $this->sendVerificationCode();
    }

    public function verifyCode(): void
    {
        $this->validate([
            'verification_code' => ['required', 'digits:6'],
        ]);

        $state = $this->verificationSessionState();

        if (! $state) {
            throw ValidationException::withMessages([
                'verification_code' => __('Please request a new verification code.'),
            ]);
        }

        if (($state['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'verification_code' => __('This verification code has expired. Please request a new one.'),
            ]);
        }

        if (! Hash::check($this->verification_code, (string) ($state['hash'] ?? ''))) {
            throw ValidationException::withMessages([
                'verification_code' => __('The verification code is incorrect.'),
            ]);
        }

        $state['verified'] = true;
        session()->put($this->verificationSessionKey(), $state);
        $this->hydrateVerificationState($state);
        $this->step = 6;
    }

    public function cancelVerification(): void
    {
        $this->verificationSent = false;
        $this->verification_code = '';
        $this->step = 2;
    }

    public function updatedAppointmentDate(): void
    {
        $this->selectedSlot = '';
        $this->refreshSlots();
    }

    public function updatedBranchId(): void
    {
        $this->selectedSlot = '';
        $this->refreshSlots();
    }

    public function updatedGarmentCategoryId(): void
    {
        $this->selectedOptions = [];
        $this->measurements = [];
    }

    public function refreshSlots(): void
    {
        if (! $this->requiresAppointment() || ! $this->appointmentTypeId() || ! $this->appointment_date) {
            $this->availableSlots = [];

            return;
        }

        $this->availableSlots = app(AppointmentAvailabilityService::class)
            ->slots($this->branch_id, $this->appointmentTypeId(), $this->appointment_date)
            ->values()
            ->all();
    }

    public function submit(CreateOnlineBookingAction $action): void
    {
        $this->validate($this->rulesForSubmit());

        if (! $this->verificationVerified) {
            throw ValidationException::withMessages([
                'verification_code' => __('Please verify your booking code before submitting.'),
            ]);
        }

        $slot = $this->selectedSlotData();

        if ($this->requiresAppointment() && ! $slot) {
            throw ValidationException::withMessages([
                'selectedSlot' => __('That appointment slot is no longer available. Please choose another time.'),
            ]);
        }

        $booking = $action->execute([
            'booking_type' => $this->booking_type,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->matchedCustomerId(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_whatsapp' => $this->customer_whatsapp ?: null,
            'customer_email' => $this->customer_email ?: null,
            'customer_location' => $this->customer_location ?: null,
            'preferred_contact_method' => $this->preferred_contact_method,
            'preferred_language' => $this->preferred_language,
            'notes' => $this->notes ?: null,
            'needed_by_date' => $this->needed_by_date ?: null,
            'event_date' => $this->event_date ?: null,
            'is_urgent' => $this->is_urgent,
            'measurement_option' => $this->measurement_option,
            'payload' => $this->payload(),
            'items' => $this->itemsPayload(),
            'uploads' => $this->uploads,
            'appointment' => $slot ? [
                'appointment_type_id' => $this->appointmentTypeId(),
                'scheduled_start_at' => $slot['start_at'],
                'scheduled_end_at' => $slot['end_at'],
                'customer_note' => $this->notes ?: null,
            ] : null,
        ]);

        BookingSubmitted::dispatch($booking);

        $this->confirmationNumber = $booking->booking_number;
        $this->confirmationMessage = $booking->appointment?->status === 'confirmed'
            ? __('Your appointment is confirmed. We will contact you through your preferred channel.')
            : __('Your appointment request is pending confirmation. We will contact you through your preferred channel.');
        session()->forget($this->verificationSessionKey());
        $this->step = 7;
    }

    public function render()
    {
        return view('livewire.public.online-booking-wizard', [
            'branches' => Branch::query()->active()->orderBy('name')->get(),
            'categories' => GarmentCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'optionGroups' => $this->garment_category_id
                ? GarmentOptionGroup::query()->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                    ->where('garment_category_id', $this->garment_category_id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                : collect(),
            'measurementFields' => $this->measurementFieldsForSelectedCategory(),
            'requiresAppointment' => $this->requiresAppointment(),
        ]);
    }

    protected function rulesForStep(): array
    {
        return match ($this->step) {
            1 => ['booking_type' => ['required', Rule::in(OnlineBooking::TYPES)]],
            2 => [
                'customer_name' => ['required', 'string', 'max:191'],
                'customer_phone' => ['required', 'string', 'max:40'],
                'customer_whatsapp' => ['nullable', 'string', 'max:40'],
                'customer_email' => ['nullable', 'email', 'max:191'],
                'customer_location' => ['nullable', 'string', 'max:191'],
                'preferred_contact_method' => ['nullable', Rule::in(['phone', 'whatsapp', 'email'])],
                'preferred_language' => ['nullable', Rule::in(['en', 'sw'])],
            ],
            3 => $this->dynamicRules(),
            4 => $this->requiresAppointment() ? [
                'branch_id' => ['nullable', 'exists:branches,id'],
                'appointment_date' => ['required', 'date', 'after_or_equal:today'],
                'selectedSlot' => ['required', 'string'],
            ] : [],
            default => [],
        };
    }

    protected function rulesForSubmit(): array
    {
        return array_merge(
            [
                'booking_type' => ['required', Rule::in(OnlineBooking::TYPES)],
                'customer_name' => ['required', 'string', 'max:191'],
                'customer_phone' => ['required', 'string', 'max:40'],
                'customer_email' => ['nullable', 'email', 'max:191'],
                'needed_by_date' => ['nullable', 'date', 'after_or_equal:today'],
                'event_date' => ['nullable', 'date', 'after_or_equal:today'],
                'uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            $this->dynamicRules(),
            $this->requiresAppointment() ? [
                'appointment_date' => ['required', 'date', 'after_or_equal:today'],
                'selectedSlot' => ['required', 'string'],
            ] : []
        );
    }

    public function rules(): array
    {
        return $this->rulesForSubmit();
    }

    protected function messages(): array
    {
        return [
            'booking_type.required' => __('Please choose the type of booking you want.'),
            'customer_name.required' => __('Please enter your full name.'),
            'customer_phone.required' => __('Please enter your phone number.'),
            'garment_category_id.required' => __('Please choose a garment category.'),
            'organization_name.required' => __('Please enter the organization name.'),
            'estimated_quantity.required' => __('Please enter the estimated quantity.'),
            'appointment_date.required' => __('Please choose an appointment date.'),
            'selectedSlot.required' => __('Please choose an available appointment time.'),
        ];
    }

    public function steps(): array
    {
        return [
            ['number' => 1, 'label' => __('Service')],
            ['number' => 2, 'label' => __('Customer')],
            ['number' => 3, 'label' => __('Details')],
            ['number' => 4, 'label' => __('Schedule')],
            ['number' => 5, 'label' => __('Verify')],
            ['number' => 6, 'label' => __('Review')],
        ];
    }

    public function bookingTypes(): array
    {
        return [
            'new_custom_order' => [
                'label' => __('New Custom Order'),
                'description' => __('Create a new outfit from your design, fabric, or reference photos.'),
                'icon' => 'NC',
            ],
            'repeat_previous_order' => [
                'label' => __('Repeat Previous Order'),
                'description' => __('Repeat an earlier order exactly or with small changes.'),
                'icon' => 'RP',
            ],
            'alteration_repair' => [
                'label' => __('Alteration / Repair'),
                'description' => __('Resize, repair, or adjust an existing garment.'),
                'icon' => 'AR',
            ],
            'measurement_appointment' => [
                'label' => __('Measurement Appointment'),
                'description' => __('Visit us so we can record your measurements.'),
                'icon' => 'MA',
            ],
            'fitting_appointment' => [
                'label' => __('Fitting Appointment'),
                'description' => __('Schedule a fitting for an existing order.'),
                'icon' => 'FA',
            ],
            'style_consultation' => [
                'label' => __('Style Consultation'),
                'description' => __('Get guidance before deciding on your outfit.'),
                'icon' => 'SC',
            ],
            'bulk_uniform_order' => [
                'label' => __('Bulk / Uniform Order'),
                'description' => __('For schools, companies, teams, and organizations.'),
                'icon' => 'BU',
            ],
        ];
    }

    protected function dynamicRules(): array
    {
        return match ($this->booking_type) {
            'new_custom_order' => [
                'garment_category_id' => ['required', 'exists:garment_categories,id'],
                'quantity' => ['required', 'integer', 'min:1', 'max:500'],
                'measurement_option' => ['required', Rule::in(['enter_measurements_now', 'use_saved_measurements', 'book_measurement_appointment', 'measurements_not_sure'])],
            ],
            'bulk_uniform_order' => [
                'organization_name' => ['required', 'string', 'max:191'],
                'estimated_quantity' => ['required', 'integer', 'min:1'],
            ],
            'measurement_appointment', 'fitting_appointment', 'style_consultation', 'alteration_repair', 'repeat_previous_order' => [],
            default => [],
        };
    }

    protected function requiresAppointment(): bool
    {
        return in_array($this->booking_type, ['measurement_appointment', 'fitting_appointment'], true)
            || ($this->booking_type === 'new_custom_order' && $this->measurement_option === 'book_measurement_appointment')
            || ($this->booking_type === 'alteration_repair' && $this->dropoff_method === 'customer_visits_branch')
            || ($this->booking_type === 'style_consultation' && $this->consultation_mode === 'physical')
            || ($this->booking_type === 'bulk_uniform_order' && $this->measurement_appointment_needed);
    }

    protected function appointmentTypeId(): ?int
    {
        $code = match ($this->booking_type) {
            'measurement_appointment', 'new_custom_order' => 'measurement',
            'fitting_appointment' => 'fitting',
            'alteration_repair' => 'alteration_dropoff',
            'style_consultation' => 'consultation',
            'bulk_uniform_order' => 'bulk_consultation',
            default => null,
        };

        return $code ? AppointmentType::query()->where('code', $code)->value('id') : null;
    }

    protected function selectedSlotData(): ?array
    {
        if (! $this->requiresAppointment()) {
            return null;
        }

        $this->refreshSlots();

        return collect($this->availableSlots)->first(
            fn (array $slot): bool => (bool) ($slot['available'] ?? false) && $slot['start_at'] === $this->selectedSlot
        );
    }

    protected function itemsPayload(): array
    {
        if (! in_array($this->booking_type, ['new_custom_order', 'alteration_repair', 'bulk_uniform_order'], true)) {
            return [];
        }

        return [[
            'garment_category_id' => $this->garment_category_id,
            'garment_name' => $this->garment_name ?: null,
            'quantity' => $this->quantity,
            'fabric_source' => $this->fabric_source,
            'fabric_type' => $this->fabric_type ?: null,
            'primary_color' => $this->primary_color ?: null,
            'secondary_color' => $this->secondary_color ?: null,
            'preferred_fit' => $this->preferred_fit ?: null,
            'occasion' => $this->occasion ?: null,
            'style_description' => $this->style_description ?: null,
            'special_instructions' => $this->special_instructions ?: null,
            'estimated_budget_min' => $this->budget_min ?: null,
            'estimated_budget_max' => $this->budget_max ?: null,
            'selected_options' => collect($this->selectedOptions)
                ->filter()
                ->map(fn ($optionId, $groupId) => [
                    'garment_option_group_id' => (int) $groupId,
                    'garment_option_id' => (int) $optionId,
                ])
                ->values()
                ->all(),
        ]];
    }

    protected function payload(): array
    {
        return [
            'previous_order_no' => $this->previous_order_no,
            'repeat_mode' => $this->repeat_mode,
            'alteration_type' => $this->alteration_type,
            'dropoff_method' => $this->dropoff_method,
            'measurement_purpose' => $this->measurement_purpose,
            'fitting_type' => $this->fitting_type,
            'consultation_purpose' => $this->consultation_purpose,
            'consultation_mode' => $this->consultation_mode,
            'organization_name' => $this->organization_name,
            'order_type' => $this->order_type,
            'estimated_quantity' => $this->estimated_quantity,
            'male_quantity' => $this->male_quantity,
            'female_quantity' => $this->female_quantity,
            'children_quantity' => $this->children_quantity,
            'size_list_available' => $this->size_list_available,
            'embroidery_needed' => $this->embroidery_needed,
            'printing_needed' => $this->printing_needed,
            'brand_colors' => $this->brand_colors,
            'logo_position' => $this->logo_position,
            'delivery_location' => $this->delivery_location,
            'measurements' => $this->measurements,
        ];
    }

    protected function measurementFieldsForSelectedCategory()
    {
        if (! $this->garment_category_id) {
            return collect();
        }

        $global = MeasurementField::query()
            ->active()
            ->globallyApplicable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $category = GarmentCategory::query()->find($this->garment_category_id);
        $specific = $category
            ? $category->measurementFields()->active()->get()
            : collect();

        return $global->concat($specific)->unique('id')->values();
    }

    protected function matchedCustomerId(): ?int
    {
        return Customer::query()->where('phone', $this->customer_phone)->value('id');
    }

    protected function findMatchingOrders(): void
    {
        if (! in_array($this->booking_type, ['repeat_previous_order', 'fitting_appointment'], true) || $this->customer_phone === '') {
            $this->matchingOrders = [];

            return;
        }

        $this->matchingOrders = Order::query()
            ->with('customer:id,name,phone')
            ->whereHas('customer', fn ($query) => $query->where('phone', 'like', '%'.$this->customer_phone.'%'))
            ->when($this->previous_order_no !== '', fn ($query) => $query->where('order_no', 'like', '%'.$this->previous_order_no.'%'))
            ->latest()
            ->limit(5)
            ->get(['id', 'order_no', 'customer_id', 'status', 'due_date'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'customer' => $order->customer?->name,
                'status' => $order->status?->value ?? (string) $order->status,
            ])
            ->all();
    }

    protected function verificationDestination(): array
    {
        if (filled($this->customer_email)) {
            return ['channel' => 'email', 'value' => strtolower(trim($this->customer_email))];
        }

        return ['channel' => 'sms', 'value' => trim($this->customer_phone)];
    }

    protected function verificationSessionKey(): string
    {
        $destination = $this->verificationDestination();

        return 'booking_verification.'.sha1($destination['channel'].'|'.$destination['value']);
    }

    protected function verificationSessionState(): ?array
    {
        $state = session()->get($this->verificationSessionKey());

        return is_array($state) ? $state : null;
    }

    protected function hydrateVerificationState(array $state): void
    {
        $this->verificationSent = true;
        $this->verificationVerified = (bool) ($state['verified'] ?? false);
        $this->verificationExpiresAt = (int) ($state['expires_at'] ?? now()->timestamp);
        $this->verificationRetryAt = (int) ($state['retry_at'] ?? now()->timestamp);
        $this->verificationChannel = (string) ($state['channel'] ?? 'sms');
        $this->verificationTarget = (string) ($state['target'] ?? '');
    }
}
