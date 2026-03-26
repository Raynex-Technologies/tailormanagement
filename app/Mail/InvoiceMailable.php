<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Support\InvoiceTemplateResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InvoiceMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public BusinessSetting $settings
    ) {}

    public function build(): self
    {
        [$subject, $body] = $this->resolveSubjectAndBody();

        $mail = $this->subject($subject)
            ->view('emails.invoices.invoice', [
                'invoice' => $this->invoice,
                'settings' => $this->settings,
                'emailBody' => $body,
            ]);

        if ($this->settings->email_from_address) {
            $mail->from(
                $this->settings->email_from_address,
                $this->settings->email_from_name ?: $this->settings->business_name ?: config('mail.from.name')
            );
        }

        if ($this->settings->email_reply_to) {
            $mail->replyTo($this->settings->email_reply_to);
        }

        return $mail;
    }

    /**
     * @return array{0:string, 1:string}
     */
    protected function resolveSubjectAndBody(): array
    {
        $this->invoice->loadMissing('order.customer');
        $order = $this->invoice->order;

        $currency = strtoupper((string) ($order?->currency ?: 'TZS'));
        $businessName = $this->settings->business_name ?: config('app.name', 'Tailoring Business');
        $variables = [
            'business_name' => $businessName,
            'customer_name' => $order?->customer?->name ?: 'Customer',
            'order_number' => $order?->order_no ?: 'N/A',
            'invoice_number' => $this->invoice->invoice_no,
            'currency' => $currency,
            'order_total' => number_format((float) $this->invoice->total, 2, '.', ''),
            'paid_amount' => number_format((float) ($order?->paid_amount ?? 0), 2, '.', ''),
            'amount_due' => number_format((float) ($order?->balance_due ?? 0), 2, '.', ''),
        ];

        $defaultTemplate = EmailTemplate::defaultTemplates()['invoice'];
        $subjectTemplate = (string) $defaultTemplate['subject'];
        $bodyTemplate = (string) $defaultTemplate['body'];

        try {
            if (Schema::hasTable('email_templates')) {
                $template = EmailTemplate::instance()->template('invoice');
                $subjectTemplate = (string) ($template['subject'] ?? $subjectTemplate);
                $bodyTemplate = (string) ($template['body'] ?? $bodyTemplate);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            EmailTemplate::render($subjectTemplate, $variables),
            EmailTemplate::render($bodyTemplate, $variables),
        ];
    }

    public function attachments(): array
    {
        $template = app(InvoiceTemplateResolver::class)->resolve($this->settings);

        $html = view('invoices.print', [
            'invoice' => $this->invoice,
            'settings' => $this->settings,
            'paymentMethods' => PaymentMethod::forInvoiceDocument(),
            'template' => $template,
            'emailMode' => true,
        ])->render();

        return [
            Attachment::fromData(fn () => $html, "{$this->invoice->invoice_no}.html")
                ->withMime('text/html'),
        ];
    }
}
