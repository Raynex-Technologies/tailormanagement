<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderDueDateChanged;
use App\Livewire\Orders\Show as OrderShow;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDueDateChangeTest extends TestCase
{
    public function test_authorized_user_can_update_order_due_date_from_order_show(): void
    {
        Event::fake([OrderDueDateChanged::class]);

        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $oldDueDate = now()->addDays(4)->toDateString();
        $newDueDate = now()->addDays(9)->toDateString();

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'order_date' => now()->toDateString(),
            'due_date' => $oldDueDate,
            'subtotal' => 65000,
            'discount' => 0,
            'total' => 65000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('openDueDateModal')
            ->set('updatedDueDate', $newDueDate)
            ->call('updateDueDate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'due_date' => $newDueDate,
        ]);

        Event::assertDispatched(OrderDueDateChanged::class, function (OrderDueDateChanged $event) use ($order, $oldDueDate, $newDueDate, $user) {
            return $event->order->is($order)
                && $event->oldDueDate === $oldDueDate
                && $event->newDueDate === $newDueDate
                && $event->actor->is($user);
        });
    }

    public function test_due_date_change_rejects_dates_before_today(): void
    {
        Event::fake([OrderDueDateChanged::class]);

        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'subtotal' => 40000,
            'discount' => 0,
            'total' => 40000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('openDueDateModal')
            ->set('updatedDueDate', now()->subDay()->toDateString())
            ->call('updateDueDate')
            ->assertHasErrors(['updatedDueDate' => 'after_or_equal']);

        Event::assertNotDispatched(OrderDueDateChanged::class);
    }
}
