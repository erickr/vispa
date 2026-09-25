<?php

return [

    // A new household is named after its founder's last name.
    'default_name' => 'The :surname household',

    'menu' => 'My household',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email address',
        'household' => 'Household',
        'household_helper' => 'Everyone in this household can open and edit the recipe.',
    ],

    'invitation' => [
        'subject' => 'Join :household',
        'wrong_account_title' => 'This invitation is for someone else',
        'wrong_account_body' => 'It was sent to :email. Log out and log in with that address, or register it, to join :household.',
        'joined_title' => 'Welcome to :household',
        'joined_body' => 'Your recipes list now includes the household’s recipes, and anything you create is shared with them.',
        'intro' => 'You have been invited to join :household on :app, to share recipes and ingredients with the people you cook with.',
        'no_account' => 'New to :app? Create an account with this email address first, then come back and accept:',
        'create_account' => 'Create account',
        'accept_intro' => 'Accept the invitation by clicking the button below:',
        'accept' => 'Accept invitation',
        'unexpected' => 'If you did not expect this invitation, you can ignore this email.',
    ],

    'page' => [
        'subheading' => 'Everyone here shares recipes and ingredients.',
        'members' => 'Members',
        'members_description' => 'Everyone in the household can open and edit its recipes and ingredients. Only the owner invites and removes people.',
        'invitations' => 'Pending invitations',
        'invitations_description' => 'Sent by email; they join when they accept.',
        'owner' => 'Owner',
        'you' => 'you',
    ],

    'actions' => [
        'invite' => 'Invite',
        'invite_description' => 'They get an email with a link to join. Someone without an account signs up first, with this address.',
        'send_invitation' => 'Send invitation',
        'switch' => 'Switch household',
        'rename' => 'Rename household',
        'create' => 'Start a new household',
        'create_description' => 'You own the new household and can invite people into it. You switch to it straight away.',
        'leave' => 'Leave household',
        'leave_description' => 'You lose access to the household’s recipes and ingredients. What you created yourself stays yours.',
        'delete' => 'Delete household',
        'delete_description' => 'Members and invitations are removed. Recipes are not deleted: each goes back to the person who created it.',
        'remove' => 'Remove',
        'remove_heading' => 'Remove :name from the household?',
        'remove_description' => 'They lose access to the household’s recipes and ingredients. What they created themselves stays theirs.',
        'cancel_invitation' => 'Cancel invitation',
    ],

    'notifications' => [
        'invited' => 'Invitation sent to :email',
        'left' => 'You have left :household',
        'deleted' => ':household has been deleted',
        'failed' => 'That didn’t work',
    ],

    'validation' => [
        'already_member' => 'This person is already in the household.',
        'already_invited' => 'This person has already been invited.',
        'owner_cannot_leave' => 'You can’t leave a household you own.',
        'no_such_user' => 'There is no account with this email address.',
    ],

];
