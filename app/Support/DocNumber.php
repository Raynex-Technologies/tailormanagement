<?php

namespace App\Support;

use App\Models\CapitalAllocation;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class DocNumber
{
    /**
     * Generate a unique customer code.
     * Format: CUST-YYYY-XXXXXX
     */
    public static function customer(): string
    {
        return self::generate('CUST', Customer::class, 'code');
    }

    /**
     * Generate a unique order number.
     * Format: ORD-YYYY-XXXXXX
     */
    public static function order(): string
    {
        return self::generate('ORD', Order::class, 'order_no');
    }

    /**
     * Generate a unique purchase request number.
     * Format: PR-YYYY-XXXXXX
     */
    public static function purchaseRequest(): string
    {
        return self::generate('PR', PurchaseRequest::class, 'request_no');
    }

    /**
     * Generate a unique purchase order number.
     * Format: PO-YYYY-XXXXXX
     */
    public static function purchaseOrder(): string
    {
        return self::generate('PO', PurchaseOrder::class, 'po_no');
    }

    /**
     * Generate a unique goods receipt number.
     * Format: GRN-YYYY-XXXXXX
     */
    public static function goodsReceipt(): string
    {
        return self::generate('GRN', GoodsReceipt::class, 'grn_no');
    }

    /**
     * Generate a unique capital allocation number.
     * Format: CAP-YYYY-XXXXXX
     */
    public static function capitalAllocation(): string
    {
        return self::generate('CAP', CapitalAllocation::class, 'allocation_no');
    }

    /**
     * Generate a unique delivery note number.
     * Format: DN-YYYY-XXXXXX
     */
    public static function deliveryNote(): string
    {
        return self::generate('DN', DeliveryNote::class, 'delivery_note_no');
    }

    /**
     * Generate a unique invoice number.
     * Format: INV-YYYY-XXXXXX
     */
    public static function invoice(): string
    {
        return self::sequentialFromTable('INV', 'invoices', 'invoice_no');
    }

    /**
     * Generate a unique document number with the specified prefix.
     *
     * @param  string  $prefix  The prefix (e.g., 'ORD', 'CUST')
     * @param  string  $model  The model class to check for uniqueness
     * @param  string  $column  The column to check for uniqueness
     */
    protected static function generate(string $prefix, string $model, string $column): string
    {
        $year = now()->year;
        $attempts = 0;
        $maxAttempts = 10;

        do {
            // Generate a random 6-digit number
            $random = str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $number = "{$prefix}-{$year}-{$random}";

            // Check if it exists
            $exists = $model::where($column, $number)->exists();
            $attempts++;

            if ($attempts >= $maxAttempts) {
                // If we've tried too many times, use a more unique approach
                $timestamp = now()->format('His');
                $number = "{$prefix}-{$year}-{$timestamp}";

                // Final check
                if ($model::where($column, $number)->exists()) {
                    // Use microtime as last resort
                    $micro = substr(str_replace('.', '', microtime(true)), -6);
                    $number = "{$prefix}-{$year}-{$micro}";
                }
                break;
            }
        } while ($exists);

        return $number;
    }

    /**
     * Generate a sequential document number (alternative approach).
     * Format: PREFIX-YYYY-XXXXXX (where XXXXXX is sequential)
     *
     * @param  string  $prefix  The prefix (e.g., 'ORD', 'CUST')
     * @param  string  $model  The model class
     * @param  string  $column  The column to check
     */
    public static function sequential(string $prefix, string $model, string $column): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";

        // Get the latest number for this year
        $latest = $model::where($column, 'like', $pattern)
            ->orderByRaw("CAST(SUBSTRING({$column}, -6) AS UNSIGNED) DESC")
            ->value($column);

        if ($latest) {
            // Extract the number and increment
            $lastNumber = (int) substr($latest, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%d-%06d', $prefix, $year, $nextNumber);
    }

    /**
     * Generate a sequential number directly from a table/column.
     * This bypasses model scopes (including branch scope and soft-delete scope),
     * so uniqueness checks and sequencing include all stored rows.
     */
    protected static function sequentialFromTable(string $prefix, string $table, string $column): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";
        $regex = '/^' . preg_quote($prefix, '/') . '-' . $year . '-(\d+)$/';

        $existingNumbers = DB::table($table)
            ->where($column, 'like', $pattern)
            ->pluck($column);

        $maxSequence = 0;

        foreach ($existingNumbers as $value) {
            if (! is_string($value)) {
                continue;
            }

            if (! preg_match($regex, $value, $matches)) {
                continue;
            }

            $sequence = (int) $matches[1];
            if ($sequence > $maxSequence) {
                $maxSequence = $sequence;
            }
        }

        $nextSequence = $maxSequence + 1;

        do {
            $candidate = sprintf('%s-%d-%06d', $prefix, $year, $nextSequence);
            $exists = DB::table($table)->where($column, $candidate)->exists();
            $nextSequence++;
        } while ($exists);

        return $candidate;
    }
}
