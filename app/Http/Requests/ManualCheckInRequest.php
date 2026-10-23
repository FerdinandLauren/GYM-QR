<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload check-in manual (PRD §7.6) + header Idempotency-Key wajib.
 */
final class ManualCheckInRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'min:1'],
            'membership_id' => ['required', 'integer', 'min:1'],
            'locker_intent' => ['sometimes', 'in:need_locker,no_locker'],
            'reason' => ['required', 'in:no_phone,battery_dead,camera_broken,gps_issue,other'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
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
