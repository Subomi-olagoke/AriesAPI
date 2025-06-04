<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GPTSearchService
{
    protected $apiKey;
    protected $baseUrl;
    protected $model;
    protected $defaultHeaders;
    protected $maxTokens = 1000;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.endpoint');
        $this->model = config('services.openai.model');
        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }
    
    /**
     * Search the web for information using GPT
     *
     * @param string $query The search query
     * @param int $numResults Approximate number of results to return
     * @param array $includeDomains Domains to prioritize (not directly supported by GPT but will be included in prompt)
     * @param boolean $safeSearch Whether to enable safe search filtering
     * @param array $excludeDomains Domains to exclude (not directly supported by GPT but will be included in prompt)
     * @param string $type Search type: unused for GPT
     * @param string $category Optional category to filter results
     * @param array $dateRange Optional date range for results
     * @return array Search results or error information
     */
    public function search(
        string $query, 
        int $numResults = 5, 
        $includeDomains = [], 
        bool $safeSearch = true, 
        array $excludeDomains = [],
        string $type = '',
        string $category = '',
        array $dateRange = []
    ) {
        if (empty($this->apiKey)) {
            Log::error('OpenAI API key not configured');
            return [
                'success' => false,
                'message' => 'OpenAI API key not configured',
                'results' => [],
                'diagnostic' => 'API key is missing in configuration'
            ];
        }

        try {
            // Construct the prompt for GPT to simulate web search
            $searchPrompt = $this->constructSearchPrompt(
                $query, 
                $numResults, 
                $includeDomains, 
                $safeSearch, 
                $excludeDomains,
                $category,
                $dateRange
            );
            
            // Log the request
            Log::info('GPT search request', [
                'query' => $query,
                'numResults' => $numResults,
                'category' => $category,
                'model' => $this->model
            ]);
            
            // Make the API request to OpenAI
            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a web search service that returns search results in JSON format. Your responses should contain factual, accurate information with proper citations. Only return the JSON without any preamble or explanation.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $searchPrompt
                        ]
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => $this->maxTokens,
                    'response_format' => ['type' => 'json_object']
                ]);
                
            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? null;
                
                if (!empty($content)) {
                    try {
                        // Parse the JSON response
                        $jsonResponse = json_decode($content, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && isset($jsonResponse['results'])) {
                            // Format results to match the expected structure
                            $formattedResults = array_map(function($result) {
                                return [
                                    'title' => $result['title'] ?? 'Untitled',
                                    'url' => $result['url'] ?? 'https://example.com/resource',
                                    'text' => $result['content'] ?? $result['text'] ?? $result['description'] ?? '',
                                    'domain' => $result['domain'] ?? parse_url($result['url'] ?? '', PHP_URL_HOST) ?? 'unknown',
                                    'published_date' => $result['published_date'] ?? $result['date'] ?? null,
                                    'summary' => $result['summary'] ?? null,
                                ];
                            }, $jsonResponse['results']);
                            
                            return [
                                'success' => true,
                                'results' => $formattedResults,
                                'search_type' => 'gpt',
                                'total_results' => count($formattedResults)
                            ];
                        } else {
                            Log::error('GPT search: Invalid JSON response', [
                                'error' => json_last_error_msg(),
                                'content_excerpt' => substr($content, 0, 200)
                            ]);
                            
                            return [
                                'success' => false,
                                'message' => 'Invalid JSON response from GPT',
                                'results' => []
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('GPT search: JSON parsing error', [
                            'message' => $e->getMessage(),
                            'content_excerpt' => substr($content, 0, 200)
                        ]);
                        
                        return [
                            'success' => false,
                            'message' => 'Error parsing GPT response: ' . $e->getMessage(),
                            'results' => []
                        ];
                    }
                } else {
                    Log::error('GPT search: Empty response content');
                    
                    return [
                        'success' => false,
                        'message' => 'Empty response from GPT',
                        'results' => []
                    ];
                }
            } else {
                Log::error('GPT API error: ' . $response->body());
                
                return [
                    'success' => false,
                    'message' => 'API error: ' . $response->status(),
                    'details' => $response->json(),
                    'results' => []
                ];
            }
        } catch (\Exception $e) {
            Log::error('GPT search exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Construct a prompt for GPT to simulate web search
     */
    private function constructSearchPrompt(
        string $query,
        int $numResults,
        $includeDomains,
        bool $safeSearch,
        array $excludeDomains,
        string $category,
        array $dateRange
    ) {
        $prompt = "Please simulate a web search for the following query: \"{$query}\"\n\n";
        
        // Add instructions for number of results
        $prompt .= "Return exactly {$numResults} search results as a JSON object.\n";
        
        // Add domain constraints if provided
        if (!empty($includeDomains) && is_array($includeDomains)) {
            $prompt .= "Prioritize results from these domains: " . implode(", ", $includeDomains) . ".\n";
        }
        
        if (!empty($excludeDomains)) {
            $prompt .= "Exclude results from these domains: " . implode(", ", $excludeDomains) . ".\n";
        }
        
        // Add category filter if provided
        if (!empty($category)) {
            $prompt .= "Focus on the category: {$category}.\n";
        }
        
        // Add date range if provided
        if (!empty($dateRange)) {
            if (!empty($dateRange['start'])) {
                $prompt .= "Only include results published after {$dateRange['start']}.\n";
            }
            if (!empty($dateRange['end'])) {
                $prompt .= "Only include results published before {$dateRange['end']}.\n";
            }
        }
        
        // Add safe search instruction
        if ($safeSearch) {
            $prompt .= "Ensure all results are safe and appropriate for all audiences.\n";
        }
        
        // Add instructions for the response format
        $prompt .= <<<EOT
Format your response as a JSON object with this structure:
{
  "results": [
    {
      "title": "Title of the resource",
      "url": "https://example.com/resource-url",
      "domain": "example.com",
      "content": "A snippet of content from the resource, about 2-3 sentences long",
      "published_date": "YYYY-MM-DD",
      "summary": "A brief summary of what this resource contains"
    }
  ]
}

For each result, include:
1. An informative title
2. A plausible and relevant URL
3. The domain name
4. A realistic content snippet (2-3 sentences)
5. A plausible publication date when appropriate
6. A brief summary of the resource

Base your results on your knowledge of reliable sources that would likely contain information about this query. Make the results diverse and informative.
EOT;

        return $prompt;
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
        // Create a more specific query for educational content
        $query = "educational resources about {$topic}";
        
        // Add level specification if provided
        if (!empty($level)) {
            $query .= " for {$level} level learners";
        }
        
        // Domains that typically have educational content
        $includeDomains = [
            'edu',
            'gov',
            'org',
            'coursera.org',
            'khanacademy.org',
            'edx.org',
            'udemy.com',
            'udacity.com',
            'mit.edu',
            'openculture.com'
        ];
        
        // Run the search with educational focus
        return $this->search(
            $query, 
            $numResults, 
            $includeDomains, 
            true, 
            [],
            '',
            'educational'
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
        $truncatedContent = substr($postContent, 0, 800); // Reasonable length for GPT
        
        // Basic query
        $query = "Find academic or educational resources related to: {$truncatedContent}";
        
        // If topic keywords are provided, append them to focus the search
        if (!empty($topicKeywords)) {
            $query .= " Specifically about: " . implode(', ', $topicKeywords);
        }
        
        // Domains that typically have reliable academic content
        $includeDomains = [
            'edu',
            'gov',
            'org',
            'jstor.org',
            'scholar.google.com',
            'researchgate.net',
            'academia.edu',
            'arxiv.org',
            'springer.com',
            'sciencedirect.com'
        ];
        
        // Recent date range (within last 3 years) for relevant results
        $dateRange = [
            'start' => date('Y-m-d', strtotime('-3 years'))
        ];
        
        // Run the search
        return $this->search(
            $query, 
            $numResults, 
            $includeDomains, 
            true, 
            [],
            '',
            'research',
            $dateRange
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
                    'query' => 'beginner guides and tutorials for ' . $topic,
                    'category' => 'educational'
                ],
                'Advanced Resources' => [
                    'query' => 'advanced guides and in-depth resources for ' . $topic,
                    'category' => 'research'
                ],
                'Latest Developments' => [
                    'query' => 'latest developments and updates about ' . $topic,
                    'category' => 'news'
                ],
                'Best Practices' => [
                    'query' => 'best practices and standards for ' . $topic,
                    'category' => 'educational'
                ],
                'Video Tutorials' => [
                    'query' => 'video tutorials and courses about ' . $topic,
                    'category' => 'educational'
                ]
            ];
        }

        $categorizedResults = [];
        
        // Common domains to exclude
        $excludeDomains = [
            'pinterest.com',
            'quora.com',
            'reddit.com',
            'twitter.com',
            'facebook.com',
            'instagram.com'
        ];
        
        // Educational websites to prioritize
        $includeDomains = [
            'edu',
            'gov',
            'org',
            'coursera.org',
            'youtube.com',
            'khanacademy.org',
            'edx.org',
            'udemy.com'
        ];

        // Search for each category
        foreach ($categories as $categoryName => $categoryConfig) {
            // Handle both old and new format of categories
            if (is_string($categoryConfig)) {
                // Old format: string of search terms
                $searchQuery = $topic . ' ' . $categoryConfig;
                $contentCategory = 'educational';
            } else {
                // New format: array with configuration
                $searchQuery = $categoryConfig['query'] ?? $topic;
                $contentCategory = $categoryConfig['category'] ?? 'educational';
            }
            
            // Set date range based on category
            $dateRange = [];
            if (strpos($categoryName, 'Latest') !== false || strpos($categoryName, 'Recent') !== false) {
                $dateRange = [
                    'start' => date('Y-m-d', strtotime('-6 months'))
                ];
            }
            
            // Use search with appropriate parameters for each category
            $results = $this->search(
                $searchQuery, 
                $resultsPerCategory, 
                $includeDomains, 
                true, 
                $excludeDomains,
                '',
                $contentCategory,
                $dateRange
            );
            
            if ($results['success'] && !empty($results['results'])) {
                $categorizedResults[$categoryName] = [
                    'results' => $results['results'],
                    'search_type' => $results['search_type'] ?? 'gpt',
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
     * Check if the GPT service is properly configured
     *
     * @return bool True if the service is properly configured
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }
}