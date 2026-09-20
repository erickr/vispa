<?php

return [

    'label' => 'Revision',
    'plural_label' => 'Revisioner',

    'breadcrumb' => ':locale v:version',

    'status' => [
        'draft' => 'Utkast',
        'published' => 'Publicerad',
        'archived' => 'Arkiverad',
    ],

    'status_words' => [
        'draft' => 'Testas ännu',
        'published' => 'Klart',
        'archived' => 'Undanlagt',
    ],

    'tabs' => [
        'details' => 'Detaljer',
        'ingredients' => 'Ingredienser',
        'instructions' => 'Instruktioner',
        'photos' => 'Foton',
    ],

    'source' => [
        'saved_link' => 'Sparad länk',
        'description' => 'Originalet finns på webben. Skriv av bitar när du känner för det.',
        'description_short' => 'Originalet finns på webben.',
        'open' => 'Öppna originalet',
    ],

    'fields' => [
        'locale' => 'Språk',
        'locale_placeholder' => 'en, sv, ...',
        'version_number' => 'Versionsnummer',
        'version' => 'Version',
        'status' => 'Status',
        'title' => 'Titel',
        'description' => 'Beskrivning',
        'notes' => 'Anteckningar',
        'source_credit' => 'Vem ska tackas',
        'source_credit_placeholder' => 'Mormor Ingrids anteckningsbok, en vän, en kokbok…',
        'source_credit_helper' => 'Själva länken ligger på receptet, under Inställningar.',
        'servings' => 'Portioner',
        'prep_time' => 'Förberedelse (min)',
        'cook_time' => 'Tillagning (min)',
        'published_at' => 'Publicerad',

        'group_title' => 'Gruppnamn',
        'group_title_placeholder' => 't.ex. Till degen (lämna tomt för ogrupperat)',
        'quantity' => 'Mängd',
        'quantity_placeholder' => '1, 1.5, 1/2 ...',
        'unit' => 'Enhet',
        'ingredient' => 'Ingrediens',
        'ingredient_name' => 'Ingrediensens namn',
        'optional' => 'Valfri',
        'preparation_note' => 'Hantering',
        'preparation_note_placeholder' => 'finhackad',

        'section_title' => 'Avsnittsnamn',
        'section_title_placeholder' => 't.ex. Förberedelser (lämna tomt för ett enda avsnitt)',
        'step' => 'Steg',
        'timer_seconds' => 'Timer (s)',

        'alt_text' => 'Beskriv fotot',
        'alt_text_placeholder' => 'Bullar som svalnar på galler',
        'alt_text_helper' => 'Läses upp för den som använder skärmläsare.',
        'is_cover' => 'Använd som omslag',
        'is_cover_helper' => 'Bara ett foto får leda.',
    ],

    'empty' => [
        'ingredients' => [
            'heading' => 'Inget listat än — och det gör inget',
            'with_source' => 'Ingredienserna finns i originalet. Lägg bara till dem här om du vill ha en egen version.',
            'without_source' => 'Lägg till ingredienser när du vill. Ett recept med bara en titel är också ett recept.',
        ],
        'instructions' => [
            'heading' => 'Inga steg nedskrivna',
            'with_source' => 'Tillvägagångssättet finns i originalet — följ länken när du lagar det.',
            'without_source' => 'Skriv ner tillvägagångssättet när du får en stund. Det är inget krav.',
        ],
        'photos' => [
            'heading' => 'Inga foton än',
            'description' => 'Släpp in ett nästa gång du lagar det. Recept fungerar fint utan.',
        ],
    ],

    'actions' => [
        'add_ingredient' => 'Lägg till ingrediens',
        'add_ingredient_group' => 'Lägg till ingrediensgrupp',
        'add_step' => 'Lägg till steg',
        'add_instruction_section' => 'Lägg till instruktionsavsnitt',
        'add_photo' => 'Lägg till ett foto',
        'edit_content' => 'Redigera innehåll',
    ],

    'items' => [
        'ungrouped' => 'Ogrupperat',
        'steps' => 'Steg',
        'new_ingredient' => 'Ny ingrediens',
        'optional_suffix' => '(valfri)',
    ],

    'infolist' => [
        'no_description' => 'Ingen beskrivning än.',
        'hands_on' => 'Förberedelse',
        'cooking' => 'Tillagning',
        'link_only_heading' => 'Sparat som en länk, och det räcker gott',
        'link_only_description' => 'Inga ingredienser eller steg är nedskrivna för det här.',
        'ingredients_heading' => 'Det här behöver du',
        'instructions_heading' => 'Så gör du',
        'photos_heading' => 'Foton',
        'revision_heading' => 'Revision',
        'step_number' => 'Steg :number',
        'timer' => 'Timer',
        'no_photo_description' => 'Ingen beskrivning',
        'not_published' => 'Inte publicerad',
    ],

    'notifications' => [
        'draft_copy' => [
            'title' => 'Redigerar en kopia som utkast',
            'body' => 'Publicerade revisioner kan inte ändras, så du redigerar nu utkast v:version.',
        ],
    ],

];
