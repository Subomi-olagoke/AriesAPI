<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiLibraryCategorizer
{
    private string $apiKey;
    private string $endpoint;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->endpoint = config('services.openai.endpoint');
        $this->model = config('services.openai.model');

        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured');
        }
    }

    /**
     * Categorize a URL into the most appropriate library
     *
     * @param string $url The URL to categorize
     * @param string $title The title of the content
     * @param string $summary The summary/description of the content
     * @param array $libraries Available libraries with their metadata
     * @return array Returns ['library_id' => int, 'confidence' => float, 'reasoning' => string, 'alternatives' => array]
     * @throws \Exception
     */
    public function categorize(string $url, string $title, string $summary, array $libraries)
    {
        if (empty($libraries)) {
            throw new \Exception('No libraries available for categorization');
        }

        // Build the prompt for GPT
        $prompt = $this->buildCategorizationPrompt($url, $title, $summary, $libraries);

        try {
            // Make request to OpenAI API using HTTP client
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->endpoint . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert librarian who excels at categorizing content into the most appropriate library based on topic, keywords, and criteria. Always respond with valid JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3, // Lower temperature for more consistent results
                'max_tokens' => 500,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Failed to get AI categorization: ' . $response->body());
            }

            $responseData = $response->json();

            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid response from OpenAI API');
            }

            // Parse the JSON response from GPT
            $result = json_decode($responseData['choices'][0]['message']['content'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse AI response: ' . json_last_error_msg());
            }

            // Validate the result
            if (!isset($result['library_id']) || !isset($result['confidence'])) {
                throw new \Exception('AI response missing required fields');
            }

            // Ensure confidence is between 0 and 1
            $result['confidence'] = max(0, min(1, (float)$result['confidence']));

            return $result;

        } catch (\Exception $e) {
            Log::error('AI categorization failed', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            throw $e;
        }
    }

    /**
     * Build the prompt for GPT categorization
     */
    private function buildCategorizationPrompt(string $url, string $title, string $summary, array $libraries): string
    {
        $librariesInfo = collect($libraries)->map(function ($library) {
            $keywords = is_array($library['keywords']) ? implode(', ', $library['keywords']) : '';
            $criteria = is_array($library['criteria']) ? implode(', ', $library['criteria']) : '';

            return sprintf(
                "ID: %d\nName: %s\nDescription: %s\nKeywords: %s\nCriteria: %s",
                $library['id'],
                $library['name'],
                $library['description'] ?? '',
                $keywords,
                $criteria
            );
        })->implode("\n\n---\n\n");

        return <<<PROMPT
Analyze the following content and determine which library it belongs to.

CONTENT TO CATEGORIZE:
URL: {$url}
Title: {$title}
Summary: {$summary}

AVAILABLE LIBRARIES:
{$librariesInfo}

Based on the content's topic, keywords, and subject matter, select the MOST appropriate library.

IMPORTANT RULES:
1. Match based on topical relevance, keywords, and criteria
2. If confidence is below 0.7, suggest up to 2 alternative libraries
3. If no library is a good match, set confidence below 0.5
4. Be strict with confidence scores - only use 0.8+ for clear matches

Respond with ONLY a JSON object in this exact format:
{
    "library_id": <int>,
    "confidence": <float between 0 and 1>,
    "reasoning": "<brief explanation of why this library was chosen>",
    "alternatives": [
        {"library_id": <int>, "confidence": <float>},
        {"library_id": <int>, "confidence": <float>}
    ]
}

If there are no good alternatives, return an empty array for "alternatives".
PROMPT;
    }
}
