<?php

namespace App\Models;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemorisationRecord extends Model
{
    protected $fillable = [
        'user_id',
        'ayah_id',
        'memory_strength_score',
        'last_recall_at',
        'recall_count',
        'mistake_count',
        'current_interval_stage',
        'next_review_date',
        'classification',
        'classification_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_recall_at' => 'datetime',
            'next_review_date' => 'date',
            'classification_updated_at' => 'datetime',
            'current_interval_stage' => ReviewIntervalStage::class,
            'classification' => MemorisationClassification::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(ReviewLog::class);
    }
}
