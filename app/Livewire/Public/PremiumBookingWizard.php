<?php

namespace App\Livewire\Public;

use App\Actions\Bookings\CreateOnlineBookingAction;
use App\Events\BookingSubmitted;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\FabricVariant;
use App\Models\GarmentCategory;
use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use App\Models\Order;
use App\Services\Appointments\AppointmentAvailabilityService;
use App\Services\Bookings\BookingVerificationService;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.public')]
#[Title('Book a Tailoring Service')]
class PremiumBookingWizard extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $wizardToken;

    public string $stage = 'service';

    public string $service = '';

    public string $fullName = '';

    public string $phone = '';

    public string $email = '';

    public string $location = '';

    public string $notes = '';

    public string $verificationCode = '';

    public ?int $verificationRetryAt = null;

    public string $orderNumber = '';

    public string $orderVerificationCode = '';

    public string $orderPhoneMasked = '';

    #[Locked]
    public ?int $verifiedOrderId = null;

    public ?int $branchId = null;

    public ?int $garmentCategoryId = null;

    public ?int $fabricVariantId = null;

    public int $quantity = 1;

    public array $selectedChoices = [];

    public array $uploads = [];

    public string $appointmentDate = '';

    public string $selectedSlot = '';

    public array $availableSlots = [];

    public ?string $confirmationNumber = null;

    public function mount(): void
    {
        $this->wizardToken = (string) str()->uuid();
        $this->branchId = Branch::query()->active()->orderBy('name')->value('id');
        $this->appointmentDate = now(AppointmentAvailabilityService::DEFAULT_TIMEZONE)->addDay()->toDateString();
    }

    public function chooseService(string $service): void
    {
        abort_unless(array_key_exists($service, $this->services()), 404);
        $this->resetWizardAfterService();
        $this->service = $service;
        $this->stage = 'contact';
    }

    public function sendContactCode(BookingVerificationService $verification): void
    {
        $this->validateContact();
        $state = $verification->issue($this->wizardToken, 'contact', $this->phone, $this->branchId);
        $this->verificationRetryAt = (int) $state['retry_at'];
        $this->stage = 'contact_verification';
    }

    public function verifyContact(BookingVerificationService $verification): void
    {
        $this->validate(['verificationCode' => ['required', 'digits:6']]);
        $verification->verify($this->wizardToken, 'contact', $this->phone, $this->verificationCode);
        $this->verificationCode = '';
        $this->stage = $this->service === 'new_custom_order' ? 'customize' : 'order_lookup';
    }

    public function resendContactCode(BookingVerificationService $verification): void
    {
        $verification->forget($this->wizardToken, 'contact');
        $this->sendContactCode($verification);
    }

    public function confirmOrder(BookingVerificationService $verification): void
    {
        $this->assertContactVerified($verification);
        $this->validate(['orderNumber' => ['required', 'string', 'max:50']]);

        $order = Order::withoutGlobalScopes()
            ->with('customer:id,phone')
            ->where('order_no', trim($this->orderNumber))
            ->first();

        if (! $order?->customer?->phone) {
            throw ValidationException::withMessages(['orderNumber' => __('We could not verify this order. Check the order number and try again.')]);
        }

        $this->branchId = $order->branch_id;
        $state = $verification->issue($this->wizardToken, 'order', $order->customer->phone, $order->branch_id, ['order_id' => $order->id]);
        $this->verifiedOrderId = $order->id;
        $this->verificationRetryAt = (int) $state['retry_at'];
        $this->orderPhoneMasked = $this->maskPhone($order->customer->phone);
        $this->stage = 'order_verification';
    }

    public function verifyOrder(BookingVerificationService $verification): void
    {
        $this->validate(['orderVerificationCode' => ['required', 'digits:6']]);
        $order = $this->verifiedOrder();
        $verification->verify($this->wizardToken, 'order', $order->customer->phone, $this->orderVerificationCode);
        $this->orderVerificationCode = '';
        $this->stage = 'schedule';
        $this->refreshSlots();
    }

    public function continueCustomization(): void
    {
        $this->validateCustomization();
        $this->stage = 'review';
    }

    public function continueSchedule(BookingVerificationService $verification): void
    {
        $this->assertOrderVerified($verification);
        $this->validate([
            'appointmentDate' => ['required', 'date', 'after_or_equal:today'],
            'selectedSlot' => ['required', 'string'],
        ]);
        abort_unless($this->selectedSlotData(), 422, __('That appointment slot is no longer available.'));
        $this->stage = 'review';
    }

    public function updatedGarmentCategoryId(): void
    {
        $this->fabricVariantId = null;
        $this->selectedChoices = [];
    }

    public function updatedAppointmentDate(): void
    {
        $this->selectedSlot = '';
        $this->refreshSlots();
    }

    public function refreshSlots(): void
    {
        $typeId = $this->appointmentTypeId();
        $this->availableSlots = $typeId && $this->appointmentDate
            ? app(AppointmentAvailabilityService::class)->slots($this->branchId, $typeId, $this->appointmentDate)->values()->all()
            : [];
    }

    public function submit(CreateOnlineBookingAction $action, BookingVerificationService $verification): void
    {
        $this->assertContactVerified($verification);
        $slot = null;
        if ($this->service === 'new_custom_order') {
            $this->validateCustomization();
        } else {
            $this->assertOrderVerified($verification);
            $slot = $this->selectedSlotData();
            if (! $slot) {
                throw ValidationException::withMessages(['selectedSlot' => __('That appointment slot is no longer available.')]);
            }
        }

        $booking = $action->execute([
            'booking_type' => $this->service,
            'branch_id' => $this->branchId,
            'customer_name' => trim($this->fullName),
            'customer_phone' => Phone::toE164Tz($this->phone),
            'customer_email' => filled($this->email) ? strtolower(trim($this->email)) : null,
            'customer_location' => filled($this->location) ? trim($this->location) : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'payload' => ['source_order_id' => $this->verifiedOrderId, 'source_order_number' => $this->orderNumber],
            'items' => $this->service === 'new_custom_order' ? [$this->customOrderItem()] : [],
            'uploads' => $this->uploads,
            'appointment' => $slot ? [
                'appointment_type_id' => $this->appointmentTypeId(),
                'scheduled_start_at' => $slot['start_at'],
                'scheduled_end_at' => $slot['end_at'],
                'customer_note' => filled($this->notes) ? trim($this->notes) : null,
            ] : null,
        ]);

        BookingSubmitted::dispatch($booking);
        $this->confirmationNumber = $booking->booking_number;
        $this->stage = 'complete';
    }

    public function back(): void
    {
        $this->stage = match ($this->stage) {
            'contact' => 'service',
            'contact_verification' => 'contact',
            'customize', 'order_lookup' => 'contact_verification',
            'order_verification' => 'order_lookup',
            'schedule' => 'order_verification',
            'review' => $this->service === 'new_custom_order' ? 'customize' : 'schedule',
            default => $this->stage,
        };
    }

    public function services(): array
    {
        return [
            'new_custom_order' => ['name' => __('New Custom Order'), 'description' => __('Design a garment with your preferred fabric and finishing choices.'), 'icon' => 'fa-sparkles'],
            'alteration_repair' => ['name' => __('Alteration / Repair'), 'description' => __('Book a visit for an existing order that needs adjustment or repair.'), 'icon' => 'fa-scissors'],
            'measurement_appointment' => ['name' => __('Measurement Appointment'), 'description' => __('Book measurements for an existing order.'), 'icon' => 'fa-ruler-combined'],
            'fitting_appointment' => ['name' => __('Fitting Appointment'), 'description' => __('Schedule the next fitting for an existing order.'), 'icon' => 'fa-person-dress'],
        ];
    }

    public function render()
    {
        $groups = $this->garmentCategoryId
            ? GarmentOptionGroup::query()->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])->where('garment_category_id', $this->garmentCategoryId)->where('is_active', true)->orderBy('sort_order')->get()
            : collect();

        return view('livewire.public.premium-booking-wizard', [
            'categories' => GarmentCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'fabricVariants' => FabricVariant::query()->with('fabric')->where('is_active', true)->whereHas('fabric', fn ($query) => $query->where('is_active', true)->when($this->garmentCategoryId, fn ($fabric) => $fabric->whereHas('garmentCategories', fn ($categories) => $categories->whereKey($this->garmentCategoryId))))->orderBy('sort_order')->get(),
            'groups' => $groups,
            'selectedSlotLabel' => collect($this->availableSlots)->firstWhere('start_at', $this->selectedSlot)['label'] ?? null,
        ]);
    }

    private function validateContact(): void
    {
        $this->validate(['fullName' => ['required', 'string', 'max:191'], 'phone' => ['required', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:191'], 'location' => ['nullable', 'string', 'max:191']]);
        if (! Phone::toE164Tz($this->phone)) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number.')]);
        }
    }

    private function validateCustomization(): void
    {
        $this->validate(['garmentCategoryId' => ['required', 'exists:garment_categories,id'], 'fabricVariantId' => ['required', 'exists:fabric_variants,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:100'], 'uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $required = GarmentOptionGroup::query()->where('garment_category_id', $this->garmentCategoryId)->where('is_active', true)->where('is_required', true)->pluck('id');
        if ($required->contains(fn ($id) => blank($this->selectedChoices[$id] ?? null))) {
            throw ValidationException::withMessages(['selectedChoices' => __('Complete all required customization sections.')]);
        }
    }

    private function customOrderItem(): array
    {
        $variant = FabricVariant::query()->with('fabric')->findOrFail($this->fabricVariantId);
        $choices = GarmentOption::query()->whereIn('id', array_values($this->selectedChoices))->get()->keyBy('id');

        return ['garment_category_id' => $this->garmentCategoryId, 'fabric_id' => $variant->fabric_id, 'fabric_variant_id' => $variant->id, 'quantity' => $this->quantity, 'selected_options' => collect($this->selectedChoices)->map(fn ($optionId, $groupId) => ['garment_option_group_id' => (int) $groupId, 'garment_option_id' => $choices->get($optionId)?->id])->filter(fn ($row) => $row['garment_option_id'])->values()->all()];
    }

    private function assertContactVerified(BookingVerificationService $verification): void
    {
        if (! $verification->verified($this->wizardToken, 'contact', $this->phone)) {
            throw ValidationException::withMessages(['verificationCode' => __('Verify your mobile number before continuing.')]);
        }
    }

    private function assertOrderVerified(BookingVerificationService $verification): void
    {
        $order = $this->verifiedOrder();
        if (! $verification->verified($this->wizardToken, 'order', $order->customer->phone, ['order_id' => $order->id])) {
            throw ValidationException::withMessages(['orderVerificationCode' => __('Verify ownership of this order before continuing.')]);
        }
    }

    private function verifiedOrder(): Order
    {
        return Order::withoutGlobalScopes()->with('customer:id,phone')->findOrFail($this->verifiedOrderId);
    }

    private function appointmentTypeId(): ?int
    {
        $code = match ($this->service) {
            'alteration_repair' => 'alteration_dropoff', 'measurement_appointment' => 'measurement', 'fitting_appointment' => 'fitting', default => null
        };

        return $code ? AppointmentType::query()->where('code', $code)->where('is_active', true)->value('id') : null;
    }

    private function selectedSlotData(): ?array
    {
        $this->refreshSlots();

        return collect($this->availableSlots)->first(fn ($slot) => ($slot['available'] ?? false) && $slot['start_at'] === $this->selectedSlot);
    }

    private function maskPhone(string $phone): string
    {
        $phone = Phone::toE164Tz($phone) ?? $phone;

        return substr($phone, 0, 4).'•••••'.substr($phone, -3);
    }

    private function resetWizardAfterService(): void
    {
        $this->reset(['orderNumber', 'orderVerificationCode', 'orderPhoneMasked', 'verifiedOrderId', 'garmentCategoryId', 'fabricVariantId', 'selectedChoices', 'uploads', 'selectedSlot', 'availableSlots', 'confirmationNumber']);
    }
}
