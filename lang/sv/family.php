<?php

return [

    // En ny familj får namn efter grundarens efternamn.
    'default_name' => 'Familjen :surname',

    'menu' => 'Min familj',

    'fields' => [
        'name' => 'Namn',
        'email' => 'E-postadress',
        'family' => 'Familj',
        'family_helper' => 'Alla i familjen kan öppna och redigera receptet.',
    ],

    'invitation' => [
        'subject' => 'Gå med i :family',
        'wrong_account_title' => 'Inbjudan gäller någon annan',
        'wrong_account_body' => 'Den skickades till :email. Logga ut och logga in med den adressen, eller registrera den, för att gå med i :family.',
        'joined_title' => 'Välkommen till :family',
        'joined_body' => 'Din receptlista innehåller nu familjens recept, och det du skapar delas med dem.',
        'intro' => 'Du har bjudits in till :family på :app för att dela recept med resten av familjen.',
        'no_account' => 'Ny på :app? Skapa först ett konto med den här e-postadressen och kom sedan tillbaka och tacka ja:',
        'create_account' => 'Skapa konto',
        'accept_intro' => 'Tacka ja till inbjudan med knappen nedan:',
        'accept' => 'Tacka ja',
        'unexpected' => 'Om du inte väntade dig den här inbjudan kan du bortse från mejlet.',
    ],

    'page' => [
        'subheading' => 'Alla här delar recept och ingredienser.',
        'members' => 'Medlemmar',
        'members_description' => 'Alla i familjen kan öppna och redigera dess recept och ingredienser. Bara ägaren bjuder in och tar bort personer.',
        'invitations' => 'Väntande inbjudningar',
        'invitations_description' => 'Skickade via e-post; de går med när de tackar ja.',
        'owner' => 'Ägare',
        'you' => 'du',
    ],

    'actions' => [
        'invite' => 'Bjud in',
        'invite_description' => 'De får ett mejl med en länk för att gå med. Den som saknar konto registrerar sig först, med den här adressen.',
        'send_invitation' => 'Skicka inbjudan',
        'switch' => 'Byt familj',
        'rename' => 'Byt namn på familjen',
        'create' => 'Starta en ny familj',
        'create_description' => 'Du äger den nya familjen och kan bjuda in personer till den. Du byter till den direkt.',
        'leave' => 'Lämna familjen',
        'leave_description' => 'Du förlorar åtkomsten till familjens recept och ingredienser. Det du själv har skapat behåller du.',
        'delete' => 'Radera familjen',
        'delete_description' => 'Medlemmar och inbjudningar tas bort. Recepten raderas inte, utan går tillbaka till den som skapade dem.',
        'remove' => 'Ta bort',
        'remove_heading' => 'Ta bort :name från familjen?',
        'remove_description' => 'Personen förlorar åtkomsten till familjens recept och ingredienser. Det hen själv har skapat behåller hen.',
        'cancel_invitation' => 'Avbryt inbjudan',
    ],

    'notifications' => [
        'invited' => 'Inbjudan skickad till :email',
        'left' => 'Du har lämnat :family',
        'deleted' => ':family har raderats',
        'failed' => 'Det gick inte',
    ],

    'validation' => [
        'already_member' => 'Personen är redan med i familjen.',
        'already_invited' => 'Personen är redan inbjuden.',
    ],

];
