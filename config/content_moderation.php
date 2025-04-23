<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Moderation Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls the content moderation settings
    | for the application. It defines what content is allowed and what
    | should be flagged or blocked.
    |
    */

    // Whether content moderation is enabled globally
    'enabled' => env('CONTENT_MODERATION_ENABLED', true),

    // Domains that are allowed in messages (your own domains)
    'allowed_domains' => [
        "ariesmvp-9903a26b3095.herokuapp.com",
        "aries-app.com",
        "ariesapi.com",
        // Add more domains as needed
    ],

    // Words that might indicate inappropriate content
    'inappropriate_words' => [
        "porn", "xxx", "nude", "naked", "sex", "adult content",
        // Add more terms as needed
    ],

    // Maximum file size in MB
    'max_file_size' => env('MAX_UPLOAD_FILE_SIZE', 10),

    // File extensions that are considered dangerous
    'dangerous_extensions' => [
        'exe', 'dll', 'js', 'bat', 'sh', 'command', 'app'
    ],
];