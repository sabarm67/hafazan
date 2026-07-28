<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AyahTranslation extends Model
{
    protected $fillable = ['ayah_id', 'locale', 'translation_text', 'source'];

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
