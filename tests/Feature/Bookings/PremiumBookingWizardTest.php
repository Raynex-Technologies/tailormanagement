<?php

namespace Tests\Feature\Bookings;

use App\Livewire\Public\PremiumBookingWizard;
use Livewire\Livewire;
use Tests\TestCase;

class PremiumBookingWizardTest extends TestCase
{
    public function test_public_booking_uses_the_four_service_premium_wizard(): void
    {
        $this->get(route('booking.public'))
            ->assertOk()
            ->assertSee('Book your tailoring experience')
            ->assertSee('New Custom Order')
            ->assertSee('Alteration / Repair')
            ->assertSee('Measurement Appointment')
            ->assertSee('Fitting Appointment')
            ->assertDontSee('Style Consultation')
            ->assertDontSee('Bulk / Uniform Order');
    }

    public function test_contact_screen_uses_one_required_mobile_number_without_old_preferences(): void
    {
        Livewire::test(PremiumBookingWizard::class)
            ->call('chooseService', 'new_custom_order')
            ->assertSet('stage', 'contact')
            ->assertSee('Full name')
            ->assertSee('Mobile number')
            ->assertDontSee('WhatsApp number')
            ->assertDontSee('Preferred contact')
            ->assertDontSee('Preferred language');
    }

    public function test_appointment_services_proceed_to_order_lookup_only_after_contact_verification(): void
    {
        Livewire::test(PremiumBookingWizard::class)
            ->call('chooseService', 'fitting_appointment')
            ->assertSet('stage', 'contact')
            ->set('stage', 'order_lookup')
            ->assertSee('Enter your order number')
            ->assertSee('mobile number recorded on that order');
    }
}
