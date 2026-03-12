<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Support\InvoiceTemplateResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class InvoiceMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public BusinessSetting $settings
    ) {}

    public function build(): self
    {
        $mail = $this->subject("Invoice {$this->invoice->invoice_no}")
            ->view('emails.invoices.invoice', [
                'invoice' => $this->invoice,
                'settings' => $this->settings,
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
