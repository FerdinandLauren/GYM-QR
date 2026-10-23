<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * M2 fix: dua proses paralel dengan Idempotency-Key sama hanya membuat satu baris absensi.
 * DatabaseTruncation dipakai karena data harus ter-commit agar terlihat proses anak.
 */
class CheckInIdempotencyConcurrencyTest extends TestCase
{
    use DatabaseTruncation, InteractsWithAuth, InteractsWithGym;

    protected function tearDown(): void
    {
        // Truncation tidak memakai transaksi; bersihkan agar kelas tes berikut mulai dari nol.
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_dua_proses_paralel_dengan_key_sama_hanya_membuat_satu_baris(): void
    {
        $this->createGymSettings();

        $member = $this->createMember([
            'full_name' => 'John Doe',
            'phone_number' => '628123456789',
        ]);
        $this->createMembership($member);

        $token = $this->tokenFor($member);
        $key = (string) Str::uuid();
        $payload = json_encode($this->scanPayload(['locker_intent' => 'need_locker']), JSON_THROW_ON_ERROR);
        $startAt = (string) (microtime(true) + 2.0);

        $processes = [];

        foreach (['a', 'b'] as $suffix) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/parallel-scan.php'), $token, $key, $payload, $startAt],
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

            $this->assertSame(
                0,
                $process->getExitCode(),
                "Proses {$suffix} gagal: ".$process->getErrorOutput().$output,
            );

            $results[$suffix] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }

        $this->assertSame(200, $results['a']['status'], 'Proses A: '.json_encode($results['a']['body']));
        $this->assertSame(200, $results['b']['status'], 'Proses B: '.json_encode($results['b']['body']));
        $this->assertSame(1, DB::table('attendance_logs')->where('idempotency_key', $key)->count());
        $this->assertSame(1, DB::table('attendance_logs')->count());
        $this->assertSame(
            (int) $results['a']['body']['data']['log_id'],
            (int) $results['b']['body']['data']['log_id'],
        );
    }

    /**
     * Proses anak harus memakai database tes, bukan database .env pengembangan.
     *
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
