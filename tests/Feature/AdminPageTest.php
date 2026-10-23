<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_membuka_panel(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create(['role' => 'admin', 'phone_number' => '628222222222']);

        $this->actingAs($admin, 'staff')
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
    }

    public function test_kasir_tidak_boleh_membuka_panel_admin(): void
    {
        $staff = User::factory()->create(['role' => 'receptionist', 'phone_number' => '628111111111']);

        $this->actingAs($staff, 'staff')->get('/admin')->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin')->assertRedirect('/');
    }
}
