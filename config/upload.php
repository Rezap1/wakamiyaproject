<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application upload limits
    |--------------------------------------------------------------------------
    |
    | Keep the application ceiling below the reverse-proxy/PHP request ceiling
    | so oversized multipart requests are rejected deterministically by Laravel
    | while retaining the existing MIME allow-lists in each upload flow.
    |
    */
    'max_kb' => 5120,
    'max_bytes' => 5 * 1024 * 1024,
];
