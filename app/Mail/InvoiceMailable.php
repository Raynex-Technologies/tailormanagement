<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Support\CanonicalInvoicePdf;
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
        $financialSummary = $this->invoice->order?->financialSummary() ?? [
            'total' => (float) $this->invoice->total,
            'paid' => 0.0,
            'balance' => (float) $this->invoice->total,
            'status' => null,
        ];

        $mail = $this->subject($subject)
            ->view('emails.invoices.invoice', [
                'invoice' => $this->invoice,
                'settings' => $this->settings,
                'emailBody' => $body,
                'financialSummary' => $financialSummary,
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
        $summary = $order?->financialSummary() ?? [
            'total' => (float) $this->invoice->total,
            'paid' => 0.0,
            'balance' => (float) $this->invoice->total,
        ];

        $currency = strtoupper((string) ($order?->currency ?: 'TZS'));
        $businessName = $this->settings->business_name ?: config('app.name', 'Tailoring Business');
        $variables = [
            'business_name' => $businessName,
            'customer_name' => $order?->customer?->name ?: 'Customer',
            'order_number' => $order?->order_no ?: 'N/A',
            'invoice_number' => $this->invoice->invoice_no,
            'currency' => $currency,
            'order_total' => number_format($summary['total'], 2, '.', ''),
            'paid_amount' => number_format($summary['paid'], 2, '.', ''),
            'amount_due' => number_format($summary['balance'], 2, '.', ''),
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
        $document = app(CanonicalInvoicePdf::class);

        return [
            Attachment::fromData(
                fn () => $document->render($this->invoice, $this->settings),
                $document->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
