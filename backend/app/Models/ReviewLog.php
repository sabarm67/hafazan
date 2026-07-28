<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewLog extends Model
{
    protected $fillable = [
        'review_session_id',
        'memorisation_record_id',
        'ayah_id',
        'attempted_at',
        'is_correct',
        'correctness_score',
        'time_to_recall_ms',
        'confidence_level',
        'ai_provider_used',
        'ai_evaluation_result',
        'interval_stage_before',
        'interval_stage_after',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'is_correct' => 'boolean',
            'ai_evaluation_result' => 'array',
        ];
    }

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class);
    }

    public function memorisationRecord(): BelongsTo
    {
        return $this->belongsTo(MemorisationRecord::class);
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
