<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Penjualan / perpanjangan paket oleh kasir (FR-CAS-08, PRD §4.3).
 */
final class MembershipSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone_number');

        if (is_string($phone) && $phone !== '') {
            $this->merge(['phone_number' => PhoneNumber::normalize($phone)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:new,renew'],
            'phone_number' => ['required', 'string', 'max:20'],
            'package_id' => ['required', 'integer', 'min:1'],
            'full_name' => ['exclude_unless:action,new', 'required', 'string', 'max:100'],
            'photo_url' => ['exclude_unless:action,new', 'required', 'url', 'max:255'],
            'consent' => ['exclude_unless:action,new', 'required', 'accepted'],
        ];
    }
}
