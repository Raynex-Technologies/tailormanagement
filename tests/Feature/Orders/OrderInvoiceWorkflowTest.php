<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Orders\Show as OrderShow;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderInvoiceWorkflowTest extends TestCase
{
    private OrderPaymentService $payments;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = app(OrderPaymentService::class);
        $this->paymentMethod = PaymentMethod::query()->updateOrCreate(
            ['code' => 'cash'],
            ['name' => 'Cash', 'is_enabled' => true],
        );
    }

    public function test_order_detail_displays_the_canonical_invoice_link_financials_and_authorized_actions(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 2390000);

        $component = Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSeeHtml('data-order-invoice-section')
            ->assertSeeHtml('data-order-invoice-number')
            ->assertSeeHtml('data-order-invoice-view-action')
            ->assertSeeHtml('data-order-invoice-print-action')
            ->assertSee($invoice->invoice_no)
            ->assertSee(money_tzs(2390000))
            ->assertSee('Unpaid');

        $document = new \DOMDocument;
        @$document->loadHTML($component->html());
        $xpath = new \DOMXPath($document);

        $numberLink = $xpath->query('//*[@data-order-invoice-number]')->item(0);
        $viewAction = $xpath->query('//*[@data-order-invoice-view-action]')->item(0);
        $printAction = $xpath->query('//*[@data-order-invoice-print-action]')->item(0);

        $this->assertNotNull($numberLink);
        $this->assertNotNull($viewAction);
        $this->assertNotNull($printAction);
        $this->assertSame(route('invoices.show', $invoice), $numberLink->getAttribute('href'));
        $this->assertSame(route('invoices.show', $invoice), $viewAction->getAttribute('href'));
        $this->assertSame(route('invoices.print', $invoice), $printAction->getAttribute('href'));
        $this->assertSame((float) $order->total, (float) $invoice->total);
        $this->get(route('invoices.print', $invoice))->assertOk();
    }

    public function test_user_with_order_access_but_without_financial_access_does_not_see_invoice_actions(): void
    {
        $admin = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($admin, $this->branch, 90000);

        $role = Role::query()->create(['name' => 'order_viewer_without_financials', 'guard_name' => 'web']);
        $role->givePermissionTo('orders.view');
        $viewer = $this->createUserWithRole($role->name, $this->branch);
        $this->actingAs($viewer);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertDontSeeHtml('data-order-invoice-section')
            ->assertDontSeeHtml('data-order-invoice-number')
            ->assertDontSeeHtml('data-order-invoice-view-action')
            ->assertDontSeeHtml('data-order-invoice-print-action')
            ->assertDontSee($invoice->invoice_no);
    }

    public function test_no_payment_invoice_uses_the_derived_unpaid_state(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 100000);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertViewHas('invoiceFinancialSummary', fn (array $summary): bool => $summary['total'] === 100000.0
                && $summary['paid'] === 0.0
                && $summary['balance'] === 100000.0
                && $summary['status'] === PaymentStatus::Unpaid)
            ->assertSee('Unpaid');

        $this->assertSame(0, $order->payments()->count());
        $this->assertSame((float) $invoice->total, $order->financialSummary()['total']);
    }

    public function test_partial_payment_updates_order_and_invoice_details_consistently(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 100000);
        $this->recordPayment($order, $user, 40000);

        $orderComponent = Livewire::test(OrderShow::class, ['order' => $order->fresh()]);
        $invoiceComponent = Livewire::test(InvoiceShow::class, ['invoice' => $invoice->fresh()]);
        $orderSummary = $orderComponent->viewData('invoiceFinancialSummary');
        $invoiceSummary = $invoiceComponent->viewData('financialSummary');

        $this->assertSame($orderSummary, $invoiceSummary);
        $this->assertSame(100000.0, $orderSummary['total']);
        $this->assertSame(40000.0, $orderSummary['paid']);
        $this->assertSame(60000.0, $orderSummary['balance']);
        $this->assertSame(PaymentStatus::Partial, $orderSummary['status']);
        $this->assertSame(PaymentStatus::Partial, $order->fresh()->payment_status);

        $orderComponent
            ->assertSee(money_tzs(40000))
            ->assertSee(money_tzs(60000))
            ->assertSee('Partial');
        $invoiceComponent
            ->assertSee(money_tzs(40000))
            ->assertSee(money_tzs(60000))
            ->assertSee('Partial');
    }

    public function test_multiple_payments_aggregate_to_a_zero_balance_and_paid_state(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 100000);

        $this->recordPayment($order, $user, 25000);
        $this->recordPayment($order, $user, 35000);
        $this->recordPayment($order, $user, 40000);

        $summary = $order->fresh()->financialSummary();

        $this->assertSame(3, $order->payments()->count());
        $this->assertSame(100000.0, $summary['paid']);
        $this->assertSame(0.0, $summary['balance']);
        $this->assertSame(PaymentStatus::Paid, $summary['status']);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        Livewire::test(OrderShow::class, ['order' => $order->fresh()])
            ->assertViewHas('invoiceFinancialSummary', fn (array $invoiceSummary): bool => $invoiceSummary['paid'] === 100000.0
                && $invoiceSummary['balance'] === 0.0
                && $invoiceSummary['status'] === PaymentStatus::Paid)
            ->assertSee('Paid');

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice->fresh()])
            ->assertViewHas('financialSummary', fn (array $invoiceSummary): bool => $invoiceSummary['paid'] === 100000.0
                && $invoiceSummary['balance'] === 0.0
                && $invoiceSummary['status'] === PaymentStatus::Paid);
    }

    public function test_repeated_invoice_sync_remains_idempotent(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 75000);

        $first = Invoice::syncFromOrder($order->fresh(['lines']), $user->id);
        $second = Invoice::syncFromOrder($order->fresh(['lines']), $user->id);

        $this->assertSame($invoice->id, $first->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($invoice->invoice_no, $second->invoice_no);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('order_id', $order->id)->count());
        $this->assertSame(1, $second->lines()->count());
    }

    public function test_branch_scope_and_invoice_order_branch_integrity_remain_enforced(): void
    {
        $admin = $this->actingAsRole('admin', $this->branch);
        [$visibleOrder, $mismatchedInvoice] = $this->createOrderWithInvoice($admin, $this->branch, 50000);
        [, $otherInvoice] = $this->createOrderWithInvoice($admin, $this->otherBranch, 60000);

        DB::table('invoices')->where('id', $mismatchedInvoice->id)->update([
            'branch_id' => $this->otherBranch->id,
        ]);
        $mismatchedInvoice->branch_id = $this->otherBranch->id;

        $this->assertFalse($admin->can('view', $mismatchedInvoice));

        Livewire::test(OrderShow::class, ['order' => $visibleOrder->fresh()])
            ->assertSeeHtml('data-order-invoice-unavailable')
            ->assertDontSee($mismatchedInvoice->invoice_no)
            ->assertDontSeeHtml('data-order-invoice-print-action');

        $manager = $this->createUserWithRole('branch_manager', $this->branch);
        $this->actingAs($manager);
        $this->get(route('invoices.show', $otherInvoice))->assertNotFound();
        $this->get(route('orders.show', $otherInvoice->order_id))->assertNotFound();
    }

    public function test_historical_order_without_an_active_invoice_is_handled_without_regeneration(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);
        [$order, $invoice] = $this->createOrderWithInvoice($user, $this->branch, 80000);
        $invoice->delete();

        Livewire::test(OrderShow::class, ['order' => $order->fresh()])
            ->assertSeeHtml('data-order-invoice-section')
            ->assertSeeHtml('data-order-invoice-unavailable')
            ->assertSee('Invoice unavailable')
            ->assertDontSeeHtml('data-order-invoice-view-action')
            ->assertDontSeeHtml('data-order-invoice-print-action');

        $this->assertSame(0, Invoice::withoutGlobalScope(BranchScope::class)->where('order_id', $order->id)->count());
        $this->assertSame(1, Invoice::withoutGlobalScope(BranchScope::class)->withTrashed()->where('order_id', $order->id)->count());
    }

    /**
     * @return array{0: Order, 1: Invoice}
     */
    private function createOrderWithInvoice(User $user, Branch $branch, float $total): array
    {
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Invoice Workflow Customer',
        ]);

        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'order_type' => 'tailoring',
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'priority' => Priority::Normal,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $order->lines()->create([
            'item_name' => 'Tailored garment',
            'qty' => 1,
            'unit_price' => $total,
            'line_total' => $total,
        ]);
        $order->recalculateTotals();

        $invoice = Invoice::query()
            ->withoutGlobalScopes()
            ->with('order.customer')
            ->where('order_id', $order->id)
            ->firstOrFail();

        return [$order->fresh(), $invoice];
    }

    private function recordPayment(Order $order, User $user, float $amount): void
    {
        $this->payments->recordPayment($order->fresh(), [
            'amount' => $amount,
            'payment_method_id' => $this->paymentMethod->id,
            'paid_at' => now(),
        ], $user);
    }
}
