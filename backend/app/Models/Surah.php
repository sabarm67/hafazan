<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surah extends Model
{
    protected $fillable = [
        'number',
        'name_arabic',
        'name_transliteration',
        'name_translation_ms',
        'revelation_type',
        'total_ayat',
    ];

    public function ayat(): HasMany
    {
        return $this->hasMany(Ayah::class);
    }
}
