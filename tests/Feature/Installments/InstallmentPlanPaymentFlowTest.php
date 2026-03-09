<?php

namespace Tests\Feature\Installments;

use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentScheduleStatus;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Package;
use App\Services\Installments\InstallmentPlanService;
use Tests\TestCase;

class InstallmentPlanPaymentFlowTest extends TestCase
{
    public function test_it_creates_a_schedule_and_allocates_payments_across_installments(): void
    {
        $user = $this->actingAsRole('branch_manager');
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $package = Package::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Wedding Collection',
            'price' => 1200000,
            'duration_value' => 6,
            'duration_unit' => 'months',
        ]);

        $service = app(InstallmentPlanService::class);

        $plan = $service->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'package_price' => 1200000,
            'installments_count' => 6,
            'payment_frequency' => InstallmentFrequency::Monthly,
            'start_date' => '2026-03-02',
            'first_due_date' => '2026-04-02',
        ], $user);

        $this->assertInstanceOf(InstallmentPlan::class, $plan);
        $this->assertCount(6, $plan->schedules);
        $this->assertEquals(200000.00, (float) $plan->installment_amount);

        $service->recordPayment($plan->fresh(['schedules', 'payments']), [
            'amount' => 400000,
            'payment_method_id' => 1,
            'paid_at' => '2026-04-02 10:00:00',
        ], $user);

        $plan = $plan->fresh(['schedules', 'payments']);

        $this->assertEquals(400000.00, $plan->total_paid);
        $this->assertEquals(800000.00, $plan->remaining_balance);
        $this->assertEquals(InstallmentPlanStatus::Active, $plan->status);
        $this->assertEquals(InstallmentScheduleStatus::Paid, $plan->schedules[0]->status);
        $this->assertEquals(InstallmentScheduleStatus::Paid, $plan->schedules[1]->status);
        $this->assertEquals(InstallmentScheduleStatus::Pending, $plan->schedules[2]->status);

        $service->recordPayment($plan->fresh(['schedules', 'payments']), [
            'amount' => 800000,
            'payment_method_id' => 1,
            'paid_at' => '2026-05-02 10:00:00',
        ], $user);

        $plan = $plan->fresh(['schedules', 'payments']);

        $this->assertEquals(1200000.00, $plan->total_paid);
        $this->assertEquals(0.00, $plan->remaining_balance);
        $this->assertEquals(InstallmentPlanStatus::Completed, $plan->status);
        $this->assertTrue($plan->schedules->every(fn ($schedule) => $schedule->status === InstallmentScheduleStatus::Paid));
    }

    public function test_role_mapping_exposes_the_new_installment_permissions(): void
    {
        $sales = $this->createUserWithRole('sales');
        $accountant = $this->createUserWithRole('accountant');
        $storekeeper = $this->createUserWithRole('storekeeper');

        $this->assertTrue($sales->can('installments.view'));
        $this->assertTrue($sales->can('installments.manage'));
        $this->assertFalse($sales->can('installments.packages.manage'));

        $this->assertTrue($accountant->can('installments.analytics.view'));
        $this->assertTrue($accountant->can('installments.payments.record'));
        $this->assertFalse($accountant->can('installments.manage'));

        $this->assertFalse($storekeeper->can('installments.view'));
    }
}
