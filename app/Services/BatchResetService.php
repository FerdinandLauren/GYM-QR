<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reset loker massal jam tutup: manual, dengan konfirmasi jumlah (FR-CAS-09).
 */
final class BatchResetService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function reset(User $staff, array $data): array
    {
        return DB::transaction(function () use ($staff, $data): array {
            $occupied = DB::table('lockers')->where('status', 'occupied')->lockForUpdate()->get();
            $confirm = (int) $data['confirm_count'];

            if ($confirm !== $occupied->count()) {
                throw ApiException::validationError(
                    'Jumlah loker terisi tidak cocok. Saat ini '.$occupied->count().' loker terisi.'
                );
            }

            $open = DB::table('attendance_logs')->whereNull('checkout_time')->lockForUpdate()->get();
            $now = now();

            DB::table('attendance_logs')
                ->whereNull('checkout_time')
                ->whereNotNull('locker_id')
                ->update([
                    'checkout_time' => $now,
                    'checkout_type' => 'auto_close',
                    'locker_state' => 'returned',
                ]);

            DB::table('attendance_logs')
                ->whereNull('checkout_time')
                ->update([
                    'checkout_time' => $now,
                    'checkout_type' => 'auto_close',
                ]);

            DB::table('lockers')->where('status', 'occupied')->update(['status' => 'available']);

            $this->audit->log($staff, 'locker.batch_reset', 'locker', null, null, [
                'closed_sessions' => $open->count(),
                'released_lockers' => $occupied->count(),
                'locker_numbers' => $occupied->pluck('locker_number')->all(),
            ]);

            return [
                'closed_sessions' => $open->count(),
                'released_lockers' => $occupied->count(),
            ];
        });
    }
}
