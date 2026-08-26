<?php

// Alleen de regels die de drie formulieren echt gebruiken; de rest valt
// terug op het Engelstalige Laravel-default. Een volledig taalpakket als
// dependency toevoegen is aan Arno.
return [
    'required' => 'Le champ :attribute est obligatoire.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'accepted' => 'Le champ :attribute doit être accepté.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'array' => 'Le champ :attribute doit être une liste.',
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
];
