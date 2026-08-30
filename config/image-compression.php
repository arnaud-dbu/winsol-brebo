<?php

return [

    /*
    | Master kill-switch for the auto-compression listener.
    */
    'enabled' => env('IMAGE_COMPRESSION_ENABLED', true),

    /*
    | Asset container handles whose uploads should be compressed.
    | Other containers are left untouched.
    |
    | `private` staat er bewust bij: dat zijn de klantuploads bij een offerte-
    | of herstelaanvraag, en die gaan als bijlage mee in de melding naar
    | Winsol. Een onbewerkte gsm-foto is al gauw 5 a 8 MB, en het
    | herstellingsformulier laat er twee toe — samen met de base64-opslag in
    | het bericht zit je dan boven de limiet van zowat elke verzenddienst en
    | bounct de melding. Gecomprimeerd blijft zo'n foto ruim onder 1 MB.
    */
    'containers' => ['assets', 'private'],

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
