<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FarmerPortalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['login_id' => ['required', 'string', 'max:100'], 'password' => ['required', 'string', 'max:72', function ($attribute, $value, $fail) {
            if (strlen($value) > 72 || str_contains($value, "\0")) {
                $fail('The password could not be verified.');
            }
        }]];
    }
}
