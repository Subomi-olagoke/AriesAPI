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
}