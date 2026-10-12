<?php

// De melding staat onder het veld, dus ze noemt het veld niet: ze zegt wat er
// mis is en wat je moet doen. Alleen de regels die de formulieren gebruiken.
//
// `custom` per veldnaam, voor alles wat je kiest in plaats van intypt. Een
// nieuwe keuzelijst in een formulierblueprint hoort hier haar handle bij te
// krijgen; anders vraagt ze "Vul dit veld in."
return [
    'required' => 'Vul dit veld in.',
    'email' => 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.be.',
    'accepted' => 'Vink dit vakje aan om verder te gaan.',
    'in' => 'Maak een keuze uit de lijst.',
    'array' => 'Maak een keuze uit de lijst.',
    'mimes' => 'Dit bestandstype kan niet mee. Kies een foto of een pdf.',
    'max' => [
        'file' => 'Dit bestand is te groot. Kies een kleiner bestand.',
        'array' => 'Je koos te veel bestanden. Er kunnen er hoogstens :max mee.',
    ],

    'custom' => [
        'products' => ['required' => 'Kies minstens één product.'],
        'brochures' => ['required' => 'Kies minstens één brochure.'],
        'location' => ['required' => 'Maak een keuze uit de lijst.'],
        'branch' => ['required' => 'Maak een keuze uit de lijst.'],
        'product' => ['required' => 'Maak een keuze uit de lijst.'],
        'is_winsol' => ['required' => 'Maak een keuze uit de lijst.'],
        'facade' => ['required' => 'Maak een keuze uit de lijst.'],
        'warranty' => ['required' => 'Maak een keuze uit de lijst.'],
    ],
];
