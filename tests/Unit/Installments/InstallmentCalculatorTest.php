<?php

namespace Tests\Unit\Installments;

use App\Enums\InstallmentFrequency;
use App\Services\Installments\InstallmentCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class InstallmentCalculatorTest extends TestCase
{
    public function test_it_builds_a_balanced_monthly_schedule(): void
    {
        $calculator = new InstallmentCalculator();

        $schedule = $calculator->buildSchedule(
            1200000,
            6,
            InstallmentFrequency::Monthly,
            CarbonImmutable::parse('2026-04-02'),
        );

        $this->assertCount(6, $schedule);
        $this->assertSame('2026-04-02', $schedule[0]['due_date']);
        $this->assertSame('2026-09-02', $schedule[5]['due_date']);
        $this->assertEquals(1200000.00, array_sum(array_column($schedule, 'scheduled_amount')));
        $this->assertEquals(200000.00, $schedule[0]['scheduled_amount']);
        $this->assertEquals(200000.00, $schedule[5]['scheduled_amount']);
    }

    public function test_it_assigns_rounding_difference_to_the_last_installment(): void
    {
        $calculator = new InstallmentCalculator();

        $schedule = $calculator->buildSchedule(
            1000000,
            3,
            InstallmentFrequency::Weekly,
            CarbonImmutable::parse('2026-04-02'),
        );

        $this->assertEquals(333333.33, $schedule[0]['scheduled_amount']);
        $this->assertEquals(333333.33, $schedule[1]['scheduled_amount']);
        $this->assertEquals(333333.34, $schedule[2]['scheduled_amount']);
        $this->assertEquals(1000000.00, array_sum(array_column($schedule, 'scheduled_amount')));
    }
}
