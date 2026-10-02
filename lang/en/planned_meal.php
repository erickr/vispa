<?php

return [

    'label' => 'Planned meal',
    'plural_label' => 'Planned meals',
    'menu' => 'Planned meals',

    'page' => [
        'subheading' => 'What :household is cooking next. Everyone in the household shares this list.',
    ],

    'fields' => [
        'source' => 'Dish',
        'source_existing' => 'One of our recipes',
        'source_new' => 'A new dish',
        'recipe' => 'Recipe',
        'name' => 'Name of the dish',
        'name_placeholder' => 'Grandma\'s meatballs',
        'name_helper' => 'Becomes a new recipe with just this name. Fill in the rest whenever you like.',
        'planned_for' => 'Day',
        'planned_for_helper' => 'Optional. Leave it empty to plan it for some day soon.',
    ],

    'actions' => [
        'plan' => 'Plan a meal',
        'plan_heading' => 'Plan a meal',
        'plan_description' => 'Pick a recipe you already have, or name a dish you have not written down yet.',
        'plan_submit' => 'Add to the plan',
        'change_day' => 'Change day',
        'remove' => 'Remove',
        'remove_heading' => 'Remove from the plan?',
        'remove_description' => 'Only the plan changes. The recipe stays in your recipes.',
        'rating' => 'How was it?',
        'rating_helper' => 'Optional. Leave it empty if you did not cook it.',
        'rating_labels' => [
            1 => 'Not again',
            2 => 'Meh',
            3 => 'Good',
            4 => 'Really good',
            5 => 'A favourite',
        ],
    ],

    'table' => [
        'dish' => 'Dish',
        'added_by' => 'Added by',
        'no_day' => 'Some day',
        'just_a_name' => 'Just a name so far',
        'only_upcoming' => 'Hide past days',
        'empty_heading' => 'Nothing planned yet',
        'empty_description' => 'Plan a meal from your recipes, or name a new dish to cook.',
    ],

    'notifications' => [
        'planned' => ':dish is on the plan.',
        'removed' => ':dish is off the plan.',
        'removed_rated' => ':dish is off the plan, rated :rating of 5.',
    ],

];
