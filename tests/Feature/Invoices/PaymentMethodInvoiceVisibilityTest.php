<?php

namespace Tests\Feature\Invoices;

use App\Livewire\Administration\BusinessSettings;
use App\Models\PaymentMethod;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodInvoiceVisibilityTest extends TestCase
{
    public function test_invoice_visibility_is_opt_in_and_can_be_edited_and_reset(): void
    {
        $this->actingAsRole('superadmin', $this->branch);
        $component = Livewire::test(BusinessSettings::class)
            ->call('openCreatePaymentMethodModal')
            ->assertSet('paymentMethodShowOnInvoice', false)
            ->set('paymentMethodName', 'Invoice Bank')
            ->set('paymentMethodCode', 'invoice-bank')
            ->set('paymentMethodShowOnInvoice', true)
            ->call('savePaymentMethod')
            ->assertHasNoErrors()
            ->assertSet('paymentMethodShowOnInvoice', false);

        $method = PaymentMethod::where('code', 'invoice-bank')->firstOrFail();
        $this->assertTrue($method->show_on_invoice);
        $component->call('editPaymentMethod', $method->id)
            ->assertSet('paymentMethodShowOnInvoice', true)
            ->set('paymentMethodShowOnInvoice', false)
            ->call('savePaymentMethod')
            ->assertHasNoErrors();
        $this->assertFalse($method->fresh()->show_on_invoice);
        $this->assertFalse(PaymentMethod::forInvoiceDocument()->contains('id', $method->id));
    }

    public function test_all_selected_methods_are_returned_and_unselected_methods_are_excluded(): void
    {
        $hidden = PaymentMethod::create(['name' => 'Hidden Bank', 'code' => 'hidden-bank']);
        $ids = [];
        foreach (range(1, 4) as $index) {
            $ids[] = PaymentMethod::create([
                'name' => "Selected Bank {$index}",
                'code' => "selected-bank-{$index}",
                'show_on_invoice' => true,
            ])->id;
        }
        $this->assertFalse($hidden->fresh()->show_on_invoice);
        $this->assertSame($ids, PaymentMethod::forInvoiceDocument()->pluck('id')->all());
    }

    public function test_unauthorized_user_cannot_change_invoice_visibility(): void
    {
        $this->actingAsRole('tailor', $this->branch);
        Livewire::test(BusinessSettings::class)->assertForbidden();
    }
}
