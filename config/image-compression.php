<?php

return [

    /*
    | Master kill-switch for the auto-compression listener.
    */
    'enabled' => env('IMAGE_COMPRESSION_ENABLED', true),

    /*
    | Asset container handles whose uploads should be compressed.
    | Other containers are left untouched.
    */
    'containers' => ['assets'],

    /*
    | Max width in pixels. Images wider than this are resized down
    | proportionally. Images narrower are left at their original dimensions
    | (only re-encoded).
    */
    'max_width' => 2500,

    /*
    | JPEG quality (0-100). 85 is visually indistinguishable from the
    | original for photographic content.
    */
    'jpeg_quality' => 85,

    /*
    | Mime types we will process. Anything else is skipped (returned as-is).
    | HEIC requires Imagick; the service degrades gracefully if unavailable.
    */
    'process_mimes' => [
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
    ],
];
