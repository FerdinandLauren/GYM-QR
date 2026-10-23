<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GymSettingsSingleRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_chk_gym_single_row_rejects_second_row(): void
    {
        DB::table('gym_settings')->insert($this->settings(1));

        $rejected = null;

        try {
            DB::table('gym_settings')->insert($this->settings(2));
        } catch (QueryException $exception) {
            $rejected = $exception;
        }

        $this->assertNotNull($rejected, 'Baris kedua pada gym_settings seharusnya ditolak.');
        $this->assertStringContainsString('chk_gym_single_row', $rejected->getMessage());
        $this->assertSame(1, DB::table('gym_settings')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(int $gymId): array
    {
        return [
            'gym_id' => $gymId,
            'gym_name' => 'GymPass QR',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'qr_payload' => 'gympass-qr-payload',
        ];
    }
}
