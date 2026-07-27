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
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-Light.woff2',
            'weight'  => 300,
            'style'   => 'normal',
            'display' => 'swap',
            'preload' => false,
        ],
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-LightItalic.woff2',
            'weight'  => 300,
            'style'   => 'italic',
            'display' => 'swap',
            'preload' => false,
        ],
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-Regular.woff2',
            'weight'  => 400,
            'style'   => 'normal',
            'display' => 'swap',
            'preload' => true,
        ],
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-Italic.woff2',
            'weight'  => 400,
            'style'   => 'italic',
            'display' => 'swap',
            'preload' => false,
        ],
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-Semibold.woff2',
            'weight'  => 600,
            'style'   => 'normal',
            'display' => 'swap',
            'preload' => true,
        ],
        [
            'family'  => 'General Sans',
            'src'     => '/fonts/GeneralSans-SemiboldItalic.woff2',
            'weight'  => 600,
            'style'   => 'italic',
            'display' => 'swap',
            'preload' => false,
        ],
    ],

];
