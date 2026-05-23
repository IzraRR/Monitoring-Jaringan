<?php

namespace App\Traits;

use Carbon\Carbon;

trait ParsesDateRange
{
    /**
     * Parse date range from request query parameters.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array{start: ?Carbon, end: ?Carbon, startDate: ?string, endDate: ?string}
     */
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = null;
        $end = null;

        if (is_string($startDate) && $startDate !== '') {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            } catch (\Throwable $e) {
                $start = null;
                $startDate = null;
            }
        }

        if (is_string($endDate) && $endDate !== '') {
            try {
                $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
            } catch (\Throwable $e) {
                $end = null;
                $endDate = null;
            }
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            [$startDate, $endDate] = [$start->toDateString(), $end->toDateString()];
        }

        return [
            'start' => $start,
            'end' => $end,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }
}
