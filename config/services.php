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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://ariesmvp-9903a26b3095.herokuapp.com/api/login/google/callback'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    ],

    'apn' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'app_bundle_id' => env('APNS_APP_BUNDLE_ID'),
        'private_key_content' => env('APNS_PRIVATE_KEY_CONTENT'),
        'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
        'production' => env('APNS_PRODUCTION', false),
    ],

    'heroku_api' => [
        'url' => env('HEROKU_API_URL', 'https://ariesmvp-9903a26b3095.herokuapp.com/api'),
        'admin_token' => env('HEROKU_ADMIN_API_TOKEN'),
    ],
    
    'exa' => [
        'api_key' => env('EXA_API_KEY'),
        'endpoint' => env('EXA_ENDPOINT', 'https://api.exa.ai'),
    ],
    
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

];