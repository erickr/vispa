<?php

return [

    'label' => 'Revision',
    'plural_label' => 'Revisions',

    // "<locale> v<n>", the breadcrumb for a revision page.
    'breadcrumb' => ':locale v:version',

    'status' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],

    // The badge wording the cook reads, as opposed to the raw status above.
    'status_words' => [
        'draft' => 'Still testing',
        'published' => 'Ready',
        'archived' => 'Put away',
    ],

    'tabs' => [
        'details' => 'Details',
        'ingredients' => 'Ingredients',
        'instructions' => 'Instructions',
        'photos' => 'Photos',
    ],

    'source' => [
        'saved_link' => 'Saved link',
        'description' => 'The original lives on the web. Copy bits in whenever you feel like it.',
        'description_short' => 'The original lives on the web.',
        'open' => 'Open the original',
    ],

    'fields' => [
        'locale' => 'Locale',
        'version_number' => 'Version number',
        'version' => 'Version',
        'status' => 'Status',
        'title' => 'Title',
        'description' => 'Description',
        'notes' => 'Notes',
        'source_credit' => 'Who to thank',
        'source_credit_placeholder' => "Mormor Ingrid's notebook, a friend, a cookbook…",
        'source_credit_helper' => 'The link itself is on the recipe, under Settings.',
        'servings' => 'Servings',
        'prep_time' => 'Prep (min)',
        'cook_time' => 'Cook (min)',
        'published_at' => 'Published at',

        'group_title' => 'Group title',
        'group_title_placeholder' => 'e.g. For the dough (leave blank for ungrouped)',
        'quantity' => 'Amount',
        'quantity_placeholder' => '1, 1.5, 1/2 ...',
        'unit' => 'Unit',
        'ingredient' => 'Ingredient',
        'ingredient_name' => 'Ingredient name',
        'optional' => 'Optional',
        'preparation_note' => 'Prep note',
        'preparation_note_placeholder' => 'finely chopped',

        'section_title' => 'Section title',
        'section_title_placeholder' => 'e.g. Preparation (leave blank for a single section)',
        'step' => 'Step',
        'timer_seconds' => 'Timer (s)',

        'alt_text' => 'Describe the photo',
        'alt_text_placeholder' => 'Bullar cooling on a rack',
        'alt_text_helper' => 'Read aloud to anyone using a screen reader.',
        'is_cover' => 'Use as cover',
        'is_cover_helper' => 'Only one photo leads.',
    ],

    'empty' => [
        'ingredients' => [
            'heading' => 'Nothing listed yet — and that is fine',
            'with_source' => 'The ingredients are in the original. Add them here only if you want your own version.',
            'without_source' => 'Add ingredients whenever you like. A recipe with just a title is still a recipe.',
        ],
        'instructions' => [
            'heading' => 'No steps written down',
            'with_source' => 'The method is in the original — follow the link when you cook it.',
            'without_source' => 'Write the method out when you have a moment. It is not required.',
        ],
        'photos' => [
            'heading' => 'No photos yet',
            'description' => 'Drop one in when you next make it. Recipes work fine without.',
        ],
    ],

    'actions' => [
        'add_ingredient' => 'Add ingredient',
        'add_ingredient_group' => 'Add ingredient group',
        'add_step' => 'Add step',
        'add_instruction_section' => 'Add instruction section',
        'add_photo' => 'Add a photo',
        'edit_content' => 'Edit content',
    ],

    'items' => [
        'ungrouped' => 'Ungrouped',
        'steps' => 'Steps',
        'new_ingredient' => 'New ingredient',
        'optional_suffix' => '(optional)',
    ],

    'infolist' => [
        'no_description' => 'No description yet.',
        'hands_on' => 'Hands on',
        'cooking' => 'Cooking',
        'link_only_heading' => 'Saved as a link, and that is plenty',
        'link_only_description' => 'No ingredients or steps have been written down for this one.',
        'ingredients_heading' => 'What you need',
        'instructions_heading' => 'How to make it',
        'photos_heading' => 'Photos',
        'revision_heading' => 'Revision',
        'other_versions' => 'All versions',
        'you_are_here' => 'you are reading this one',
        'step_number' => 'Step :number',
        'timer' => 'Timer',
        'no_photo_description' => 'No description',
        'not_published' => 'Not published',
    ],

    'published_choice' => [
        'heading' => 'This version is published',
        'description' => 'Anyone holding the link may be reading it right now. Start a new revision to work on, or change this one as it stands.',
        'new_revision' => 'Start a new revision',
        'edit_in_place' => 'Edit this version',
        'cancel' => 'Leave it alone',
    ],

    'notifications' => [
        'draft_copy' => [
            'title' => 'Editing draft v:version',
            'body' => 'The published version stays as it is until you publish this one.',
        ],
    ],

];
