<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menandai membership kedaluwarsa. Status hanya untuk tampilan/laporan;
 * keputusan check-in tetap memakai effective_status saat request (PRD §4.3).
 */
final class ExpireMemberships extends Command
{
    protected $signature = 'memberships:expire';

    protected $description = 'Tandai membership yang end_date-nya lewat atau kuota Day Pass habis';

    public function handle(): int
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $expiredByDate = DB::table('memberships')
            ->whereNotIn('status', ['cancelled', 'suspended'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->update(['status' => 'expired']);

        $expiredByQuota = DB::table('memberships')
            ->whereNotIn('status', ['cancelled', 'suspended'])
            ->where('type', 'single_visit')
            ->where('remaining_visits', 0)
            ->update(['status' => 'expired']);

        $this->info("Membership kedaluwarsa: {$expiredByDate} karena tanggal, {$expiredByQuota} karena kuota.");

        return self::SUCCESS;
    }
}
