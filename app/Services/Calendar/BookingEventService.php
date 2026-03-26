<?php

namespace App\Services\Calendar;

use App\Support\BranchContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BookingEventService
{
    protected const TABLE = 'fitting_bookings';

    protected static ?array $columns = null;

    /**
     * Return booking events in a normalized format for calendar UIs.
     */
    public function events(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        if (! Schema::hasTable(self::TABLE)) {
            return collect();
        }

        $columns = $this->columns();
        if ($columns === []) {
            return collect();
        }

        $dateColumn = $this->firstAvailableColumn($columns, [
            'booking_date',
            'scheduled_for',
            'date',
            'start_at',
            'starts_at',
        ]);

        if (! $dateColumn) {
            return collect();
        }

        $query = DB::table(self::TABLE);

        if (in_array('branch_id', $columns, true) && BranchContext::id()) {
            $query->where('branch_id', BranchContext::id());
        }

        if (in_array('status', $columns, true)) {
            $query->whereNotIn('status', ['cancelled', 'completed']);
        }

        $query->whereBetween(
            $dateColumn,
            $this->isDateTimeColumn($dateColumn)
                ? [$startDate->copy()->startOfDay()->toDateTimeString(), $endDate->copy()->endOfDay()->toDateTimeString()]
                : [$startDate->toDateString(), $endDate->toDateString()]
        );

        $rows = $query->orderBy($dateColumn)->get();

        return $rows->map(function ($row) use ($columns, $dateColumn): array {
            $date = Carbon::parse(data_get($row, $dateColumn))->toDateString();
            $startTime = $this->resolveTime($row, $columns, [
                'start_time',
                'start_at',
                'starts_at',
            ], '10:00');
            $endTime = $this->resolveTime($row, $columns, [
                'end_time',
                'end_at',
                'ends_at',
            ], Carbon::createFromFormat('H:i', $startTime)->addMinutes(45)->format('H:i'));

            return [
                'id' => (string) data_get($row, 'id', md5(json_encode($row))),
                'date' => $date,
                'title' => $this->resolveTitle($row, $columns),
                'subtitle' => $this->resolveSubtitle($row, $columns),
                'start' => $startTime,
                'end' => $endTime,
                'type' => 'booking',
            ];
        })->values();
    }

    /**
     * Return booking counts keyed by date (Y-m-d).
     */
    public function dateCounts(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return $this->events($startDate, $endDate)
            ->groupBy('date')
            ->map(fn (Collection $items) => $items->count())
            ->all();
    }

    protected function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        self::$columns = Schema::getColumnListing(self::TABLE);

        return self::$columns;
    }

    protected function firstAvailableColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function isDateTimeColumn(string $column): bool
    {
        return str_ends_with($column, '_at') || in_array($column, ['start_at', 'starts_at'], true);
    }

    protected function resolveTime(object $row, array $columns, array $candidates, string $default): string
    {
        $column = $this->firstAvailableColumn($columns, $candidates);
        if (! $column) {
            return $default;
        }

        $raw = data_get($row, $column);
        if (blank($raw)) {
            return $default;
        }

        try {
            return Carbon::parse((string) $raw)->format('H:i');
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function resolveTitle(object $row, array $columns): string
    {
        $titleColumn = $this->firstAvailableColumn($columns, [
            'title',
            'name',
            'customer_name',
            'description',
        ]);

        $title = $titleColumn ? (string) data_get($row, $titleColumn, '') : '';
        if (filled($title)) {
            return $title;
        }

        return __('Fitting booking');
    }

    protected function resolveSubtitle(object $row, array $columns): string
    {
        $subtitleColumn = $this->firstAvailableColumn($columns, [
            'note',
            'notes',
            'customer_phone',
            'status',
        ]);

        $subtitle = $subtitleColumn ? (string) data_get($row, $subtitleColumn, '') : '';

        return filled($subtitle) ? $subtitle : __('Reserved time slot');
    }
}
