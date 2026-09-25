<?php

return [

    // A new family is named after its founder's last name.
    'default_name' => 'The :surname family',

    'menu' => 'My family',

    'fields' => [
        'family' => 'Family',
        'family_helper' => 'Everyone in this family can open and edit the recipe.',
    ],

    'invitation' => [
        'wrong_account_title' => 'This invitation is for someone else',
        'wrong_account_body' => 'It was sent to :email. Log out and log in with that address, or register it, to join :family.',
        'joined_title' => 'Welcome to :family',
        'joined_body' => 'Your recipes list now includes the family’s recipes, and anything you create is shared with them.',
        'intro' => 'You have been invited to join :family on :app, to share recipes with the rest of the family.',
        'no_account' => 'New to :app? Create an account with this email address first, then come back and accept:',
        'create_account' => 'Create account',
        'accept_intro' => 'Accept the invitation by clicking the button below:',
        'accept' => 'Accept invitation',
        'unexpected' => 'If you did not expect this invitation, you can ignore this email.',
    ],

];
