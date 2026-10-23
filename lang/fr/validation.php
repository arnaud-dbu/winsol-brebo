<?php

// Zie lang/nl/validation.php.
return [
    'required' => 'Remplissez ce champ.',
    'email' => 'Saisissez une adresse e-mail valide, par exemple nom@exemple.be.',
    'accepted' => 'Cochez cette case pour continuer.',
    'in' => 'Faites un choix dans la liste.',
    'array' => 'Faites un choix dans la liste.',
    'mimes' => 'Ce type de fichier n’est pas accepté. Choisissez une photo ou un PDF.',
    'uploaded' => 'Ce fichier n’est pas arrivé. Choisissez un fichier plus léger ou réessayez.',
    'file' => 'Choisissez une photo ou un PDF à joindre.',
    'max' => [
        'file' => 'Ce fichier est trop lourd. Choisissez un fichier plus léger.',
        'array' => 'Vous avez choisi trop de fichiers. :max au maximum.',
    ],

    'phone' => 'Saisissez un numéro de téléphone valide, par exemple 0470 12 34 56.',
    'installation_year' => 'Indiquez une année entre :first et :year.',

    'custom' => [
        'products' => ['required' => 'Choisissez au moins un produit.'],
        'brochures' => ['required' => 'Choisissez au moins une brochure.'],
        'location' => ['required' => 'Faites un choix dans la liste.'],
        'branch' => ['required' => 'Faites un choix dans la liste.'],
        'product' => ['required' => 'Faites un choix dans la liste.'],
        'is_winsol' => ['required' => 'Faites un choix dans la liste.'],
        'facade' => ['required' => 'Faites un choix dans la liste.'],
        'warranty' => ['required' => 'Faites un choix dans la liste.'],
        'street' => ['required' => 'Indiquez votre rue.'],
        'number' => ['required' => 'Indiquez votre numéro.'],
        'postal_code' => ['required' => 'Indiquez votre code postal.', 'digits' => 'Indiquez un code postal valide (4 chiffres).'],
        'city' => ['required' => 'Indiquez votre commune.'],
        'floor' => ['integer' => 'Indiquez l’étage en chiffres, 0 pour le rez-de-chaussée.', 'between' => 'Indiquez l’étage en chiffres, 0 pour le rez-de-chaussée.'],
    ],
];
