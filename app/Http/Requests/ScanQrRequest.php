<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload scan QR (PRD §7.5) + header Idempotency-Key wajib.
 */
final class ScanQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        $this->merge(['idempotency_key' => is_string($key) && $key !== '' ? $key : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_payload' => ['required', 'string', 'max:128'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'locker_intent' => ['sometimes', 'in:need_locker,no_locker'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Header Idempotency-Key wajib diisi.',
        ];
    }
}
