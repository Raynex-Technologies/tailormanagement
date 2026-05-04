<?php

namespace App\Actions\Bookings;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\OnlineBooking;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateOnlineBookingAction
{
    public function __construct(
        protected GenerateBookingNumberAction $numbers,
        protected CreateAppointmentAction $appointments,
        protected ImageUploadService $images,
    ) {}

    public function execute(array $data): OnlineBooking
    {
        return DB::transaction(function () use ($data): OnlineBooking {
            $booking = OnlineBooking::query()->create([
                'booking_number' => $this->numbers->execute(),
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'booking_type' => $data['booking_type'],
                'status' => $data['status'] ?? 'pending_review',
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_whatsapp' => $data['customer_whatsapp'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_location' => $data['customer_location'] ?? null,
                'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                'preferred_language' => $data['preferred_language'] ?? null,
                'notes' => $data['notes'] ?? null,
                'needed_by_date' => $data['needed_by_date'] ?? null,
                'event_date' => $data['event_date'] ?? null,
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'measurement_option' => $data['measurement_option'] ?? null,
                'measurement_profile_id' => $data['measurement_profile_id'] ?? null,
                'payload' => $data['payload'] ?? [],
            ]);

            $firstItem = null;
            foreach (($data['items'] ?? []) as $itemData) {
                $item = $booking->items()->create($itemData);
                $firstItem ??= $item;

                foreach (($itemData['selected_options'] ?? []) as $selectedOption) {
                    $item->selectedOptions()->create($selectedOption);
                }
            }

            foreach (($data['uploads'] ?? []) as $upload) {
                if (! $upload instanceof UploadedFile) {
                    continue;
                }

                $stored = $this->images->storePublic($upload, 'online-bookings');
                $booking->images()->create([
                    'online_booking_item_id' => $firstItem?->id,
                    'uploaded_by_type' => 'customer',
                    'path' => $stored->path,
                    'disk' => $stored->disk,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                ]);
            }

            if (isset($data['appointment'])) {
                $appointment = $this->appointments->execute(array_merge($data['appointment'], [
                    'online_booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'branch_id' => $booking->branch_id,
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'customer_email' => $booking->customer_email,
                    'requested_by_customer' => true,
                ]));

                $booking->forceFill([
                    'status' => $appointment->status === 'confirmed' ? 'confirmed' : 'pending_review',
                ])->save();
            }

            return $booking->fresh(['items.selectedOptions.option', 'appointment']);
        });
    }
}
