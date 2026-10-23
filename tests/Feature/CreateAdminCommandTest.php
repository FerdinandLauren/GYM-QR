<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_membuat_admin_dengan_password_dan_totp(): void
    {
        $this->artisan('app:create-admin', [
            'phone' => '081222333444',
            '--name' => 'Owner',
            '--password' => 'rahasia-admin',
        ])->assertSuccessful();

        $user = DB::table('users')->where('phone_number', '6281222333444')->first();

        $this->assertNotNull($user);
        $this->assertSame('Owner', $user->full_name);
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('rahasia-admin', (string) $user->password_hash));

        $secret = Crypt::decryptString((string) $user->totp_secret);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->assertTrue((new Google2FA)->verifyKey($secret, $code));
    }
}
