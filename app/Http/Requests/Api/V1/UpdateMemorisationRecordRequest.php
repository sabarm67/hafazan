<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Score/stage/classification are engine-managed (see
 * SpacedRepetitionScheduler) — the only thing a learner can do here directly
 * is ask to be reviewed again sooner than the schedule says.
 */
class UpdateMemorisationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reset_for_review' => ['required', 'boolean', 'accepted'],
        ];
    }
}
