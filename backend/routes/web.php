<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA fallback
|--------------------------------------------------------------------------
|
| In production the Vue PWA is built into public/ alongside this Laravel
| app (see docs/02-system-architecture.md "Production Deployment"). Any
| request that isn't an actual API/Sanctum route falls through to the
| built index.html so Vue Router's client-side routing can take over.
|
| In local API-only dev (no frontend build present in public/), this just
| 404s — the frontend is served separately by the Vite dev server instead.
|
*/
Route::fallback(function () {
    $indexPath = public_path('index.html');

    abort_unless(file_exists($indexPath), 404);

    return response(file_get_contents($indexPath))->header('Content-Type', 'text/html');
});
