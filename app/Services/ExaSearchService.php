<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ExaSearchService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.exa.ai';
    protected $defaultHeaders;
    protected $maxResults = 5;

    public function __construct()
    {
        $this->apiKey = config('services.exa.api_key');
        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }

    /**
     * Search the web for relevant information related to a topic
     *
     * @param string $query The search query
     * @param int $numResults Number of results to return (max 20)
     * @param boolean $includeDomains Whether to include domains in the response
     * @return array Search results or error information
     */
    public function search(string $query, int $numResults = 5, bool $includeDomains = true)
    {
        if (empty($this->apiKey)) {
            Log::error('Exa API key not configured');
            return [
                'success' => false,
                'message' => 'Exa API key not configured',
                'results' => []
            ];
        }

        try {
            $numResults = min($numResults, 20); // Maximum 20 results per Exa API
            
            $response = Http::withHeaders($this->defaultHeaders)
                ->post("{$this->baseUrl}/search", [
                    'query' => $query,
                    'numResults' => $numResults,
                    'includeDomains' => $includeDomains,
                ]);

            if ($response->successful()) {
                $results = $response->json('results');
                
                // Format the results for easier consumption
                $formattedResults = array_map(function($result) {
                    return [
                        'title' => $result['title'] ?? 'Untitled',
                        'url' => $result['url'] ?? '',
                        'text' => $result['text'] ?? '',
                        'domain' => $result['domain'] ?? '',
                        'published_date' => $result['publishedDate'] ?? null,
                    ];
                }, $results);
                
                return [
                    'success' => true,
                    'results' => $formattedResults
                ];
            } else {
                Log::error('Exa API search error: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Search error: ' . $response->status(),
                    'details' => $response->json(),
                    'results' => []
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exa API search exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }

    /**
     * Find learning resources related to a specific topic
     *
     * @param string $topic The topic to find learning resources for
     * @param int $numResults Number of results to return
     * @return array Array of learning resources
     */
    public function findLearningResources(string $topic, int $numResults = 5)
    {
        $query = "learn about {$topic} educational resources guides tutorials";
        return $this->search($query, $numResults);
    }

    /**
     * Find related content for a post
     *
     * @param string $postContent The post content to find related resources for
     * @param int $numResults Number of results to return
     * @return array Array of related resources
     */
    public function findRelatedContent(string $postContent, int $numResults = 5)
    {
        // Extract main topics from the post content
        $truncatedContent = substr($postContent, 0, 500); // Limit to 500 chars to keep query focused
        
        $query = "Find educational resources related to: {$truncatedContent}";
        return $this->search($query, $numResults);
    }

    /**
     * Get learning resources with categorization
     *
     * @param string $topic The topic to analyze
     * @param array $categories Optional categories to organize results into
     * @return array Categorized learning resources
     */
    public function getCategorizedResources(string $topic, array $categories = [])
    {
        if (empty($categories)) {
            $categories = [
                'Beginner Guides' => 'beginner guide introduction tutorial',
                'Advanced Resources' => 'advanced in-depth comprehensive expert',
                'Latest Developments' => 'latest current new development recent update',
                'Best Practices' => 'best practices examples standards tips'
            ];
        }

        $categorizedResults = [];

        // Search for each category
        foreach ($categories as $categoryName => $searchTerms) {
            $query = "{$topic} {$searchTerms}";
            $results = $this->search($query, 3);
            
            if ($results['success'] && !empty($results['results'])) {
                $categorizedResults[$categoryName] = $results['results'];
            }
        }

        return [
            'success' => !empty($categorizedResults),
            'topic' => $topic,
            'categories' => $categorizedResults
        ];
    }

    /**
     * Check if the Exa service is properly configured
     *
     * @return bool True if the service is properly configured
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }
}