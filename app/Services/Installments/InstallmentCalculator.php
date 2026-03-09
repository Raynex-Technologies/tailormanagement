<?php

namespace App\Services\Installments;

use App\Enums\InstallmentFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InstallmentCalculator
{
    public function calculateInstallmentAmount(float $packagePrice, int $installmentsCount): float
    {
        if ($installmentsCount < 1) {
            throw new InvalidArgumentException('Installments count must be at least 1.');
        }

        return round($packagePrice / $installmentsCount, 2);
    }

    public function buildSchedule(
        float $packagePrice,
        int $installmentsCount,
        InstallmentFrequency $frequency,
        CarbonImmutable $firstDueDate,
    ): array {
        $baseAmount = $this->calculateInstallmentAmount($packagePrice, $installmentsCount);
        $rows = [];
        $allocated = 0.0;

        for ($number = 1; $number <= $installmentsCount; $number++) {
            $amount = $number === $installmentsCount
                ? round($packagePrice - $allocated, 2)
                : $baseAmount;

            $rows[] = [
                'installment_number' => $number,
                'due_date' => $frequency->addTo($firstDueDate, $number - 1)->toDateString(),
                'scheduled_amount' => $amount,
                'paid_amount' => 0,
                'status' => 'pending',
                'paid_at' => null,
            ];

            $allocated += $amount;
        }

        return $rows;
    }
}
