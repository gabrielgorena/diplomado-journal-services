<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => 'required|string|min:3|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'A topic prompt is required.',
            'prompt.string' => 'The prompt must be a string.',
        ];
    }
}
