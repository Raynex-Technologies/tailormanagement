<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Orders\Show as OrderShow;
use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

class AppUiPolish2ATest extends TestCase
{
    public function test_flux_breadcrumbs_use_the_shared_navigation_lime_tokens_globally(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        $order = $this->createOrder($user->id, now()->addWeek()->toDateString());

        $css = file_get_contents(resource_path('css/app.css'));
        $sidebar = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));

        $this->assertStringContainsString('--tailorpro-breadcrumb-accent: color-mix(in srgb, var(--tailorpro-navigation-accent-end)', $css);
        $this->assertStringContainsString('[data-flux-breadcrumbs] [data-flux-breadcrumbs-item] > a', $css);
        $this->assertStringContainsString('var(--tailorpro-navigation-accent-start)', $css);
        $this->assertStringContainsString('var(--tailorpro-navigation-accent-start)', $sidebar);

        foreach ([
            route('orders.index'),
            route('orders.create'),
            route('orders.edit', $order),
            route('orders.show', $order),
            route('orders.stock-requests', $order),
            route('order-catalog.index'),
            route('customers.index'),
            route('invoices.index'),
            route('payments.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-flux-breadcrumbs', false);
        }
    }

    public function test_order_show_uses_na_for_a_missing_due_date_and_formats_a_real_date(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $missingDateOrder = $this->createOrder($user->id, null);

        Livewire::test(OrderShow::class, ['order' => $missingDateOrder])
            ->assertSee('Due N/A')
            ->assertSee('N/A')
            ->assertSee('Change Due Date');

        $realDate = CarbonImmutable::parse('2027-01-15');
        $datedOrder = $this->createOrder($user->id, $realDate->toDateString());

        Livewire::test(OrderShow::class, ['order' => $datedOrder])
            ->assertSee('Jan 15, 2027')
            ->assertSee('Change Due Date');
    }

    public function test_orders_views_no_longer_contain_corrupted_fallback_sequences(): void
    {
        foreach ([
            resource_path('views/livewire/orders/show.blade.php'),
            resource_path('views/livewire/orders/stock-requests/index.blade.php'),
        ] as $view) {
            $source = file_get_contents($view);

            $this->assertTrue(mb_check_encoding($source, 'UTF-8'));
            $this->assertStringNotContainsString('â', $source);
            $this->assertStringNotContainsString('Ã', $source);
            $this->assertStringNotContainsString('Â', $source);
            $this->assertStringNotContainsString('�', $source);
        }
    }

    private function createOrder(int $userId, ?string $dueDate): Order
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        return Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => $dueDate,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $userId,
        ]);
    }
}
