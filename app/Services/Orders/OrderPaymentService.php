<?php

namespace App\Services\Orders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaymentRecorded;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPaymentService
{
    /**
     * Record a payment for an order.
     *
     * @param  Order  $order  The order to record payment for
     * @param  array  $data  Payment data (amount, method, reference, paid_at, note)
     * @param  User  $actor  The user recording the payment
     *
     * @throws ValidationException
     */
    public function recordPayment(Order $order, array $data, User $actor): OrderPayment
    {
        // Validate amount
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        // Validate method
        $method = $data['method'] ?? null;
        if (! $method || ! in_array($method, PaymentMethod::values())) {
            throw ValidationException::withMessages([
                'method' => 'Invalid payment method.',
            ]);
        }

        return DB::transaction(function () use ($order, $data, $actor, $amount, $method) {
            // Lock the order for update
            $order = Order::lockForUpdate()->find($order->id);

            // Calculate current paid amount
            $currentPaid = $order->payments()->sum('amount');
            $newTotal = $currentPaid + $amount;
            $orderTotal = (float) $order->total;

            // Block overpayment unless global admin (with small tolerance)
            $tolerance = 0.01;
            if ($newTotal > ($orderTotal + $tolerance) && ! $actor->isGlobalAdmin()) {
                $maxPayable = $orderTotal - $currentPaid;
                throw ValidationException::withMessages([
                    'amount' => "Payment amount exceeds remaining balance. Maximum payable: " . money_tzs($maxPayable),
                ]);
            }

            // Create payment record
            $payment = OrderPayment::create([
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]);

            // Recalculate payment status
            $this->recalculatePaymentStatus($order);

            // Fire event
            event(new OrderPaymentRecorded($order->fresh(), $payment, $actor));

            return $payment->load('receiver', 'order');
        });
    }

    /**
     * Recalculate and update the order's payment status.
     */
    public function recalculatePaymentStatus(Order $order): void
    {
        $total = (float) $order->total;
        $paid = (float) $order->payments()->sum('amount');

        $status = match (true) {
            $paid <= 0 => PaymentStatus::Unpaid,
            $paid >= $total => PaymentStatus::Paid,
            default => PaymentStatus::Partial,
        };

        $order->update(['payment_status' => $status]);
    }

    /**
     * Get payment summary for an order.
     */
    public function getPaymentSummary(Order $order): array
    {
        $total = (float) $order->total;
        $paid = (float) $order->payments()->sum('amount');
        $balance = $total - $paid;

        return [
            'total' => $total,
            'paid' => $paid,
            'balance' => max(0, $balance),
            'status' => $order->payment_status,
            'is_fully_paid' => $paid >= $total,
            'is_overpaid' => $paid > $total,
            'overpaid_amount' => $paid > $total ? $paid - $total : 0,
        ];
    }
}
