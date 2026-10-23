<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'regex:/^(?:\+?62|0)8[0-9]{7,12}$/'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
