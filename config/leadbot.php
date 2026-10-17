<?php

/*
 * De website-assistent: een chatwidget die vragen beantwoordt uit de inhoud
 * van de site en de vier formulieren van de site als gesprek afneemt.
 *
 * Staat standaard uit. Zonder LEADBOT_ENABLED verschijnt er niets op de site
 * en geven alle routes een 404.
 */
return [

    'enabled' => (bool) env('LEADBOT_ENABLED', false),

    /*
     * Toegang tot het demo-ERP-scherm (`/leadbot/demo?key=...`). Leeg laten
     * mag alleen lokaal; op elke andere omgeving blijft het scherm dan dicht.
     */
    'demo_key' => env('LEADBOT_DEMO_KEY'),

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('LEADBOT_MODEL', 'claude-opus-5'),
        'effort' => env('LEADBOT_EFFORT', 'low'),
        'timeout' => 40,
    ],

    /*
     * Leeg: de WhatsApp-knop verdwijnt uit de widget. Het globale mobiele
     * nummer van de site staat nog niet ingevuld, dus dit kan er niet op
     * terugvallen.
     */
    'whatsapp' => env('LEADBOT_WHATSAPP'),

    /*
     * Welke showroom de assistent voorstelt bij een postcode. Een grove
     * indeling op de eerste cijfers; de klant kiest zelf, dit zet alleen de
     * meest logische bovenaan.
     *
     * @var array<string, string> prefix => slug van de showroom
     */
    'postcode_showroom' => [
        '13' => 'winsol-sint-pieters-leeuw',
        '14' => 'winsol-sint-pieters-leeuw',
        '15' => 'winsol-sint-pieters-leeuw',
        '16' => 'winsol-sint-pieters-leeuw',
        '7' => 'winsol-sint-pieters-leeuw',
        '2' => 'winsol-aartselaar',
        '1' => 'winsol-dilbeek',
        '3' => 'winsol-dilbeek',
        '9' => 'winsol-dilbeek',
    ],

    /*
     * De formulieren die de assistent kan afnemen, met de volgorde waarin hij
     * de velden vraagt. Een geneste lijst is één stap: die velden staan samen
     * op één kaartje. De velden zelf, hun keuzes en hun validatie komen uit de
     * blueprint (resources/blueprints/forms); een veld dat hier ontbreekt,
     * vraagt de assistent achteraan alsnog.
     *
     * De volgorde wijkt bewust af van het formulier op de pagina. Eerst het
     * verhaal van de klant, dan pas de gegevens: zo voelt het als een gesprek.
     * En het adres komt vóór de keuze van de showroom, zodat de assistent de
     * dichtstbijzijnde kan voorstellen.
     */
    'forms' => [
        'offerte' => [
            'icon' => 'calculator',
            'order' => ['products', 'project', 'attachment', ['name', 'email', 'phone', 'address'], 'location', 'gdpr'],
        ],
        'herstelling' => [
            'icon' => 'wrench',
            'order' => [
                'product', 'problem', 'photo', 'is_winsol', 'installed', ['facade', 'floor'], 'dimensions', 'invoice',
                ['name', 'email', 'phone', 'address'], 'branch', 'warranty', 'vat_six',
                ['rates_agreed', 'warranty_terms', 'gdpr'],
            ],
        ],
        'brochure' => [
            'icon' => 'book-open-text',
            'order' => ['brochures', ['name', 'email', 'phone', 'address'], 'gdpr'],
        ],
        'contact' => [
            'icon' => 'chat-circle-text',
            'order' => ['message', ['name', 'email']],
        ],
    ],

];
