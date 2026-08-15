<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SmsTemplate extends Model
{
    protected $fillable = ['templates', 'whatsapp_templates', 'template_settings'];

    protected function casts(): array
    {
        return [
            'templates' => 'array',
            'whatsapp_templates' => 'array',
            'template_settings' => 'array',
        ];
    }

    /** Template keys (categories) used in the JSON column */
    public const CATEGORIES = [
        'order_created',
        'order_status_change',
        'order_status_changed',
        'order_ready',
        'order_delivered',
        'order_completed',
        'order_delivery_date_change',
        'order_cancelled',
        'order_payment',
        'order_payment_received',
        'order_balance_reminder',
        'order_due_date_reminder',
        'order_overdue_reminder',
        'appointment_created',
        'appointment_confirmed',
        'appointment_rescheduled',
        'appointment_cancelled',
        'appointment_reminder',
        'customer_created',
        'measurement_recorded',
        'thank_you_message',
        'custom_campaign',
        'promotional_message',
        'installment_payment_reminder',
        'installment_payment_received',
        'installment_completed',
        'booking_verification',
        'custom_order_progress_update',
        'pos_sale_completed',
    ];

    /** Human labels for each category */
    public static function categoryLabels(): array
    {
        return [
            'order_created' => __('Order Created'),
            'order_status_change' => __('Order Status Change'),
            'order_status_changed' => __('Order Status Changed'),
            'order_ready' => __('Order Ready'),
            'order_delivered' => __('Order Delivered'),
            'order_completed' => __('Order Completed'),
            'order_delivery_date_change' => __('Order Due Date Changed'),
            'order_cancelled' => __('Order Cancelled'),
            'order_payment' => __('Order Payment'),
            'order_payment_received' => __('Order Payment Received'),
            'order_balance_reminder' => __('Order Balance Reminder'),
            'order_due_date_reminder' => __('Order Due Date Reminder'),
            'order_overdue_reminder' => __('Order Overdue Reminder'),
            'appointment_created' => __('Appointment Created'),
            'appointment_confirmed' => __('Appointment Confirmed'),
            'appointment_rescheduled' => __('Appointment Rescheduled'),
            'appointment_cancelled' => __('Appointment Cancelled'),
            'appointment_reminder' => __('Appointment Reminder'),
            'customer_created' => __('Customer Created'),
            'measurement_recorded' => __('Measurement Recorded'),
            'thank_you_message' => __('Thank You Message'),
            'custom_campaign' => __('Custom Campaign'),
            'promotional_message' => __('Promotional Message'),
            'installment_payment_reminder' => __('Installment Payment Reminder'),
            'installment_payment_received' => __('Installment Payment Received'),
            'installment_completed' => __('Installment Plan Completed'),
            'booking_verification' => __('Booking Verification'),
            'custom_order_progress_update' => __('Custom Order Progress Update'),
            'pos_sale_completed' => __('POS Sale Completed'),
        ];
    }

    public static function defaultTemplates(): array
    {
        return [
            'order_created' => 'Hello {customer_name}, your order #{order_number} for {garments} was created on {order_date}. Total: {total_amount}. Due date: {due_date}.',
            'order_status_change' => 'Hello {customer_name}, your order #{order_number} for {garments} is now {status}. Due date: {due_date}.',
            'order_status_changed' => 'Hello {customer_name}, your order #{order_number} status has changed to {status}.',
            'order_ready' => 'Hello {customer_name}, good news. Your order #{order_number} for {garments} is ready for pickup. Balance due: {balance_due}.',
            'order_delivered' => 'Hello {customer_name}, your order #{order_number} for {garments} has been delivered. Thank you!',
            'order_completed' => 'Hello {customer_name}, your order #{order_number} has been completed. Thank you.',
            'order_delivery_date_change' => 'Hello {customer_name}, your order #{order_number} due date has been updated from {old_due_date} to {new_due_date}.',
            'order_cancelled' => 'Hello {customer_name}, your order #{order_number} for {garments} has been cancelled.',
            'order_payment' => 'Hello {customer_name}, we received payment of {amount_paid} for order #{order_number}. Balance due: {balance_due}.',
            'order_payment_received' => 'Hello {customer_name}, we have received your payment of {amount_paid} for order #{order_number}. Balance: {balance_due}.',
            'order_balance_reminder' => 'Hello {customer_name}, your order #{order_number} has a pending balance of {balance_due}. Kindly complete payment before collection.',
            'order_due_date_reminder' => 'Hello {customer_name}, reminder: your order #{order_number} for {garments} is due on {due_date}. Total: {total_amount}, Balance: {balance_due}.',
            'order_overdue_reminder' => 'Hello {customer_name}, your order #{order_number} is overdue. Please contact us for assistance.',
            'appointment_created' => 'Hello {customer_name}, your appointment request has been received for {appointment_date} at {appointment_time}.',
            'appointment_confirmed' => 'Hello {customer_name}, your appointment has been confirmed for {appointment_date} at {appointment_time}.',
            'appointment_rescheduled' => 'Hello {customer_name}, your appointment has been rescheduled to {appointment_date} at {appointment_time}.',
            'appointment_cancelled' => 'Hello {customer_name}, your appointment scheduled for {appointment_date} has been cancelled. Please contact us for assistance.',
            'appointment_reminder' => 'Hello {customer_name}, reminder: your appointment is scheduled for {appointment_date} at {appointment_time}.',
            'customer_created' => 'Hello {customer_name}, welcome to {business_name}.',
            'measurement_recorded' => 'Hello {customer_name}, your measurements have been recorded. Thank you.',
            'thank_you_message' => 'Hello {customer_name}, thank you for choosing {business_name}.',
            'custom_campaign' => 'Hello {customer_name}, {message}',
            'promotional_message' => 'Hello {customer_name}, {message}',
            'installment_payment_reminder' => 'Hello {customer_name}, reminder: installment {installment_number}/{total_installments} for package {package_name} is due on {due_date}. Amount due: {installment_amount}. Remaining balance: {remaining_balance}.',
            'installment_payment_received' => 'Hello {customer_name}, we received {payment_amount} for package {package_name} under plan {plan_number}. Remaining balance: {remaining_balance}. Next due date: {next_due_date}.',
            'installment_completed' => 'Hello {customer_name}, congratulations. Your installment plan {plan_number} for package {package_name} is fully paid and completed.',
            'booking_verification' => 'Your booking verification Code is {verification_code}. This code is valid for 10 Minutes',
            'custom_order_progress_update' => 'Hello {customer_name}, your custom order #{order_number} has a new progress update: {stage_label}. {progress_note} {requested_payment_text}',
            'pos_sale_completed' => 'Hello {customer_name}, receipt {sale_number} is complete. Items: {items}. Total: {total_amount}. Paid: {amount_paid}. Change: {change_amount}. Thank you for shopping with {business_name}.',
        ];
    }

    public static function templateDefinitions(): array
    {
        return [
            'order_created' => ['category' => 'Orders', 'description' => __('Sent when a new customer order is created.'), 'enabled' => true],
            'order_status_change' => ['category' => 'Orders', 'description' => __('Sent when an order status changes.'), 'enabled' => true],
            'order_status_changed' => ['category' => 'Orders', 'description' => __('Sent when an order status changes.'), 'enabled' => true],
            'order_ready' => ['category' => 'Orders', 'description' => __('Sent when an order is ready for pickup or delivery.'), 'enabled' => true],
            'order_delivered' => ['category' => 'Orders', 'description' => __('Sent when an order has been delivered.'), 'enabled' => true],
            'order_completed' => ['category' => 'Orders', 'description' => __('Sent when an order is completed.'), 'enabled' => true],
            'order_delivery_date_change' => ['category' => 'Orders', 'description' => __('Sent when an order due date changes.'), 'enabled' => true],
            'order_cancelled' => ['category' => 'Orders', 'description' => __('Sent when an order is cancelled.'), 'enabled' => true],
            'order_payment' => ['category' => 'Payments', 'description' => __('Sent when a payment is recorded for an order.'), 'enabled' => true],
            'order_payment_received' => ['category' => 'Payments', 'description' => __('Sent when a payment is received for an order.'), 'enabled' => true],
            'order_balance_reminder' => ['category' => 'Payments', 'description' => __('Sent to remind customers about pending order balances.'), 'enabled' => false],
            'order_due_date_reminder' => ['category' => 'Orders', 'description' => __('Sent before an order due date.'), 'enabled' => true],
            'order_overdue_reminder' => ['category' => 'Orders', 'description' => __('Sent after an order becomes overdue.'), 'enabled' => false],
            'appointment_created' => ['category' => 'Appointments', 'description' => __('Sent when an appointment request is created.'), 'enabled' => false],
            'appointment_confirmed' => ['category' => 'Appointments', 'description' => __('Sent when an appointment is confirmed.'), 'enabled' => true],
            'appointment_rescheduled' => ['category' => 'Appointments', 'description' => __('Sent when an appointment is rescheduled.'), 'enabled' => true],
            'appointment_cancelled' => ['category' => 'Appointments', 'description' => __('Sent when an appointment is cancelled.'), 'enabled' => true],
            'appointment_reminder' => ['category' => 'Appointments', 'description' => __('Sent before a scheduled appointment.'), 'enabled' => true],
            'customer_created' => ['category' => 'Customers', 'description' => __('Sent when a new customer account is created.'), 'enabled' => false],
            'measurement_recorded' => ['category' => 'Customers', 'description' => __('Sent when customer measurements are recorded.'), 'enabled' => false],
            'thank_you_message' => ['category' => 'Customers', 'description' => __('Sent as a general customer thank-you message.'), 'enabled' => false],
            'custom_campaign' => ['category' => 'Marketing', 'description' => __('Used for custom SMS campaigns.'), 'enabled' => false],
            'promotional_message' => ['category' => 'Marketing', 'description' => __('Used for promotional SMS messages.'), 'enabled' => false],
            'installment_payment_reminder' => ['category' => 'Payments', 'description' => __('Sent before an installment payment is due.'), 'enabled' => true],
            'installment_payment_received' => ['category' => 'Payments', 'description' => __('Sent when an installment payment is received.'), 'enabled' => true],
            'installment_completed' => ['category' => 'Payments', 'description' => __('Sent when an installment plan is fully paid.'), 'enabled' => true],
            'booking_verification' => ['category' => 'Bookings', 'description' => __('Sent with the verification code for public online bookings.'), 'enabled' => true],
            'custom_order_progress_update' => ['category' => 'Orders', 'description' => __('Sent when a customer-visible custom order progress update is published.'), 'enabled' => true],
            'pos_sale_completed' => ['category' => 'Point of Sale', 'description' => __('Sent to the selected customer when a POS sale is completed.'), 'enabled' => true],
        ];
    }

    public static function defaultTemplateSettings(): array
    {
        return collect(self::templateDefinitions())
            ->map(fn (array $definition, string $code) => [
                'sms_enabled' => (bool) $definition['enabled'],
                'whatsapp_enabled' => false,
                'whatsapp_status' => 'not_submitted',
                'whatsapp_template_name' => $code,
                'whatsapp_template_language' => null,
                'is_active' => true,
                'category' => $definition['category'],
                'description' => $definition['description'],
            ])
            ->all();
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
            'deposit' => [
                'label' => __('Deposit'),
                'description' => __('The deposit recorded when the order was created.'),
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
            'verification_code' => [
                'label' => __('Verification Code'),
                'description' => __('The one-time booking verification code.'),
            ],
            'stage_label' => [
                'label' => __('Progress Stage'),
                'description' => __('The published progress stage label.'),
            ],
            'progress_note' => [
                'label' => __('Progress Note'),
                'description' => __('The note added to the progress update.'),
            ],
            'requested_payment_amount' => [
                'label' => __('Requested Payment Amount'),
                'description' => __('The payment amount requested with the progress update.'),
            ],
            'requested_payment_note' => [
                'label' => __('Requested Payment Note'),
                'description' => __('The payment note added to the progress update.'),
            ],
            'requested_payment_text' => [
                'label' => __('Requested Payment Text'),
                'description' => __('A ready-to-send payment request sentence when an amount is requested.'),
            ],
            'sale_number' => [
                'label' => __('POS Sale Number'),
                'description' => __('The POS sale receipt number.'),
            ],
            'items' => [
                'label' => __('Items Sold'),
                'description' => __('A comma-separated list of item names and quantities sold.'),
            ],
            'subtotal' => [
                'label' => __('Subtotal'),
                'description' => __('The POS sale subtotal before sale-level discount and tax.'),
            ],
            'discount_amount' => [
                'label' => __('Discount Amount'),
                'description' => __('The sale-level discount amount.'),
            ],
            'tax_amount' => [
                'label' => __('Tax Amount'),
                'description' => __('The sale tax amount.'),
            ],
            'change_amount' => [
                'label' => __('Change Amount'),
                'description' => __('The change due to the customer.'),
            ],
            'payment_method' => [
                'label' => __('Payment Method'),
                'description' => __('The payment method used for the POS sale.'),
            ],
            'cashier_name' => [
                'label' => __('Cashier Name'),
                'description' => __('The staff user who completed the POS sale.'),
            ],
            'business_name' => [
                'label' => __('Business Name'),
                'description' => __('The configured business name.'),
            ],
        ];
    }

    public static function normalizeTemplates(?array $templates): array
    {
        return array_replace(self::defaultTemplates(), $templates ?? []);
    }

    public static function normalizeTemplateSettings(?array $settings): array
    {
        $normalized = self::defaultTemplateSettings();

        foreach ($settings ?? [] as $code => $values) {
            if (! is_array($values) || ! array_key_exists($code, $normalized)) {
                continue;
            }

            $normalized[$code] = array_replace($normalized[$code], [
                'sms_enabled' => array_key_exists('sms_enabled', $values) ? (bool) $values['sms_enabled'] : $normalized[$code]['sms_enabled'],
                'whatsapp_enabled' => array_key_exists('whatsapp_enabled', $values) ? (bool) $values['whatsapp_enabled'] : $normalized[$code]['whatsapp_enabled'],
                'whatsapp_status' => in_array(($values['whatsapp_status'] ?? null), ['not_submitted', 'pending', 'approved', 'rejected'], true) ? $values['whatsapp_status'] : $normalized[$code]['whatsapp_status'],
                'whatsapp_template_name' => filled($values['whatsapp_template_name'] ?? null) ? (string) $values['whatsapp_template_name'] : $normalized[$code]['whatsapp_template_name'],
                'whatsapp_template_language' => filled($values['whatsapp_template_language'] ?? null) ? (string) $values['whatsapp_template_language'] : null,
                'is_active' => array_key_exists('is_active', $values) ? (bool) $values['is_active'] : $normalized[$code]['is_active'],
                'category' => $values['category'] ?? $normalized[$code]['category'],
                'description' => $values['description'] ?? $normalized[$code]['description'],
            ]);
        }

        return $normalized;
    }

    public function settingsFor(string $code): ?array
    {
        $settings = self::normalizeTemplateSettings($this->supportsTemplateSettings() ? ($this->template_settings ?? []) : []);

        return $settings[$code] ?? null;
    }

    public function isSmsEnabledFor(string $code): bool
    {
        $settings = $this->settingsFor($code);

        return $settings !== null
            && (bool) ($settings['is_active'] ?? false)
            && (bool) ($settings['sms_enabled'] ?? false);
    }

    public function isWhatsappEnabledFor(string $code): bool
    {
        $settings = $this->settingsFor($code);

        return $settings !== null
            && (bool) ($settings['is_active'] ?? false)
            && (bool) ($settings['whatsapp_enabled'] ?? false);
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
            'order_created' => [...$common, 'deposit'],
            'order_delivery_date_change' => [...$common, 'old_due_date', 'new_due_date'],
            'appointment_created', 'appointment_confirmed', 'appointment_rescheduled', 'appointment_cancelled', 'appointment_reminder' => ['customer_name', 'appointment_date', 'appointment_time'],
            'customer_created', 'measurement_recorded', 'thank_you_message' => ['customer_name', 'business_name'],
            'custom_campaign', 'promotional_message' => ['customer_name', 'message'],
            'installment_payment_reminder' => ['customer_name', 'package_name', 'plan_number', 'installment_number', 'total_installments', 'installment_amount', 'due_date', 'remaining_balance'],
            'installment_payment_received' => ['customer_name', 'package_name', 'plan_number', 'payment_amount', 'remaining_balance', 'next_due_date'],
            'installment_completed' => ['customer_name', 'package_name', 'plan_number'],
            'booking_verification' => ['verification_code'],
            'custom_order_progress_update' => ['customer_name', 'order_number', 'stage_label', 'progress_note', 'requested_payment_amount', 'requested_payment_note', 'requested_payment_text'],
            'pos_sale_completed' => ['customer_name', 'sale_number', 'items', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'amount_paid', 'change_amount', 'payment_method', 'cashier_name', 'business_name'],
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
            $attributes = [
                'templates' => self::defaultTemplates(),
            ];

            if (self::supportsWhatsappTemplates()) {
                $attributes['whatsapp_templates'] = self::defaultTemplates();
            }

            if (self::supportsTemplateSettings()) {
                $attributes['template_settings'] = self::defaultTemplateSettings();
            }

            $row = self::create($attributes);
        } else {
            $normalized = self::normalizeTemplates($row->templates);
            $updates = [];

            if ($normalized !== ($row->templates ?? [])) {
                $updates['templates'] = $normalized;
            }

            if (self::supportsWhatsappTemplates()) {
                $normalizedWhatsappTemplates = self::normalizeTemplates($row->whatsapp_templates ?? $row->templates);

                if ($normalizedWhatsappTemplates !== ($row->whatsapp_templates ?? [])) {
                    $updates['whatsapp_templates'] = $normalizedWhatsappTemplates;
                }
            }

            if (self::supportsTemplateSettings()) {
                $normalizedSettings = self::normalizeTemplateSettings($row->template_settings);

                if ($normalizedSettings !== ($row->template_settings ?? [])) {
                    $updates['template_settings'] = $normalizedSettings;
                }
            }

            if ($updates !== []) {
                $row->update($updates);
                $row->refresh();
            }
        }

        return $row;
    }

    public static function supportsTemplateSettings(): bool
    {
        return Schema::hasTable('sms_templates')
            && Schema::hasColumn('sms_templates', 'template_settings');
    }

    public static function supportsWhatsappTemplates(): bool
    {
        return Schema::hasTable('sms_templates')
            && Schema::hasColumn('sms_templates', 'whatsapp_templates');
    }
}
