<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SurahResource;
use App\Models\Surah;

class SurahController extends Controller
{
    public function index()
    {
        return SurahResource::collection(Surah::orderBy('number')->get());
    }

    public function show(int $number)
    {
        return SurahResource::make(Surah::where('number', $number)->firstOrFail());
    }
}
