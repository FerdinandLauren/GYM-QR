<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * AT-08: dua kasir assign loker yang sama secara paralel.
 * Tepat satu sukses, satu 409 LOCKER_TAKEN, loker tetap satu pemilik.
 */
class LockerAssignConcurrencyTest extends TestCase
{
    use DatabaseTruncation, InteractsWithGym;

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_dua_proses_assign_loker_sama_hanya_satu_yang_menang(): void
    {
        $member = User::factory()->create(['full_name' => 'John Doe', 'role' => 'member']);
        $membershipId = $this->createMembership($member);
        $staff = User::factory()->create(['role' => 'receptionist']);
        $lockerId = $this->createLocker('05', 'available');

        $logId = DB::table('attendance_logs')->insertGetId([
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_state' => 'pending',
            'checkin_time' => now()->subMinutes(5),
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 5,
        ]);

        $startAt = (string) (microtime(true) + 2.0);
        $processes = [];

        foreach (['a', 'b'] as $suffix) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/parallel-assign.php'), (string) $staff->user_id, (string) $logId, (string) $lockerId, $startAt],
                base_path(),
                $this->childEnvironment(),
            );
            $process->setTimeout(60);
            $process->start();
            $processes[$suffix] = $process;
        }

        $results = [];

        foreach ($processes as $suffix => $process) {
            $process->wait();

            $output = trim($process->getOutput());

            $this->assertSame(0, $process->getExitCode(), "Proses {$suffix} gagal: ".$process->getErrorOutput().$output);

            $results[$suffix] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }

        $statuses = [$results['a']['status'], $results['b']['status']];
        sort($statuses);

        $this->assertSame(['error', 'success'], $statuses);
        $this->assertContains('LOCKER_TAKEN', [$results['a']['code'] ?? null, $results['b']['code'] ?? null]);

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('assigned', $log->locker_state);
        $this->assertSame($lockerId, (int) $log->locker_id);
        $this->assertSame('occupied', DB::table('lockers')->where('locker_id', $lockerId)->value('status'));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'locker.assigned')->count());
    }

    /**
     * @return array<string, string>
     */
    private function childEnvironment(): array
    {
        $database = config('database.connections.mysql');

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'BCRYPT_ROUNDS' => '4',
        ];
    }
}
