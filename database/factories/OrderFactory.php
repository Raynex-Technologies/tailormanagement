<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(OrderStatus::cases());
        $subtotal = fake()->numberBetween(50000, 500000);
        $discount = fake()->optional(0.3)->numberBetween(0, $subtotal * 0.1) ?? 0;
        $total = $subtotal - $discount;

        // Determine payment status based on order status
        $paymentStatus = match ($status) {
            OrderStatus::Completed, OrderStatus::Delivered => PaymentStatus::Paid,
            OrderStatus::Cancelled => PaymentStatus::Unpaid,
            default => fake()->randomElement(PaymentStatus::cases()),
        };

        return [
            'customer_id' => Customer::inRandomOrder()->first()?->id ?? Customer::factory(),
            'assigned_tailor_id' => User::role('tailor')->inRandomOrder()->first()?->id,
            'status' => $status,
            'due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'priority' => fake()->randomElement(Priority::cases()),
            'notes' => fake()->optional(0.4)->sentence(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'payment_status' => $paymentStatus,
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            // Create 1-3 order lines
            $lineCount = fake()->numberBetween(1, 3);
            $garments = [
                'Men\'s Suit - 2 Piece',
                'Men\'s Suit - 3 Piece',
                'Men\'s Shirt',
                'Men\'s Trousers',
                'Women\'s Dress',
                'Women\'s Blouse',
                'Women\'s Skirt',
                'Traditional Kitenge Dress',
                'Kaftan',
                'School Uniform Set',
            ];

            $subtotal = 0;
            for ($i = 0; $i < $lineCount; $i++) {
                $qty = fake()->numberBetween(1, 3);
                $unitPrice = fake()->numberBetween(25000, 150000);
                $lineTotal = $qty * $unitPrice;
                $subtotal += $lineTotal;

                $line = $order->lines()->create([
                    'item_name' => fake()->randomElement($garments),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'notes' => fake()->optional(0.2)->sentence(),
                ]);

                // Add measurements for some lines
                if (fake()->boolean(70)) {
                    $line->measurement()->create([
                        'measurements' => [
                            'chest' => fake()->numberBetween(90, 120),
                            'waist' => fake()->numberBetween(70, 100),
                            'hips' => fake()->numberBetween(85, 115),
                            'shoulder' => fake()->numberBetween(40, 50),
                            'sleeve_length' => fake()->numberBetween(55, 70),
                            'body_length' => fake()->numberBetween(65, 85),
                        ],
                        'notes' => fake()->optional(0.3)->sentence(),
                    ]);
                }
            }

            // Update order totals
            $discount = fake()->optional(0.3)->numberBetween(0, $subtotal * 0.1) ?? 0;
            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            // Add payment if partially or fully paid
            if ($order->payment_status !== PaymentStatus::Unpaid) {
                $paidAmount = $order->payment_status === PaymentStatus::Paid
                    ? $order->total
                    : fake()->numberBetween($order->total * 0.3, $order->total * 0.7);

                $order->payments()->create([
                    'branch_id' => $order->branch_id,
                    'amount' => $paidAmount,
                    'method' => fake()->randomElement(['cash', 'mobile', 'bank']),
                    'reference' => fake()->optional(0.5)->numerify('REF-####'),
                    'paid_at' => fake()->dateTimeBetween($order->created_at, 'now'),
                    'received_by' => User::inRandomOrder()->first()?->id,
                    'note' => fake()->optional(0.2)->sentence(),
                ]);
            }
        });
    }
}
