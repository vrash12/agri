<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IssueFarmerPortalActivationRequest extends FormRequest
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
            'identity_verified' => ['required', 'accepted'],
            '_record_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity_verified.required' => 'Confirm that you verified this farmer before issuing an activation code.',
            'identity_verified.accepted' => 'Confirm that you verified this farmer before issuing an activation code.',
            '_record_version.required' => 'Reload this page before issuing an activation code.',
            '_record_version.regex' => 'Reload this page before issuing an activation code.',
        ];
    }
}
