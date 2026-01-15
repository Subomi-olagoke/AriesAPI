<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Throttle Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the throttling configuration for the API endpoints.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default API Rate Limit
    |--------------------------------------------------------------------------
    |
    | This value determines the default number of API requests allowed per minute
    | for standard API endpoints.
    |
    */
    'api' => [
        'max_attempts' => env('API_THROTTLE_MAX_ATTEMPTS', 1000),
        'decay_minutes' => env('API_THROTTLE_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Rate Limits
    |--------------------------------------------------------------------------
    |
    | These values determine the rate limits for file upload endpoints.
    | This is separate from the standard API rate limit to accommodate
    | for larger requests.
    |
    */
    'file_uploads' => [
        'max_attempts' => env('FILE_UPLOAD_THROTTLE_MAX_ATTEMPTS', 1000),
        'decay_minutes' => env('FILE_UPLOAD_THROTTLE_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authenticated User Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for authenticated users. These are typically higher than
    | the limits for unauthenticated users.
    |
    */
    'authenticated' => [
        'max_attempts' => env('AUTH_THROTTLE_MAX_ATTEMPTS', 1000),
        'decay_minutes' => env('AUTH_THROTTLE_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unauthenticated User Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for unauthenticated users. These are typically lower than
    | the limits for authenticated users to prevent abuse.
    |
    */
    'unauthenticated' => [
        'max_attempts' => env('UNAUTH_THROTTLE_MAX_ATTEMPTS', 1000),
        'decay_minutes' => env('UNAUTH_THROTTLE_DECAY_MINUTES', 1),
    ],
];