<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateRecitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transcribed_text' => ['required', 'string', 'max:2000'],
            'audio_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
