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
    ];

    /** Human labels for each category */
    public static function categoryLabels(): array
    {
        return [
            'order_created' => __('Order Created'),
            'order_status_change' => __('Order Status Change'),
            'order_delivered' => __('Order Delivered'),
            'order_delivery_date_change' => __('Order Delivery Date Change'),
            'order_cancelled' => __('Order Cancelled'),
            'order_payment' => __('Order Payment'),
            'order_due_date_reminder' => __('Order Due Date Reminder'),
        ];
    }

    /** Variables available per category (for UI hints) */
    public static function variablesForCategory(string $category): array
    {
        $all = [
            'customer_name',
            'order_number',
            'status',
            'expected_delivery_date',
            'total_amount',
            'amount_paid',
            'balance_due',
        ];
        $byCategory = [
            'order_created' => ['customer_name', 'order_number', 'total_amount', 'expected_delivery_date'],
            'order_status_change' => ['customer_name', 'order_number', 'status'],
            'order_delivered' => ['customer_name', 'order_number'],
            'order_delivery_date_change' => ['customer_name', 'order_number', 'expected_delivery_date'],
            'order_cancelled' => ['customer_name', 'order_number'],
            'order_payment' => ['customer_name', 'order_number', 'amount_paid', 'balance_due'],
            'order_due_date_reminder' => ['customer_name', 'order_number', 'expected_delivery_date', 'total_amount', 'balance_due'],
        ];

        return $byCategory[$category] ?? $all;
    }

    /**
     * Get the singleton templates instance (first row).
     */
    public static function instance(): self
    {
        $row = self::first();
        if (! $row) {
            $row = self::create([
                'templates' => array_fill_keys(self::CATEGORIES, ''),
            ]);
        }

        return $row;
    }
}
