<?php

return [

    // Multisite staat aan in statamic/system.php en werkt niet zonder Pro,
    // dus de default hoort hier true te zijn en niet aan .env te hangen.
    'pro' => env('STATAMIC_PRO_ENABLED', true),

    'addons' => [
        //
    ],

];
