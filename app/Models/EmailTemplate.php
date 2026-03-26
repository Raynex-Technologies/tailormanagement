<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['templates'];

    protected function casts(): array
    {
        return [
            'templates' => 'array',
        ];
    }

    public const CATEGORIES = [
        'storefront_payment_confirmation',
        'invoice',
    ];

    public static function categoryLabels(): array
    {
        return [
            'storefront_payment_confirmation' => __('Payment Confirmation'),
            'invoice' => __('Invoice Email'),
        ];
    }

    public static function defaultTemplates(): array
    {
        return [
            'storefront_payment_confirmation' => [
                'subject' => 'Payment confirmed for order #{order_number}',
                'body' => "Hello {customer_name},\n\nWe have received your payment of {currency} {amount_paid} for order #{order_number}.\n\nOrder total: {currency} {order_total}\nPayment method: {payment_method}\nReference: {payment_reference}\n\nYou can track your order here: {order_url}\n\nThank you,\n{business_name}",
            ],
            'invoice' => [
                'subject' => 'Invoice {invoice_number} for order #{order_number}',
                'body' => "Hello {customer_name},\n\nYour invoice {invoice_number} for order #{order_number} is ready.\n\nTotal: {currency} {order_total}\nPaid: {currency} {paid_amount}\nAmount due: {currency} {amount_due}\n\nA copy of your invoice is attached to this email.\n\nRegards,\n{business_name}",
            ],
        ];
    }

    public static function variableDefinitions(): array
    {
        return [
            'business_name' => [
                'label' => __('Business Name'),
                'description' => __('Your business name.'),
            ],
            'customer_name' => [
                'label' => __('Customer Name'),
                'description' => __('Recipient customer full name.'),
            ],
            'order_number' => [
                'label' => __('Order Number'),
                'description' => __('Order reference number.'),
            ],
            'invoice_number' => [
                'label' => __('Invoice Number'),
                'description' => __('Invoice reference number.'),
            ],
            'currency' => [
                'label' => __('Currency'),
                'description' => __('Order currency code.'),
            ],
            'order_total' => [
                'label' => __('Order Total'),
                'description' => __('Total payable amount for the order.'),
            ],
            'paid_amount' => [
                'label' => __('Paid Amount'),
                'description' => __('Amount already paid for the order.'),
            ],
            'amount_due' => [
                'label' => __('Amount Due'),
                'description' => __('Remaining unpaid amount.'),
            ],
            'payment_method' => [
                'label' => __('Payment Method'),
                'description' => __('Checkout payment method name.'),
            ],
            'payment_reference' => [
                'label' => __('Payment Reference'),
                'description' => __('Gateway merchant or tracking reference.'),
            ],
            'order_url' => [
                'label' => __('Order Link'),
                'description' => __('Link to customer order details page.'),
            ],
        ];
    }

    public static function variablesForCategory(string $category): array
    {
        return match ($category) {
            'storefront_payment_confirmation' => [
                'customer_name',
                'order_number',
                'currency',
                'amount_paid',
                'order_total',
                'payment_method',
                'payment_reference',
                'order_url',
                'business_name',
            ],
            'invoice' => [
                'customer_name',
                'invoice_number',
                'order_number',
                'currency',
                'order_total',
                'paid_amount',
                'amount_due',
                'business_name',
            ],
            default => [],
        };
    }

    public static function normalizeTemplates(?array $templates): array
    {
        $defaults = self::defaultTemplates();
        $normalized = [];

        foreach ($defaults as $key => $defaultTemplate) {
            $current = $templates[$key] ?? [];
            if (! is_array($current)) {
                $current = [];
            }

            $normalized[$key] = [
                'subject' => (string) ($current['subject'] ?? $defaultTemplate['subject']),
                'body' => (string) ($current['body'] ?? $defaultTemplate['body']),
            ];
        }

        return $normalized;
    }

    /**
     * Get the singleton templates row.
     */
    public static function instance(): self
    {
        $row = self::first();

        if (! $row) {
            return self::create([
                'templates' => self::defaultTemplates(),
            ]);
        }

        $normalized = self::normalizeTemplates($row->templates);

        if ($normalized !== ($row->templates ?? [])) {
            $row->update([
                'templates' => $normalized,
            ]);
            $row->refresh();
        }

        return $row;
    }

    public function template(string $category): array
    {
        return self::normalizeTemplates($this->templates)[$category]
            ?? self::defaultTemplates()[$category];
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }
}

