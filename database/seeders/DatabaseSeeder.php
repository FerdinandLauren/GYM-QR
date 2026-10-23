<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data awal lokal. Koordinat dari Plus Code J7MG+73, Tj. Senang, Bandar Lampung.
 * Radius belum dikalibrasi di lokasi; jam tutup dan jumlah loker menunggu Q4/Q5.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('gym_settings')->updateOrInsert(['gym_id' => 1], [
            'gym_name' => 'GymPass QR',
            'timezone' => 'Asia/Jakarta',
            'latitude' => -5.36681250,
            'longitude' => 105.27518750,
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
