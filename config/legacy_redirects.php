<?php

return [

    /*
    |--------------------------------------------------------------------------
    | De nieuwe site
    |--------------------------------------------------------------------------
    |
    | Waar een adres van het oude domein naartoe gaat. Altijd `https`, zodat de
    | omleiding een bezoeker die op `https` binnenkomt nooit degradeert.
    |
    */

    'target' => 'https://winsol-brebo.be',

    /*
    |--------------------------------------------------------------------------
    | Het oude domein
    |--------------------------------------------------------------------------
    |
    | Apex en `www` allebei: de oude site stuurde apex naar `www` naar `http`,
    | drie sprongen met een degradatie erin. Op deze hosts wordt de klimregel
    | toegepast en gaat de omleiding naar `target`. Op elke andere host wordt
    | alleen het pad omgezet, zodat een oud adres dat iemand op de nieuwe site
    | plakt ook aankomt.
    |
    */

    'hosts' => [
        'winsoldilbeek.be',
        'www.winsoldilbeek.be',
    ],

    /*
    |--------------------------------------------------------------------------
    | Brochureadressen
    |--------------------------------------------------------------------------
    |
    | De oude site had per product een aparte downloadpagina per brochure,
    | herkenbaar aan het laatste segment. Die gaan naar de brochurepagina in de
    | taal van het oude pad, gevonden op het eerste segment. Zonder deze regel
    | zou zo'n adres naar de productpagina klimmen: het juiste onderwerp, maar
    | niet wat de bezoeker kwam halen.
    |
    */

    'brochure_slugs' => [
        'depliant-pergola-so',
        'depliant-portes-de-garage',
        'depliant-portes-et-fenetres',
        'depliant-protections-solaires',
        'download-brochure-berner',
        'download-brochure-garagepoorten',
        'download-brochure-genieten-van-outdoor-living',
        'download-brochure-pergola-so',
        'download-brochure-pergola-zip-en-zip-cube-fr',
        'download-brochure-pergola-zip-en-zip-cube-nl',
        'download-brochure-ramen-en-deuren',
        'download-brochure-rolluiken-fr',
        'download-brochure-rolluiken-nl',
        'download-brochure-steel-design',
        'download-brochure-zonwering',
    ],

    'brochures' => [
        '/nl' => '/brochures',
        '/fr' => '/fr/brochures',
    ],

    /*
    |--------------------------------------------------------------------------
    | Waar de klim niet mag uitkomen
    |--------------------------------------------------------------------------
    |
    | De homepages staan als gewone regel in de tabel: `/nl` was de oude
    | Nederlandse homepage. Maar een onbekend adres als `/nl/iets` mag daar
    | niet naartoe klimmen — alles naar de root sturen leest Google als soft
    | 404, en zet de bezoeker op een pagina die zijn vraag niet raakt. De klim
    | slaat deze bestemmingen over en klimt verder; komt hij nergens uit, dan
    | volgt de 404 van de nieuwe site.
    |
    | Een exacte regel mag er wél op uitkomen: wie `/nl/Home/` opvraagt vroeg
    | om de homepage.
    |
    */

    'homepages' => [
        '/',
        '/fr',
    ],

    /*
    |--------------------------------------------------------------------------
    | De tabel
    |--------------------------------------------------------------------------
    |
    | Sleutels zijn kleine letters zonder afsluitende slash: de oude site
    | serveerde dezelfde pagina onder verschillende hoofdletters, en door alles
    | te normaliseren vallen die varianten op dezelfde regel.
    |
    | Er is geen aparte laag voor de 1512 gemeentepagina's. Die vinden geen
    | eigen regel, klimmen naar de wortel van hun productgroep, en die staat
    | hieronder gewoon als normale regel. De klim stopt bij het laatste segment
    | en komt dus nooit op de homepage uit; alles naar de root sturen leest
    | Google als soft 404.
    |
    | De redenering bij de regels die niet vanzelf spreken staat in
    | `.scratch/redirects/mapping.md`.
    |
    */

    'paths' => [
        '/' => '/',
        '/winsol-ramen' => '/aanbod/ramen-en-deuren',
        '/winsol-deuren' => '/aanbod/ramen-en-deuren',
        '/winsol-rolluiken' => '/aanbod/rolluiken',
        '/winsol-zonwering' => '/aanbod/zonwering',
        '/winsol-terrasoverkapping' => '/aanbod/terrasoverkapping',
        '/winsol-velux' => '/aanbod/velux',
        '/fenetres-winsol' => '/fr/gamme/chassis-et-portes',
        '/portes-winsol' => '/fr/gamme/chassis-et-portes',
        '/volets-roulants-winsol' => '/fr/gamme/volets-roulants',
        '/protections-solaires-winsol' => '/fr/gamme/protection-solaire',
        '/couvertures-de-terrasse-winsol' => '/fr/gamme/couverture-de-terrasse',
        '/velux-winsol' => '/fr/gamme/fenetres-de-toit-velux',
        '/nl' => '/',
        '/nl/home' => '/',
        '/nl/contact' => '/contact',
        '/nl/over-ons' => '/over-ons',
        '/nl/inspiratie' => '/realisaties',
        '/nl/simuleer-je-lening' => '/simuleer-je-lening',
        '/nl/screens-verticale-zonwering' => '/aanbod/zonwering/screens',
        '/nl/vacatures' => '/over-ons',
        '/nl/ons-aanbod' => '/aanbod',
        '/nl/ons-aanbod/somfy-smart-home' => '/aanbod/somfy-smart-home',
        '/nl/ons-aanbod/velux' => '/aanbod/velux',
        '/nl/ons-aanbod/airco' => '/aanbod',
        '/nl/ons-aanbod/garagepoorten' => '/aanbod/garagepoorten',
        '/nl/ons-aanbod/garagepoorten/garagerolluiken' => '/aanbod/garagepoorten/garagerolluiken',
        '/nl/ons-aanbod/garagepoorten/sectionale-poorten' => '/aanbod/garagepoorten/sectionale-poorten',
        '/nl/ons-aanbod/garagepoorten/schuifpoorten' => '/aanbod/garagepoorten/sectionale-schuifpoorten',
        '/nl/ons-aanbod/garagepoorten/somfy-smart-home' => '/aanbod/somfy-smart-home',
        '/nl/ons-aanbod/ramen-en-deuren' => '/aanbod/ramen-en-deuren',
        '/nl/ons-aanbod/ramen-en-deuren/aluminium-ramen' => '/aanbod/ramen-en-deuren/aluminium-ramen',
        '/nl/ons-aanbod/ramen-en-deuren/aluminium-ramen-en-deuren' => '/aanbod/ramen-en-deuren/aluminium-ramen-en-deuren',
        '/nl/ons-aanbod/ramen-en-deuren/pvc-ramen' => '/aanbod/ramen-en-deuren/pvc-ramen',
        '/nl/ons-aanbod/ramen-en-deuren/pvc-deuren' => '/aanbod/ramen-en-deuren/pvc-deuren',
        '/nl/ons-aanbod/ramen-en-deuren/sierluiken' => '/aanbod/ramen-en-deuren/sierluiken',
        '/nl/ons-aanbod/ramen-en-deuren/steellook' => '/aanbod/ramen-en-deuren/steellook',
        '/nl/ons-aanbod/ramen-en-deuren/vliegenramen' => '/aanbod/ramen-en-deuren/vliegenramen',
        '/nl/ons-aanbod/ramen-en-deuren/veiligheidsdeuren' => '/aanbod/ramen-en-deuren',
        '/nl/ons-aanbod/rolluiken' => '/aanbod/rolluiken',
        '/nl/ons-aanbod/rolluiken/inbouwrolluiken' => '/aanbod/rolluiken/inbouwrolluiken',
        '/nl/ons-aanbod/rolluiken/opbouwrolluiken' => '/aanbod/rolluiken/opbouwrolluiken',
        '/nl/ons-aanbod/rolluiken/voorzetrolluiken' => '/aanbod/rolluiken/voorzetrolluiken',
        '/nl/ons-aanbod/rolluiken/rolluiken-met-fusion-lamellen' => '/aanbod/rolluiken/rolluiken-met-fusion-lamellen',
        '/nl/ons-aanbod/rolluiken/rolluiken-op-zonne-energie' => '/aanbod/rolluiken/rolluiken-op-zonne-energie',
        '/nl/ons-aanbod/rolluiken/garagerolluiken' => '/aanbod/garagepoorten/garagerolluiken',
        '/nl/ons-aanbod/stalen-deuren' => '/aanbod/stalen-binnendeuren',
        '/nl/ons-aanbod/terrasoverkapping' => '/aanbod/terrasoverkapping',
        '/nl/ons-aanbod/terrasoverkapping/pergola-so' => '/aanbod/terrasoverkapping/pergola-so',
        '/nl/ons-aanbod/terrasoverkapping/pergola-zip' => '/aanbod/terrasoverkapping/pergola-zip',
        '/nl/ons-aanbod/terrasoverkapping/pergola-zip-cube' => '/aanbod/terrasoverkapping/pergola-zip-cube',
        '/nl/ons-aanbod/terrasoverkapping/win-cube' => '/aanbod/terrasoverkapping/wincube',
        '/nl/ons-aanbod/terrasoverkapping/somfy-smart-home' => '/aanbod/somfy-smart-home',
        '/nl/ons-aanbod/terrasoverkapping/patiola' => '/aanbod/terrasoverkapping',
        '/nl/ons-aanbod/zonwering' => '/aanbod/zonwering',
        '/nl/ons-aanbod/zonwering/solarfix' => '/aanbod/zonwering/solarfix',
        '/nl/ons-aanbod/zonwering/verandazonwering' => '/aanbod/zonwering/verandazonwering',
        '/nl/ons-aanbod/zonwering/zonneschermen' => '/aanbod/zonwering/zonneschermen',
        '/nl/ons-aanbod/zonwering/terrasoverkapping' => '/aanbod/terrasoverkapping',
        '/nl/ons-aanbod/zonwering/configurator' => '/aanbod/zonwering',
        '/nl/wij-plaatsen-ramen-in-jouw-gemeente' => '/aanbod/ramen-en-deuren',
        '/nl/wij-plaatsen-deuren-in-jouw-gemeente' => '/aanbod/ramen-en-deuren',
        '/nl/wij-plaatsen-rolluiken-in-jouw-gemeente' => '/aanbod/rolluiken',
        '/nl/wij-plaatsen-zonweringen-in-jouw-gemeente' => '/aanbod/zonwering',
        '/nl/wij-plaatsen-terrasoverkappingen-in-jouw-gemeente' => '/aanbod/terrasoverkapping',
        '/nl/wij-plaatsen-velux-in-jouw-gemeente' => '/aanbod/velux',
        '/nl/winsol-ramen' => '/aanbod/ramen-en-deuren',
        '/nl/winsol-deuren' => '/aanbod/ramen-en-deuren',
        '/nl/winsol-rolluiken' => '/aanbod/rolluiken',
        '/nl/winsol-zonwering' => '/aanbod/zonwering',
        '/nl/winsol-terrasoverkapping' => '/aanbod/terrasoverkapping',
        '/nl/winsol-velux' => '/aanbod/velux',
        '/fr' => '/fr',
        '/fr/accueil' => '/fr',
        '/fr/a-propos-de-nous' => '/fr/a-propos',
        '/fr/contact' => '/fr/contact',
        '/fr/realisations' => '/fr/realisations',
        '/fr/simulez-votre-pret' => '/fr/simulez-votre-pret',
        '/fr/vacatures' => '/fr/a-propos',
        '/fr/notre-gamme' => '/fr/gamme',
        '/fr/notre-gamme/airco' => '/fr/gamme',
        '/fr/notre-gamme/somfy-smart-home' => '/fr/gamme/somfy-smart-home',
        '/fr/notre-gamme/velux' => '/fr/gamme/fenetres-de-toit-velux',
        '/fr/notre-gamme/couvertures-de-terrasse' => '/fr/gamme/couverture-de-terrasse',
        '/fr/notre-gamme/couvertures-de-terrasse/pergola-so' => '/fr/gamme/couverture-de-terrasse/pergola-so',
        '/fr/notre-gamme/couvertures-de-terrasse/pergola-zip' => '/fr/gamme/couverture-de-terrasse/pergola-zip',
        '/fr/notre-gamme/couvertures-de-terrasse/pergola-zip-cube' => '/fr/gamme/couverture-de-terrasse/pergola-zip-cube',
        '/fr/notre-gamme/couvertures-de-terrasse/win-cube' => '/fr/gamme/couverture-de-terrasse/wincube',
        '/fr/notre-gamme/couvertures-de-terrasse/somfy-smart-home' => '/fr/gamme/somfy-smart-home',
        '/fr/notre-gamme/portes-de-garage' => '/fr/gamme/portes-de-garage',
        '/fr/notre-gamme/portes-de-garage/portes-de-garage-sectionnelle' => '/fr/gamme/portes-de-garage/portes-de-garage-sectionnelles',
        '/fr/notre-gamme/portes-de-garage/porte-de-garage-a-deplacement-lateral' => '/fr/gamme/portes-de-garage/portes-sectionnelles-coulissantes',
        '/fr/notre-gamme/portes-de-garage/volets-de-garage' => '/fr/gamme/portes-de-garage/volets-de-garage',
        '/fr/notre-gamme/portes-de-garage/somfy-smart-home' => '/fr/gamme/somfy-smart-home',
        '/fr/notre-gamme/portes-en-acier' => '/fr/gamme/portes-interieur-acier',
        '/fr/notre-gamme/portes-et-fenetres' => '/fr/gamme/chassis-et-portes',
        '/fr/notre-gamme/portes-et-fenetres/moustiquaires' => '/fr/gamme/chassis-et-portes/moustiquaires',
        '/fr/notre-gamme/portes-et-fenetres/steellook' => '/fr/gamme/chassis-et-portes/steellook',
        '/fr/notre-gamme/portes-et-fenetres/volets-decoratifs' => '/fr/gamme/chassis-et-portes/volets-battants-decoratifs',
        '/fr/notre-gamme/portes-et-fenetres/portes-et-fenetres-en-aluminium' => '/fr/gamme/chassis-et-portes/chassis-et-portes-en-aluminium',
        '/fr/notre-gamme/portes-et-fenetres/portes-et-fenetres-en-pvc' => '/fr/gamme/chassis-et-portes',
        '/fr/notre-gamme/portes-et-fenetres/portes-de-securite' => '/fr/gamme/chassis-et-portes',
        '/fr/notre-gamme/protections-solaires' => '/fr/gamme/protection-solaire',
        '/fr/notre-gamme/protections-solaires/solarfix' => '/fr/gamme/protection-solaire/solarfix',
        '/fr/notre-gamme/protections-solaires/protection-solaire-pour-veranda' => '/fr/gamme/protection-solaire/protection-solaire-pour-veranda',
        '/fr/notre-gamme/protections-solaires/protection-verticale' => '/fr/gamme/protection-solaire/screens',
        '/fr/notre-gamme/protections-solaires/stores-bannes' => '/fr/gamme/protection-solaire/tentes-solaires',
        '/fr/notre-gamme/protections-solaires/configurateur' => '/fr/gamme/protection-solaire',
        '/fr/notre-gamme/volets-roulants' => '/fr/gamme/volets-roulants',
        '/fr/notre-gamme/volets-roulants/volets-roulants-interieurs' => '/fr/gamme/volets-roulants/volets-encastres',
        '/fr/notre-gamme/volets-roulants/volets-roulants-mini-caisson' => '/fr/gamme/volets-roulants/volets-a-caisson-apparent',
        '/fr/notre-gamme/volets-roulants/volets-roulants-superposes' => '/fr/gamme/volets-roulants/volets-en-applique',
        '/fr/notre-gamme/volets-roulants/volets-roulants-a-lames-fusion' => '/fr/gamme/volets-roulants/volets-a-lames-fusion',
        '/fr/notre-gamme/volets-roulants/volets-roulants-a-energie-solaire' => '/fr/gamme/volets-roulants/volets-solaires',
        '/fr/notre-gamme/volets-roulants/volets-de-garage' => '/fr/gamme/portes-de-garage/volets-de-garage',
        '/fr/nous-installons-des-fenetres-dans-votre-commune' => '/fr/gamme/chassis-et-portes',
        '/fr/nous-installons-des-portes-dans-votre-commune' => '/fr/gamme/chassis-et-portes',
        '/fr/nous-installons-des-volets-roulants-dans-votre-commune' => '/fr/gamme/volets-roulants',
        '/fr/nous-installons-des-protections-solaires-dans-votre-commune' => '/fr/gamme/protection-solaire',
        '/fr/nous-installons-des-couvertures-de-terrasse-dans-votre-commune' => '/fr/gamme/couverture-de-terrasse',
        '/fr/nous-installons-des-velux-dans-votre-commune' => '/fr/gamme/fenetres-de-toit-velux',
        '/fr/fenetres-winsol' => '/fr/gamme/chassis-et-portes',
        '/fr/portes-winsol' => '/fr/gamme/chassis-et-portes',
        '/fr/volets-roulants-winsol' => '/fr/gamme/volets-roulants',
        '/fr/protections-solaires-winsol' => '/fr/gamme/protection-solaire',
        '/fr/couvertures-de-terrasse-winsol' => '/fr/gamme/couverture-de-terrasse',
        '/fr/velux-winsol' => '/fr/gamme/fenetres-de-toit-velux',
    ],

];
