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
     * @param boolean $safeSearch Whether to enable safe search filtering
     * @param array $excludeDomains Domains to exclude from results
     * @return array Search results or error information
     */
    public function search(string $query, int $numResults = 5, bool $includeDomains = true, bool $safeSearch = true, array $excludeDomains = [])
    {
        if (empty($this->apiKey)) {
            Log::error('Exa API key not configured');
            return [
                'success' => false,
                'message' => 'Exa API key not configured',
                'results' => []
            ];
        }

        // Append safety filters for educational content
        if ($safeSearch) {
            $query .= " educational content";
        }

        try {
            $numResults = min($numResults, 20); // Maximum 20 results per Exa API
            
            $payload = [
                'query' => $query,
                'numResults' => $numResults,
                'includeDomains' => $includeDomains,
                'safeSearch' => $safeSearch
            ];
            
            // Add excluded domains if provided
            if (!empty($excludeDomains)) {
                $payload['excludeDomains'] = $excludeDomains;
            }
            
            // Log the attempt to call Exa API
            Log::info('Calling Exa API with payload', [
                'query' => $query,
                'numResults' => $numResults,
                'baseUrl' => $this->baseUrl
            ]);
            
            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(10) // Add a timeout to prevent long-running requests
                ->post("{$this->baseUrl}/search", $payload);
                
            // Log the response
            Log::info('Exa API response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_excerpt' => substr($response->body(), 0, 500)
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
                
                // Apply additional content moderation
                $formattedResults = $this->moderateResults($formattedResults);
                
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
            Log::error('Exa API search exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'api_key_length' => $this->apiKey ? strlen($this->apiKey) : 0
            ]);
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Apply additional content moderation to search results
     * 
     * @param array $results The search results to moderate
     * @return array Filtered search results
     */
    private function moderateResults(array $results)
    {
        // Get content moderation service
        $contentModerationService = app(\App\Services\ContentModerationService::class);
        
        // Filter out results that don't pass content moderation
        return array_filter($results, function($result) use ($contentModerationService) {
            // Check title and text for inappropriate content
            $titleCheck = $contentModerationService->analyzeText($result['title'] ?? '');
            if (!$titleCheck['isAllowed']) {
                return false;
            }
            
            $textCheck = $contentModerationService->analyzeText($result['text'] ?? '');
            if (!$textCheck['isAllowed']) {
                return false;
            }
            
            // Check URL for allowed domains
            $url = $result['url'] ?? '';
            try {
                if (!empty($url)) {
                    $parsedUrl = parse_url($url);
                    if (isset($parsedUrl['host'])) {
                        $domain = $parsedUrl['host'];
                        $allowedDomains = config('content_moderation.allowed_domains', []);
                        
                        // Special case: For external search, we don't want to strictly enforce the domain whitelist
                        // Instead, we'll check if the domain contains any inappropriate words
                        $domainCheck = $contentModerationService->analyzeText($domain);
                        if (!$domainCheck['isAllowed']) {
                            return false;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error parsing URL during content moderation', [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
                // If we can't parse the URL, exclude the result to be safe
                return false;
            }
            
            return true;
        });
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
        // Use specific educational keywords to ensure quality results
        $query = "learn about {$topic} educational resources guides tutorials academic course";
        
        // Common spam or inappropriate domains to exclude
        $excludeDomains = [
            'pinterest.com', // Often contains low-quality content
            'quora.com',     // Can contain unverified information
            'reddit.com',    // May contain inappropriate content
            'twitter.com',   // May contain unverified information
            'facebook.com',  // May contain unverified information
            'instagram.com', // May contain inappropriate content
        ];
        
        return $this->search($query, $numResults, true, true, $excludeDomains);
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
        
        // Add educational focus to query
        $query = "Find academic educational resources related to: {$truncatedContent}";
        
        // Common spam or inappropriate domains to exclude
        $excludeDomains = [
            'pinterest.com', // Often contains low-quality content
            'quora.com',     // Can contain unverified information
            'reddit.com',    // May contain inappropriate content
            'twitter.com',   // May contain unverified information
            'facebook.com',  // May contain unverified information
            'instagram.com', // May contain inappropriate content
        ];
        
        return $this->search($query, $numResults, true, true, $excludeDomains);
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
                'Beginner Guides' => 'beginner guide introduction tutorial academic course',
                'Advanced Resources' => 'advanced in-depth comprehensive expert scholarly research',
                'Latest Developments' => 'latest current new development recent update research paper',
                'Best Practices' => 'best practices examples standards tips official documentation'
            ];
        }

        $categorizedResults = [];
        
        // Common spam or inappropriate domains to exclude
        $excludeDomains = [
            'pinterest.com', // Often contains low-quality content
            'quora.com',     // Can contain unverified information
            'reddit.com',    // May contain inappropriate content
            'twitter.com',   // May contain unverified information
            'facebook.com',  // May contain unverified information
            'instagram.com', // May contain inappropriate content
        ];

        // Search for each category
        foreach ($categories as $categoryName => $searchTerms) {
            $query = "{$topic} {$searchTerms}";
            $results = $this->search($query, 3, true, true, $excludeDomains);
            
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