<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Unggah foto member untuk verifikasi kasir (D11). Disimpan di disk public.
 */
final class ReceptionistMemberPhotoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('photo')?->store('members', 'public');

        return ApiResponse::success('Foto member diunggah.', [
            'photo_url' => Storage::disk('public')->url((string) $path),
        ], 201);
    }
}
