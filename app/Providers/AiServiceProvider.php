<?php

namespace App\Providers;

use App\Contracts\AI\AiProviderInterface;
use App\Services\AI\AiProviderManager;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderManager::class);

        $this->app->bind(
            AiProviderInterface::class,
            fn ($app) => $app->make(AiProviderManager::class)->driver()
        );
    }
}
