<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for OpenAI API integration.
    |
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
        'endpoint' => env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'embedding_endpoint' => env('OPENAI_EMBEDDING_ENDPOINT', 'https://api.openai.com/v1/embeddings'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-ada-002'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | AI Features Configuration
    |--------------------------------------------------------------------------
    |
    | Control which AI features are enabled and their specific settings.
    |
    */
    
    'features' => [
        'personalized_learning_paths' => [
            'enabled' => env('AI_FEATURE_LEARNING_PATHS_ENABLED', true),
            'cache_ttl' => env('AI_FEATURE_LEARNING_PATHS_CACHE_TTL', 86400), // 24 hours
            'refresh_interval' => env('AI_FEATURE_LEARNING_PATHS_REFRESH', 604800), // 7 days
            'max_courses_to_suggest' => env('AI_FEATURE_LEARNING_PATHS_MAX_COURSES', 10),
        ],
        
        'ai_teaching_assistant' => [
            'enabled' => env('AI_FEATURE_TEACHING_ASSISTANT_ENABLED', true),
            'response_timeout' => env('AI_FEATURE_TEACHING_ASSISTANT_TIMEOUT', 15), // seconds
            'max_context_length' => env('AI_FEATURE_TEACHING_ASSISTANT_CONTEXT', 10), // number of messages to include
            'system_prompt' => env('AI_FEATURE_TEACHING_ASSISTANT_PROMPT', 'You are an expert teaching assistant helping students in an educational platform. Provide accurate, clear, and helpful explanations to student questions. If you\'re unsure, acknowledge that instead of making up information.'),
        ],
        
        'social_learning' => [
            'enabled' => env('AI_FEATURE_SOCIAL_LEARNING_ENABLED', true),
            'similarity_threshold' => env('AI_FEATURE_SOCIAL_LEARNING_THRESHOLD', 0.7), // minimum similarity score
            'max_suggestions' => env('AI_FEATURE_SOCIAL_LEARNING_MAX_SUGGESTIONS', 5),
            'refresh_interval' => env('AI_FEATURE_SOCIAL_LEARNING_REFRESH', 172800), // 2 days
        ],
    ],
];