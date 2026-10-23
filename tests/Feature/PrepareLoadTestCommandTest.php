<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrepareLoadTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_menyiapkan_member_loker_dan_token(): void
    {
        $path = storage_path('app/load-tokens.txt');

        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('load:prepare', ['--members' => 2, '--lockers' => 3])->assertSuccessful();

        $this->assertSame(2, DB::table('users')->where('role', 'member')->count());
        $this->assertSame(3, DB::table('lockers')->count());
        $this->assertSame(1, DB::table('gym_settings')->count());

        $tokens = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->assertCount(2, $tokens);

        unlink($path);
    }
}
