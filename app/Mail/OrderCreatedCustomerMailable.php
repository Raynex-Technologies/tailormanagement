<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\CanonicalInvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class OrderCreatedCustomerMailable extends Mailable
{
    use Queueable, SerializesModels;

    public array $financialSummary;

    public function __construct(
        public Order $order,
        public Invoice $invoice,
        public BusinessSetting $settings,
    ) {
        $this->financialSummary = $order->financialSummary();
    }

    public function build(): self
    {
        $mail = $this->subject(__('Order :order confirmed', ['order' => $this->order->order_no]))
            ->view('emails.orders.created');

        return $this->applyIdentity($mail);
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

    protected function applyIdentity(self $mail): self
    {
        if ($this->settings->email_from_address) {
            $mail->from(
                $this->settings->email_from_address,
                $this->settings->email_from_name ?: $this->settings->business_name ?: config('mail.from.name'),
            );
        }

        if ($this->settings->email_reply_to) {
            $mail->replyTo($this->settings->email_reply_to);
        }

        return $mail;
    }
}
