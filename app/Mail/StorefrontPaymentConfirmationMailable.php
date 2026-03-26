<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StorefrontPaymentConfirmationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public PaymentTransaction $transaction,
        public BusinessSetting $settings,
        public string $subjectLine,
        public string $bodyContent,
    ) {
    }

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.storefront.payment-confirmation', [
                'order' => $this->order,
                'transaction' => $this->transaction,
                'settings' => $this->settings,
                'bodyContent' => $this->bodyContent,
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
}

