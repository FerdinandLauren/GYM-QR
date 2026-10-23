<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data awal lokal. Koordinat, jam tutup, dan jumlah loker masih placeholder
 * sampai owner menjawab Q4/Q5; nilai sebenarnya diisi lewat panel admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('gym_settings')->updateOrInsert(['gym_id' => 1], [
            'gym_name' => 'GymPass QR',
            'timezone' => 'Asia/Jakarta',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'geofence_radius_m' => 30,
            'geofence_accuracy_max_m' => 150,
            'rescan_guard_seconds' => 300,
            'closing_time' => '23:00:00',
            'qr_payload' => 'gympass-qr-local-dev',
        ]);

        DB::table('packages')->insertOrIgnore([
            [
                'code' => 'MONTHLY-30D',
                'name' => 'Paket Bulanan',
                'type' => 'monthly_unlimited',
                'duration_days' => 30,
                'visit_quota' => null,
                'price' => 250000.00,
            ],
            [
                'code' => 'DAY-PASS',
                'name' => 'Day Pass',
                'type' => 'single_visit',
                'duration_days' => 1,
                'visit_quota' => 1,
                'price' => 50000.00,
            ],
        ]);

        $lockers = [];

        foreach (range(1, 5) as $number) {
            $lockers[] = ['locker_number' => sprintf('LK-%02d', $number)];
        }

        DB::table('lockers')->insertOrIgnore($lockers);
    }
}
