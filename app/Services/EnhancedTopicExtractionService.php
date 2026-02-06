<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnhancedTopicExtractionService
{
    protected $apiKey;
    protected $baseUrl;
    
    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.endpoint', 'https://api.openai.com/v1');
    }
    
    /**
     * Extract and enhance topic from user input, using conversation context to resolve vague references.
     *
     * @param string $userInput The raw user input
     * @param array $userInterests Optional user interests for context
     * @param array|null $conversationContext Recent conversation messages for context (optional)
     * @return array Enhanced topic information
     */
    public function extractAndEnhanceTopic(string $userInput, array $userInterests = [], ?array $conversationContext = null): array
    {
        try {
            $cleanedInput = $this->cleanUserInput($userInput);
            $prompt = $this->createContextAwareTopicExtractionPrompt($cleanedInput, $userInterests, $conversationContext);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert at understanding user interests and extracting educational topics. You help create personalized learning experiences.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);
            if ($response->successful()) {
                $data = $response->json();
                $result = $data['choices'][0]['message']['content'] ?? null;
                if ($result) {
                    return $this->parseTopicExtractionResult($result, $cleanedInput);
                }
            }
            return $this->fallbackTopicExtraction($cleanedInput);
        } catch (\Exception $e) {
            Log::error('Error in enhanced topic extraction (context-aware)', [
                'message' => $e->getMessage(),
                'user_input' => $userInput
            ]);
            return $this->fallbackTopicExtraction($cleanedInput ?? $userInput);
        }
    }
    
    /**
     * Generate intelligent description for readlist based on topic and content
     * 
     * @param string $topic The main topic
     * @param array $contentItems The content items in the readlist
     * @param array $userInterests Optional user interests
     * @return string Generated description
     */
    public function generateIntelligentDescription(string $topic, array $contentItems = [], array $userInterests = []): string
    {
        try {
            $prompt = $this->createDescriptionGenerationPrompt($topic, $contentItems, $userInterests);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert at creating engaging, educational descriptions for learning resources. You write in a friendly, inspiring tone that motivates learners.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.8,
                'max_tokens' => 300,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $description = $data['choices'][0]['message']['content'] ?? null;
                
                if ($description) {
                    return trim($description);
                }
            }
            
            // Fallback description
            return $this->generateFallbackDescription($topic, $contentItems);
            
        } catch (\Exception $e) {
            Log::error('Error generating intelligent description', [
                'message' => $e->getMessage(),
                'topic' => $topic
            ]);
            
            return $this->generateFallbackDescription($topic, $contentItems);
        }
    }
    
    /**
     * Clean and normalize user input
     * 
     * @param string $input Raw user input
     * @return string Cleaned input
     */
    private function cleanUserInput(string $input): string
    {
        // Remove common readlist creation phrases
        $patterns = [
            '/^(cogni,?\s*)?(please\s*)?(create|make|build)(\s+a|\s+me\s+a)?\s+(readlist|reading list)(\s+for\s+me)?(\s+about|\s+on)?\s*/i',
            '/^(i\s+want\s+to\s+learn\s+about\s*)/i',
            '/^(can\s+you\s+help\s+me\s+with\s*)/i',
            '/^(show\s+me\s+resources\s+about\s*)/i',
            '/^(i\'m\s+interested\s+in\s*)/i',
        ];
        
        $cleaned = $input;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        
        return trim($cleaned);
    }
    
    /**
     * Create a context-aware prompt for topic extraction, instructing GPT to resolve vague references using conversation history.
     *
     * @param string $cleanedInput Cleaned user input
     * @param array $userInterests User interests for context
     * @param array|null $conversationContext Recent conversation messages
     * @return string The prompt
     */
    private function createContextAwareTopicExtractionPrompt(string $cleanedInput, array $userInterests = [], ?array $conversationContext = null): string
    {
        $prompt = "Analyze the following user request and extract the educational topic they're interested in learning about.\n";
        if (!empty($conversationContext)) {
            $prompt .= "Here is the recent conversation history (most recent last):\n";
            foreach ($conversationContext as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';
                $prompt .= ucfirst($role) . ": " . $content . "\n";
            }
            $prompt .= "\n";
            $prompt .= "If the user's request uses vague words like 'this', 'that', or pronouns, use the conversation context to infer what they mean.\n";
        }
        $prompt .= "User request: \"{$cleanedInput}\"\n\n";
        if (!empty($userInterests)) {
            $prompt .= "User's known interests: " . implode(', ', $userInterests) . "\n\n";
        }
        $prompt .= "Please provide a JSON response with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"primary_topic\": \"The main educational topic\",\n";
        $prompt .= "  \"subtopics\": [\"related subtopic 1\", \"related subtopic 2\"],\n";
        $prompt .= "  \"learning_level\": \"beginner|intermediate|advanced\",\n";
        $prompt .= "  \"context\": \"Why the user might be interested in this topic\",\n";
        $prompt .= "  \"search_keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],\n";
        $prompt .= "  \"category\": \"technology|science|art|business|health|history|other\"\n";
        $prompt .= "}\n\n";
        $prompt .= "Focus on educational value and make the topic specific enough for creating a meaningful learning experience.";
        return $prompt;
    }
    
    /**
     * Parse the topic extraction result
     * 
     * @param string $result The AI response
     * @param string $originalInput Original user input
     * @return array Parsed topic information
     */
    private function parseTopicExtractionResult(string $result, string $originalInput): array
    {
        try {
            // Try to extract JSON from the response
            $jsonStart = strpos($result, '{');
            $jsonEnd = strrpos($result, '}') + 1;
            
            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonStr = substr($result, $jsonStart, $jsonEnd - $jsonStart);
                $parsed = json_decode($jsonStr, true);
                
                if (is_array($parsed)) {
                    return array_merge($parsed, [
                        'original_input' => $originalInput,
                        'extraction_method' => 'ai_enhanced'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI topic extraction result', [
                'result' => $result,
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback parsing
        return $this->fallbackTopicExtraction($originalInput);
    }
    
    /**
     * Fallback topic extraction when AI fails
     * 
     * @param string $input User input
     * @return array Basic topic information
     */
    private function fallbackTopicExtraction(string $input): array
    {
        $topic = trim($input);
        
        return [
            'primary_topic' => $topic,
            'subtopics' => [],
            'learning_level' => 'beginner',
            'context' => 'User requested information about ' . $topic,
            'search_keywords' => [$topic],
            'category' => 'other',
            'original_input' => $input,
            'extraction_method' => 'fallback'
        ];
    }
    
    /**
     * Create prompt for intelligent description generation
     * 
     * @param string $topic The main topic
     * @param array $contentItems Content items
     * @param array $userInterests User interests
     * @return string The prompt
     */
    private function createDescriptionGenerationPrompt(string $topic, array $contentItems, array $userInterests): string
    {
        $prompt = "Create an engaging, educational description for a readlist about '{$topic}'.\n\n";
        
        if (!empty($contentItems)) {
            $prompt .= "The readlist contains " . count($contentItems) . " items including:\n";
            foreach (array_slice($contentItems, 0, 5) as $item) {
                $title = $item['title'] ?? 'Untitled';
                $type = $item['type'] ?? 'resource';
                $prompt .= "- {$title} ({$type})\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($userInterests)) {
            $prompt .= "The user is interested in: " . implode(', ', $userInterests) . "\n\n";
        }
        
        $prompt .= "Write a 2-3 sentence description that:\n";
        $prompt .= "- Explains what the readlist covers\n";
        $prompt .= "- Highlights the value for learners\n";
        $prompt .= "- Uses an inspiring, educational tone\n";
        $prompt .= "- Is engaging but professional\n";
        $prompt .= "- Avoids being too technical or jargon-heavy\n\n";
        $prompt .= "Description:";
        
        return $prompt;
    }
    
    /**
     * Generate fallback description
     * 
     * @param string $topic The topic
     * @param array $contentItems Content items
     * @return string Fallback description
     */
    private function generateFallbackDescription(string $topic, array $contentItems): string
    {
        $itemCount = count($contentItems);
        $itemText = $itemCount === 1 ? 'resource' : 'resources';
        
        return "A curated collection of {$itemCount} {$itemText} about {$topic}. " .
               "This readlist provides comprehensive coverage of the topic, combining " .
               "internal platform content with carefully selected external resources to " .
               "create a complete learning experience.";
    }
    
    /**
     * Analyze user interests from conversation history
     * 
     * @param array $conversationHistory Previous conversation messages
     * @return array Extracted interests
     */
    public function analyzeUserInterests(array $conversationHistory): array
    {
        if (empty($conversationHistory)) {
            return [];
        }
        
        try {
            // Combine recent messages for analysis
            $recentMessages = array_slice($conversationHistory, -5); // Last 5 messages
            $combinedText = implode(' ', array_column($recentMessages, 'content'));
            
            $prompt = "Analyze this conversation and extract the user's main learning interests and topics they're curious about.\n\n";
            $prompt .= "Conversation: {$combinedText}\n\n";
            $prompt .= "Return a JSON array of interest topics: [\"interest1\", \"interest2\", \"interest3\"]";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.5,
                'max_tokens' => 200,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $result = $data['choices'][0]['message']['content'] ?? null;
                
                if ($result) {
                    // Try to extract JSON array
                    $jsonStart = strpos($result, '[');
                    $jsonEnd = strrpos($result, ']') + 1;
                    
                    if ($jsonStart !== false && $jsonEnd !== false) {
                        $jsonStr = substr($result, $jsonStart, $jsonEnd - $jsonStart);
                        $interests = json_decode($jsonStr, true);
                        
                        if (is_array($interests)) {
                            return array_slice($interests, 0, 5); // Limit to top 5 interests
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to analyze user interests', [
                'error' => $e->getMessage()
            ]);
        }
        
        return [];
    }
} 