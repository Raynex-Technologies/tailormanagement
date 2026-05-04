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
        $this->step = min(5, $this->step + 1);
        $this->refreshSlots();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
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
        $this->step = 6;
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
            'measurementFields' => $this->garment_category_id
                ? MeasurementField::query()
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('garment_category_id')->orWhere('garment_category_id', $this->garment_category_id);
                    })
                    ->orderByRaw('garment_category_id is null desc')
                    ->orderBy('sort_order')
                    ->get()
                : collect(),
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
            ['number' => 5, 'label' => __('Review')],
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
}
