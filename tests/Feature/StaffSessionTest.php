<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_masa_sesi_staf_delapan_jam(): void
    {
        $this->assertSame(480, (int) config('session.lifetime'));
    }

    public function test_sesi_staf_disimpan_di_redis_bukan_tabel(): void
    {
        config(['session.driver' => 'redis']);

        $this->assertFalse(Schema::hasTable('sessions'));

        $handler = $this->app['session']->driver('redis')->getHandler();
        $this->assertInstanceOf(CacheBasedSessionHandler::class, $handler);

        $before = count(Redis::connection()->keys('*'));

        User::factory()->create([
            'phone_number' => '628123456789',
            'role' => 'receptionist',
            'password_hash' => 'rahasia-staf',
        ]);

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'rahasia-staf',
        ])->assertOk();

        $after = count(Redis::connection()->keys('*'));

        $this->assertGreaterThan($before, $after, 'Sesi staf tidak tersimpan di Redis.');
    }
}
