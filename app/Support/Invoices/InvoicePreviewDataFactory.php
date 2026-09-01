<?php

namespace App\Support\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderPackageInstance;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvoicePreviewDataFactory
{
    /**
     * Build the canonical invoice view contract entirely in memory.
     *
     * @return array{invoice: Invoice, settings: BusinessSetting, paymentMethods: Collection<int, PaymentMethod>}
     */
    public function make(?BusinessSetting $currentSettings = null): array
    {
        $customer = new Customer([
            'name' => 'Decines Smith',
            'phone' => '0712 345 678',
            'email' => 'customer@example.com',
            'address' => '24 Samora Avenue, Dar es Salaam',
        ]);

        $branch = new Branch([
            'code' => 'BR-MAIN',
            'name' => 'Main Branch',
            'phone' => '+255 22 212 3456',
            'address' => 'Dar es Salaam, Tanzania',
            'is_active' => true,
        ]);

        $package = new OrderPackageInstance([
            'source_template_revision' => 1,
            'package_name' => 'Executive Tailoring Package',
            'package_description' => 'A coordinated formalwear package prepared for the sample invoice.',
            'original_package_total' => 1310000,
            'configured_package_total' => 1310000,
            'original_component_snapshot' => $this->packageComponents(),
            'configured_component_snapshot' => $this->packageComponents(),
        ]);
        $package->id = 9001;

        $payment = new OrderPayment([
            'amount' => 750000,
            'status' => 'completed',
            'paid_at' => Carbon::create(2026, 8, 31, 13, 50),
            'reference' => 'SAMPLE-DEPOSIT',
        ]);

        $order = new Order([
            'order_no' => 'ORD-2026-252273',
            'status' => OrderStatus::InProgress,
            'priority' => Priority::Normal,
            'payment_status' => PaymentStatus::Partial,
            'order_date' => '2026-08-31',
            'due_date' => '2026-09-10',
            'subtotal' => 1750000,
            'discount' => 100000,
            'total' => 1947000,
            'currency' => 'TZS',
        ]);
        $order->setRelation('customer', $customer);
        $order->setRelation('branch', $branch);
        $order->setRelation('payments', new EloquentCollection([$payment]));
        $order->setRelation('packageInstances', new EloquentCollection([$package]));

        $invoiceLines = new EloquentCollection;

        foreach ($this->lineItems() as $index => $item) {
            $lineId = 9100 + $index;
            $sourceLine = new OrderLine([
                'order_package_instance_id' => $item['package'] ? $package->id : null,
                'order_package_template_item_id' => $item['component_id'],
                'item_name' => $item['name'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'notes' => $item['notes'],
                'meta' => $item['meta'],
            ]);
            $sourceLine->id = $lineId;

            $invoiceLine = new InvoiceLine([
                'order_line_id' => $lineId,
                'item_name' => $item['name'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'notes' => $item['notes'],
            ]);
            $invoiceLine->id = $lineId;
            $invoiceLine->setRelation('orderLine', $sourceLine);
            $invoiceLines->push($invoiceLine);
        }

        $invoice = new Invoice([
            'invoice_no' => 'INV-2026-003979',
            'issue_date' => '2026-08-31',
            'due_date' => '2026-09-10',
            'subtotal' => 1750000,
            'discount' => 100000,
            'tax_amount' => 297000,
            'total' => 1947000,
            'notes' => "A deposit of Tsh 750,000 has been received.\nFinal fitting is required before collection.\nPayment is due by Sep 10, 2026.",
        ]);
        $invoice->setRelation('order', $order);
        $invoice->setRelation('branch', $branch);
        $invoice->setRelation('lines', $invoiceLines);

        $settings = new BusinessSetting([
            'business_name' => 'TailorPro',
            'phone' => '+255 757 901 018',
            'alternate_phone' => '+255 754 123 456',
            'email' => 'hello@tailorpro.example',
            'tin_number' => '123-456-789',
            'address' => 'Samora Avenue, Dar es Salaam, Tanzania',
            'logo_path' => $currentSettings?->logo_path,
            'tax_enabled' => true,
            'tax_name' => 'VAT',
            'tax_rate' => 18,
        ]);

        return [
            'invoice' => $invoice,
            'settings' => $settings,
            'paymentMethods' => collect([
                new PaymentMethod([
                    'name' => 'Bank Transfer',
                    'code' => 'bank-transfer',
                    'account_number' => '012 345 678 900',
                    'account_holder_name' => 'TailorPro Limited',
                    'type' => 'offline',
                    'is_enabled' => true,
                ]),
                new PaymentMethod([
                    'name' => 'Mobile Money',
                    'code' => 'mobile-money',
                    'account_number' => '0757 901 018',
                    'account_holder_name' => 'TailorPro',
                    'type' => 'offline',
                    'is_enabled' => true,
                ]),
            ]),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function packageComponents(): array
    {
        return [
            ['template_item_id' => 101, 'name' => "Men's Suit", 'configured_quantity' => 1, 'quantity_behavior' => 'bulk'],
            ['template_item_id' => 102, 'name' => 'Shirt', 'configured_quantity' => 2, 'quantity_behavior' => 'bulk'],
            ['template_item_id' => 103, 'name' => 'Waistcoat', 'configured_quantity' => 1, 'quantity_behavior' => 'bulk'],
        ];
    }

    /** @return array<int, array{name: string, qty: int, unit_price: int, line_total: int, notes: ?string, package: bool, component_id: ?int, meta: array<string, mixed>}> */
    private function lineItems(): array
    {
        return [
            ['name' => "Men's Suit", 'qty' => 1, 'unit_price' => 850000, 'line_total' => 850000, 'notes' => 'Navy wool, peak lapel, full canvas.', 'package' => true, 'component_id' => 101, 'meta' => []],
            ['name' => 'Shirt', 'qty' => 2, 'unit_price' => 120000, 'line_total' => 240000, 'notes' => 'White cotton with monogrammed cuffs.', 'package' => true, 'component_id' => 102, 'meta' => []],
            ['name' => 'Waistcoat', 'qty' => 1, 'unit_price' => 220000, 'line_total' => 220000, 'notes' => null, 'package' => true, 'component_id' => 103, 'meta' => []],
            ['name' => 'Alteration Service', 'qty' => 1, 'unit_price' => 80000, 'line_total' => 80000, 'notes' => 'Final sleeve and trouser adjustment.', 'package' => false, 'component_id' => null, 'meta' => []],
            ['name' => 'Tailored Trousers', 'qty' => 1, 'unit_price' => 180000, 'line_total' => 180000, 'notes' => null, 'package' => false, 'component_id' => null, 'meta' => []],
            ['name' => 'Premium Silk Lining', 'qty' => 1, 'unit_price' => 95000, 'line_total' => 95000, 'notes' => null, 'package' => false, 'component_id' => null, 'meta' => []],
            ['name' => 'Hand Finishing', 'qty' => 1, 'unit_price' => 60000, 'line_total' => 60000, 'notes' => null, 'package' => false, 'component_id' => null, 'meta' => []],
            ['name' => 'Monogram Embroidery', 'qty' => 1, 'unit_price' => 25000, 'line_total' => 25000, 'notes' => 'Initials D.S.', 'package' => false, 'component_id' => null, 'meta' => []],
        ];
    }
}
