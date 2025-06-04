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
        // Track timing for performance analysis
        $startTime = microtime(true);
        
        // Track request ID for correlating logs
        $requestId = uniqid('gpt_search_');
        
        // Log API configuration status
        $configStatus = [
            'api_key_configured' => !empty($this->apiKey),
            'api_key_length' => !empty($this->apiKey) ? strlen($this->apiKey) : 0,
            'base_url_configured' => !empty($this->baseUrl),
            'model' => $this->model ?? 'not_set',
            'request_id' => $requestId
        ];
        
        \Log::info('GPT search service configuration check', $configStatus);
        
        if (empty($this->apiKey)) {
            \Log::error('OpenAI API key not configured', [
                'request_id' => $requestId,
                'api_key_empty' => empty($this->apiKey),
                'config_key_empty' => empty(config('services.openai.api_key')),
                'config_key_length' => !empty(config('services.openai.api_key')) ? strlen(config('services.openai.api_key')) : 0,
                'model' => $this->model ?? 'not_set',
                'base_url' => $this->baseUrl ?? 'not_set'
            ]);
            
            return [
                'success' => false,
                'message' => 'OpenAI API key not configured',
                'results' => [],
                'diagnostic' => 'API key is missing in configuration',
                'request_id' => $requestId
            ];
        }

        try {
            // Normalize and sanitize query for logging
            $sanitizedQuery = trim($query);
            if (empty($sanitizedQuery)) {
                \Log::warning('Empty search query provided', [
                    'request_id' => $requestId,
                    'raw_query' => $query
                ]);
                $sanitizedQuery = 'general information';
            }
            
            // Log full search request details
            \Log::info('GPT search request details', [
                'request_id' => $requestId,
                'query' => $sanitizedQuery,
                'query_length' => strlen($sanitizedQuery),
                'numResults' => $numResults,
                'includeDomains' => $includeDomains,
                'excludeDomains' => $excludeDomains,
                'type' => $type,
                'category' => $category,
                'dateRange' => $dateRange,
                'safeSearch' => $safeSearch,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Construct the prompt for GPT to simulate web search
            $searchPrompt = $this->constructSearchPrompt(
                $sanitizedQuery, 
                $numResults, 
                $includeDomains, 
                $safeSearch, 
                $excludeDomains,
                $category,
                $dateRange
            );
            
            \Log::debug('Constructed GPT search prompt', [
                'request_id' => $requestId,
                'prompt_length' => strlen($searchPrompt),
                'prompt_sample' => substr($searchPrompt, 0, 200) . '...' // Log beginning of prompt
            ]);
            
            // Make the API request to OpenAI
            $requestStartTime = microtime(true);
            
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
            
            $requestEndTime = microtime(true);
            $requestDuration = round(($requestEndTime - $requestStartTime) * 1000, 2); // in ms
            
            \Log::info('GPT API request completed', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'duration_ms' => $requestDuration,
                'model' => $this->model,
                'query' => $sanitizedQuery
            ]);
                
            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? null;
                
                // Check if content is present and log
                if (!empty($content)) {
                    \Log::debug('GPT response received', [
                        'request_id' => $requestId,
                        'content_length' => strlen($content),
                        'model' => $responseData['model'] ?? $this->model,
                        'finish_reason' => $responseData['choices'][0]['finish_reason'] ?? 'unknown',
                        'content_sample' => substr($content, 0, 100) . '...' // Log beginning of content
                    ]);
                    
                    try {
                        // Parse the JSON response
                        $jsonResponse = json_decode($content, true);
                        $jsonError = json_last_error();
                        
                        \Log::debug('JSON parsing result', [
                            'request_id' => $requestId,
                            'json_error_code' => $jsonError,
                            'json_error_message' => json_last_error_msg(),
                            'has_results_field' => isset($jsonResponse['results']),
                            'results_count' => isset($jsonResponse['results']) ? count($jsonResponse['results']) : 0
                        ]);
                        
                        if ($jsonError === JSON_ERROR_NONE && isset($jsonResponse['results'])) {
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
                            
                            // Log success with result details
                            $totalDuration = round((microtime(true) - $startTime) * 1000, 2); // in ms
                            
                            \Log::info('GPT search successful', [
                                'request_id' => $requestId,
                                'total_duration_ms' => $totalDuration,
                                'results_count' => count($formattedResults),
                                'query' => $sanitizedQuery,
                                'first_result_title' => $formattedResults[0]['title'] ?? 'No title',
                                'domains' => array_column($formattedResults, 'domain')
                            ]);
                            
                            return [
                                'success' => true,
                                'results' => $formattedResults,
                                'search_type' => 'gpt',
                                'total_results' => count($formattedResults),
                                'duration_ms' => $totalDuration,
                                'request_id' => $requestId,
                                'query' => $sanitizedQuery
                            ];
                        } else {
                            // Detailed logging for JSON errors
                            \Log::error('GPT search: Invalid JSON response', [
                                'request_id' => $requestId,
                                'error' => json_last_error_msg(),
                                'error_code' => $jsonError,
                                'content_excerpt' => substr($content, 0, 200),
                                'has_results_field' => isset($jsonResponse['results']),
                                'json_keys' => $jsonResponse ? array_keys($jsonResponse) : 'null_response',
                                'query' => $sanitizedQuery
                            ]);
                            
                            return [
                                'success' => false,
                                'message' => 'Invalid JSON response from GPT: ' . json_last_error_msg(),
                                'results' => [],
                                'request_id' => $requestId,
                                'query' => $sanitizedQuery,
                                'debug_info' => [
                                    'has_content' => !empty($content),
                                    'content_length' => strlen($content ?? ''),
                                    'content_sample' => substr($content ?? '', 0, 100),
                                    'json_error' => json_last_error_msg(),
                                    'response_keys' => $jsonResponse ? array_keys($jsonResponse) : []
                                ]
                            ];
                        }
                    } catch (\Exception $e) {
                        // Detailed logging for parsing errors
                        \Log::error('GPT search: JSON parsing exception', [
                            'request_id' => $requestId,
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'content_excerpt' => substr($content, 0, 200),
                            'query' => $sanitizedQuery
                        ]);
                        
                        return [
                            'success' => false,
                            'message' => 'Error parsing GPT response: ' . $e->getMessage(),
                            'results' => [],
                            'request_id' => $requestId,
                            'query' => $sanitizedQuery,
                            'debug_info' => [
                                'exception_type' => get_class($e),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'content_sample' => substr($content ?? '', 0, 100)
                            ]
                        ];
                    }
                } else {
                    // Log empty content error
                    \Log::error('GPT search: Empty response content', [
                        'request_id' => $requestId,
                        'response_data' => $responseData,
                        'model' => $responseData['model'] ?? $this->model,
                        'query' => $sanitizedQuery
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Empty response from GPT',
                        'results' => [],
                        'request_id' => $requestId,
                        'query' => $sanitizedQuery,
                        'debug_info' => [
                            'response_keys' => array_keys($responseData),
                            'has_choices' => isset($responseData['choices']),
                            'choices_count' => isset($responseData['choices']) ? count($responseData['choices']) : 0
                        ]
                    ];
                }
            } else {
                // Log API error with details
                \Log::error('GPT API error response', [
                    'request_id' => $requestId,
                    'status_code' => $response->status(),
                    'error_body' => $response->body(),
                    'error_json' => $response->json(),
                    'query' => $sanitizedQuery,
                    'request_duration_ms' => $requestDuration
                ]);
                
                return [
                    'success' => false,
                    'message' => 'API error: ' . $response->status(),
                    'details' => $response->json(),
                    'results' => [],
                    'request_id' => $requestId,
                    'query' => $sanitizedQuery,
                    'debug_info' => [
                        'status_code' => $response->status(),
                        'error_type' => $response->json('error.type') ?? 'unknown',
                        'error_message' => $response->json('error.message') ?? 'No error message'
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Comprehensive exception logging
            $totalDuration = round((microtime(true) - $startTime) * 1000, 2); // in ms
            
            \Log::error('GPT search exception', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'query' => $query,
                'duration_ms' => $totalDuration,
                'exception_type' => get_class($e)
            ]);
            
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'results' => [],
                'request_id' => $requestId,
                'query' => $query,
                'debug_info' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'duration_ms' => $totalDuration
                ]
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