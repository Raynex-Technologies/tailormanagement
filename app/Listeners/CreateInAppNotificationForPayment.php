<?php

namespace App\Listeners;

use App\Events\OrderPaymentRecorded;
use App\Notifications\OrderPaymentReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class CreateInAppNotificationForPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderPaymentRecorded $event): void
    {
        $order = $event->order;
        $payment = $event->payment;
        $actor = $event->actor;

        // Get order watchers who should be notified
        $watchers = $order->watchers()
            ->with('user')
            ->where('notify_on_status_change', true)
            ->get()
            ->pluck('user')
            ->filter();

        // Also notify the order creator if they're not the actor
        $orderCreator = $order->creator;
        if ($orderCreator && $orderCreator->id !== $actor->id) {
            $watchers->push($orderCreator);
        }

        // Remove duplicates and the actor
        $recipients = $watchers
            ->unique('id')
            ->filter(fn ($user) => $user->id !== $actor->id);

        // Send notifications
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new OrderPaymentReceivedNotification($order, $payment));
        }
    }
}
