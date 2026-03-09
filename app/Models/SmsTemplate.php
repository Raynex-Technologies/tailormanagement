<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['templates'];

    protected function casts(): array
    {
        return [
            'templates' => 'array',
        ];
    }

    /** Template keys (categories) used in the JSON column */
    public const CATEGORIES = [
        'order_created',
        'order_status_change',
        'order_delivered',
        'order_delivery_date_change',
        'order_cancelled',
        'order_payment',
        'order_due_date_reminder',
        'installment_payment_reminder',
        'installment_payment_received',
        'installment_completed',
    ];

    /** Human labels for each category */
    public static function categoryLabels(): array
    {
        return [
            'order_created' => __('Order Created'),
            'order_status_change' => __('Order Status Change'),
            'order_delivered' => __('Order Delivered'),
            'order_delivery_date_change' => __('Order Due Date Changed'),
            'order_cancelled' => __('Order Cancelled'),
            'order_payment' => __('Order Payment'),
            'order_due_date_reminder' => __('Order Due Date Reminder'),
            'installment_payment_reminder' => __('Installment Payment Reminder'),
            'installment_payment_received' => __('Installment Payment Received'),
            'installment_completed' => __('Installment Plan Completed'),
        ];
    }

    public static function defaultTemplates(): array
    {
        return [
            'order_created' => 'Hello {customer_name}, your order #{order_number} for {garments} was created on {order_date}. Total: {total_amount}. Due date: {due_date}.',
            'order_status_change' => 'Hello {customer_name}, your order #{order_number} for {garments} is now {status}. Due date: {due_date}.',
            'order_delivered' => 'Hello {customer_name}, your order #{order_number} for {garments} has been delivered. Thank you!',
            'order_delivery_date_change' => 'Hello {customer_name}, your order #{order_number} due date has been updated from {old_due_date} to {new_due_date}.',
            'order_cancelled' => 'Hello {customer_name}, your order #{order_number} for {garments} has been cancelled.',
            'order_payment' => 'Hello {customer_name}, we received payment of {amount_paid} for order #{order_number}. Balance due: {balance_due}.',
            'order_due_date_reminder' => 'Hello {customer_name}, reminder: your order #{order_number} for {garments} is due on {due_date}. Total: {total_amount}, Balance: {balance_due}.',
            'installment_payment_reminder' => 'Hello {customer_name}, reminder: installment {installment_number}/{total_installments} for package {package_name} is due on {due_date}. Amount due: {installment_amount}. Remaining balance: {remaining_balance}.',
            'installment_payment_received' => 'Hello {customer_name}, we received {payment_amount} for package {package_name} under plan {plan_number}. Remaining balance: {remaining_balance}. Next due date: {next_due_date}.',
            'installment_completed' => 'Hello {customer_name}, congratulations. Your installment plan {plan_number} for package {package_name} is fully paid and completed.',
        ];
    }

    public static function variableDefinitions(): array
    {
        return [
            'customer_name' => [
                'label' => __('Customer Name'),
                'description' => __('The customer receiving the SMS.'),
            ],
            'order_number' => [
                'label' => __('Order Number'),
                'description' => __('The order reference number.'),
            ],
            'garments' => [
                'label' => __('Garments'),
                'description' => __('A comma-separated list of order line items.'),
            ],
            'order_date' => [
                'label' => __('Order Date'),
                'description' => __('The order creation date shown to the customer.'),
            ],
            'due_date' => [
                'label' => __('Due Date'),
                'description' => __('The current due date for the order.'),
            ],
            'expected_delivery_date' => [
                'label' => __('Due Date Alias'),
                'description' => __('Legacy alias for the current due date.'),
            ],
            'old_due_date' => [
                'label' => __('Old Due Date'),
                'description' => __('The previous due date before it was changed.'),
            ],
            'new_due_date' => [
                'label' => __('New Due Date'),
                'description' => __('The new due date selected for the order.'),
            ],
            'status' => [
                'label' => __('Status'),
                'description' => __('The current order status label.'),
            ],
            'total_amount' => [
                'label' => __('Total Amount'),
                'description' => __('The total order amount.'),
            ],
            'amount_paid' => [
                'label' => __('Amount Paid'),
                'description' => __('The paid amount relevant to the message.'),
            ],
            'balance_due' => [
                'label' => __('Balance Due'),
                'description' => __('The remaining unpaid balance.'),
            ],
            'package_name' => [
                'label' => __('Package Name'),
                'description' => __('The package assigned to the customer.'),
            ],
            'plan_number' => [
                'label' => __('Plan Number'),
                'description' => __('The installment agreement reference number.'),
            ],
            'installment_number' => [
                'label' => __('Installment Number'),
                'description' => __('The current installment sequence number.'),
            ],
            'total_installments' => [
                'label' => __('Total Installments'),
                'description' => __('The total number of scheduled installments.'),
            ],
            'installment_amount' => [
                'label' => __('Installment Amount'),
                'description' => __('The scheduled amount for each installment.'),
            ],
            'payment_amount' => [
                'label' => __('Payment Amount'),
                'description' => __('The latest payment amount received.'),
            ],
            'remaining_balance' => [
                'label' => __('Remaining Balance'),
                'description' => __('The unpaid balance for the installment plan.'),
            ],
            'next_due_date' => [
                'label' => __('Next Due Date'),
                'description' => __('The next outstanding installment due date.'),
            ],
        ];
    }

    public static function normalizeTemplates(?array $templates): array
    {
        return array_replace(self::defaultTemplates(), $templates ?? []);
    }

    /** Variables available per category (for UI hints) */
    public static function variablesForCategory(string $category): array
    {
        $common = [
            'customer_name',
            'order_number',
            'garments',
            'order_date',
            'due_date',
            'expected_delivery_date',
            'total_amount',
            'amount_paid',
            'balance_due',
            'status',
        ];

        return match ($category) {
            'order_delivery_date_change' => [...$common, 'old_due_date', 'new_due_date'],
            'installment_payment_reminder' => ['customer_name', 'package_name', 'plan_number', 'installment_number', 'total_installments', 'installment_amount', 'due_date', 'remaining_balance'],
            'installment_payment_received' => ['customer_name', 'package_name', 'plan_number', 'payment_amount', 'remaining_balance', 'next_due_date'],
            'installment_completed' => ['customer_name', 'package_name', 'plan_number'],
            default => $common,
        };
    }

    /**
     * Get the singleton templates instance (first row).
     */
    public static function instance(): self
    {
        $row = self::first();
        if (! $row) {
            $row = self::create([
                'templates' => self::defaultTemplates(),
            ]);
        } else {
            $normalized = self::normalizeTemplates($row->templates);

            if ($normalized !== ($row->templates ?? [])) {
                $row->update(['templates' => $normalized]);
                $row->refresh();
            }
        }

        return $row;
    }
}
