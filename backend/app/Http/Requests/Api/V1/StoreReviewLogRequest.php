<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ayah_id' => ['required', 'integer', 'exists:ayat,id'],
            'is_correct' => ['required', 'boolean'],
            'correctness_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'time_to_recall_ms' => ['nullable', 'integer', 'min:0'],
            'confidence_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ai_provider_used' => ['nullable', 'string'],
            'ai_evaluation_result' => ['nullable', 'array'],
        ];
    }
}
