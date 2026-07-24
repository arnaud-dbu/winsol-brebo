<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preloaded fonts
    |--------------------------------------------------------------------------
    |
    | Self-hosted woff2 fonts for this project. Each entry generates an
    | @font-face rule, and (when "preload" is true) a <link rel="preload">
    | so the font loads early and text does not shift.
    |
    | Per project:
    |   1. Drop your .woff2 files in public/fonts/
    |   2. Add an entry per face below
    |   3. Point --font-base / --font-display in resources/css/site.css
    |      at the family name
    |
    | Only preload above-the-fold faces (usually body-regular + heading weight)
    | to avoid hurting performance. "weight" may be a range for variable fonts,
    | e.g. '100 900'.
    |
    */

    'fonts' => [
        // [
        //     'family'  => 'Acme',
        //     'src'     => '/fonts/acme-regular.woff2',
        //     'weight'  => 400,
        //     'style'   => 'normal',
        //     'display' => 'swap',
        //     'preload' => true,
        // ],
    ],

];
