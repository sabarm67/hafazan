<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relying Party ID
    |--------------------------------------------------------------------------
    |
    | The relying party ID represents your application in the WebAuthn protocol.
    | This is typically your domain (e.g., "example.com"). Passkeys are bound
    | to this ID and can only be verified on matching domains.
    |
    */

    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The origins permitted to complete WebAuthn ceremonies. Passkeys bound
    | to the relying party ID above will only verify when the browser
    | reports one of these origins. Defaults to the app URL plus the local
    | Vite dev server origins (matching SANCTUM_STATEFUL_DOMAINS in
    | .env.example) so registration/login work against `npm run dev`
    | without extra config; production is single-origin (see
    | docs/02-system-architecture.md "Production Deployment"), so app.url
    | alone covers it there.
    |
    */

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('PASSKEYS_ALLOWED_ORIGINS', config('app.url').',http://localhost:5173,http://127.0.0.1:5173'))
    )),

    /*
    |--------------------------------------------------------------------------
    | User Handle Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to derive a stable WebAuthn user handle from each user model.
    | Set this explicitly if you rotate your application key.
    |
    */

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),

    /*
    |--------------------------------------------------------------------------
    | WebAuthn Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in milliseconds for WebAuthn operations. This determines
    | how long users have to complete passkey registration or verification.
    |
    */

    'timeout' => 60000,

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use when logging in users with passkeys.
    | This should match your application's primary authentication guard.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Package routes
    |--------------------------------------------------------------------------
    |
    | The package's own routes are disabled entirely — see
    | Passkeys::ignoreRoutes() in AppServiceProvider::boot(). This app
    | defines its own routes/controller under /api/v1 instead, matching
    | production's Nginx config (which only proxies /api, /sanctum, /up)
    | and returning the same {data: {...UserResource}} shape the existing
    | password login/register endpoints use. `middleware`,
    | `management_middleware`, and `redirect` below are therefore unused.
    |
    */

];
