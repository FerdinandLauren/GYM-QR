<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    public function test_route_api_tidak_dikenal_memakai_envelope_404(): void
    {
        $this->getJson('/api/v1/tidak-ada')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['status', 'code', 'message', 'data', 'meta' => ['request_id']]);
    }

    public function test_validation_error_memakai_envelope_400(): void
    {
        Route::middleware('api')->post('/api/v1/uji/validasi', function (Request $request) {
            $request->validate(['nama' => 'required']);
        });

        $this->postJson('/api/v1/uji/validasi', [])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['meta' => ['request_id', 'details' => ['nama']]]);
    }

    public function test_error_server_tidak_membocorkan_detail_internal(): void
    {
        Route::middleware('api')->get('/api/v1/uji/error', function () {
            throw new RuntimeException('rahasia internal');
        });

        $response = $this->getJson('/api/v1/uji/error');

        $response->assertStatus(500)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INTERNAL_ERROR');

        $this->assertStringNotContainsString('rahasia internal', $response->getContent());
    }
}
