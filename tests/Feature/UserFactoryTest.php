<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_membuat_baris_sesuai_kolom_prd(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Budi Santoso',
            'phone_number' => '628123456789',
        ]);

        $this->assertDatabaseHas('users', [
            'user_id' => $user->user_id,
            'full_name' => 'Budi Santoso',
            'phone_number' => '628123456789',
            'role' => 'member',
            'is_active' => 1,
            'password_hash' => null,
        ]);

        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));
    }

    public function test_soft_delete_menyimpan_baris_dan_nomor_hp(): void
    {
        $user = User::factory()->create(['phone_number' => '628111222333']);

        $user->delete();

        $this->assertSoftDeleted('users', ['user_id' => $user->user_id]);
        $this->assertSame('628111222333', User::withTrashed()->findOrFail($user->user_id)->phone_number);
    }
}
