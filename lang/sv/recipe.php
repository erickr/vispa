<?php

return [

    'label' => 'Recept',
    'plural_label' => 'Recept',

    'untitled' => 'Namnlöst recept',

    'breadcrumb' => 'Recept',

    'tabs' => [
        'recipe' => 'Recept',
        'slugs' => 'Länknamn',
        'revisions' => 'Revisioner',
    ],

    'fields' => [
        'default_locale' => 'Standardspråk',
        'visibility' => 'Synlighet',
        'source_url' => 'Länk till originalet',
        'source_url_placeholder' => 'https://www.ica.se/recept/...',
        'source_url_helper' => 'Var det kommer ifrån. Bara en länk räcker som recept — ingredienser och instruktioner är valfria.',
        'forked_from_recipe' => 'Förgrenat från recept',
        'forked_from_revision' => 'Förgrenat från revision',
        'locale' => 'Språk',
        'locale_placeholder' => 'en, sv, ...',
        'slug' => 'Länknamn',
        'is_primary' => 'Primärt',
        'uuid' => 'UUID',
    ],

    'visibility' => [
        'private' => 'Privat',
        'unlisted' => 'Olistat',
        'public' => 'Publikt',
    ],

    'actions' => [
        'add_slug' => 'Lägg till länknamn',
        'add_revision' => 'Lägg till revision',
        'edit_content' => 'Redigera innehåll',
        'view' => 'Visa',
        'settings' => 'Inställningar',
    ],

    'notifications' => [
        'save_first' => [
            'title' => 'Spara receptet först',
            'body' => 'Spara den nya revisionen innan du redigerar dess ingredienser och instruktioner.',
        ],
    ],

    'table' => [
        'recipe' => 'Recept',
        'from' => 'Från',
        'touched' => 'Ändrat',
        'locale' => 'Språk',
        'revisions' => 'Revisioner',
        'untitled' => 'Namnlöst recept',
        'not_started' => 'Inte påbörjat',
        'only_saved_links' => 'Endast sparade länkar',
        'empty_heading' => 'Inga recept än',
        'empty_description' => 'Lägg till ett för att komma igång — en länk räcker gott.',
    ],

    'summary' => [
        'nothing_yet' => 'Inget nedskrivet än',
        'saved_from' => 'Sparat från :host · inget nedskrivet',
        'servings' => '{1} :count portion|[2,*] :count portioner',
        'ingredients' => '{1} :count ingrediens|[2,*] :count ingredienser',
        'steps' => '{1} :count steg|[2,*] :count steg',
        'photos' => '{1} :count foto|[2,*] :count foton',
        'no_photos' => 'inga foton',
    ],

];
