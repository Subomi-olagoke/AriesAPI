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
        // Hardcoded API key - replace this with your actual OpenAI API key
        $this->apiKey = 'sk-vVeQcJc7smgCrz3_CA-kSjOFNeK9mZK1PAh-oeFBmTT3BlbkFJtx_ZcL0zdW2-H4TREVGCWSutKtT37wifoXV7vJZNsA';
        
        // Don't use config for now
        // $this->apiKey = config('services.openai.api_key');
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
}