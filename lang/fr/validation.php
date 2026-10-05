<?php

return [
    'required' => 'Ce champ est obligatoire.',
    'email' => 'Saisissez une adresse e-mail valide.',
    'string' => 'Saisissez un texte valide.',
    'confirmed' => 'La confirmation ne correspond pas.',
    'unique' => 'Cette valeur est déjà utilisée.',
    'min' => ['string' => 'Saisissez au moins :min caractères.'],
    'max' => ['string' => 'Saisissez au plus :max caractères.'],
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe apparaît dans une fuite de données : choisissez-en un autre.',
    ],
    'attributes' => [
        'name' => 'nom', 'email' => 'adresse e-mail', 'password' => 'mot de passe',
    ],
];
