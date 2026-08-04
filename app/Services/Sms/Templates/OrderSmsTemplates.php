<?php

namespace App\Services\Sms\Templates;

use App\Enums\OrderStatus;
use App\Models\CustomOrderProgressUpdate;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsTemplateRenderer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

        return app(SmsTemplateRenderer::class)->render($body, $replacements);
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
            'order_created' => "[{$appName}] Order {$orderNo} for ".($replacements['garments'] ?? 'your items').' created on '.($replacements['order_date'] ?? '').'. Due: '.($replacements['due_date'] ?? '').'.',
            'order_status_change' => "[{$appName}] Order {$orderNo} status: ".($replacements['status'] ?? '').'. Due: '.($replacements['due_date'] ?? '').'.',
            'order_ready' => "[{$appName}] Good news {$customerName}, order {$orderNo} for ".($replacements['garments'] ?? 'your items').' is READY for pickup. Balance: '.($replacements['balance_due'] ?? '').'.',
            'order_delivered' => "[{$appName}] Order {$orderNo} for ".($replacements['garments'] ?? 'your items').' has been DELIVERED. Thank you!',
            'order_delivery_date_change' => "[{$appName}] Order {$orderNo} due date updated from ".($replacements['old_due_date'] ?? '').' to '.($replacements['new_due_date'] ?? $replacements['due_date'] ?? '').'.',
            'order_cancelled' => "[{$appName}] Order {$orderNo} has been cancelled.",
            'order_payment' => "[{$appName}] Payment received for order {$orderNo}. Balance: ".($replacements['balance_due'] ?? '').'.',
            'order_due_date_reminder' => "[{$appName}] Reminder: Order {$orderNo} is due on ".($replacements['due_date'] ?? $replacements['expected_delivery_date'] ?? '').'.',
            default => "[{$appName}] Order {$orderNo} update.",
        };
    }

    /**
     * Build replacements array from Order (and optional Payment).
     */
    public static function replacementsForOrder(Order $order, ?OrderPayment $payment = null): array
    {
        $order->loadMissing(['customer', 'lines']);
        $orderDate = $order->order_date?->format('M d, Y') ?? $order->created_at?->format('M d, Y') ?? '';
        $dueDate = $order->due_date?->format('M d, Y') ?? '';
        $garments = $order->lines
            ->map(function ($line) {
                $itemName = trim((string) $line->item_name);
                $qty = self::formatQuantity($line->qty);

                if ($itemName === '') {
                    return null;
                }

                return $qty === '1'
                    ? $itemName
                    : "{$itemName} x{$qty}";
            })
            ->filter()
            ->implode(', ');

        $replacements = [
            'customer_name' => $order->customer?->name ?? 'Customer',
            'order_number' => $order->order_no,
            'garments' => $garments !== '' ? $garments : __('Order items'),
            'order_date' => $orderDate,
            'status' => $order->status?->label() ?? $order->status,
            'due_date' => $dueDate,
            'expected_delivery_date' => $dueDate,
            'total_amount' => money_tzs($order->total),
            'amount_paid' => $payment ? money_tzs($payment->amount) : money_tzs($order->paid_amount),
            'deposit' => $payment ? money_tzs($payment->amount) : money_tzs(0),
            'balance_due' => money_tzs($order->balance_due),
        ];

        return $replacements;
    }

    /**
     * Get SMS message for order creation.
     */
    public static function orderCreated(Order $order, ?OrderPayment $deposit = null): string
    {
        $replacements = self::replacementsForOrder($order, $deposit);

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
            OrderStatus::Ready->value => self::templateCodeForStatus($newStatus),
            OrderStatus::Delivered->value => self::templateCodeForStatus($newStatus),
            OrderStatus::Completed->value => self::templateCodeForStatus($newStatus),
            OrderStatus::Cancelled->value => self::templateCodeForStatus($newStatus),
            default => self::templateCodeForStatus($newStatus),
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

    public static function replacementsForCustomProgressUpdate(Order $order, CustomOrderProgressUpdate $progressUpdate): array
    {
        $replacements = self::replacementsForOrder($order);
        $requestedPaymentAmount = $progressUpdate->requested_payment_amount
            ? money_currency($progressUpdate->requested_payment_amount, $order->currency ?: 'TZS')
            : '';

        $replacements['stage_label'] = $progressUpdate->stage_label;
        $replacements['progress_note'] = trim((string) $progressUpdate->note);
        $replacements['requested_payment_amount'] = $requestedPaymentAmount;
        $replacements['requested_payment_note'] = trim((string) $progressUpdate->requested_payment_note);
        $replacements['requested_payment_text'] = $requestedPaymentAmount !== ''
            ? trim('Payment requested: '.$requestedPaymentAmount.'. '.$replacements['requested_payment_note'])
            : '';

        return $replacements;
    }

    public static function templateCodeForStatus(string $newStatus): string
    {
        return match ($newStatus) {
            OrderStatus::Ready->value => 'order_ready',
            OrderStatus::Delivered->value => 'order_delivered',
            OrderStatus::Completed->value => 'order_completed',
            OrderStatus::Cancelled->value => 'order_cancelled',
            default => 'order_status_change',
        };
    }

    public static function dueDateChanged(Order $order, ?string $oldDueDate, string $newDueDate): string
    {
        $replacements = self::replacementsForOrder($order);
        $replacements['old_due_date'] = self::formatDateValue($oldDueDate);
        $replacements['new_due_date'] = self::formatDateValue($newDueDate);
        $replacements['due_date'] = $replacements['new_due_date'];
        $replacements['expected_delivery_date'] = $replacements['new_due_date'];

        return self::resolveTemplate('order_delivery_date_change', $replacements);
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

    public static function formatDateValue(null|string|CarbonInterface $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('M d, Y');
        }

        if (blank($value)) {
            return '';
        }

        return Carbon::parse($value)->format('M d, Y');
    }

    protected static function formatQuantity(mixed $value): string
    {
        $quantity = (float) $value;

        if (fmod($quantity, 1.0) === 0.0) {
            return (string) (int) $quantity;
        }

        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }
}
