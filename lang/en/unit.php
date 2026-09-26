<?php

return [

    'label' => 'Unit',
    'plural_label' => 'Units',

    'fields' => [
        'code' => 'Code',
        'type' => 'Type',
        'base_unit' => 'Base unit',
        'name' => 'Name',
        'abbreviation' => 'Abbreviation',
        'abbreviation_help' => 'What an ingredient line shows, e.g. tbsp.',
        'locale' => 'Language',
        'factor_to_base' => 'Factor to base',
    ],

    'translations' => [
        'heading' => 'Translations',
        'description' => 'How this unit reads in each language. Every language needs one.',
        'add' => 'Add translation',
    ],

    'types' => [
        'mass' => 'Mass',
        'volume' => 'Volume',
        'count' => 'Count',
    ],

];
