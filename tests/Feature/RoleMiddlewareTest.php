<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:staff', 'role:admin'])
            ->get('/api/v1/_test/admin-only', fn (): JsonResponse => response()->json(['status' => 'success']));

        Route::middleware(['auth:staff', 'role:receptionist,admin'])
            ->get('/api/v1/_test/reception', fn (): JsonResponse => response()->json(['status' => 'success']));
    }

    public function test_receptionist_ditolak_di_endpoint_admin(): void
    {
        $this->actingAs($this->staff('receptionist'), 'staff')
            ->getJson('/api/v1/_test/admin-only')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_admin_diterima_di_endpoint_admin(): void
    {
        $this->actingAs($this->staff('admin'), 'staff')
            ->getJson('/api/v1/_test/admin-only')
            ->assertOk();
    }

    public function test_receptionist_diterima_di_endpoint_kasir(): void
    {
        $this->actingAs($this->staff('receptionist'), 'staff')
            ->getJson('/api/v1/_test/reception')
            ->assertOk();
    }

    public function test_member_tidak_punya_sesi_staf(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'staff')
            ->getJson('/api/v1/_test/reception')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_tanpa_sesi_menghasilkan_unauthenticated(): void
    {
        $this->getJson('/api/v1/_test/reception')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }
}
