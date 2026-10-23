<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashierPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_kasir_dirender(): void
    {
        $this->withoutVite();

        $this->get('/kasir/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Staff/Login'));
    }

    public function test_dasbor_kasir_butuh_login_staf(): void
    {
        $this->get('/kasir')->assertRedirect('/');
    }

    public function test_staf_bisa_membuka_dasbor(): void
    {
        $this->withoutVite();

        $staff = User::factory()->create(['role' => 'receptionist', 'phone_number' => '628111111111']);

        $this->actingAs($staff, 'staff')
            ->get('/kasir')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cashier/Dashboard'));
    }
}
