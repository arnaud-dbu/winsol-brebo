<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Leesbare collecties
    |--------------------------------------------------------------------------
    |
    | Welke collecties GET /pages teruggeeft. `null` betekent alle collecties.
    | Lezen is bewust breder dan schrijven: Nova heeft de sitestructuur nodig
    | om interne links te kunnen leggen.
    |
    */

    'readable' => null,

    /*
    |--------------------------------------------------------------------------
    | Schrijfbare collecties en hun veldmapping
    |--------------------------------------------------------------------------
    |
    | Per collectie: de API-veldnaam links, de blueprint-handle rechts. Twee
    | namen wijken bewust af. `intro` heet in het blueprint `text`, want een
    | API-veld `text` naast `content` is voor de koppelende partij niet te
    | onderscheiden. En `theme` is enkelvoud omdat max_items 1 het al tot een
    | term beperkt, terwijl de handle `themes` heet.
    |
    */

    'writable' => [
        'articles' => [
            'content_field' => 'redactor',

            /*
            | Canonieke bron voor "verplicht bij het aanmaken". Dit is een
            | contractkeuze (wat POST /pages eist), geen bladwijzer naar het
            | CMS-blueprint: `image` en `content` zijn hier bijvoorbeeld
            | verplicht terwijl hun blueprintveld dat niet is. Zowel
            | `PayloadValidator` als `SchemaBuilder` lezen uitsluitend hier,
            | zodat GET /schema nooit meer iets anders beweert dan wat
            | POST /pages afdwingt.
            */
            'required_on_create' => ['title', 'theme', 'image', 'content'],

            /*
            | Canonieke bron voor tekenlimieten die de API zelf afdwingt,
            | los van een eventuele `character_limit` op het blueprintveld.
            | `title` en `slug` hebben geen `character_limit` in het
            | blueprint; zonder deze lijst zou GET /schema daar stil een
            | lagere of ontbrekende `max` tonen dan wat POST /pages en
            | PATCH /pages/{id} echt afdwingen.
            */
            'max_lengths' => [
                'title' => 255,
                'slug' => 200,
                'external_id' => 255,
                'meta_title' => 60,
                'meta_description' => 160,
            ],

            'fields' => [
                'title' => 'title',
                'intro' => 'text',
                'content' => 'redactor',
                'image' => 'image',
                'theme' => 'themes',
                'slug' => 'slug',
                'date' => 'date',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'meta_image' => 'meta_image',
                'seo_noindex' => 'seo_noindex',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'assets' => [
        'container' => 'assets',
        'folder' => 'inspace',
        'max_kb' => 8192,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Label => sha256-hash van het token. Het token zelf staat nooit in code
    | of config, alleen de hash. Genereren:
    |
    |   php -r 'echo hash("sha256", "jouw-token");'
    |
    */

    'tokens' => array_filter([
        'nova' => env('INSPACE_TOKEN_NOVA'),
    ]),

    'rate_limit' => (int) env('INSPACE_RATE_LIMIT', 120),

];
