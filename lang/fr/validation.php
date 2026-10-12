<?php

// Zie lang/nl/validation.php.
return [
    'required' => 'Remplissez ce champ.',
    'email' => 'Saisissez une adresse e-mail valide, par exemple nom@exemple.be.',
    'accepted' => 'Cochez cette case pour continuer.',
    'in' => 'Faites un choix dans la liste.',
    'array' => 'Faites un choix dans la liste.',
    'mimes' => 'Ce type de fichier n’est pas accepté. Choisissez une photo ou un PDF.',
    'max' => [
        'file' => 'Ce fichier est trop lourd. Choisissez un fichier plus léger.',
        'array' => 'Vous avez choisi trop de fichiers. :max au maximum.',
    ],

    'custom' => [
        'products' => ['required' => 'Choisissez au moins un produit.'],
        'brochures' => ['required' => 'Choisissez au moins une brochure.'],
        'location' => ['required' => 'Faites un choix dans la liste.'],
        'branch' => ['required' => 'Faites un choix dans la liste.'],
        'product' => ['required' => 'Faites un choix dans la liste.'],
        'is_winsol' => ['required' => 'Faites un choix dans la liste.'],
        'facade' => ['required' => 'Faites un choix dans la liste.'],
        'warranty' => ['required' => 'Faites un choix dans la liste.'],
    ],
];
