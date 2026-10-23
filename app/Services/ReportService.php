<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan kunjungan dan peak hours (FR-ADM-03). Data UTC, tampil Asia/Jakarta.
 */
final class ReportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function attendance(string $from, string $to, string $group): array
    {
        [$start, $end] = $this->range($from, $to);

        $period = match ($group) {
            'week' => "DATE_FORMAT(CONVERT_TZ(a.checkin_time, '+00:00', '+07:00'), '%x-W%v')",
            'month' => "DATE_FORMAT(CONVERT_TZ(a.checkin_time, '+00:00', '+07:00'), '%Y-%m')",
            default => "DATE(CONVERT_TZ(a.checkin_time, '+00:00', '+07:00'))",
        };

        $rows = DB::table('attendance_logs as a')
            ->whereBetween('a.checkin_time', [$start, $end])
            ->groupByRaw($period)
            ->orderByRaw($period)
            ->selectRaw("{$period} as period")
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT a.user_id) as unique_members')
            ->selectRaw('ROUND(AVG(CASE WHEN a.checkout_time IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND, a.checkin_time, a.checkout_time) / 60 END), 1) as avg_duration_minutes')
            ->get();

        return $rows->map(fn (object $row): array => [
            'period' => (string) $row->period,
            'sessions' => (int) $row->sessions,
            'unique_members' => (int) $row->unique_members,
            'avg_duration_minutes' => $row->avg_duration_minutes === null ? null : (float) $row->avg_duration_minutes,
        ])->all();
    }

    /**
     * @return array<int, array{hour: int, sessions: int}>
     */
    public function peakHours(string $from, string $to): array
    {
        [$start, $end] = $this->range($from, $to);

        $rows = DB::table('attendance_logs')
            ->whereBetween('checkin_time', [$start, $end])
            ->groupByRaw("HOUR(CONVERT_TZ(checkin_time, '+00:00', '+07:00'))")
            ->selectRaw("HOUR(CONVERT_TZ(checkin_time, '+00:00', '+07:00')) as hour")
            ->selectRaw('COUNT(*) as sessions')
            ->get()
            ->keyBy('hour');

        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = [
                'hour' => $hour,
                'sessions' => (int) ($rows->get($hour)->sessions ?? 0),
            ];
        }

        return $hours;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function csv(array $rows): string
    {
        $lines = ['period,sessions,unique_members,avg_duration_minutes'];

        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $row['period'],
                $row['sessions'],
                $row['unique_members'],
                $row['avg_duration_minutes'] ?? '',
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(string $from, string $to): array
    {
        $start = Carbon::parse($from, 'Asia/Jakarta')->startOfDay()->utc();
        $end = Carbon::parse($to, 'Asia/Jakarta')->endOfDay()->utc();

        return [$start, $end];
    }
}
