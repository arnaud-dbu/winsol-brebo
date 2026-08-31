<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics aan of uit
    |--------------------------------------------------------------------------
    |
    | Uit betekent: geen Tag Manager, geen GA4, geen consent-defaults. Bedoeld
    | om een omgeving volledig buiten de metingen te houden.
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager container ID
    |--------------------------------------------------------------------------
    |
    | Tags die hierdoorheen laden moeten de keuze van de bezoeker respecteren
    | — zie de Consent Mode v2-defaults in de analytics-partial, die op
    | `denied` staan tot de bezoeker toestemt.
    |
    | De waarde staat hier als default en niet alleen in .env: een
    | container-id is een publieke identificator die sowieso in de broncode
    | van elke pagina staat, dus er valt niets te verbergen. Zo werkt het
    | meteen op staging en productie zonder dat er per omgeving iets gezet
    | moet worden.
    |
    | `?:` en niet de tweede parameter van env(): staging en productie hebben
    | GTM_CONTAINER_ID al leeg in hun .env staan, en een lege waarde is voor
    | env() een echte waarde die de default zou overschrijven. Een omgeving
    | buiten de metingen houden doe je daarom met ANALYTICS_ENABLED=false,
    | niet door deze sleutel leeg te maken.
    |
    */

    'gtm_container_id' => env('GTM_CONTAINER_ID') ?: 'GTM-MD6NGCCV',

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 meet-id
    |--------------------------------------------------------------------------
    |
    | LET OP: GA4 laadt hier rechtstreeks via gtag.js. Voeg daarom géén
    | GA4-configuratietag toe binnen Tag Manager — dan telt elke paginaweergave
    | dubbel. Wil je GA4 liever volledig via Tag Manager beheren, zet deze
    | waarde dan leeg en maak de configuratietag daar aan.
    |
    */

    'ga4_measurement_id' => env('GA4_MEASUREMENT_ID') ?: 'G-598NEYQ382',

];
