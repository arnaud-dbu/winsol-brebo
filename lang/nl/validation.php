<?php

// De melding staat onder het veld, dus ze noemt het veld niet: ze zegt wat er
// mis is en wat je moet doen. Alleen de regels die de formulieren gebruiken.
//
// `custom` per veldnaam, voor alles wat je kiest in plaats van intypt. Een
// nieuwe keuzelijst in een formulierblueprint hoort hier haar handle bij te
// krijgen; anders vraagt ze "Vul dit veld in." De vakken van het adres staan
// er ook: vier keer "Vul dit veld in." onder elkaar zegt niet welk vak leeg is.
return [
    'required' => 'Vul dit veld in.',
    'email' => 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.be.',
    'accepted' => 'Vink dit vakje aan om verder te gaan.',
    'in' => 'Maak een keuze uit de lijst.',
    'array' => 'Maak een keuze uit de lijst.',
    'mimes' => 'Dit bestandstype kan niet mee. Kies een foto of een pdf.',
    'uploaded' => 'Dit bestand kwam niet aan. Kies een kleiner bestand of probeer het opnieuw.',
    'file' => 'Kies een foto of een pdf om mee te sturen.',
    'max' => [
        'file' => 'Dit bestand is te groot. Kies een kleiner bestand.',
        'array' => 'Je koos te veel bestanden. Er kunnen er hoogstens :max mee.',
    ],

    'phone' => 'Vul een geldig telefoonnummer in, bijvoorbeeld 0470 12 34 56.',
    'installation_year' => 'Vul een jaartal in tussen :first en :year.',

    'custom' => [
        'products' => ['required' => 'Kies minstens één product.'],
        'brochures' => ['required' => 'Kies minstens één brochure.'],
        'location' => ['required' => 'Maak een keuze uit de lijst.'],
        'branch' => ['required' => 'Maak een keuze uit de lijst.'],
        'product' => ['required' => 'Maak een keuze uit de lijst.'],
        'is_winsol' => ['required' => 'Maak een keuze uit de lijst.'],
        'facade' => ['required' => 'Maak een keuze uit de lijst.'],
        'warranty' => ['required' => 'Maak een keuze uit de lijst.'],
        'street' => ['required' => 'Vul je straat in.'],
        'number' => ['required' => 'Vul je huisnummer in.'],
        'postal_code' => ['required' => 'Vul je postcode in.', 'digits' => 'Vul een geldige postcode in (4 cijfers).'],
        'city' => ['required' => 'Vul je gemeente in.'],
        'floor' => ['integer' => 'Vul de verdieping in als getal, 0 voor het gelijkvloers.', 'between' => 'Vul de verdieping in als getal, 0 voor het gelijkvloers.'],
    ],
];
