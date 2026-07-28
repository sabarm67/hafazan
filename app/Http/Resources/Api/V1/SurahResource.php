<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'name_arabic' => $this->name_arabic,
            'name_transliteration' => $this->name_transliteration,
            'name_translation_ms' => $this->name_translation_ms,
            'revelation_type' => $this->revelation_type,
            'total_ayat' => $this->total_ayat,
        ];
    }
}
