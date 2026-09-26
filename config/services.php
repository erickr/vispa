<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Recipe import reads recipes with Claude (App\Recipes\Import\ClaudeRecipeExtractor).
    // Without a key the "Import from link" action is hidden.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        // Every import is a paid model call and sign-up is open, so each user gets a budget
        // (App\Jobs\ImportRecipe::start()). Both windows apply; 0 turns imports off.
        'imports_per_hour' => (int) env('ANTHROPIC_IMPORTS_PER_HOUR', 10),
        'imports_per_day' => (int) env('ANTHROPIC_IMPORTS_PER_DAY', 30),
    ],

    // The "Fetch" button beside a recipe's link reads the page's title server-side
    // (App\Support\PageTitleFetcher). Limited per user so the server is no one's fetch proxy.
    'page_titles' => [
        'fetches_per_hour' => (int) env('PAGE_TITLE_FETCHES_PER_HOUR', 30),
    ],

];
