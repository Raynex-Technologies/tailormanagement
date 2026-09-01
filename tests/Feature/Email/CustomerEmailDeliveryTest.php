<?php

namespace Tests\Feature\Email;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Jobs\SendOrderCreatedCustomerEmail;
use App\Listeners\SendOrderCreatedEmail;
use App\Listeners\SendOrderPaymentEmail;
use App\Livewire\Administration\EmailSetup;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Roles\Form as RoleForm;
use App\Mail\InvoiceMailable;
use App\Mail\OrderCreatedCustomerMailable;
use App\Mail\PaymentReceivedCustomerMailable;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\CustomerEmailDelivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Services\Mail\CustomerEmailDeliveryService;
use App\Services\Mail\MailConfiguration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CustomerEmailDeliveryTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'Cash'],
        );
    }

    public function test_delivery_controls_default_off_and_can_be_saved_without_replacing_blank_passwords(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $settings = BusinessSetting::instance();
        $settings->update([
            'mail_mailer' => 'array',
            'mail_password' => 'existing-secret',
        ]);

        Livewire::test(EmailSetup::class)
            ->assertSet('email_sending_enabled', false)
            ->assertSet('email_order_created_enabled', false)
            ->assertSet('email_payment_received_enabled', false)
            ->set('email_sending_enabled', true)
            ->set('email_order_created_enabled', true)
            ->set('email_payment_received_enabled', false)
            ->set('mail_password', '')
            ->call('saveSmtpSettings')
            ->assertHasNoErrors();

        $settings->refresh();
        $this->assertTrue($settings->email_sending_enabled);
        $this->assertTrue($settings->email_order_created_enabled);
        $this->assertFalse($settings->email_payment_received_enabled);
        $this->assertSame('existing-secret', $settings->mail_password);
        $this->assertNotSame('existing-secret', DB::table('business_settings')->where('id', 1)->value('mail_password'));
    }

    public function test_master_switch_blocks_every_automatic_customer_email(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order, $invoice] = $this->createOrderAndInvoice('master-off@example.test');
        $payment = $this->createPayment($order, 20000);
        BusinessSetting::instance()->update([
            'email_sending_enabled' => false,
            'email_order_created_enabled' => true,
            'email_payment_received_enabled' => true,
        ]);
        Mail::fake();

        $service = app(CustomerEmailDeliveryService::class);
        $service->sendOrderCreated($order);
        $service->sendPaymentReceived($order, $payment);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('customer_email_deliveries', [
            'delivery_key' => 'order_created:'.$order->id,
            'status' => CustomerEmailDelivery::STATUS_SKIPPED,
        ]);
        $this->assertDatabaseHas('customer_email_deliveries', [
            'delivery_key' => 'payment_received:'.$payment->id,
            'status' => CustomerEmailDelivery::STATUS_SKIPPED,
        ]);
        $this->assertNull($invoice->fresh()->sent_at);
    }

    public function test_disabled_delivery_path_avoids_invoice_line_and_payment_aggregate_queries(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order] = $this->createOrderAndInvoice('no-work@example.test');
        BusinessSetting::instance()->update([
            'email_sending_enabled' => false,
            'email_order_created_enabled' => true,
        ]);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(CustomerEmailDeliveryService::class)->sendOrderCreated($order->withoutRelations());

        $this->assertFalse(collect($queries)->contains(
            fn (string $query): bool => str_contains($query, 'from "invoices"')
                || str_contains($query, 'from "order_lines"')
                || (str_contains($query, 'order_payments') && str_contains($query, 'sum(')),
        ));
    }

    public function test_order_email_uses_independent_toggle_and_attaches_the_canonical_pdf(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order, $invoice] = $this->createOrderAndInvoice('order@example.test');
        BusinessSetting::instance()->update([
            'email_sending_enabled' => true,
            'email_order_created_enabled' => true,
            'email_payment_received_enabled' => false,
        ]);
        Mail::fake();

        app(CustomerEmailDeliveryService::class)->sendOrderCreated($order);

        Mail::assertSent(OrderCreatedCustomerMailable::class, function (OrderCreatedCustomerMailable $mail): bool {
            return $mail->hasTo('order@example.test')
                && $mail->order->branch_id === $this->branch->id
                && $mail->financialSummary['total'] === 100000.0;
        });
        $this->assertDatabaseHas('customer_email_deliveries', [
            'delivery_key' => 'order_created:'.$order->id,
            'invoice_id' => $invoice->id,
            'recipient' => 'order@example.test',
            'status' => CustomerEmailDelivery::STATUS_SENT,
        ]);

        $mailable = new OrderCreatedCustomerMailable(
            $order->fresh(['customer', 'branch', 'lines']),
            $invoice,
            BusinessSetting::instance(),
        );
        $attachment = $mailable->attachments()[0];
        $pdf = $attachment->attachWith(fn () => null, fn ($data) => $data());

        $this->assertSame($invoice->invoice_no.'.pdf', $attachment->as);
        $this->assertSame('application/pdf', $attachment->mime);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_payment_email_contains_post_payment_cumulative_state_and_updated_pdf(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order, $invoice] = $this->createOrderAndInvoice('payment@example.test');
        $this->createPayment($order, 25000);
        $payment = $this->createPayment($order, 15000);
        BusinessSetting::instance()->update([
            'email_sending_enabled' => true,
            'email_order_created_enabled' => false,
            'email_payment_received_enabled' => true,
        ]);
        Mail::fake();

        app(CustomerEmailDeliveryService::class)->sendPaymentReceived($order, $payment);

        Mail::assertSent(PaymentReceivedCustomerMailable::class, function (PaymentReceivedCustomerMailable $mail) use ($payment): bool {
            return $mail->hasTo('payment@example.test')
                && $mail->payment->is($payment)
                && $mail->financialSummary['paid'] === 40000.0
                && $mail->financialSummary['balance'] === 60000.0;
        });
        $this->assertSame('payment@example.test', $invoice->fresh()->sent_to_email);
    }

    public function test_missing_or_invalid_customer_email_is_skipped_safely(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order] = $this->createOrderAndInvoice('not-an-email');
        BusinessSetting::instance()->update([
            'email_sending_enabled' => true,
            'email_order_created_enabled' => true,
        ]);
        Mail::fake();

        app(CustomerEmailDeliveryService::class)->sendOrderCreated($order);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('customer_email_deliveries', [
            'delivery_key' => 'order_created:'.$order->id,
            'recipient' => null,
            'status' => CustomerEmailDelivery::STATUS_SKIPPED,
        ]);
    }

    public function test_automatic_delivery_identity_prevents_duplicate_sends(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order] = $this->createOrderAndInvoice('once@example.test');
        BusinessSetting::instance()->update([
            'email_sending_enabled' => true,
            'email_order_created_enabled' => true,
        ]);
        Mail::fake();

        $service = app(CustomerEmailDeliveryService::class);
        $service->sendOrderCreated($order);
        $service->sendOrderCreated($order);

        Mail::assertSentCount(1);
        $this->assertSame(1, CustomerEmailDelivery::where('delivery_key', 'order_created:'.$order->id)->count());
    }

    public function test_failed_automatic_email_is_observable_and_does_not_remove_business_records(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$order] = $this->createOrderAndInvoice('failure@example.test');
        $payment = $this->createPayment($order, 30000);
        BusinessSetting::instance()->update([
            'email_sending_enabled' => true,
            'email_payment_received_enabled' => true,
        ]);
        Mail::shouldReceive('to')
            ->once()
            ->with('failure@example.test')
            ->andThrow(new RuntimeException('smtp password=do-not-expose'));

        app(CustomerEmailDeliveryService::class)->sendPaymentReceived($order, $payment);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id]);
        $delivery = CustomerEmailDelivery::where('delivery_key', 'payment_received:'.$payment->id)->firstOrFail();
        $this->assertSame(CustomerEmailDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        $this->assertStringNotContainsString('do-not-expose', (string) $delivery->failure_reason);
    }

    public function test_email_listeners_are_queued_only_after_commit(): void
    {
        $this->assertTrue(is_subclass_of(SendOrderCreatedEmail::class, ShouldQueueAfterCommit::class));
        $this->assertTrue(is_subclass_of(SendOrderPaymentEmail::class, ShouldQueueAfterCommit::class));
        $this->assertTrue(is_subclass_of(SendOrderCreatedCustomerEmail::class, ShouldQueue::class));
    }

    public function test_invoice_send_permission_is_least_privilege_and_available_to_role_management(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        [, $invoice] = $this->createOrderAndInvoice('permission@example.test');

        $this->assertFalse($manager->can('invoices.send_email'));
        $this->assertFalse(Gate::forUser($manager)->allows('send', $invoice));
        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->assertDontSee('Send via Email');

        $this->actingAsRole('admin', $this->branch);
        Livewire::test(RoleForm::class)
            ->assertSee('Send Invoices by Email')
            ->assertDontSee('invoices.send_email');

        $superadmin = $this->actingAsRole('superadmin', $this->branch);
        $this->assertTrue($superadmin->can('invoices.send_email'));
    }

    public function test_manual_send_requires_confirmation_and_records_a_deliberate_delivery(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->givePermissionTo('invoices.send_email');
        [, $invoice] = $this->createOrderAndInvoice('manual@example.test');
        BusinessSetting::instance()->update(['email_sending_enabled' => true]);
        Mail::fake();

        $component = Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openSendInvoiceModal')
            ->assertSet('showSendInvoiceModal', true)
            ->assertSee('Confirm Invoice Email')
            ->assertSee($invoice->invoice_no.'.pdf');

        Mail::assertNothingSent();

        $component->call('sendByEmail')
            ->assertHasNoErrors()
            ->assertSet('showSendInvoiceModal', false);

        Mail::assertSent(InvoiceMailable::class, fn (InvoiceMailable $mail): bool => $mail->hasTo('manual@example.test'));
        $this->assertDatabaseHas('customer_email_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => CustomerEmailDelivery::TYPE_INVOICE_MANUAL,
            'recipient' => 'manual@example.test',
            'status' => CustomerEmailDelivery::STATUS_SENT,
        ]);
    }

    public function test_manual_send_is_blocked_server_side_when_master_switch_is_off(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->givePermissionTo('invoices.send_email');
        [, $invoice] = $this->createOrderAndInvoice('blocked@example.test');
        BusinessSetting::instance()->update(['email_sending_enabled' => false]);
        Mail::fake();

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openSendInvoiceModal')
            ->assertSee('Customer email sending is disabled')
            ->call('sendByEmail')
            ->assertHasErrors('emailTo');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('customer_email_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => CustomerEmailDelivery::TYPE_INVOICE_MANUAL,
        ]);
    }

    public function test_test_email_remains_available_with_master_switch_off_and_defaults_to_admin_email(): void
    {
        $admin = $this->actingAsRole('admin', $this->branch);
        BusinessSetting::instance()->update([
            'mail_mailer' => 'array',
            'email_sending_enabled' => false,
        ]);
        Mail::shouldReceive('purge')->once();
        Mail::shouldReceive('raw')->once();

        Livewire::test(EmailSetup::class)
            ->assertSet('test_email_to', $admin->email)
            ->call('sendTestEmail')
            ->assertHasNoErrors()
            ->assertSee('Test email sent');
    }

    public function test_local_tls_uses_the_real_ca_bundle_while_production_uses_system_trust(): void
    {
        $configuration = app(MailConfiguration::class);

        $this->assertSame(storage_path('ssl/cacert.pem'), $configuration->localCertificateAuthority('testing'));
        $this->assertFileExists($configuration->localCertificateAuthority('local'));
        $this->assertNull($configuration->localCertificateAuthority('production'));
    }

    public function test_blank_database_smtp_credentials_preserve_environment_transport_fallbacks(): void
    {
        $settings = BusinessSetting::instance();
        $settings->update([
            'mail_host' => null,
            'mail_username' => null,
            'mail_password' => null,
            'mail_encryption' => null,
        ]);
        config()->set('mail.mailers.smtp.host', 'smtp.environment.test');
        config()->set('mail.mailers.smtp.username', 'environment-user');
        config()->set('mail.mailers.smtp.password', 'environment-secret');
        config()->set('mail.mailers.smtp.scheme', 'smtp');

        app(MailConfiguration::class)->apply($settings->refresh());

        $this->assertSame('smtp.environment.test', config('mail.mailers.smtp.host'));
        $this->assertSame('environment-user', config('mail.mailers.smtp.username'));
        $this->assertSame('environment-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    /**
     * @return array{0: Order, 1: Invoice}
     */
    protected function createOrderAndInvoice(string $email): array
    {
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Email Delivery Customer',
            'email' => $email,
        ]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'priority' => Priority::Normal,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);
        $order->lines()->create([
            'item_name' => 'Tailored suit',
            'qty' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);
        $order->recalculateTotals();

        $invoice = Invoice::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->firstOrFail();

        return [$order->fresh(['customer', 'branch', 'lines']), $invoice];
    }

    protected function createPayment(Order $order, float $amount): OrderPayment
    {
        return OrderPayment::create([
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method_id' => $this->paymentMethod->id,
            'paid_at' => now(),
            'received_by' => auth()->id(),
        ]);
    }
}
