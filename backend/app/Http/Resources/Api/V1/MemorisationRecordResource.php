<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemorisationRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ayah_id' => $this->ayah_id,
            'surah_number' => $this->whenLoaded('ayah', fn () => $this->ayah->surah->number),
            'number_in_surah' => $this->whenLoaded('ayah', fn () => $this->ayah->number_in_surah),
            'memory_strength_score' => $this->memory_strength_score,
            'classification' => $this->classification->value,
            'current_interval_stage' => $this->current_interval_stage->value,
            'next_review_date' => $this->next_review_date?->toDateString(),
            'last_recall_at' => $this->last_recall_at?->toIso8601String(),
            'recall_count' => $this->recall_count,
            'mistake_count' => $this->mistake_count,
        ];
    }
}
