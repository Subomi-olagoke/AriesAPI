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

    // Domains that are allowed in messages (your own domains and trusted educational sites)
    'allowed_domains' => [
        // Internal domains
        "ariesmvp-9903a26b3095.herokuapp.com",
        "aries-app.com",
        "ariesapi.com",
        
        // Trusted educational platforms
        "coursera.org",
        "edx.org",
        "khanacademy.org",
        "udemy.com",
        "udacity.com",
        "pluralsight.com",
        "mit.edu",
        "stanford.edu",
        "harvard.edu",
        "yale.edu",
        "berkeley.edu",
        "ocw.mit.edu",
        "freecodecamp.org",
        "codecademy.com",
        "w3schools.com",
        "mdn.mozilla.org",
        "developer.mozilla.org",
        "arxiv.org",
        "jstor.org",
        "scholar.google.com",
        
        // Trusted documentation sites
        "docs.python.org",
        "reactjs.org",
        "vuejs.org",
        "angular.io",
        "developer.android.com",
        "developer.apple.com",
        "docs.microsoft.com",
        "cloud.google.com",
        "aws.amazon.com",
        
        // Trusted learning blogs and references
        "medium.com",
        "github.com",
        "stackoverflow.com",
        "towardsdatascience.com",
        "dev.to",
        "smashingmagazine.com",
        "css-tricks.com",
    ],

    // Words that might indicate inappropriate content
    'inappropriate_words' => [
        // Adult content
        "porn", "xxx", "nude", "naked", "sex", "adult content", "explicit", "18+",
        "nsfw", "pornography", "erotic", "x-rated", "mature content", "fetish",
        
        // Hate speech and discrimination
        "racist", "sexist", "homophobic", "bigot", "supremacist", "hate speech",
        "discrimination", "ethnic slur", "racial slur", "holocaust denial",
        
        // Violence and harm
        "torture", "suicide", "self-harm", "kill yourself", "murder tutorial",
        "bombmaking", "weapon making", "illegal weapons",
        
        // Illegal activities
        "illegal drugs", "buy drugs online", "drug dealer", "black market",
        "buy fake id", "counterfeit money", "hack account", "phishing",
        
        // Spam
        "get rich quick", "make money fast", "pyramid scheme", "multilevel marketing",
        "miracle cure", "weight loss secret", "one weird trick", "doctors hate him",
        
        // Unsafe URLs
        "download crack", "free keygen", "pirated", "warez", "torrent download"
    ],

    // Maximum file size in MB
    'max_file_size' => env('MAX_UPLOAD_FILE_SIZE', 10),

    // File extensions that are considered dangerous
    'dangerous_extensions' => [
        'exe', 'dll', 'js', 'bat', 'sh', 'command', 'app'
    ],
];