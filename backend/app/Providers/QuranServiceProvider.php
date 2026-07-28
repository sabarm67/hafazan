<?php

namespace App\Providers;

use App\Contracts\Quran\QuranContentRepositoryInterface;
use App\Services\Quran\Repositories\TanzilAlQuranCloudRepository;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class QuranServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuranContentRepositoryInterface::class, function ($app) {
            $source = config('quran.source');

            return match ($source) {
                'tanzil_alquran_cloud' => new TanzilAlQuranCloudRepository(config('quran.tanzil_alquran_cloud')),
                default => throw new RuntimeException("Unknown Quran content source [{$source}]."),
            };
        });
    }
}
