<?php

return [

    // Ett nytt hushåll får namn efter grundarens efternamn.
    'default_name' => 'Hushållet :surname',

    'menu' => 'Mitt hushåll',

    'fields' => [
        'name' => 'Namn',
        'email' => 'E-postadress',
        'household' => 'Hushåll',
        'household_helper' => 'Alla i hushållet kan öppna och redigera receptet.',
    ],

    'invitation' => [
        'subject' => 'Gå med i :household',
        'wrong_account_title' => 'Inbjudan gäller någon annan',
        'wrong_account_body' => 'Den skickades till :email. Logga ut och logga in med den adressen, eller registrera den, för att gå med i :household.',
        'joined_title' => 'Välkommen till :household',
        'joined_body' => 'Din receptlista innehåller nu hushållets recept, och det du skapar delas med dem.',
        'intro' => 'Du har bjudits in till :household på :app för att dela recept och ingredienser med dem du lagar mat med.',
        'no_account' => 'Ny på :app? Skapa först ett konto med den här e-postadressen och kom sedan tillbaka och tacka ja:',
        'create_account' => 'Skapa konto',
        'accept_intro' => 'Tacka ja till inbjudan med knappen nedan:',
        'accept' => 'Tacka ja',
        'unexpected' => 'Om du inte väntade dig den här inbjudan kan du bortse från mejlet.',
    ],

    'page' => [
        'subheading' => 'Alla här delar recept och ingredienser.',
        'members' => 'Medlemmar',
        'members_description' => 'Alla i hushållet kan öppna och redigera dess recept och ingredienser. Bara ägaren bjuder in och tar bort personer.',
        'invitations' => 'Väntande inbjudningar',
        'invitations_description' => 'Skickade via e-post; de går med när de tackar ja.',
        'owner' => 'Ägare',
        'you' => 'du',
    ],

    'actions' => [
        'invite' => 'Bjud in',
        'invite_description' => 'De får ett mejl med en länk för att gå med. Den som saknar konto registrerar sig först, med den här adressen.',
        'send_invitation' => 'Skicka inbjudan',
        'switch' => 'Byt hushåll',
        'rename' => 'Byt namn på hushållet',
        'create' => 'Starta ett nytt hushåll',
        'create_description' => 'Du äger det nya hushållet och kan bjuda in personer till det. Du byter till det direkt.',
        'leave' => 'Lämna hushållet',
        'leave_description' => 'Du förlorar åtkomsten till hushållets recept och ingredienser. Det du själv har skapat behåller du.',
        'delete' => 'Radera hushållet',
        'delete_description' => 'Medlemmar och inbjudningar tas bort. Recepten raderas inte, utan går tillbaka till den som skapade dem.',
        'remove' => 'Ta bort',
        'remove_heading' => 'Ta bort :name från hushållet?',
        'remove_description' => 'Personen förlorar åtkomsten till hushållets recept och ingredienser. Det hen själv har skapat behåller hen.',
        'cancel_invitation' => 'Avbryt inbjudan',
    ],

    'widget' => [
        'heading' => 'Recept per hushåll',
        'members' => '{1} :count medlem|[2,*] :count medlemmar',
    ],

    'notifications' => [
        'invited' => 'Inbjudan skickad till :email',
        'left' => 'Du har lämnat :household',
        'deleted' => ':household har raderats',
        'failed' => 'Det gick inte',
    ],

    'validation' => [
        'already_member' => 'Personen är redan med i hushållet.',
        'already_invited' => 'Personen är redan inbjuden.',
        'owner_cannot_leave' => 'Du kan inte lämna ett hushåll som du äger.',
        'no_such_user' => 'Det finns inget konto med den e-postadressen.',
    ],

];
