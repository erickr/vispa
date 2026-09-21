<?php

return [

    'label' => 'Ingrediens',
    'plural_label' => 'Ingredienser',

    'fields' => [
        'canonical_name' => 'Grundnamn',
        'locale' => 'Språk',
        'name' => 'Namn',
        'created_at' => 'Skapad',
        'visibility' => 'Synlig för',
    ],

    'translations' => [
        'heading' => 'Översättningar',
        'description' => 'Visningsnamn för den här ingrediensen på olika språk.',
        'add' => 'Lägg till översättning',
        'count' => 'Översättningar',
    ],

    'visibility' => [
        'shared' => 'Alla',
        'private' => 'Bara dig',
    ],

    'validation' => [
        'name_taken' => 'Det finns redan en ingrediens med det här namnet.',
    ],

];
