<?php

namespace Tests\Feature\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Roles\Form as RoleForm;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceUiUpgradeTest extends TestCase
{
    public function test_invoice_defaults_show_latest_fifteen_across_months_and_dates_are_optional(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 8));
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->givePermissionTo('invoices.view_kpis');

        $ids = [];
        foreach (range(1, 16) as $index) {
            $invoice = $this->createInvoice($this->branch, "Historical Client {$index}", 10000);
            $invoice->update(['issue_date' => '2026-08-15']);
            $ids[] = $invoice->id;
        }

        $component = Livewire::test(InvoicesIndex::class)
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('perPage', 15)
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 16
                && $invoices->modelKeys() === array_slice(array_reverse($ids), 0, 15))
            ->assertViewHas('kpiPeriodLabel', 'All time')
            ->assertDontSee('vs previous month');

        $component->set('dateFrom', '2026-09-01')
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 0)
            ->set('dateFrom', '')
            ->set('dateTo', '2026-08-14')
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 0)
            ->set('dateFrom', '2026-08-01')
            ->set('dateTo', '2026-08-31')
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 16)
            ->assertSee('vs previous month')
            ->call('gotoPage', 2)
            ->call('clearFilters')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->currentPage() === 1
                && $invoices->count() === 15 && $invoices->total() === 16)
            ->assertDontSee('vs previous month');
    }

    public function test_user_with_invoice_kpi_permission_sees_canonical_invoice_overview(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->givePermissionTo('invoices.view_kpis');

        $invoice = $this->createInvoice($this->branch, 'Current KPI Client', 120000);
        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $invoice->order_id,
            'amount' => 50000,
            'received_by' => $user->id,
        ]);

        $component = Livewire::test(InvoicesIndex::class)
            ->assertSee('Invoice Overview')
            ->assertSeeHtml('data-invoice-kpis')
            ->assertViewHas('canViewKpis', true);

        $this->assertSame(1, $component->viewData('kpis')['invoices']['value']);
        $this->assertSame(120000.0, $component->viewData('kpis')['amount']['value']);
        $this->assertSame(50000.0, $component->viewData('kpis')['paid']['value']);
        $this->assertSame(70000.0, $component->viewData('kpis')['outstanding']['value']);
    }

    public function test_superadmin_retains_invoice_kpi_access_through_the_existing_override(): void
    {
        $user = $this->actingAsRole('superadmin', $this->branch);

        $this->assertTrue($user->can('invoices.view_kpis'));

        Livewire::test(InvoicesIndex::class)
            ->assertViewHas('canViewKpis', true)
            ->assertSee('Invoice Overview');
    }

    public function test_user_with_invoice_access_but_without_kpi_permission_does_not_see_or_query_kpis(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $this->assertFalse($user->can('invoices.view_kpis'));

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        Livewire::test(InvoicesIndex::class)
            ->assertDontSee('Invoice Overview')
            ->assertDontSeeHtml('data-invoice-kpis')
            ->assertViewHas('canViewKpis', false)
            ->assertViewHas('kpis', []);

        $this->assertFalse(collect($queries)->contains(
            fn (string $query): bool => str_contains($query, 'invoice_count')
                || str_contains($query, 'invoiced_total')
                || str_contains($query, 'invoice_payments_total')
        ));
    }

    public function test_invoice_list_search_and_pagination_remain_usable_without_kpi_permission(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        foreach (range(1, 11) as $index) {
            $this->createInvoice($this->branch, "Invoice Client {$index}", 10000 + $index);
        }

        $target = $this->createInvoice($this->branch, 'Needlework Search Client', 45000);

        $component = Livewire::test(InvoicesIndex::class)
            ->set('perPage', 10)
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->count() === 10 && $invoices->hasPages())
            ->set('search', 'Needlework Search Client')
            ->assertSee($target->invoice_no)
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->count() === 1)
            ->assertDontSee('Invoice Overview');

        $component->set('search', $target->order->order_no)
            ->assertSee($target->invoice_no);
    }

    public function test_invoice_table_uses_invoice_link_financial_columns_and_print_only_action(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $invoice = $this->createInvoice($this->branch, 'Table Design Client', 90000);

        $component = Livewire::test(InvoicesIndex::class)
            ->assertSeeHtml('data-invoice-number-link')
            ->assertSeeHtml('data-invoice-row-actions')
            ->assertSeeHtml('data-invoice-print-action')
            ->assertDontSee('Issue Date')
            ->assertDontSee('Due Date')
            ->assertDontSeeHtml('fa-folder-open');

        $document = new \DOMDocument;
        @$document->loadHTML($component->html());
        $xpath = new \DOMXPath($document);

        $invoiceLink = $xpath->query('//*[@data-invoice-number-link]')->item(0);
        $this->assertNotNull($invoiceLink);
        $this->assertSame(route('invoices.show', $invoice), $invoiceLink->getAttribute('href'));

        $rowActions = $xpath->query('//*[@data-invoice-row-actions]//*[self::a or self::button]');
        $this->assertCount(1, $rowActions);
        $this->assertSame(route('invoices.print', $invoice), $rowActions->item(0)->getAttribute('href'));
        $this->assertStringContainsString('Print', trim($rowActions->item(0)->textContent));
    }

    public function test_invoice_detail_financial_summary_uses_canonical_payment_state(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $invoice = $this->createInvoice($this->branch, 'Summary Client', 100000);
        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $invoice->order_id,
            'amount' => 40000,
            'received_by' => $user->id,
        ]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->assertSeeHtml('data-invoice-workspace-header')
            ->assertSeeHtml('data-invoice-financial-summary')
            ->assertSeeHtml('data-invoice-summary-card="total"')
            ->assertSeeHtml('data-invoice-summary-card="paid"')
            ->assertSeeHtml('data-invoice-summary-card="balance"')
            ->assertSeeHtml('data-invoice-summary-card="status"')
            ->assertViewHas('financialSummary', function (array $summary): bool {
                return $summary['total'] === 100000.0
                    && $summary['paid'] === 40000.0
                    && $summary['balance'] === 60000.0
                    && $summary['status'] === PaymentStatus::Partial;
            })
            ->assertSee(money_tzs(100000))
            ->assertSee(money_tzs(40000))
            ->assertSee(money_tzs(60000))
            ->assertSee('Partial');
    }

    public function test_invoice_branch_scope_and_existing_authorization_remain_intact(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $visible = $this->createInvoice($this->branch, 'Main Branch Invoice Client', 30000);

        $this->actingAsRole('admin', $this->branch);
        $hidden = $this->createInvoice($this->otherBranch, 'Other Branch Invoice Client', 40000);
        $this->actingAs($manager);

        Livewire::test(InvoicesIndex::class)
            ->assertSee($visible->invoice_no)
            ->assertDontSee($hidden->invoice_no);

        $this->get(route('invoices.show', $hidden))->assertNotFound();
    }

    public function test_invoice_kpi_permission_is_available_in_role_management(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(RoleForm::class)
            ->assertSee('View Invoice KPIs')
            ->assertDontSee('invoices.view_kpis');
    }

    private function createInvoice(Branch $branch, string $customerName, float $total): Invoice
    {
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'name' => $customerName,
        ]);

        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'priority' => Priority::Normal,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);

        $order->lines()->create([
            'item_name' => 'Tailored garment',
            'qty' => 1,
            'unit_price' => $total,
            'line_total' => $total,
        ]);
        $order->recalculateTotals();

        return Invoice::query()
            ->withoutGlobalScopes()
            ->with('order.customer')
            ->where('order_id', $order->id)
            ->firstOrFail();
    }
}
