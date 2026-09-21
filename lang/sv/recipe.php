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
        'title' => 'Titel',
        'title_helper' => 'Blir titeln på den första revisionen. Lämna tomt för att namnge det senare.',
        'forked_from_recipe' => 'Förgrenat från recept',
        'forked_from_revision' => 'Förgrenat från revision',
        'locale' => 'Språk',
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
        'fetch_title' => 'Hämta',
        'add_slug' => 'Lägg till länknamn',
        'add_revision' => 'Lägg till revision',
        'edit_content' => 'Redigera innehåll',
        'view' => 'Visa',
        'settings' => 'Inställningar',
    ],

    'import' => [
        'action' => 'Importera från länk',
        'modal_heading' => 'Importera ett recept från en länk',
        'modal_description' => 'Vispa läser sidan och fyller i ett utkast: ingredienser, steg, portioner och bilden. Gå igenom det innan du publicerar.',
        'submit' => 'Importera',
        'heading' => 'Importerar recept',
        'status' => [
            'pending' => 'Väntar på att starta…',
            'running' => 'Läser receptet… det brukar ta under en minut.',
            'done' => 'Klart — öppnar receptet…',
            'failed' => 'Importen fungerade inte',
        ],
        'retry' => 'Försök igen',
        'back' => 'Tillbaka till recepten',
        'total_time' => 'Total tid: :minutes min.',
        'errors' => [
            'unreachable' => 'Sidan gick inte att hämta. Kontrollera länken och försök igen.',
            'no_recipe' => 'Hittade inget recept på sidan.',
            'too_large' => 'Sidan är för stor för att läsas som ett recept.',
            'refused' => 'Receptet gick inte att läsa från den här källan.',
            'busy' => 'Receptläsningen är upptagen just nu. Försök igen om en minut.',
            'api' => 'Receptläsningen är inte tillgänglig just nu. Försök igen senare.',
            'unexpected' => 'Något gick fel under importen.',
        ],
    ],

    'notifications' => [
        'fetch_title' => [
            'no_url' => 'Ange en giltig länk först',
            'not_found' => 'Hittade ingen titel på sidan',
            'not_found_body' => 'Sidan gick inte att läsa, eller saknar rubrik. Skriv titeln själv.',
        ],
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
