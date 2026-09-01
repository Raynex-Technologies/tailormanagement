<?php

namespace App\Services\Mail;

use App\Mail\InvoiceMailable;
use App\Mail\OrderCreatedCustomerMailable;
use App\Mail\PaymentReceivedCustomerMailable;
use App\Models\BusinessSetting;
use App\Models\CustomerEmailDelivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerEmailDeliveryService
{
    public function __construct(
        protected MailFailureSanitizer $failureSanitizer,
    ) {}

    public function sendOrderCreated(Order $eventOrder): ?CustomerEmailDelivery
    {
        $settings = BusinessSetting::instance();
        $key = CustomerEmailDelivery::TYPE_ORDER_CREATED.':'.$eventOrder->id;

        if (! $settings->email_sending_enabled || ! $settings->email_order_created_enabled) {
            return $this->skip($key, CustomerEmailDelivery::TYPE_ORDER_CREATED, $eventOrder, null, null, 'Automatic order email is disabled.');
        }

        $order = $this->loadOrder($eventOrder);
        $recipient = $this->recipientFor($order);
        if (! $recipient) {
            return $this->skip($key, CustomerEmailDelivery::TYPE_ORDER_CREATED, $order, null, null, 'No valid customer email address.');
        }

        $this->loadEmailRelations($order);
        $invoice = $this->invoiceFor($order);

        return $this->sendAutomatic(
            key: $key,
            type: CustomerEmailDelivery::TYPE_ORDER_CREATED,
            order: $order,
            invoice: $invoice,
            payment: null,
            recipient: $recipient,
            mailable: new OrderCreatedCustomerMailable($order, $invoice, $settings),
        );
    }

    public function sendPaymentReceived(Order $eventOrder, OrderPayment $eventPayment): ?CustomerEmailDelivery
    {
        $settings = BusinessSetting::instance();
        $key = CustomerEmailDelivery::TYPE_PAYMENT_RECEIVED.':'.$eventPayment->id;

        if (! $settings->email_sending_enabled || ! $settings->email_payment_received_enabled) {
            return $this->skip($key, CustomerEmailDelivery::TYPE_PAYMENT_RECEIVED, $eventOrder, $eventPayment, null, 'Automatic payment email is disabled.');
        }

        $order = $this->loadOrder($eventOrder);
        $recipient = $this->recipientFor($order);
        if (! $recipient) {
            return $this->skip($key, CustomerEmailDelivery::TYPE_PAYMENT_RECEIVED, $order, $eventPayment, null, 'No valid customer email address.');
        }

        $this->loadEmailRelations($order);
        $payment = OrderPayment::query()
            ->withoutGlobalScope(BranchScope::class)
            ->with('paymentMethod')
            ->findOrFail($eventPayment->id);
        $invoice = $this->invoiceFor($order);

        return $this->sendAutomatic(
            key: $key,
            type: CustomerEmailDelivery::TYPE_PAYMENT_RECEIVED,
            order: $order,
            invoice: $invoice,
            payment: $payment,
            recipient: $recipient,
            mailable: new PaymentReceivedCustomerMailable($order, $invoice, $payment, $settings),
        );
    }

    public function sendInvoiceManually(Invoice $invoice, string $recipient, User $actor): CustomerEmailDelivery
    {
        $settings = BusinessSetting::instance();
        if (! $settings->email_sending_enabled) {
            throw new RuntimeException(__('Customer email sending is disabled in Email Setup.'));
        }

        $recipient = trim($recipient);
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(__('Enter a valid recipient email address.'));
        }

        $invoice = app(\App\Support\CanonicalInvoicePdf::class)->invoice($invoice);
        $order = $invoice->order;
        if (! $order || (int) $invoice->branch_id !== (int) $order->branch_id) {
            throw new RuntimeException(__('The invoice is not linked to a valid order in this branch.'));
        }

        $delivery = CustomerEmailDelivery::create([
            'branch_id' => $invoice->branch_id,
            'order_id' => $invoice->order_id,
            'invoice_id' => $invoice->id,
            'type' => CustomerEmailDelivery::TYPE_INVOICE_MANUAL,
            'delivery_key' => CustomerEmailDelivery::TYPE_INVOICE_MANUAL.':'.$invoice->id.':'.Str::uuid(),
            'recipient' => $recipient,
            'status' => CustomerEmailDelivery::STATUS_SENDING,
            'attempts' => 1,
        ]);

        try {
            Mail::to($recipient)->send(new InvoiceMailable($invoice, $settings));

            $delivery->update([
                'status' => CustomerEmailDelivery::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
            $invoice->update([
                'sent_at' => now(),
                'sent_to_email' => $recipient,
                'updated_by' => $actor->id,
            ]);

            return $delivery->refresh();
        } catch (Throwable $exception) {
            $reason = $this->recordFailure($delivery, $exception);

            throw new RuntimeException($reason, previous: $exception);
        }
    }

    protected function sendAutomatic(
        string $key,
        string $type,
        Order $order,
        Invoice $invoice,
        ?OrderPayment $payment,
        string $recipient,
        Mailable $mailable,
    ): CustomerEmailDelivery {
        $delivery = CustomerEmailDelivery::firstOrCreate(
            ['delivery_key' => $key],
            [
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'order_payment_id' => $payment?->id,
                'type' => $type,
                'recipient' => $recipient,
                'status' => CustomerEmailDelivery::STATUS_PENDING,
            ],
        );

        if (! $delivery->wasRecentlyCreated) {
            if (in_array($delivery->status, [
                CustomerEmailDelivery::STATUS_SENT,
                CustomerEmailDelivery::STATUS_SKIPPED,
            ], true)) {
                return $delivery;
            }

            if (
                in_array($delivery->status, [
                    CustomerEmailDelivery::STATUS_PENDING,
                    CustomerEmailDelivery::STATUS_SENDING,
                ], true)
                && $delivery->updated_at?->isAfter(now()->subMinutes(15))
            ) {
                return $delivery;
            }
        }

        $delivery->update([
            'invoice_id' => $invoice->id,
            'recipient' => $recipient,
            'status' => CustomerEmailDelivery::STATUS_SENDING,
            'attempts' => $delivery->attempts + 1,
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        try {
            Mail::to($recipient)->send($mailable);
            $sentAt = now();
            $delivery->update([
                'status' => CustomerEmailDelivery::STATUS_SENT,
                'sent_at' => $sentAt,
            ]);
            $invoice->update([
                'sent_at' => $sentAt,
                'sent_to_email' => $recipient,
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure($delivery, $exception);
        }

        return $delivery->refresh();
    }

    protected function skip(
        string $key,
        string $type,
        Order $order,
        ?OrderPayment $payment,
        ?string $recipient,
        string $reason,
    ): CustomerEmailDelivery {
        return CustomerEmailDelivery::firstOrCreate(
            ['delivery_key' => $key],
            [
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'order_payment_id' => $payment?->id,
                'type' => $type,
                'recipient' => $recipient,
                'status' => CustomerEmailDelivery::STATUS_SKIPPED,
                'failure_reason' => $reason,
            ],
        );
    }

    protected function loadOrder(Order $order): Order
    {
        return Order::query()
            ->withoutGlobalScope(BranchScope::class)
            ->with('customer.user')
            ->findOrFail($order->id);
    }

    protected function loadEmailRelations(Order $order): void
    {
        $order->loadMissing(['branch', 'lines', 'packageInstances']);
        $order->loadSum('payments', 'amount');
    }

    protected function invoiceFor(Order $order): Invoice
    {
        $invoice = Invoice::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('order_id', $order->id)
            ->first();

        return $invoice ?: Invoice::syncFromOrder($order->fresh(['lines']));
    }

    protected function recipientFor(Order $order): ?string
    {
        $recipient = trim((string) (
            $order->checkout_email
            ?: $order->customer?->email
            ?: $order->customer?->user?->email
        ));

        return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
    }

    protected function recordFailure(CustomerEmailDelivery $delivery, Throwable $exception): string
    {
        $reason = $this->failureSanitizer->message($exception);
        $delivery->update([
            'status' => CustomerEmailDelivery::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        Log::warning('Customer email delivery failed.', [
            'delivery_id' => $delivery->id,
            'type' => $delivery->type,
            'exception' => $exception::class,
            'reason' => $reason,
        ]);

        return $reason;
    }
}
