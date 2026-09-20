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
        'forked_from_recipe' => 'Forked from recipe',
        'forked_from_revision' => 'Forked from revision',
        'locale' => 'Locale',
        'locale_placeholder' => 'en, sv, ...',
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
        'add_slug' => 'Add slug',
        'add_revision' => 'Add revision',
        'edit_content' => 'Edit content',
        'view' => 'View',
        'settings' => 'Settings',
    ],

    'notifications' => [
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
