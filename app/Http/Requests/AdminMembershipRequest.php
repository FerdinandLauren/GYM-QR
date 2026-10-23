<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aksi admin pada membership (FR-ADM-07).
 */
final class AdminMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:suspend,reactivate,cancel,extend'],
            'reason' => ['required', 'string', 'max:255'],
            'days' => ['required_if:action,extend', 'integer', 'min:1', 'max:365'],
        ];
    }
}
