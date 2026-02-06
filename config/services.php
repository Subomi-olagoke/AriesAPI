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

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
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
        'key_id' => '88BWD8CCX2',
        'team_id' => 'BMBR5BDGDM',
        'app_bundle_id' => 'com.Oubomi.Ariess',
        'private_key_content' => 'LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JR1RBZ0VBTUJNR0J5cUdTTTQ5QWdFR0NDcUdTTTQ5QXdFSEJIa3dkd0lCQVFRZ1Q0Qm44bytzamRUMzZuMG8KZm04Vy9UamZpVURkek1WbXFKLzkwMEZMNldTZ0NnWUlLb1pJemowREFRZWhSQU5DQUFSZ1J5OWZWdzFBWmxlaQptYUdSNFVvcEFZWFUrcWYrT0g5S3g5Szk3bktOaTFiRVI1TEJSUXdlSEdoSlBoZTM1dXMzcCt3eUxpUG0wTy9yCjEydHVOVTU1Ci0tLS0tRU5EIFBSSVZBVEUgS0VZLS0tLS0K',
        'private_key_path' => null,
        'production' => true,
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