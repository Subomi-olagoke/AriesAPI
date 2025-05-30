<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CogniService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';
    protected $model = 'gpt-3.5-turbo';

    public function __construct()
    {
        // Use config values from services.php
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.endpoint', 'https://api.openai.com/v1');
        $this->model = config('services.openai.model', 'gpt-3.5-turbo');
        
        // Log warning if API key is not set
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key is not configured in environment');
        }
    }

    /**
     * Ask a question to Cogni (OpenAI's ChatGPT)
     *
     * @param string $question The user's question
     * @param array $context Additional context or previous messages
     * @return array Response with success/error status and answer
     */
    public function askQuestion(string $question, array $context = []): array
    {
        if (empty($this->apiKey)) {
            Log::error('OpenAI API key is not configured');
            return [
                'success' => false,
                'message' => 'Cogni service is not properly configured',
                'code' => 500
            ];
        }

        try {
            // Create the messages array starting with a system message
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are Cogni, a friendly and knowledgeable teacher AI. Your goal is to explain concepts clearly and accurately, providing helpful examples where appropriate. You should adapt your explanations to be accessible but not condescending. If you\'re unsure about something, acknowledge the limits of your knowledge rather than making up information.'
                ]
            ];

            // Add context messages if provided
            if (!empty($context)) {
                foreach ($context as $message) {
                    $messages[] = $message;
                }
            }

            // Add the user's question
            $messages[] = [
                'role' => 'user',
                'content' => $question
            ];

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $answer = $data['choices'][0]['message']['content'] ?? null;

                if ($answer) {
                    return [
                        'success' => true,
                        'answer' => $answer,
                        'code' => 200
                    ];
                }
            }

            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get an answer from Cogni',
                'code' => 500
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI request error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'An error occurred while processing your question',
                'code' => 500
            ];
        }
    }

    /**
     * Generate an explanation for a topic
     *
     * @param string $topic The topic to explain
     * @param string $level The detail level (basic, intermediate, advanced)
     * @return array Response with success/error status and explanation
     */
    public function explainTopic(string $topic, string $level = 'intermediate'): array
    {
        $prompt = "Explain the concept of '{$topic}' at a {$level} level. Include key points, examples, and any relevant background information.";
        
        return $this->askQuestion($prompt);
    }

    /**
     * Generate a quiz on a topic
     *
     * @param string $topic The topic for the quiz
     * @param int $questionCount Number of questions to generate (default: 5)
     * @return array Response with success/error status and quiz data
     */
    public function generateQuiz(string $topic, int $questionCount = 5): array
    {
        $prompt = "Create a quiz with {$questionCount} questions about '{$topic}'. For each question, provide 4 possible answers and indicate the correct one. Format the response as JSON with the following structure: { \"questions\": [ { \"question\": \"Question text\", \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"], \"correctAnswer\": 0 } ] }";
        
        $result = $this->askQuestion($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        try {
            // Extract JSON from the answer
            $jsonStart = strpos($result['answer'], '{');
            $jsonEnd = strrpos($result['answer'], '}') + 1;
            $jsonStr = substr($result['answer'], $jsonStart, $jsonEnd - $jsonStart);
            
            $quiz = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($quiz['questions'])) {
                return [
                    'success' => true,
                    'quiz' => $quiz,
                    'code' => 200
                ];
            }
            
            // Fallback if JSON parsing fails
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Quiz generation error: ' . $e->getMessage());
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        }
    }
    
    /**
     * Generate a themed readlist based on a topic
     *
     * @param string $topic The topic for the readlist
     * @param array $availableContent Array of content that can be included in the readlist
     * @param int $itemCount Maximum number of items to include (default: 5)
     * @return array Response with success/error status and readlist data
     */
    public function generateReadlist(string $topic, array $availableContent, int $itemCount = 5): array
    {
        $prompt = "Generate a curated readlist on the topic '{$topic}'. ";
        
        // Add available content for the AI to select from
        $prompt .= "Here is the available content you can choose from:\n\n";
        
        foreach ($availableContent as $index => $item) {
            $type = isset($item['type']) ? $item['type'] : 'unknown';
            $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Untitled');
            $desc = isset($item['description']) ? $item['description'] : '';
            $id = isset($item['id']) ? $item['id'] : $index;
            
            $prompt .= "ID: {$id} | Type: {$type} | Title: {$title} | Description: {$desc}\n\n";
        }
        
        // Instructions for the AI
        $prompt .= "Select the {$itemCount} most relevant items for someone learning about '{$topic}'. ";
        $prompt .= "Organize them in a logical learning sequence. ";
        $prompt .= "Return your response in JSON format with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"An appropriate title for the readlist\",\n";
        $prompt .= "  \"description\": \"A helpful description of this readlist\",\n";
        $prompt .= "  \"items\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"id\": \"The ID of the item\",\n";
        $prompt .= "      \"notes\": \"Brief notes explaining why this item is included\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}";
        
        $result = $this->askQuestion($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        try {
            // Extract JSON from the answer
            $jsonStart = strpos($result['answer'], '{');
            $jsonEnd = strrpos($result['answer'], '}') + 1;
            $jsonStr = substr($result['answer'], $jsonStart, $jsonEnd - $jsonStart);
            
            $readlistData = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($readlistData['title'], $readlistData['description'], $readlistData['items'])) {
                return [
                    'success' => true,
                    'readlist' => $readlistData,
                    'code' => 200
                ];
            }
            
            // Fallback if JSON parsing fails
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        } catch (\Exception $e) {
            \Log::error('Readlist generation error: ' . $e->getMessage());
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        }
    }
    
    /**
     * Generate a readlist from a user's description, using both internal and external content
     *
     * @param string $description User's description of the readlist they want
     * @param array $internalContent Array of content from the database
     * @param int $itemCount Maximum number of items to include (default: 5)
     * @param int $externalItemCount Maximum number of external items to include (default: 3)
     * @return array Response with success/error status and readlist data
     */
    public function generateReadlistFromDescription(string $description, array $internalContent, int $itemCount = 5, int $externalItemCount = 3): array
    {
        $exaService = app(\App\Services\ExaSearchService::class);
        $contentModerationService = app(\App\Services\ContentModerationService::class);
        
        // First, check that the description itself doesn't contain inappropriate content
        $moderationResult = $contentModerationService->analyzeText($description);
        if (!$moderationResult['isAllowed']) {
            return [
                'success' => false,
                'message' => 'Description contains inappropriate content. Please modify your request.',
                'code' => 400
            ];
        }
        
        // Extract key topics from the description
        $prompt = "Extract 3-5 key academic or educational topics or search terms from the following description of a readlist. ";
        $prompt .= "Exclude any inappropriate or adult-oriented topics. ";
        $prompt .= "Return these as a JSON array of strings.\n\n";
        $prompt .= "Description: {$description}\n\n";
        $prompt .= "Format your response as a JSON array: [\"topic1\", \"topic2\", \"topic3\"]\n";
        
        $topicsResult = $this->askQuestion($prompt);
        $searchTopics = [];
        
        if ($topicsResult['success'] && isset($topicsResult['answer'])) {
            try {
                // Extract JSON array from the response
                $jsonStart = strpos($topicsResult['answer'], '[');
                $jsonEnd = strrpos($topicsResult['answer'], ']') + 1;
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonStr = substr($topicsResult['answer'], $jsonStart, $jsonEnd - $jsonStart);
                    $searchTopics = json_decode($jsonStr, true);
                }
                
                if (!is_array($searchTopics) || empty($searchTopics)) {
                    // Fallback: use the description as a single search term
                    $searchTopics = [trim($description)];
                }
            } catch (\Exception $e) {
                // Fallback: use the description as a single search term
                $searchTopics = [trim($description)];
            }
        } else {
            // Fallback: use the description as a single search term
            $searchTopics = [trim($description)];
        }
        
        // Check each extracted topic for inappropriate content
        foreach ($searchTopics as $key => $topic) {
            $topicCheck = $contentModerationService->analyzeText($topic);
            if (!$topicCheck['isAllowed']) {
                // Remove inappropriate topics
                unset($searchTopics[$key]);
                \Log::warning('Removed inappropriate search topic during readlist generation', ['topic' => $topic]);
            }
        }
        
        // If no valid topics remain, return an error
        if (empty($searchTopics)) {
            return [
                'success' => false,
                'message' => 'Unable to extract appropriate search topics from the description.',
                'code' => 400
            ];
        }
        
        // Fetch external content using Exa
        $externalContent = [];
        $searchTerm = implode(' educational resources ', $searchTopics);
        
        if ($exaService->isConfigured()) {
            // First try to get categorized resources for a more structured approach
            $externalResult = $exaService->getCategorizedResources($searchTerm);
            
            if ($externalResult['success']) {
                // Format external content from categorized results
                foreach ($externalResult['categories'] as $category => $results) {
                    foreach ($results as $result) {
                        // Verify that result content passes moderation
                        $titleCheck = $contentModerationService->analyzeText($result['title'] ?? '');
                        $textCheck = $contentModerationService->analyzeText($result['text'] ?? '');
                        
                        if ($titleCheck['isAllowed'] && $textCheck['isAllowed']) {
                            $externalContent[] = [
                                'title' => $result['title'] ?? 'Untitled',
                                'description' => $result['text'] ?? '',
                                'url' => $result['url'] ?? '',
                                'type' => 'external',
                                'category' => $category,
                                'domain' => $result['domain'] ?? ''
                            ];
                        }
                    }
                }
            }
            
            // If we didn't get enough content, or it failed, try a direct search
            if (count($externalContent) < $externalItemCount) {
                $searchResult = $exaService->findLearningResources($searchTerm, $externalItemCount * 3); // Request more to account for filtering
                
                if ($searchResult['success'] && !empty($searchResult['results'])) {
                    foreach ($searchResult['results'] as $result) {
                        // Verify that result content passes moderation
                        $titleCheck = $contentModerationService->analyzeText($result['title'] ?? '');
                        $textCheck = $contentModerationService->analyzeText($result['text'] ?? '');
                        $urlCheck = true;
                        
                        // Check if the URL is from a trustworthy domain
                        if (!empty($result['url'])) {
                            try {
                                $parsedUrl = parse_url($result['url']);
                                if (isset($parsedUrl['host'])) {
                                    $domain = $parsedUrl['host'];
                                    
                                    // Check domain against our inappropriate words list
                                    $domainCheck = $contentModerationService->analyzeText($domain);
                                    if (!$domainCheck['isAllowed']) {
                                        $urlCheck = false;
                                    }
                                }
                            } catch (\Exception $e) {
                                // If we can't parse the URL, exclude it to be safe
                                $urlCheck = false;
                                \Log::error('Error parsing URL in readlist generation', [
                                    'url' => $result['url'],
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        if ($titleCheck['isAllowed'] && $textCheck['isAllowed'] && $urlCheck) {
                            $externalContent[] = [
                                'title' => $result['title'] ?? 'Untitled',
                                'description' => $result['text'] ?? '',
                                'url' => $result['url'] ?? '',
                                'type' => 'external',
                                'domain' => $result['domain'] ?? ''
                            ];
                        }
                    }
                }
            }
        }
        
        // Log warning if no external content found, but continue with internal content
        if (empty($externalContent) && $externalItemCount > 0) {
            \Log::info('No external content found for readlist generation', [
                'description' => $description,
                'topics' => $searchTopics
            ]);
        }
        
        // Prepare prompt for generating readlist with combined content
        $prompt = "Generate a curated readlist based on this description: '{$description}'. ";
        
        // Add available internal content
        if (!empty($internalContent)) {
            $prompt .= "Here is the available internal content you can choose from:\n\n";
            
            foreach ($internalContent as $index => $item) {
                $type = isset($item['type']) ? $item['type'] : 'unknown';
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Untitled');
                $desc = isset($item['description']) ? $item['description'] : '';
                $id = isset($item['id']) ? $item['id'] : $index;
                
                $prompt .= "ID: {$id} | Type: internal_{$type} | Title: {$title} | Description: {$desc}\n\n";
            }
        }
        
        // Add available external content
        if (!empty($externalContent)) {
            if (empty($internalContent)) {
                $prompt .= "Here is the available content you can choose from (all external resources):\n\n";
            } else {
                $prompt .= "Here is additional external content you can choose from:\n\n";
            }
            
            foreach ($externalContent as $index => $item) {
                $title = $item['title'] ?? 'Untitled';
                $desc = $item['description'] ?? '';
                $url = $item['url'] ?? '';
                $id = "ext_" . ($index + 1); // Prefix with "ext_" to distinguish from internal content
                
                $prompt .= "ID: {$id} | Type: external | Title: {$title} | URL: {$url} | Description: {$desc}\n\n";
            }
        }
        
        // Instructions for the AI
        $totalItems = min($itemCount, count($internalContent) + count($externalContent));
        $prompt .= "Select up to {$totalItems} most relevant items for someone learning about '{$description}'. ";
        $prompt .= "Organize them in a logical learning sequence. ";
        $prompt .= "Aim to include both internal content and external resources if available, with a preference for high-quality, diverse sources. ";
        $prompt .= "For each item, explain why it's included and how it relates to the user's learning journey. ";
        $prompt .= "Return your response in JSON format with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"An appropriate title for the readlist\",\n";
        $prompt .= "  \"description\": \"A helpful description of this readlist\",\n";
        $prompt .= "  \"items\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"id\": \"The ID of the item\",\n";
        $prompt .= "      \"notes\": \"Brief notes explaining why this item is included\",\n";
        $prompt .= "      \"type\": \"internal_post, internal_course, or external\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}";
        
        $result = $this->askQuestion($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        try {
            // Extract JSON from the answer
            $jsonStart = strpos($result['answer'], '{');
            $jsonEnd = strrpos($result['answer'], '}') + 1;
            $jsonStr = substr($result['answer'], $jsonStart, $jsonEnd - $jsonStart);
            
            $readlistData = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($readlistData['title'], $readlistData['description'], $readlistData['items'])) {
                // Process the external items to include their full details
                foreach ($readlistData['items'] as &$item) {
                    if (isset($item['id']) && strpos($item['id'], 'ext_') === 0) {
                        // This is an external item, find its details
                        $externalIndex = (int)substr($item['id'], 4) - 1;
                        if (isset($externalContent[$externalIndex])) {
                            $item['url'] = $externalContent[$externalIndex]['url'] ?? '';
                            $item['title'] = $externalContent[$externalIndex]['title'] ?? '';
                            $item['description'] = $externalContent[$externalIndex]['description'] ?? '';
                            $item['domain'] = $externalContent[$externalIndex]['domain'] ?? '';
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'readlist' => $readlistData,
                    'code' => 200
                ];
            }
            
            // Fallback if JSON parsing fails
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        } catch (\Exception $e) {
            \Log::error('Readlist generation error: ' . $e->getMessage());
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        }
    }

    /**
     * Analyze a user's interests based on their data
     * 
     * @param array $userData User activity and preference data
     * @return array Response with success/error status and analysis
     */
    public function analyzeUserInterests(array $userData): array
    {
        $prompt = "Based on the following user activity data, analyze their interests and learning patterns. ";
        $prompt .= "Identify key topic areas they are interested in, with confidence levels (1-100) for each topic. ";
        $prompt .= "Also suggest potential related topics they might be interested in exploring, based on their current interests.\n\n";
        $prompt .= "User data:\n" . json_encode($userData, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Format your response as a JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"interests\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"topic\": \"Topic name\",\n";
        $prompt .= "      \"confidence\": 85,\n";
        $prompt .= "      \"subtopics\": [\"Subtopic 1\", \"Subtopic 2\"]\n";
        $prompt .= "    },\n";
        $prompt .= "    ...\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"recommendations\": [\"Topic 1\", \"Topic 2\", ...]\n";
        $prompt .= "}";
        
        return $this->askQuestion($prompt);
    }
    
    /**
     * Generate a list of relevant web resources for a set of topics
     * 
     * @param array $topics List of topics to find resources for
     * @param int $maxItems Maximum number of resources to return
     * @return array Response with success/error status and resources
     */
    public function findWebResources(array $topics, int $maxItems = 5): array
    {
        $topicsStr = implode(", ", $topics);
        $prompt = "Generate a list of {$maxItems} high-quality educational resources for the following topics: {$topicsStr}.\n\n";
        $prompt .= "For each resource, provide:\n";
        $prompt .= "1. A resource title\n";
        $prompt .= "2. A brief description of what the resource covers\n";
        $prompt .= "3. A URL for the resource (must be a real, well-known educational website URL)\n";
        $prompt .= "4. Brief notes to help the user understand why this resource is valuable\n\n";
        $prompt .= "Focus on resources from well-known educational platforms like Coursera, Khan Academy, MIT OpenCourseWare, edX, etc.\n\n";
        $prompt .= "Format your response as a JSON array with the following structure:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"title\": \"Resource title\",\n";
        $prompt .= "    \"description\": \"Brief description\",\n";
        $prompt .= "    \"url\": \"https://example.com/resource\",\n";
        $prompt .= "    \"notes\": \"Why this resource is valuable\"\n";
        $prompt .= "  },\n";
        $prompt .= "  ...\n";
        $prompt .= "]\n";
        
        return $this->askQuestion($prompt);
    }
    
    /**
     * Categorize a collection of posts into library topics
     * 
     * @param array $posts Array of posts to categorize
     * @param int $minPostsPerLibrary Minimum number of posts required for a library (default: 5)
     * @return array Response with success/error status and categorized libraries
     */
    public function categorizePosts(array $posts, int $minPostsPerLibrary = 5): array
    {
        $prompt = "You will be given a collection of posts from an education platform. Your task is to analyze these posts and group them into coherent libraries or collections based on their topics, themes, and content. Each library should have a minimum of {$minPostsPerLibrary} posts.\n\n";
        $prompt .= "Here are the posts to categorize:\n\n";
        
        foreach ($posts as $index => $post) {
            $id = $post['id'] ?? $index;
            $title = $post['title'] ?? 'Untitled';
            $content = $post['body'] ?? $post['content'] ?? '';
            
            // Truncate content if it's too long
            if (strlen($content) > 500) {
                $content = substr($content, 0, 500) . '...';
            }
            
            $prompt .= "Post ID: {$id}\n";
            $prompt .= "Title: {$title}\n";
            $prompt .= "Content: {$content}\n\n";
        }
        
        $prompt .= "For each library you identify, please provide:\n";
        $prompt .= "1. A descriptive name for the library\n";
        $prompt .= "2. A brief description of what this library covers\n";
        $prompt .= "3. A list of post IDs that belong in this library\n";
        $prompt .= "4. A short explanation of why these posts are grouped together\n";
        $prompt .= "5. 3-5 relevant keywords for this library\n\n";
        
        $prompt .= "Format your response as a JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"libraries\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"name\": \"Library name\",\n";
        $prompt .= "      \"description\": \"A clear description of this library's focus\",\n";
        $prompt .= "      \"post_ids\": [1, 2, 3, 4, 5],\n";
        $prompt .= "      \"rationale\": \"Why these posts belong together\",\n";
        $prompt .= "      \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        
        $prompt .= "Important notes:\n";
        $prompt .= "- Only create libraries with at least {$minPostsPerLibrary} posts\n";
        $prompt .= "- A post can belong to multiple libraries if relevant\n";
        $prompt .= "- Focus on educational value and coherence when creating libraries\n";
        $prompt .= "- Use clear, descriptive names that reflect the content\n";
        
        $result = $this->askQuestion($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        try {
            // Extract JSON from the answer
            $jsonStart = strpos($result['answer'], '{');
            $jsonEnd = strrpos($result['answer'], '}') + 1;
            $jsonStr = substr($result['answer'], $jsonStart, $jsonEnd - $jsonStart);
            
            $categorizedData = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($categorizedData['libraries'])) {
                return [
                    'success' => true,
                    'libraries' => $categorizedData['libraries'],
                    'code' => 200
                ];
            }
            
            // Fallback if JSON parsing fails
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        } catch (\Exception $e) {
            \Log::error('Post categorization error: ' . $e->getMessage());
            return [
                'success' => true,
                'answer' => $result['answer'],
                'code' => 200
            ];
        }
    }
}