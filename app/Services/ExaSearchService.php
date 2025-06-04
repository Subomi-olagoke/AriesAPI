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
     * @param int $numResults Number of results to return (max 100)
     * @param mixed $includeDomains Array of domains to include, or boolean (true = all domains)
     * @param boolean $safeSearch Whether to enable safe search filtering
     * @param array $excludeDomains Domains to exclude from results
     * @param string $type Search type: 'neural', 'keyword', or 'auto' (default)
     * @param string $category Optional category to filter results ('news', 'research', 'company', etc.)
     * @param array $dateRange Optional associative array with 'start' and 'end' dates
     * @param array $contentsOptions Optional array for content retrieval settings
     * @return array Search results or error information
     */
    public function search(
        string $query, 
        int $numResults = 5, 
        $includeDomains = [], 
        bool $safeSearch = true, 
        array $excludeDomains = [],
        string $type = 'auto',
        string $category = '',
        array $dateRange = [],
        array $contentsOptions = []
    ) {
        if (empty($this->apiKey)) {
            Log::error('Exa API key not configured', [
                'api_key_empty' => empty($this->apiKey),
                'config_key_empty' => empty(config('services.exa.api_key')),
                'config_key_length' => !empty(config('services.exa.api_key')) ? strlen(config('services.exa.api_key')) : 0
            ]);
            return [
                'success' => false,
                'message' => 'Exa API key not configured',
                'results' => [],
                'diagnostic' => 'API key is missing in configuration'
            ];
        }

        try {
            $numResults = min($numResults, 100); // Maximum 100 results per Exa API
            
            // Base payload with required parameters
            $payload = [
                'query' => $query,
                'numResults' => $numResults,
                'safeSearch' => $safeSearch,
                'type' => $type // Search type (neural, keyword, or auto)
            ];
            
            // Add category if specified
            if (!empty($category)) {
                $payload['category'] = $category;
            }
            
            // Handle includeDomains parameter correctly
            // API expects includeDomains to be an array, never a boolean
            if (is_array($includeDomains)) {
                $payload['includeDomains'] = $includeDomains;
            } else if ($includeDomains === true) {
                // If true was passed, use an empty array (include all domains)
                $payload['includeDomains'] = [];
            } else if ($includeDomains === false) {
                // If false was passed, simply don't include the parameter
                // as there's no way to specify "include no domains"
            }
            
            // Add excluded domains if provided
            if (!empty($excludeDomains)) {
                $payload['excludeDomains'] = $excludeDomains;
            }
            
            // Add date range filters if provided
            if (!empty($dateRange)) {
                if (!empty($dateRange['start'])) {
                    $payload['startCrawlDate'] = $dateRange['start'];
                }
                if (!empty($dateRange['end'])) {
                    $payload['endCrawlDate'] = $dateRange['end'];
                }
            }
            
            // Add content options if provided
            if (!empty($contentsOptions)) {
                $payload['contents'] = $contentsOptions;
            } else {
                // Default content options to improve results
                $payload['contents'] = [
                    'highlights' => true, // Get relevant snippets
                    'text' => true        // Include full text
                ];
            }
            
            // Log the attempt to call Exa API with detailed information
            Log::info('Calling Exa API with payload', [
                'query' => $query,
                'numResults' => $numResults,
                'type' => $type,
                'category' => $category,
                'includeDomains_type' => gettype($includeDomains),
                'includeDomains_value' => is_array($includeDomains) ? $includeDomains : 'not an array',
                'payload' => $payload,
                'baseUrl' => $this->baseUrl
            ]);
            
            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(15) // Increased timeout for larger responses
                ->post("{$this->baseUrl}/search", $payload);
                
            // Log the complete response for debugging
            Log::info('Exa API response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_excerpt' => substr($response->body(), 0, 500),
                'request_payload' => $payload,
                'headers' => array_keys($this->defaultHeaders) // Only log header keys, not values for security
            ]);

            if ($response->successful()) {
                $results = $response->json('results');
                $resolvedSearchType = $response->json('resolvedSearchType');
                
                // Format the results for easier consumption
                $formattedResults = array_map(function($result) {
                    $formatted = [
                        'title' => $result['title'] ?? 'Untitled',
                        'url' => $result['url'] ?? '',
                        'text' => $result['text'] ?? '',
                        'domain' => $result['domain'] ?? '',
                        'published_date' => $result['publishedDate'] ?? null,
                    ];
                    
                    // Add highlights if available
                    if (isset($result['highlights']) && !empty($result['highlights'])) {
                        $formatted['highlights'] = $result['highlights'];
                    }
                    
                    // Add summary if available
                    if (isset($result['summary']) && !empty($result['summary'])) {
                        $formatted['summary'] = $result['summary'];
                    }
                    
                    // Add search score if available
                    if (isset($result['score'])) {
                        $formatted['score'] = $result['score'];
                    }
                    
                    return $formatted;
                }, $results);
                
                // Apply additional content moderation
                $formattedResults = $this->moderateResults($formattedResults);
                
                return [
                    'success' => true,
                    'results' => $formattedResults,
                    'search_type' => $resolvedSearchType ?? $type,
                    'total_results' => count($formattedResults)
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
        // Return all results without moderation for now
        // Removed content moderation filters to get more comprehensive results
        return $results;
        
        /* Original moderation code (commented out)
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
        */
    }

    /**
     * Find learning resources related to a specific topic
     *
     * @param string $topic The topic to find learning resources for
     * @param int $numResults Number of results to return
     * @param string $level Optional learning level (beginner, intermediate, advanced)
     * @return array Array of learning resources
     */
    public function findLearningResources(string $topic, int $numResults = 5, string $level = '')
    {
        // Use specific educational keywords to ensure quality results
        $query = "learn about {$topic} educational resources guides tutorials academic course";
        
        // Add level specification if provided
        if (!empty($level)) {
            $query .= " {$level} level";
        }
        
        // Common spam or inappropriate domains to exclude
        $excludeDomains = [
            'pinterest.com', // Often contains low-quality content
            'quora.com',     // Can contain unverified information
            'reddit.com',    // May contain inappropriate content
            'twitter.com',   // May contain unverified information
            'facebook.com',  // May contain unverified information
            'instagram.com', // May contain inappropriate content
        ];
        
        // Educational websites to prioritize
        $includeDomains = [
            'edu', // Educational institutions
            'gov', // Government resources
            'org', // Non-profit organizations
            'coursera.org',
            'khanacademy.org',
            'edx.org',
            'udemy.com',
            'udacity.com',
            'scholarpedia.org',
            'mitopencourseware.org',
            'openculture.com'
        ];
        
        // Content options for better results
        $contentsOptions = [
            'highlights' => true,
            'summary' => true,
            'text' => true
        ];
        
        // Use the enhanced search with appropriate parameters
        return $this->search(
            $query, 
            $numResults, 
            $includeDomains, 
            true, 
            $excludeDomains,
            'neural', // Neural search works better for educational content
            'educational', // Filter to educational content
            [], // No date range filter
            $contentsOptions
        );
    }

    /**
     * Find related content for a post
     *
     * @param string $postContent The post content to find related resources for
     * @param int $numResults Number of results to return
     * @param array $topicKeywords Optional array of keywords to focus the search
     * @return array Array of related resources
     */
    public function findRelatedContent(string $postContent, int $numResults = 5, array $topicKeywords = [])
    {
        // Extract main topics from the post content
        $truncatedContent = substr($postContent, 0, 1000); // Increased to 1000 chars for better context
        
        // Basic query
        $query = "Find academic educational resources related to: {$truncatedContent}";
        
        // If topic keywords are provided, append them to focus the search
        if (!empty($topicKeywords)) {
            $query .= " Specifically about: " . implode(', ', $topicKeywords);
        }
        
        // Common spam or inappropriate domains to exclude
        $excludeDomains = [
            'pinterest.com', // Often contains low-quality content
            'quora.com',     // Can contain unverified information
            'reddit.com',    // May contain inappropriate content
            'twitter.com',   // May contain unverified information
            'facebook.com',  // May contain unverified information
            'instagram.com', // May contain inappropriate content
        ];
        
        // Credible domains to prioritize
        $includeDomains = [
            'edu', // Educational institutions
            'gov', // Government resources
            'org', // Non-profit organizations
            'jstor.org',
            'scholar.google.com',
            'researchgate.net',
            'academia.edu',
            'arxiv.org',
            'springer.com',
            'sciencedirect.com',
            'nature.com',
            'acm.org',
            'ieee.org'
        ];
        
        // Content options to improve results
        $contentsOptions = [
            'highlights' => true,
            'summary' => true,
            'text' => true
        ];
        
        // Get recent date range (within last 2 years) for timely results
        $dateRange = [
            'start' => date('Y-m-d', strtotime('-2 years'))
        ];
        
        // Use the enhanced search with appropriate parameters
        return $this->search(
            $query, 
            $numResults, 
            $includeDomains, 
            true, 
            $excludeDomains,
            'neural',         // Neural search for better semantic understanding
            'research',       // Bias toward research content
            $dateRange,       // Recent content
            $contentsOptions
        );
    }

    /**
     * Get learning resources with categorization
     *
     * @param string $topic The topic to analyze
     * @param array $categories Optional categories to organize results into
     * @param int $resultsPerCategory Number of results to fetch per category
     * @return array Categorized learning resources
     */
    public function getCategorizedResources(string $topic, array $categories = [], int $resultsPerCategory = 3)
    {
        if (empty($categories)) {
            $categories = [
                'Beginner Guides' => [
                    'query' => 'beginner guide introduction tutorial academic course',
                    'type' => 'keyword',
                    'category' => 'educational'
                ],
                'Advanced Resources' => [
                    'query' => 'advanced in-depth comprehensive expert scholarly research',
                    'type' => 'neural',
                    'category' => 'research'
                ],
                'Latest Developments' => [
                    'query' => 'latest current new development recent update research paper',
                    'type' => 'neural',
                    'category' => 'news'
                ],
                'Best Practices' => [
                    'query' => 'best practices examples standards tips official documentation',
                    'type' => 'keyword',
                    'category' => 'educational'
                ],
                'Video Tutorials' => [
                    'query' => 'video tutorial course lesson how-to educational',
                    'type' => 'keyword',
                    'category' => 'educational'
                ]
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
        
        // Educational websites to prioritize
        $includeDomains = [
            'edu', // Educational institutions
            'gov', // Government resources
            'org', // Non-profit organizations
            'coursera.org',
            'khanacademy.org',
            'edx.org',
            'udemy.com',
            'udacity.com',
            'scholarpedia.org',
            'mitopencourseware.org',
            'openculture.com',
            'youtube.com', // For video tutorials
            'dev.to',
            'medium.com',
            'stackoverflow.com'
        ];
        
        // Default content options
        $contentsOptions = [
            'highlights' => true,
            'text' => true
        ];

        // Search for each category
        foreach ($categories as $categoryName => $categoryConfig) {
            // Handle both old and new format of categories
            if (is_string($categoryConfig)) {
                // Old format: string of search terms
                $searchTerms = $categoryConfig;
                $searchType = 'auto';
                $contentCategory = '';
            } else {
                // New format: array with configuration
                $searchTerms = $categoryConfig['query'] ?? '';
                $searchType = $categoryConfig['type'] ?? 'auto';
                $contentCategory = $categoryConfig['category'] ?? '';
            }
            
            // Set date range based on category
            $dateRange = [];
            if (strpos($categoryName, 'Latest') !== false || strpos($categoryName, 'Recent') !== false) {
                $dateRange = [
                    'start' => date('Y-m-d', strtotime('-6 months'))
                ];
            }
            
            $query = "{$topic} {$searchTerms}";
            
            // Use enhanced search with appropriate parameters for each category
            $results = $this->search(
                $query, 
                $resultsPerCategory, 
                $includeDomains, 
                true, 
                $excludeDomains,
                $searchType,
                $contentCategory,
                $dateRange,
                $contentsOptions
            );
            
            if ($results['success'] && !empty($results['results'])) {
                $categorizedResults[$categoryName] = [
                    'results' => $results['results'],
                    'search_type' => $results['search_type'] ?? $searchType,
                    'total_results' => count($results['results'])
                ];
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