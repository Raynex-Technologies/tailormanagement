<?php

namespace App\Services\Storefront\Payments;

use App\Enums\PaymentTransactionStatus;
use App\Enums\StorefrontFulfillmentStatus;
use App\Mail\InvoiceMailable;
use App\Mail\StorefrontPaymentConfirmationMailable;
use App\Models\BusinessSetting;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\StorefrontPaymentStatusNotification;
use App\Services\Orders\OrderPaymentService;
use App\Services\Storefront\InventoryReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentTransactionService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected OrderPaymentService $orderPaymentService,
        protected InventoryReservationService $inventoryReservationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentTransaction
    {
        $attributes['status'] = PaymentTransactionStatus::Initiated;
        $attributes['initiated_at'] = now();

        return PaymentTransaction::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function initiate(PaymentTransaction $transaction, array $context): PaymentTransaction
    {
        $transaction->loadMissing('paymentMethod');

        $gatewayCode = strtolower(trim((string) ($transaction->paymentMethod?->code ?: $transaction->gateway)));
        $gateway = $this->gatewayManager->resolve($gatewayCode);

        $payload = $gateway->initiate($transaction, $context);

        $transaction->update([
            'gateway' => $gatewayCode,
            'gateway_reference' => $payload['gateway_reference'] ?? $transaction->gateway_reference,
            'checkout_url' => $payload['redirect_url'] ?? null,
            'request_payload' => $context,
            'response_payload' => $payload['raw'] ?? $payload,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return $transaction->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndApply(PaymentTransaction $transaction, array $payload = []): PaymentTransaction
    {
        $shouldNotifyCustomer = false;
        $shouldSendSuccessEmails = false;

        $verifiedTransaction = DB::transaction(function () use ($transaction, $payload, &$shouldNotifyCustomer, &$shouldSendSuccessEmails) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($locked->status?->isTerminal()) {
                return $locked;
            }

            $locked->loadMissing('paymentMethod', 'order.customer', 'actor');

            $gatewayCode = strtolower(trim((string) ($locked->paymentMethod?->code ?: $locked->gateway)));
            $gateway = $this->gatewayManager->resolve($gatewayCode);
            $verification = $gateway->verify($locked, $payload);

            $nextStatus = $verification['status'] ?? 'failed';
            $updateData = [
                'gateway_reference' => $verification['gateway_reference'] ?? $locked->gateway_reference,
                'response_payload' => $verification['raw'] ?? $verification,
            ];

            if ($nextStatus === 'verified') {
                $updateData['status'] = PaymentTransactionStatus::Verified;
                $updateData['verified_at'] = now();
            } elseif ($nextStatus === 'pending') {
                $updateData['status'] = PaymentTransactionStatus::Pending;
            } else {
                $updateData['status'] = PaymentTransactionStatus::Failed;
                $updateData['failed_at'] = now();
                $updateData['failure_reason'] = 'Gateway verification returned non-success status.';
            }

            $locked->update($updateData);
            $locked = $locked->fresh(['order.customer', 'paymentMethod', 'actor']);

            if ($locked->status === PaymentTransactionStatus::Verified) {
                $this->recordOrderPayment($locked);
            }

            if ($locked->status === PaymentTransactionStatus::Failed && $locked->order?->isStorefrontOrder()) {
                $this->inventoryReservationService->release($locked->order);

                $locked->order->update([
                    'fulfillment_status' => StorefrontFulfillmentStatus::FailedPayment,
                ]);

                $locked->order->statusHistory()->create([
                    'status' => StorefrontFulfillmentStatus::FailedPayment->value,
                    'title' => 'Payment Failed',
                    'note' => 'Customer payment attempt failed.',
                    'is_customer_visible' => true,
                ]);
            }

            if (in_array($locked->status, [PaymentTransactionStatus::Verified, PaymentTransactionStatus::Failed], true)) {
                $shouldNotifyCustomer = true;
            }

            if ($locked->status === PaymentTransactionStatus::Verified) {
                $shouldSendSuccessEmails = true;
            }

            return $locked;
        });

        if ($shouldNotifyCustomer) {
            $this->notifyCustomerOfPaymentUpdate($verifiedTransaction);
        }

        if ($shouldSendSuccessEmails) {
            $this->sendSuccessfulPaymentEmails($verifiedTransaction);
        }

        return $verifiedTransaction;
    }

    protected function recordOrderPayment(PaymentTransaction $transaction): void
    {
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $alreadyRecorded = OrderPayment::query()
            ->where('payment_transaction_id', $transaction->id)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $actor = $transaction->actor ?: $this->fallbackActor($order);

        $payment = $this->orderPaymentService->recordPayment($order, [
            'amount' => (float) $transaction->amount,
            'payment_method_id' => $transaction->payment_method_id,
            'reference' => $transaction->merchant_reference,
            'paid_at' => $transaction->verified_at ?: now(),
            'note' => 'Online payment captured via '.strtoupper($transaction->gateway),
        ], $actor);

        $payment->update([
            'payment_transaction_id' => $transaction->id,
            'gateway' => $transaction->gateway,
            'gateway_reference' => $transaction->gateway_reference,
            'status' => 'captured',
            'raw_payload' => $transaction->response_payload,
        ]);

        if ($order->isStorefrontOrder()) {
            $this->inventoryReservationService->commit($order, $actor);

            $order->update([
                'fulfillment_status' => StorefrontFulfillmentStatus::Paid,
                'paid_at' => now(),
            ]);

            $order->statusHistory()->create([
                'status' => StorefrontFulfillmentStatus::Paid->value,
                'title' => 'Payment Confirmed',
                'note' => 'Payment was verified by the gateway.',
                'is_customer_visible' => true,
                'changed_by' => $actor->id,
            ]);
        }
    }

    protected function fallbackActor(Order $order): User
    {
        if ($order->creator) {
            return $order->creator;
        }

        $fallback = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->first();

        if ($fallback) {
            return $fallback;
        }

        $any = User::query()->first();

        if ($any) {
            return $any;
        }

        throw new RuntimeException('No system actor is available to record payment.');
    }

    public function createForOrder(Order $order, PaymentMethod $method, float $amount, string $purpose, ?User $actor = null): PaymentTransaction
    {
        return $this->create([
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'payment_method_id' => $method->id,
            'gateway' => strtolower(trim((string) ($method->code ?: 'manual'))),
            'merchant_reference' => 'TXN-'.now()->format('YmdHis').'-'.$order->id.'-'.Str::lower(Str::random(6)),
            'amount' => $amount,
            'currency' => $order->currency ?: 'TZS',
            'purpose' => $purpose,
            'created_by' => $actor?->id,
        ]);
    }

    protected function notifyCustomerOfPaymentUpdate(PaymentTransaction $transaction): void
    {
        if (! in_array($transaction->status, [PaymentTransactionStatus::Verified, PaymentTransactionStatus::Failed], true)) {
            return;
        }

        $order = $transaction->order;
        if (! $order) {
            return;
        }

        $order->loadMissing('customer.user');
        $customerUser = $order->customer?->user;

        if (! $customerUser) {
            return;
        }

        try {
            $customerUser->notify(new StorefrontPaymentStatusNotification(
                $order,
                $transaction,
                $transaction->status === PaymentTransactionStatus::Verified
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function sendSuccessfulPaymentEmails(PaymentTransaction $transaction): void
    {
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $order->loadMissing('customer.user', 'invoice.lines', 'invoice.order.customer', 'invoice.branch');

        $recipientEmail = $this->resolveRecipientEmail($order);
        if (blank($recipientEmail)) {
            return;
        }

        $settings = BusinessSetting::instance();

        $this->sendPaymentConfirmationEmail($order, $transaction, $settings, $recipientEmail);
        $this->sendInvoiceEmail($order, $transaction, $settings, $recipientEmail);
    }

    protected function sendPaymentConfirmationEmail(
        Order $order,
        PaymentTransaction $transaction,
        BusinessSetting $settings,
        string $recipientEmail
    ): void {
        $currency = strtoupper((string) ($transaction->currency ?: $order->currency ?: 'TZS'));
        $orderUrl = $order->customer?->user
            ? ($order->order_type === 'tailoring'
                ? route('storefront.account.custom-orders.show', $order)
                : route('storefront.account.orders.show', $order))
            : route('storefront.home');

        $variables = [
            'business_name' => $settings->business_name ?: config('app.name', 'Tailoring Business'),
            'customer_name' => $order->customer?->name ?: 'Customer',
            'order_number' => $order->order_no,
            'currency' => $currency,
            'amount_paid' => number_format((float) $transaction->amount, 2, '.', ''),
            'order_total' => number_format((float) $order->payableTotal(), 2, '.', ''),
            'payment_method' => $transaction->paymentMethod?->name ?: strtoupper((string) $transaction->gateway),
            'payment_reference' => $transaction->merchant_reference ?: $transaction->gateway_reference ?: 'N/A',
            'order_url' => $orderUrl,
        ];

        $template = $this->resolveEmailTemplate('storefront_payment_confirmation');
        $subject = EmailTemplate::render((string) ($template['subject'] ?? ''), $variables);
        $body = EmailTemplate::render((string) ($template['body'] ?? ''), $variables);

        try {
            Mail::to($recipientEmail)->send(
                new StorefrontPaymentConfirmationMailable(
                    $order,
                    $transaction->loadMissing('paymentMethod'),
                    $settings,
                    $subject,
                    $body
                )
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function sendInvoiceEmail(
        Order $order,
        PaymentTransaction $transaction,
        BusinessSetting $settings,
        string $recipientEmail
    ): void {
        try {
            $invoice = $order->invoice;

            if (! $invoice) {
                $invoice = Invoice::syncFromOrder($order, $transaction->actor?->id);
            }

            $invoice->loadMissing('order.customer', 'branch', 'lines');

            Mail::to($recipientEmail)->send(new InvoiceMailable($invoice, $settings));

            $invoice->update([
                'sent_at' => now(),
                'sent_to_email' => $recipientEmail,
                'updated_by' => $transaction->actor?->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function resolveRecipientEmail(Order $order): ?string
    {
        $email = $order->checkout_email
            ?: $order->customer?->email
            ?: $order->customer?->user?->email;

        return filled($email) ? trim((string) $email) : null;
    }

    /**
     * @return array{subject:string, body:string}
     */
    protected function resolveEmailTemplate(string $category): array
    {
        $defaults = EmailTemplate::defaultTemplates()[$category] ?? [
            'subject' => '',
            'body' => '',
        ];

        try {
            if (Schema::hasTable('email_templates')) {
                $template = EmailTemplate::instance()->template($category);

                return [
                    'subject' => (string) ($template['subject'] ?? $defaults['subject']),
                    'body' => (string) ($template['body'] ?? $defaults['body']),
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            'subject' => (string) $defaults['subject'],
            'body' => (string) $defaults['body'],
        ];
    }
}
