<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ayah_id' => $this->ayah_id,
            'is_correct' => $this->is_correct,
            'correctness_score' => $this->correctness_score !== null ? (float) $this->correctness_score : null,
            'time_to_recall_ms' => $this->time_to_recall_ms,
            'confidence_level' => $this->confidence_level,
            'interval_stage_before' => $this->interval_stage_before,
            'interval_stage_after' => $this->interval_stage_after,
            'attempted_at' => $this->attempted_at?->toIso8601String(),
            'memorisation_record' => MemorisationRecordResource::make($this->whenLoaded('memorisationRecord')),
        ];
    }
}
