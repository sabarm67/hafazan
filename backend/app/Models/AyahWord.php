<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AyahWord extends Model
{
    protected $fillable = ['ayah_id', 'position', 'text_arabic', 'transliteration', 'translation_ms'];

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
