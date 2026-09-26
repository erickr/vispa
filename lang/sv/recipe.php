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
        'callout' => [
            'heading' => 'Hittat ett recept på nätet? Ta hem det.',
            'description' => 'Klistra in en länk så gör Vispa om sidan till ett eget recept: ingredienser, steg, portioner och bild, redo att laga mat efter och dela med ditt hushåll.',
            'read_title' => 'Lättare att läsa',
            'read_body' => 'Bara ingredienserna och stegen – inga annonser, popup-rutor eller livsberättelser att scrolla förbi med mjöl på händerna.',
            'find_title' => 'Lättare att hitta',
            'find_body' => 'Allt du lagar mat efter på ett ställe, sökbart och delat med ditt hushåll, i stället för bortglömt bland bokmärken och öppna flikar.',
            'notes_title' => 'Plats för dina justeringar',
            'notes_body' => 'Anteckna vad du ändrade – mindre socker, längre tid i ugnen, ett byte som funkade – och spara varje version, med originalet kvar.',
        ],
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

    'share' => [
        'action' => 'Dela',
        'heading' => 'Dela receptet',
        'description' => 'Alla med länken kan läsa receptet. Inget annat av ditt syns.',
        'private_heading' => 'Receptet är privat',
        'private_description' => 'Ett privat recept har ingen sida att länka till. Gör det olistat så börjar länken nedan fungera — olistat betyder att bara de du ger länken till hittar det, och det syns inte någon annanstans.',
        'make_unlisted' => 'Gör olistat och dela',
        'close' => 'Stäng',
        'copy' => 'Kopiera länk',
        'copied' => 'Länken kopierad',
        'open' => 'Öppna sidan',
        'draft_warning' => 'Inget är publicerat än, så länken visar din senaste revision som den är.',
        'now_unlisted' => [
            'title' => 'Nu olistat — länken fungerar',
            'body' => 'Du kan ändra tillbaka under Inställningar när du vill.',
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

    // Ett annat hushålls publicerade recept, sparat bland det här hushållets recept.
    'saved' => [
        'badge' => 'Sparat',
        'callout_heading' => 'Sparat från ett annat hushåll',
        'callout_description' => 'Läs det och laga efter det här. Det är fortfarande deras, så du ser deras senast publicerade version — vill du ändra något gör du en egen version.',
        'fork' => 'Gör en egen version',
        'fork_heading' => 'Göra en egen version?',
        'fork_description' => 'Du får en kopia av den här versionen som ett privat utkast i ditt hushåll, att ändra hur du vill. Din version tar originalets plats bland era recept; originalet förblir som det är, och din version länkar tillbaka till det.',
        'fork_submit' => 'Gör min version',
        'forked' => [
            'title' => 'Din egen version är klar',
            'body' => 'Den är ett privat utkast i ditt hushåll — ändra det du vill och publicera när den är klar.',
        ],
        'remove' => 'Ta bort från våra recept',
        'remove_heading' => 'Ta bort från era recept?',
        'remove_description' => 'Det försvinner från hushållets recept. Versioner du gjort av det förblir dina.',
        'remove_submit' => 'Ta bort',
        'removed' => 'Borttaget från era recept',
        'saved' => [
            'title' => 'Sparat bland era recept',
            'body' => 'Du hittar det bland recepten i :household.',
        ],
        'based_on' => 'Bygger på ”:title”',
        'open_original' => 'Öppna originalet',
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
        'only_saved_from_others' => 'Endast sparade från andra hushåll',
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
