<?php

namespace App\Services\Sms\Templates;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;

class OrderSmsTemplates
{
    /**
     * Get SMS message for order status change.
     */
    public static function statusChanged(Order $order, string $newStatus): string
    {
        $appName = config('app.name', 'TailorPro');
        $orderNo = $order->order_no;
        $statusLabel = self::getStatusLabel($newStatus);
        $balance = money_tzs($order->balance_amount);

        // Different messages based on status
        return match ($newStatus) {
            'in_progress' => "[{$appName}] Order {$orderNo} is now IN PROGRESS. We are working on your order. Balance: {$balance}.",
            'ready' => "[{$appName}] Order {$orderNo} is READY for pickup! Balance: {$balance}. Please visit us to collect your order.",
            'delivered' => "[{$appName}] Order {$orderNo} has been DELIVERED. Balance: {$balance}. Thank you for your business!",
            'completed' => "[{$appName}] Order {$orderNo} is now COMPLETED. Balance: {$balance}. Thank you for choosing us!",
            default => "[{$appName}] Order {$orderNo} status: {$statusLabel}. Balance: {$balance}.",
        };
    }

    /**
     * Get SMS message for payment received.
     */
    public static function paymentReceived(Order $order, OrderPayment $payment): string
    {
        $appName = config('app.name', 'TailorPro');
        $orderNo = $order->order_no;
        $amountPaid = money_tzs($payment->amount);
        $balance = money_tzs($order->balance_amount);

        if ($order->balance_amount <= 0) {
            return "[{$appName}] Payment received {$amountPaid} for order {$orderNo}. FULLY PAID. Thank you!";
        }

        return "[{$appName}] Payment received {$amountPaid} for order {$orderNo}. Balance: {$balance}. Thank you!";
    }

    /**
     * Get SMS message for order creation (optional).
     */
    public static function orderCreated(Order $order): string
    {
        $appName = config('app.name', 'TailorPro');
        $orderNo = $order->order_no;
        $total = money_tzs($order->total);
        $balance = money_tzs($order->balance_amount);

        return "[{$appName}] Order {$orderNo} created. Total: {$total}, Balance: {$balance}. Thank you for your order!";
    }

    /**
     * Get human-readable status label.
     */
    protected static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'new', OrderStatus::New->value => 'NEW',
            'in_progress', OrderStatus::InProgress->value => 'IN PROGRESS',
            'ready', OrderStatus::Ready->value => 'READY',
            'delivered', OrderStatus::Delivered->value => 'DELIVERED',
            'completed', OrderStatus::Completed->value => 'COMPLETED',
            'cancelled', OrderStatus::Cancelled->value => 'CANCELLED',
            default => strtoupper($status),
        };
    }

    /**
     * Get list of statuses that should trigger SMS notifications.
     */
    public static function getNotifiableStatuses(): array
    {
        return [
            OrderStatus::InProgress->value,
            OrderStatus::Ready->value,
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ];
    }

    /**
     * Check if a status should trigger SMS notification.
     */
    public static function shouldNotifyForStatus(string $status): bool
    {
        return in_array($status, self::getNotifiableStatuses());
    }
}
