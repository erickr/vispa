<?php

return [

    'label' => 'Planerad måltid',
    'plural_label' => 'Planerade måltider',
    'menu' => 'Planerade måltider',

    'page' => [
        'subheading' => 'Det här lagar :household härnäst. Alla i hushållet delar listan.',
    ],

    'fields' => [
        'source' => 'Rätt',
        'source_existing' => 'Ett av våra recept',
        'source_new' => 'En ny rätt',
        'recipe' => 'Recept',
        'name' => 'Rättens namn',
        'name_placeholder' => 'Mormors köttbullar',
        'name_helper' => 'Blir ett nytt recept med bara det här namnet. Fyll i resten när du vill.',
        'planned_for' => 'Dag',
        'planned_for_helper' => 'Valfritt. Lämna tomt för att planera den till någon dag snart.',
    ],

    'actions' => [
        'plan' => 'Planera en måltid',
        'plan_heading' => 'Planera en måltid',
        'plan_description' => 'Välj ett recept ni redan har, eller skriv namnet på en rätt ni inte skrivit ner än.',
        'plan_submit' => 'Lägg till i planen',
        'change_day' => 'Byt dag',
        'remove' => 'Ta bort',
        'remove_heading' => 'Ta bort från planen?',
        'remove_description' => 'Bara planen ändras. Receptet finns kvar bland era recept.',
        'rating' => 'Hur blev det?',
        'rating_helper' => 'Valfritt. Lämna tomt om ni inte lagade den.',
        'rating_labels' => [
            1 => 'Aldrig igen',
            2 => 'Sådär',
            3 => 'Gott',
            4 => 'Riktigt gott',
            5 => 'En favorit',
        ],
    ],

    'table' => [
        'dish' => 'Rätt',
        'added_by' => 'Tillagd av',
        'no_day' => 'Någon dag',
        'just_a_name' => 'Bara ett namn än så länge',
        'only_upcoming' => 'Dölj passerade dagar',
        'empty_heading' => 'Inget planerat än',
        'empty_description' => 'Planera en måltid från era recept, eller skriv namnet på en ny rätt att laga.',
    ],

    'notifications' => [
        'planned' => ':dish finns nu i planen.',
        'removed' => ':dish är borttagen från planen.',
        'removed_rated' => ':dish är borttagen från planen, betyg :rating av 5.',
    ],

];
