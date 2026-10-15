<?php

namespace App\Http\Requests;

use App\Modules\Assistant\Application\OfficeAssistant;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AssistantMessageRequest extends FormRequest
{
    private const RETIRED_FIELDS = ['mode', 'history', 'year', 'farmer_id'];

    public function authorize(): bool
    {
        return $this->user()?->can('use', OfficeAssistant::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:800'],
            'module' => ['required', Rule::in(OfficeAssistant::MODULES)],
            'context' => ['nullable', 'string', 'max:4096'],
            'choice' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            // Retired clients cannot choose provider disclosure or send transcripts.
            foreach (self::RETIRED_FIELDS as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'Reload this page to use the updated Assistant.');
                }
            }
            $text = $this->input('message');
            if (is_string($text) && preg_match('/sk-[a-zA-Z0-9_-]{12,}/i', $text)) {
                $validator->errors()->add('message', 'Do not enter passwords or account keys in the Assistant.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'message.max' => 'Keep your question within 800 characters.',
            'context.max' => 'Start a new conversation, then try your question again.',
            'choice.max' => 'Find the farmer again before choosing their record.',
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $message = 'Check your question before sending it.';
        foreach (self::RETIRED_FIELDS as $field) {
            if ($this->exists($field)) {
                $message = 'Reload this page to use the updated Assistant.';
                break;
            }
        }
        // This JSON-only action must never flash a chat message into the session.
        throw new HttpResponseException(response()->json([
            'message' => $message, 'errors' => $validator->errors(),
        ], 422));
    }
}
