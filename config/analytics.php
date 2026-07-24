<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager container ID
    |--------------------------------------------------------------------------
    |
    | Set GTM_CONTAINER_ID in .env to enable Google Tag Manager. When empty,
    | the analytics partial renders nothing and no tags are loaded. Tags must
    | still respect the visitor's cookie consent choice.
    |
    */

    'gtm_container_id' => env('GTM_CONTAINER_ID'),

];
