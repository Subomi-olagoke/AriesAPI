<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Article AI Service - GPT-powered article analysis
 * 
 * Provides AI-powered features for articles:
 * - Article summarization
 * - Question answering about articles
 * - Key points extraction
 */
class ArticleAIService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.openai.com/v1/chat/completions';
    protected $model = 'gpt-4o-mini'; // Cost-effective model
    protected $maxTokens = 1000;
    protected $temperature = 0.7;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured');
        }
    }

    /**
     * Summarize an article
     * 
     * @param string $articleText Full article text
     * @param string|null $url Article URL for caching
     * @return array ['summary' => string, 'key_points' => array, 'reading_time' => int]
     */
    public function summarizeArticle(string $articleText, ?string $url = null): array
    {
        // Check cache first (cache for 7 days)
        if ($url) {
            $cacheKey = 'article_summary_' . md5($url);
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('Returning cached summary for: ' . $url);
                return $cached;
            }
        }

        if (empty($this->apiKey)) {
            return $this->getFallbackSummary($articleText);
        }

        // Truncate article if too long (max ~3000 words / 12000 chars for context)
        $truncatedText = $this->truncateText($articleText, 12000);

        $prompt = "Please provide a concise summary of this article in 3-4 sentences. Also extract 3-5 key points as bullet points.\n\nArticle:\n{$truncatedText}\n\nRespond in this exact JSON format:\n{\n  \"summary\": \"Your concise summary here\",\n  \"key_points\": [\"Point 1\", \"Point 2\", \"Point 3\"]\n}";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant that summarizes articles concisely. Always respond with valid JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                // Parse JSON response
                $parsed = $this->parseAIResponse($content);
                
                // Calculate reading time (200 words per minute)
                $wordCount = str_word_count($articleText);
                $parsed['reading_time'] = max(1, ceil($wordCount / 200));
                $parsed['word_count'] = $wordCount;

                // Cache the result
                if ($url) {
                    Cache::put($cacheKey, $parsed, now()->addDays(7));
                }

                Log::info('Article summarized successfully', [
                    'url' => $url,
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0
                ]);

                return $parsed;
            } else {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->getFallbackSummary($articleText);
            }
        } catch (\Exception $e) {
            Log::error('Article summarization failed: ' . $e->getMessage());
            return $this->getFallbackSummary($articleText);
        }
    }

    /**
     * Ask a question about an article
     * 
     * @param string $articleText Full article text
     * @param string $question User's question
     * @param array $conversationHistory Previous Q&A pairs
     * @return array ['answer' => string, 'confidence' => string]
     */
    public function askAboutArticle(string $articleText, string $question, array $conversationHistory = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'answer' => 'AI features are currently unavailable. Please try again later.',
                'confidence' => 'low'
            ];
        }

        // Truncate article if too long
        $truncatedText = $this->truncateText($articleText, 10000);

        // Build conversation context
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that answers questions about articles. Provide accurate, concise answers based on the article content. If the answer is not in the article, say so clearly.'
            ],
            [
                'role' => 'user',
                'content' => "Here is the article:\n\n{$truncatedText}\n\nI'll ask you questions about this article."
            ],
            [
                'role' => 'assistant',
                'content' => 'I\'ve read the article. I\'m ready to answer your questions about it.'
            ]
        ];

        // Add conversation history
        foreach ($conversationHistory as $exchange) {
            if (isset($exchange['question']) && isset($exchange['answer'])) {
                $messages[] = ['role' => 'user', 'content' => $exchange['question']];
                $messages[] = ['role' => 'assistant', 'content' => $exchange['answer']];
            }
        }

        // Add current question
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $answer = $data['choices'][0]['message']['content'] ?? 'Unable to generate answer.';

                Log::info('Question answered successfully', [
                    'question' => $question,
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0
                ]);

                return [
                    'answer' => trim($answer),
                    'confidence' => 'high',
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0
                ];
            } else {
                Log::error('OpenAI API error for Q&A', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'answer' => 'Sorry, I encountered an error while processing your question. Please try again.',
                    'confidence' => 'low'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Article Q&A failed: ' . $e->getMessage());
            
            return [
                'answer' => 'Sorry, I encountered an error while processing your question. Please try again.',
                'confidence' => 'low'
            ];
        }
    }

    /**
     * Get suggested questions for an article
     * 
     * @param string $articleText Article text or summary
     * @return array List of suggested questions
     */
    public function getSuggestedQuestions(string $articleText): array
    {
        if (empty($this->apiKey)) {
            return $this->getGenericQuestions();
        }

        $truncatedText = $this->truncateText($articleText, 5000);

        $prompt = "Based on this article, suggest 5 interesting questions a reader might ask.\n\nArticle:\n{$truncatedText}\n\nReturn only a JSON array of questions, like: [\"Question 1?\", \"Question 2?\"]";

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful assistant that generates relevant questions. Always return valid JSON array.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 200,
                    'temperature' => 0.8,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '[]';
                
                // Parse JSON array
                $questions = json_decode($content, true);
                
                if (is_array($questions) && count($questions) > 0) {
                    return array_slice($questions, 0, 5);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate suggested questions: ' . $e->getMessage());
        }

        return $this->getGenericQuestions();
    }

    /**
     * Truncate text to max length while preserving words
     */
    private function truncateText(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = substr($text, 0, $maxLength);
        
        // Truncate at last complete word
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    /**
     * Parse AI response (handles both JSON and plain text)
     */
    private function parseAIResponse(string $content): array
    {
        // Try to parse as JSON first
        $decoded = json_decode($content, true);
        
        if (is_array($decoded) && isset($decoded['summary'])) {
            return [
                'summary' => $decoded['summary'],
                'key_points' => $decoded['key_points'] ?? []
            ];
        }

        // Fallback: parse plain text
        $lines = explode("\n", $content);
        $summary = '';
        $keyPoints = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (str_starts_with($line, '-') || str_starts_with($line, '•') || str_starts_with($line, '*')) {
                $keyPoints[] = ltrim($line, '- •*');
            } else if (empty($summary)) {
                $summary = $line;
            }
        }

        return [
            'summary' => $summary ?: $content,
            'key_points' => $keyPoints
        ];
    }

    /**
     * Get fallback summary when AI is unavailable
     */
    private function getFallbackSummary(string $articleText): array
    {
        // Simple extractive summary: first 3 sentences
        $sentences = preg_split('/[.!?]+/', $articleText, -1, PREG_SPLIT_NO_EMPTY);
        $summary = implode('. ', array_slice($sentences, 0, 3)) . '.';
        
        $wordCount = str_word_count($articleText);
        
        return [
            'summary' => trim($summary),
            'key_points' => ['AI summarization unavailable', 'Full article available below'],
            'reading_time' => max(1, ceil($wordCount / 200)),
            'word_count' => $wordCount,
            'is_fallback' => true
        ];
    }

    /**
     * Get generic questions when AI is unavailable
     */
    private function getGenericQuestions(): array
    {
        return [
            "What's the main point of this article?",
            "Can you explain this in simpler terms?",
            "What are the key takeaways?",
            "What evidence supports the main argument?",
            "How does this relate to current events?"
        ];
    }
}
