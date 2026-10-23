<?php

// Zie lang/nl/validation.php.
return [

    'required' => 'Please fill in this field.',
    'email' => 'Enter a valid email address, for example name@example.be.',
    'accepted' => 'Tick this box to continue.',
    'in' => 'Choose an option from the list.',
    'array' => 'Choose an option from the list.',
    'mimes' => 'This file type can’t be sent. Choose a photo or a PDF.',
    'uploaded' => 'This file didn’t come through. Choose a smaller file or try again.',
    'file' => 'Choose a photo or a PDF to send.',
    'max' => [
        'file' => 'This file is too large. Choose a smaller file.',
        'array' => 'You picked too many files. :max at most.',
    ],

    'phone' => 'Enter a valid phone number, for example 0470 12 34 56.',
    'installation_year' => 'Enter a year between :first and :year.',

    'custom' => [
        'products' => ['required' => 'Choose at least one product.'],
        'brochures' => ['required' => 'Choose at least one brochure.'],
        'location' => ['required' => 'Choose an option from the list.'],
        'branch' => ['required' => 'Choose an option from the list.'],
        'product' => ['required' => 'Choose an option from the list.'],
        'is_winsol' => ['required' => 'Choose an option from the list.'],
        'facade' => ['required' => 'Choose an option from the list.'],
        'warranty' => ['required' => 'Choose an option from the list.'],
        'floor' => ['integer' => 'Enter the floor as a number, 0 for the ground floor.', 'between' => 'Enter the floor as a number, 0 for the ground floor.'],
    ],

    'unique_entry_value' => 'The :attribute has already been taken.',
    'unique_user_value' => 'The :attribute has already been taken.',

];
