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
        'alquran_cloud_base_url' => env('ALQURAN_CLOUD_BASE_URL', 'https://api.alquran.cloud/v1'),
        'audio_base_url' => env('QURAN_AUDIO_BASE_URL', 'https://cdn.islamic.network/quran/audio'),
        'cache_ttl_seconds' => env('QURAN_CACHE_TTL_SECONDS', 604800),
        // Path to the downloaded Tanzil Uthmani corpus consumed by
        // `php artisan quran:import-tanzil`. See database/data/quran-uthmani/README.md.
        'tanzil_corpus_path' => env('TANZIL_CORPUS_PATH', database_path('data/quran-uthmani/quran-uthmani.txt')),
    ],

    'default_reciter' => env('QURAN_DEFAULT_RECITER', 'ar.alafasy'),

];
