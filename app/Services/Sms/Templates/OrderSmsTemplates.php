<?php

namespace App\Services\Sms\Templates;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\SmsTemplate;

class OrderSmsTemplates
{
    /**
     * Resolve a template from DB and replace variables.
     */
    public static function resolveTemplate(string $category, array $replacements): string
    {
        $row = SmsTemplate::instance();
        $templates = $row->templates ?? [];
        $body = $templates[$category] ?? '';

        if ($body === '') {
            return self::fallbackMessage($category, $replacements);
        }

        foreach ($replacements as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return $body;
    }

    /**
     * Fallback when no template is set in DB.
     */
    protected static function fallbackMessage(string $category, array $replacements): string
    {
        $appName = config('app.name', 'TailorPro');
        $orderNo = $replacements['order_number'] ?? '';
        $customerName = $replacements['customer_name'] ?? 'Customer';

        return match ($category) {
            'order_created' => "[{$appName}] Order {$orderNo} created. Total: " . ($replacements['total_amount'] ?? '') . '. Thank you!',
            'order_status_change' => "[{$appName}] Order {$orderNo} status: " . ($replacements['status'] ?? '') . '.',
            'order_delivered' => "[{$appName}] Order {$orderNo} has been DELIVERED. Thank you!",
            'order_cancelled' => "[{$appName}] Order {$orderNo} has been cancelled.",
            'order_payment' => "[{$appName}] Payment received for order {$orderNo}. Balance: " . ($replacements['balance_due'] ?? '') . '.',
            'order_due_date_reminder' => "[{$appName}] Reminder: Order {$orderNo} is due on " . ($replacements['expected_delivery_date'] ?? '') . '.',
            default => "[{$appName}] Order {$orderNo} update.",
        };
    }

    /**
     * Build replacements array from Order (and optional Payment).
     */
    public static function replacementsForOrder(Order $order, ?OrderPayment $payment = null): array
    {
        $order->loadMissing('customer');
        $dueDate = $order->due_date?->format('M d, Y');

        $replacements = [
            'customer_name' => $order->customer?->name ?? 'Customer',
            'order_number' => $order->order_no,
            'status' => $order->status?->label() ?? $order->status,
            'expected_delivery_date' => $dueDate ?? '',
            'total_amount' => money_tzs($order->total),
            'amount_paid' => $payment ? money_tzs($payment->amount) : money_tzs($order->paid_amount),
            'balance_due' => money_tzs($order->balance_due),
        ];

        return $replacements;
    }

    /**
     * Get SMS message for order creation.
     */
    public static function orderCreated(Order $order): string
    {
        $replacements = self::replacementsForOrder($order);

        return self::resolveTemplate('order_created', $replacements);
    }

    /**
     * Get SMS message for order status change.
     */
    public static function statusChanged(Order $order, string $newStatus): string
    {
        $replacements = self::replacementsForOrder($order);
        $replacements['status'] = self::getStatusLabel($newStatus);

        $category = match ($newStatus) {
            OrderStatus::Delivered->value => 'order_delivered',
            OrderStatus::Cancelled->value => 'order_cancelled',
            default => 'order_status_change',
        };

        return self::resolveTemplate($category, $replacements);
    }

    /**
     * Get SMS message for payment received.
     */
    public static function paymentReceived(Order $order, OrderPayment $payment): string
    {
        $replacements = self::replacementsForOrder($order, $payment);

        return self::resolveTemplate('order_payment', $replacements);
    }

    /**
     * Get SMS message for due date reminder.
     */
    public static function dueDateReminder(Order $order): string
    {
        $replacements = self::replacementsForOrder($order);

        return self::resolveTemplate('order_due_date_reminder', $replacements);
    }

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
            OrderStatus::Cancelled->value,
        ];
    }

    public static function shouldNotifyForStatus(string $status): bool
    {
        return in_array($status, self::getNotifiableStatuses());
    }
}
