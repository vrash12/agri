<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DisableFarmerPortalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farmer = $this->route('farmer');
        $user = $this->user();

        return $farmer instanceof Farmer && $user instanceof User && $user->can('update', $farmer);
    }

    public function rules(): array
    {
        return [
            '_record_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            '_record_version.required' => 'Reload this page before disabling the farmer account.',
            '_record_version.regex' => 'Reload this page before disabling the farmer account.',
        ];
    }
}
