<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PersonalizedFactsService
{
    protected $cogniService;
    
    /**
     * Create a new service instance
     * 
     * @param CogniService $cogniService
     */
    public function __construct(CogniService $cogniService)
    {
        $this->cogniService = $cogniService;
    }
    
    /**
     * Get personalized interesting facts for user based on their interests
     * 
     * @param User $user
     * @param string|null $topic Optional specific topic to get facts about
     * @param int $count Number of facts to return
     * @return array
     */
    public function getInterestingFacts(User $user, ?string $topic = null, int $count = 1): array
    {
        try {
            // If topic is provided, use that directly
            if ($topic) {
                return $this->getFactsForTopic($topic, $count);
            }
            
            // Otherwise, get user's interests
            $userInterests = $this->getUserInterests($user);
            
            if (empty($userInterests)) {
                return [
                    'success' => false,
                    'message' => 'No interests found for this user',
                    'code' => 404
                ];
            }
            
            // Select a random interest to provide facts about
            $randomInterest = $userInterests[array_rand($userInterests)];
            
            return $this->getFactsForTopic($randomInterest, $count);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating interesting facts: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Get a user's interests based on their profile and activities
     * 
     * @param User $user
     * @return array List of interests
     */
    private function getUserInterests(User $user): array
    {
        $interests = [];
        
        // Get interests from user topics
        $topicInterests = $user->topic()->pluck('name')->toArray();
        $interests = array_merge($interests, $topicInterests);
        
        // Get interests from courses enrolled
        $courseTopics = $user->enrollments()
            ->with('course.topic')
            ->get()
            ->map(function($enrollment) {
                return $enrollment->course->topic->name ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $interests = array_merge($interests, $courseTopics);
        
        // Get interests from posts interacted with (likes, comments, bookmarks)
        $postKeywords = $user->likes()
            ->where('likeable_type', 'App\\Models\\Post')
            ->with('likeable')
            ->get()
            ->map(function($like) {
                return $this->extractKeywords($like->likeable->title . ' ' . $like->likeable->body);
            })
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
        $interests = array_merge($interests, $postKeywords);
        
        // Get interests from search history (if available)
        // This would require additional implementation
        
        // Remove duplicates and empty values
        $interests = array_unique(array_filter($interests));
        
        // If no interests found, return some default topics
        if (empty($interests)) {
            return [
                'Education', 'Technology', 'Science', 'Art', 
                'History', 'Mathematics', 'Programming', 'Literature'
            ];
        }
        
        return $interests;
    }
    
    /**
     * Get interesting facts for a specific topic
     * 
     * @param string $topic
     * @param int $count
     * @return array
     */
    public function getFactsForTopic(string $topic, int $count = 1): array
    {
        try {
            // Check cache first
            $cacheKey = 'interesting_facts_' . strtolower(str_replace(' ', '_', $topic)) . '_' . $count;
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // Use Cogni to generate interesting facts
            $prompt = "Generate {$count} fascinating and surprising fact" . ($count > 1 ? 's' : '') . 
                      " about {$topic} that would captivate someone interested in this subject. " . 
                      "The fact" . ($count > 1 ? 's' : '') . " should be specific, accurate, and attention-grabbing. " .
                      "Make them sound interesting enough that someone would want to share them. " .
                      "Include 1-2 sentence explanation for each fact that provides context or additional information. " .
                      "Format your response as a JSON object with this structure: " .
                      "{ \"facts\": [{ \"fact\": \"The surprising fact\", \"explanation\": \"More context about why this is interesting\" }] }";
            
            $result = $this->cogniService->askQuestion($prompt);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate facts: ' . ($result['message'] ?? 'Unknown error'),
                    'code' => $result['code'] ?? 500
                ];
            }
            
            // Try to extract JSON from the response
            $jsonStr = $this->extractJsonFromText($result['answer']);
            
            if ($jsonStr) {
                $facts = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($facts['facts'])) {
                    $response = [
                        'success' => true,
                        'topic' => $topic,
                        'facts' => $facts['facts'],
                        'code' => 200
                    ];
                    
                    // Cache for 1 day
                    Cache::put($cacheKey, $response, 86400);
                    
                    return $response;
                }
            }
            
            // If JSON parsing fails, return the raw text
            return [
                'success' => true,
                'topic' => $topic,
                'facts' => [
                    [
                        'fact' => 'Interesting fact about ' . $topic,
                        'explanation' => $result['answer']
                    ]
                ],
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating facts: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Extract keywords from text
     * 
     * @param string $text
     * @return array
     */
    private function extractKeywords(string $text): array
    {
        // Remove common words and special characters
        $stopWords = ['the', 'and', 'a', 'of', 'to', 'in', 'is', 'it', 'that', 'for', 'on', 'with'];
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        // Filter out stop words and short words
        $words = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) >= 4;
        });
        
        // Count word frequencies
        $wordCounts = array_count_values($words);
        
        // Sort by frequency
        arsort($wordCounts);
        
        // Return top keywords (maximum 5)
        return array_slice(array_keys($wordCounts), 0, 5);
    }
    
    /**
     * Extract JSON object from text
     * 
     * @param string $text
     * @return string|null JSON string or null if not found
     */
    private function extractJsonFromText(string $text): ?string
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/x', $text, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Get a daily interesting fact for a user
     * 
     * @param User $user
     * @return array
     */
    public function getDailyFact(User $user): array
    {
        $cacheKey = 'daily_fact_' . $user->id . '_' . date('Y-m-d');
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $result = $this->getInterestingFacts($user);
        
        if ($result['success']) {
            // Cache for 24 hours
            Cache::put($cacheKey, $result, 86400);
        }
        
        return $result;
    }
}