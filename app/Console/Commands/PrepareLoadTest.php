<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\JwtService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Menyiapkan data untuk uji beban: gym settings, paket, loker, member aktif,
 * dan access token JWT per member.
 */
final class PrepareLoadTest extends Command
{
    protected $signature = 'load:prepare {--members=50} {--lockers=50}';

    protected $description = 'Siapkan data dan token untuk uji beban check-in';

    public function handle(JwtService $jwt): int
    {
        $members = max(1, (int) $this->option('members'));
        $lockers = max(1, (int) $this->option('lockers'));

        if (DB::table('gym_settings')->doesntExist()) {
            DB::table('gym_settings')->insert([
                'gym_id' => 1,
                'gym_name' => 'GymPass Load Test',
                'timezone' => 'Asia/Jakarta',
                'latitude' => 0,
                'longitude' => 0,
                'geofence_radius_m' => 50,
                'geofence_accuracy_max_m' => 150,
                'rescan_guard_seconds' => 0,
                'closing_time' => '23:00:00',
                'qr_payload' => 'GYM_LOAD_'.Str::upper(Str::random(16)),
            ]);
        }

        $packageId = DB::table('packages')->where('code', 'LOAD-MONTHLY')->value('package_id')
            ?? DB::table('packages')->insertGetId([
                'code' => 'LOAD-MONTHLY',
                'name' => 'Load Bulanan',
                'type' => 'monthly_unlimited',
                'duration_days' => 365,
                'visit_quota' => null,
                'price' => 0,
                'is_active' => 1,
            ]);

        for ($index = 1; $index <= $lockers; $index++) {
            DB::table('lockers')->insertOrIgnore([
                'locker_number' => 'L'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }

        $today = now('Asia/Jakarta')->toDateString();
        $tokens = [];

        for ($index = 1; $index <= $members; $index++) {
            $member = User::query()->firstOrCreate(
                ['phone_number' => '628'.str_pad((string) $index, 9, '0', STR_PAD_LEFT)],
                [
                    'full_name' => 'Load Member '.$index,
                    'email' => 'load'.$index.'@example.test',
                    'role' => 'member',
                    'password_hash' => Hash::make(Str::random(32)),
                    'is_active' => true,
                ],
            );

            if (! DB::table('memberships')->where('user_id', $member->user_id)->exists()) {
                DB::table('memberships')->insert([
                    'user_id' => $member->user_id,
                    'package_id' => $packageId,
                    'type' => 'monthly_unlimited',
                    'remaining_visits' => null,
                    'price_snapshot' => 0,
                    'start_date' => $today,
                    'end_date' => now('Asia/Jakarta')->addYear()->toDateString(),
                    'status' => 'active',
                ]);
            }

            $tokens[] = $jwt->issueAccessToken($member);
        }

        $path = storage_path('app/load-tokens.txt');
        file_put_contents($path, implode(PHP_EOL, $tokens).PHP_EOL);

        $this->info("Siap: {$members} member, {$lockers} loker.");
        $this->line('Token: '.$path);
        $this->line('QR payload: '.DB::table('gym_settings')->value('qr_payload'));

        return self::SUCCESS;
    }
}
