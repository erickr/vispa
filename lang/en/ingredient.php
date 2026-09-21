<?php

return [

    'label' => 'Ingredient',
    'plural_label' => 'Ingredients',

    'fields' => [
        'canonical_name' => 'Canonical name',
        'locale' => 'Locale',
        'name' => 'Name',
        'created_at' => 'Created at',
        'visibility' => 'Visible to',
    ],

    'translations' => [
        'heading' => 'Translations',
        'description' => 'Localized display names for this ingredient.',
        'add' => 'Add translation',
        'count' => 'Translations',
    ],

    'visibility' => [
        'shared' => 'Everyone',
        'private' => 'Only you',
    ],

    'validation' => [
        'name_taken' => 'An ingredient with this name already exists.',
    ],

];
