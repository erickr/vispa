<?php

return [

    'label' => 'Recipe',
    'plural_label' => 'Recipes',

    // Seeded as the title of a new recipe's first revision. Written in the
    // revision's locale, not the reader's — see CreateRecipe::afterCreate().
    'untitled' => 'Untitled recipe',

    'breadcrumb' => 'Recipe',

    'tabs' => [
        'recipe' => 'Recipe',
        'slugs' => 'Slugs',
        'revisions' => 'Revisions',
    ],

    'fields' => [
        'default_locale' => 'Default locale',
        'visibility' => 'Visibility',
        'source_url' => 'Source link',
        'source_url_placeholder' => 'https://www.ica.se/recept/...',
        'source_url_helper' => 'Where it came from. A link on its own is a complete recipe — ingredients and instructions are optional.',
        'title' => 'Title',
        'title_helper' => 'Becomes the title of the first revision. Leave it empty to name it later.',
        'forked_from_recipe' => 'Forked from recipe',
        'forked_from_revision' => 'Forked from revision',
        'locale' => 'Locale',
        'slug' => 'Slug',
        'is_primary' => 'Primary',
        'uuid' => 'UUID',
    ],

    'visibility' => [
        'private' => 'Private',
        'unlisted' => 'Unlisted',
        'public' => 'Public',
    ],

    'actions' => [
        'fetch_title' => 'Fetch',
        'add_slug' => 'Add slug',
        'add_revision' => 'Add revision',
        'edit_content' => 'Edit content',
        'view' => 'View',
        'settings' => 'Settings',
    ],

    'import' => [
        'callout' => [
            'heading' => 'Found a recipe online? Bring it home.',
            'description' => 'Paste a link and Vispa turns the page into a recipe of your own: ingredients, steps, servings and the photo, ready to cook from and share with your household.',
            'read_title' => 'Easier to read',
            'read_body' => 'Just the ingredients and the steps — no ads, pop-ups or life story to scroll past while your hands are floury.',
            'find_title' => 'Easier to find',
            'find_body' => 'Everything you cook from in one place, searchable and shared with your household, instead of lost in bookmarks and open tabs.',
            'notes_title' => 'Room for your adjustments',
            'notes_body' => 'Note what you changed — less sugar, a longer bake, a swap that worked — and keep each version, with the original still there.',
        ],
        'action' => 'Import from link',
        'modal_heading' => 'Import a recipe from a link',
        'modal_description' => 'Vispa reads the page and fills in a draft: ingredients, steps, servings and the photo. Check it over before you publish.',
        'submit' => 'Import',
        'heading' => 'Importing recipe',
        'status' => [
            'pending' => 'Waiting to start…',
            'running' => 'Reading the recipe… this usually takes under a minute.',
            'done' => 'Done — opening the recipe…',
            'failed' => 'The import didn’t work',
        ],
        'retry' => 'Try again',
        'back' => 'Back to recipes',
        'total_time' => 'Total time: :minutes min.',
        'errors' => [
            'unreachable' => 'The page couldn’t be fetched. Check the link and try again.',
            'no_recipe' => 'No recipe was found on that page.',
            'too_large' => 'The page is too large to read as one recipe.',
            'refused' => 'The recipe couldn’t be read from this source.',
            'busy' => 'Recipe reading is busy right now. Try again in a minute.',
            'api' => 'Recipe reading is unavailable right now. Try again later.',
            'unexpected' => 'Something went wrong during the import.',
        ],
    ],

    'share' => [
        'action' => 'Share',
        'heading' => 'Share this recipe',
        'description' => 'Anyone with this link can read the recipe. Nothing else of yours is visible.',
        'private_heading' => 'This recipe is private',
        'private_description' => 'A private recipe has no page to link to. Make it unlisted and the link below starts working — unlisted means only people you give the link to can find it, and it stays out of anything public.',
        'make_unlisted' => 'Make it unlisted and share',
        'close' => 'Close',
        'copy' => 'Copy link',
        'copied' => 'Link copied',
        'open' => 'Open the page',
        'draft_warning' => 'Nothing is published yet, so the link shows your latest revision as it stands.',
        'now_unlisted' => [
            'title' => 'Now unlisted — the link works',
            'body' => 'Change it back under Settings whenever you like.',
        ],
    ],

    'notifications' => [
        'fetch_title' => [
            'no_url' => 'Enter a valid link first',
            'not_found' => 'Couldn’t find a title on that page',
            'not_found_body' => 'The page couldn’t be read, or it has no heading. Type the title yourself.',
        ],
        'save_first' => [
            'title' => 'Save the recipe first',
            'body' => 'Save this new revision before editing its ingredients and instructions.',
        ],
    ],

    'table' => [
        'recipe' => 'Recipe',
        'from' => 'From',
        'touched' => 'Touched',
        'locale' => 'Locale',
        'revisions' => 'Revisions',
        'untitled' => 'Untitled recipe',
        'not_started' => 'Not started',
        'only_saved_links' => 'Only saved links',
        'empty_heading' => 'No recipes yet',
        'empty_description' => 'Add one to get started — a link on its own is plenty.',
    ],

    // The line under a recipe's name on the list.
    'summary' => [
        'nothing_yet' => 'Nothing written down yet',
        'saved_from' => 'Saved from :host · nothing written down',
        'servings' => '{1} :count serving|[2,*] :count servings',
        'ingredients' => '{1} :count ingredient|[2,*] :count ingredients',
        'steps' => '{1} :count step|[2,*] :count steps',
        'photos' => '{1} :count photo|[2,*] :count photos',
        'no_photos' => 'no photos',
    ],

];
