<?php

return [

    'label' => 'Enhet',
    'plural_label' => 'Enheter',

    'fields' => [
        'code' => 'Kod',
        'type' => 'Typ',
        'base_unit' => 'Basenhet',
        'name' => 'Namn',
        'abbreviation' => 'Förkortning',
        'abbreviation_help' => 'Det som står på ingrediensraden, t.ex. msk.',
        'locale' => 'Språk',
        'factor_to_base' => 'Faktor till bas',
    ],

    'translations' => [
        'heading' => 'Översättningar',
        'description' => 'Hur enheten skrivs på varje språk. Alla språk behöver en.',
        'add' => 'Lägg till översättning',
    ],

    'types' => [
        'mass' => 'Massa',
        'volume' => 'Volym',
        'count' => 'Antal',
    ],

];
