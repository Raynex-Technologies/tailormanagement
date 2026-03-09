<?php

namespace App\Services\Installments;

use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentScheduleStatus;
use App\Models\Customer;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Package;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentPlanService
{
    public function __construct(protected InstallmentCalculator $calculator)
    {
    }

    public function create(array $data, User $actor): InstallmentPlan
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var Package $package */
            $package = Package::query()->findOrFail($data['package_id']);
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $branchId = $data['branch_id'] ?? $actor->branch_id ?? $package->branch_id;

            if ((int) $customer->branch_id !== (int) $package->branch_id || (int) $package->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Customer, package, and selected branch must belong to the same branch.',
                ]);
            }

            $price = (float) ($data['package_price'] ?? $package->price);
            $firstDueDate = CarbonImmutable::parse($data['first_due_date']);
            $frequency = $data['payment_frequency'] instanceof InstallmentFrequency
                ? $data['payment_frequency']
                : InstallmentFrequency::from((string) $data['payment_frequency']);
            $schedule = $this->calculator->buildSchedule(
                $price,
                (int) $data['installments_count'],
                $frequency,
                $firstDueDate,
            );

            $plan = InstallmentPlan::create([
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'],
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_price' => $price,
                'package_duration_value' => $package->duration_value,
                'package_duration_unit' => $package->duration_unit,
                'installments_count' => $data['installments_count'],
                'payment_frequency' => $frequency,
                'installment_amount' => $this->calculator->calculateInstallmentAmount($price, (int) $data['installments_count']),
                'start_date' => $data['start_date'],
                'first_due_date' => $data['first_due_date'],
                'maturity_date' => last($schedule)['due_date'],
                'status' => InstallmentPlanStatus::Active,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $plan->schedules()->createMany($schedule);

            return $plan->load(['customer', 'package', 'schedules']);
        });
    }

    public function recordPayment(InstallmentPlan $plan, array $data, User $actor): InstallmentPayment
    {
        return DB::transaction(function () use ($plan, $data, $actor) {
            $remaining = $plan->remaining_balance;
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Payment amount must be greater than zero.',
                ]);
            }

            if ($amount - $remaining > 0.01) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Payment amount cannot exceed the remaining balance.',
                ]);
            }

            $payment = $plan->payments()->create([
                'branch_id' => $plan->branch_id,
                'amount' => $amount,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'received_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]);

            $balance = $amount;
            $schedules = $plan->schedules()
                ->whereIn('status', [InstallmentScheduleStatus::Pending->value, InstallmentScheduleStatus::Partial->value])
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($schedules as $schedule) {
                if ($balance <= 0) {
                    break;
                }

                $outstanding = $schedule->outstanding_amount;

                if ($outstanding <= 0) {
                    continue;
                }

                $allocation = min($balance, $outstanding);
                $newPaidAmount = round((float) $schedule->paid_amount + $allocation, 2);

                $schedule->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount + 0.01 >= (float) $schedule->scheduled_amount
                        ? InstallmentScheduleStatus::Paid->value
                        : InstallmentScheduleStatus::Partial->value,
                    'paid_at' => $newPaidAmount + 0.01 >= (float) $schedule->scheduled_amount ? $data['paid_at'] : null,
                ]);

                $balance = round($balance - $allocation, 2);
            }

            $this->refreshPlanStatus($plan->fresh('schedules'));

            return $payment->load(['paymentMethod', 'receiver']);
        });
    }

    public function refreshPlanStatus(InstallmentPlan $plan): void
    {
        $remaining = $plan->remaining_balance;
        $completed = $remaining <= 0.01;

        $plan->update([
            'status' => $completed ? InstallmentPlanStatus::Completed : InstallmentPlanStatus::Active,
            'last_paid_at' => $plan->payments()->max('paid_at'),
            'completed_at' => $completed ? now() : null,
        ]);
    }
}
