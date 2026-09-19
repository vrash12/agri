<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ActivateFarmerPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login_id' => ['required', 'string', 'max:100'],
            'activation_code' => ['required', 'string', 'size:32'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(15)->uncompromised(), function ($attribute, $value, $fail) {
                // Bcrypt accepts at most 72 bytes, which differs from 72 Unicode characters.
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('This password is too long or contains unsupported characters. Choose a shorter password with at least 15 characters.');
                }
            }],
        ];
    }
}
