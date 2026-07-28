<?php

use App\Http\Controllers\Api\V1\AiEvaluationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\AyahController;
use App\Http\Controllers\Api\V1\MemorisationRecordController;
use App\Http\Controllers\Api\V1\ReviewLogController;
use App\Http\Controllers\Api\V1\ReviewSessionController;
use App\Http\Controllers\Api\V1\SurahController;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes
|--------------------------------------------------------------------------
|
| See docs/04-api-design.md for the endpoint catalogue. Everything below is
| real; teacher/parent portal, analytics, and notification endpoints aren't
| routed yet at all (see the comment at the bottom).
|
*/
Route::prefix('v1')->group(function () {

    // --- Auth (real — cookie-based Sanctum SPA auth) ---
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login', LoginController::class);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/auth/me', fn (Request $request) => UserResource::make($request->user()->load('roles')));
    });

    // --- Quran reference data (real — read-only) ---
    Route::get('/surahs', [SurahController::class, 'index']);
    Route::get('/surahs/{number}', [SurahController::class, 'show']);
    Route::get('/surahs/{surahNumber}/ayat', [AyahController::class, 'index']);
    Route::get('/surahs/{surahNumber}/ayat/{ayahNumber}', [AyahController::class, 'show']);
    Route::get('/surahs/{surahNumber}/ayat/{ayahNumber}/translation', [AyahController::class, 'translation']);

    // --- Adaptive Hifz Engine surface (real — Phase 6/7) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('memorisation-records', MemorisationRecordController::class)
            ->parameters(['memorisation-records' => 'id'])
            ->only(['index', 'store', 'show', 'update']);

        Route::apiResource('review-sessions', ReviewSessionController::class)
            ->parameters(['review-sessions' => 'id'])
            ->only(['index', 'store', 'show', 'update']);

        Route::get('/review-sessions/{reviewSessionId}/logs', [ReviewLogController::class, 'index']);
        Route::post('/review-sessions/{reviewSessionId}/logs', [ReviewLogController::class, 'store']);

        // Throttled separately — each call is a real, billed AI provider request.
        Route::post(
            '/surahs/{surahNumber}/ayat/{ayahNumber}/evaluate-recitation',
            [AiEvaluationController::class, 'evaluateRecitation']
        )->middleware('throttle:20,1');
    });

    // --- Not yet routed at all (future phases, listed for visibility) ---
    // Teacher portal (assign/approve/monitor)         — Phase 10
    // Parent portal (progress/streaks/notifications)  — Phase 10
    // Analytics/reports (dashboard, heat maps, PDF)    — Phase 11+
    // Push notification subscriptions                 — Phase 9
});
