<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Must happen in register(), not boot() — the package resolves this
        // before boot() runs on some requests (e.g. artisan commands).
        Passkeys::useUserModel(User::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The package's own routes assume a page-based app (redirects,
        // `password.confirm` middleware). This app defines its own
        // controller/routes under /api/v1 instead — see config/passkeys.php.
        Passkeys::ignoreRoutes();

        // The passwordless/usernameless login flow (GenerateVerificationOptions
        // with no user) can't scope by email, so this is IP-only — same
        // precedent as Sanctum's own throttle:api default.
        RateLimiter::for('passkey', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
