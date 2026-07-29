<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quran Content Source
    |--------------------------------------------------------------------------
    |
    | Selects the App\Contracts\Quran\QuranContentRepositoryInterface binding
    | in App\Providers\QuranServiceProvider. Only "tanzil_alquran_cloud" ships
    | in this scaffold; add a key + case here when a new source is adopted.
    |
    */
    'source' => env('QURAN_SOURCE', 'tanzil_alquran_cloud'),

    'tanzil_alquran_cloud' => [
        // Only used by getTranslation()'s fallback path for locales not
        // covered by the bundled database/data/quran-translation-*.json
        // files (currently just 'ms') — see TanzilAlQuranCloudRepository.
        // Not reachable from the production server at all (confirmed via
        // diagnostic curl: TCP connections to its IPs hang indefinitely
        // while other HTTPS hosts connect fine), which is why the Arabic
        // text and Malay translation are bundled locally instead of
        // fetched live. Kept for completeness / other environments.
        'alquran_cloud_base_url' => env('ALQURAN_CLOUD_BASE_URL', 'https://api.alquran.cloud/v1'),
        'audio_base_url' => env('QURAN_AUDIO_BASE_URL', 'https://cdn.islamic.network/quran/audio'),
        'cache_ttl_seconds' => env('QURAN_CACHE_TTL_SECONDS', 604800),
    ],

    'default_reciter' => env('QURAN_DEFAULT_RECITER', 'ar.alafasy'),

];
