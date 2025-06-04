<?php

namespace App\Http\Controllers;

use App\Models\CogniChat;
use App\Models\CogniChatMessage;
use App\Services\CogniService;
use App\Services\PersonalizedFactsService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CogniController extends Controller
{
    protected $cogniService;
    protected $youtubeService;
    protected $factsService;

    public function __construct(
        CogniService $cogniService, 
        YouTubeService $youtubeService,
        PersonalizedFactsService $factsService
    ) {
        $this->cogniService = $cogniService;
        $this->youtubeService = $youtubeService;
        $this->factsService = $factsService;
    }

    /**
     * Ask a question to Cogni
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
            'use_web_search' => 'nullable|boolean'
        ]);

        $user = Auth::user();
        $question = $request->input('question');
        $conversationId = $request->input('conversation_id');
        $useWebSearch = $request->input('use_web_search', false);

        if (empty($conversationId)) {
            // Generate a new conversation ID if none provided
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }

        // Check if this is a readlist creation request
        if (preg_match('/create\s+a\s+readlist|make\s+a\s+readlist|create\s+readlist|build\s+a\s+readlist/i', $question)) {
            // This appears to be a readlist creation request
            return $this->handleReadlistCreationRequest($user, $question, $conversationId);
        }

        // Check if this is a search request
        $isSearchRequest = $useWebSearch || 
                          preg_match('/search(\s+for|\s+the|\s+about)?\s+|find(\s+information|\s+about|\s+articles)?\s+|look\s+up|research/i', $question) ||
                          preg_match('/what\'s\s+the\s+latest|what\s+is\s+happening|current\s+news|recent\s+developments/i', $question);

        // Get conversation history from session or initialize new one
        $conversationKey = 'cogni_conversation_' . $conversationId;
        $context = Session::get($conversationKey, []);

        // Add the new user question to context
        $context[] = [
            'role' => 'user',
            'content' => $question
        ];

        // Use web search if requested or detected
        if ($isSearchRequest) {
            return $this->handleWebSearchRequest($user, $question, $context, $conversationId);
        }

        // Ask the question with context
        $result = $this->cogniService->askQuestion($question, $context);

        if ($result['success'] && isset($result['answer'])) {
            // Add assistant's response to context
            $context[] = [
                'role' => 'assistant',
                'content' => $result['answer']
            ];
            
            // Store updated conversation in session
            // Keep only the last 10 messages to prevent context size issues
            if (count($context) > 10) {
                // Keep system message if present, plus last 9 exchanges
                if ($context[0]['role'] === 'system') {
                    $context = array_merge(
                        [$context[0]],
                        array_slice($context, -9)
                    );
                } else {
                    $context = array_slice($context, -10);
                }
            }
            Session::put($conversationKey, $context);
            
            // Optional: Store in database for persistence beyond session
            $this->storeConversationInDatabase($user, $conversationId, $question, $result['answer']);
            
            return response()->json([
                'success' => true,
                'answer' => $result['answer'],
                'conversation_id' => $conversationId
            ]);
        }

        // Return error response
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to get an answer from Cogni'
        ], $result['code'] ?? 500);
    }

    /**
     * Get a list of user's conversations
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversations()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $conversations = \App\Models\CogniConversation::getUserConversations($user->id);
        
        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

    /**
     * Get conversation history for a specific conversation
     * 
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversationHistory($conversationId)
    {
        $user = Auth::user();
        
        // Try to get from session first (more complete with context)
        $conversationKey = 'cogni_conversation_' . $conversationId;
        $context = Session::get($conversationKey, []);
        
        // If not in session, try to get from database
        if (empty($context) && $user) {
            $dbConversation = \App\Models\CogniConversation::getConversationHistory($conversationId);
            
            if ($dbConversation->isNotEmpty()) {
                // Convert from DB format to context format
                foreach ($dbConversation as $message) {
                    $context[] = [
                        'role' => 'user',
                        'content' => $message->question
                    ];
                    $context[] = [
                        'role' => 'assistant',
                        'content' => $message->answer
                    ];
                }
                
                // Store in session for future use
                Session::put($conversationKey, $context);
            }
        }
        
        // Filter out system messages which are internal
        $history = array_filter($context, function($message) {
            return $message['role'] !== 'system';
        });
        
        // Format for display
        $formattedHistory = [];
        foreach ($history as $message) {
            $formattedHistory[] = [
                'role' => $message['role'],
                'content' => $message['content']
            ];
        }
        
        return response()->json([
            'success' => true,
            'history' => $formattedHistory,
            'conversation_id' => $conversationId
        ]);
    }

    /**
     * Store conversation in database for persistence
     * 
     * @param User $user
     * @param string $conversationId
     * @param string $question
     * @param string $answer
     */
    private function storeConversationInDatabase($user, $conversationId, $question, $answer)
    {
        if (!$user) {
            // Skip storing if no user is authenticated
            return;
        }
        
        try {
            \App\Models\CogniConversation::create([
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'question' => $question,
                'answer' => $answer
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store conversation: ' . $e->getMessage());
        }
    }

    /**
     * Handle readlist creation request from chat interface
     *
     * @param \App\Models\User $user
     * @param string $question
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleReadlistCreationRequest($user, $question, $conversationId)
    {
        // Create an array to collect debug information throughout the process
        $debugLogs = [
            'process_steps' => [],
            'internal_content' => [],
            'search_results' => [],
            'validation' => [],
            'error_details' => []
        ];
        
        try {
            $debugLogs['process_steps'][] = 'Starting readlist creation process';
            
            // Extract the topic from the question
            // Remove common phrases like "create a readlist about" to get just the topic
            $description = preg_replace('/^(cogni,?\s*)?(please\s*)?(create|make|build)(\s+a|\s+me\s+a)?\s+(readlist|reading list)(\s+for\s+me)?(\s+about|\s+on)?\s*/i', '', $question);
            $description = trim($description);
            
            $debugLogs['process_steps'][] = 'Extracted description: "' . $description . '"';
            
            if (empty($description)) {
                $description = $question; // Use the full question if extraction failed
            }
            
            // Default values
            $itemCount = 8;
            $includeExternal = true;
            $externalCount = 3;
            
            // Content moderation check
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            $moderationResult = $contentModerationService->analyzeText($description);
            
            if (!$moderationResult['isAllowed']) {
                // Store this interaction in the conversation history
                $this->storeConversationInDatabase(
                    $user, 
                    $conversationId, 
                    $question, 
                    "I'm sorry, but I can't create a readlist on this topic as it contains inappropriate content."
                );
                
                return response()->json([
                    'success' => false,
                    'message' => 'Your request contains inappropriate content. Please modify and try again.',
                    'conversation_id' => $conversationId
                ], 400);
            }
            
            // Search for relevant internal content
            $internalContent = $this->findRelevantContent($description, $debugLogs);
            
            // Check if we have enough internal content before proceeding
            $minRequiredContent = 3; // Minimum number of internal items needed
            if (count($internalContent) < $minRequiredContent) {
                // Not enough internal content, try to get some from the web
                $debugLogs['process_steps'][] = "Not enough internal content (found " . count($internalContent) . ", need " . $minRequiredContent . "), searching web";
                \Log::info("Not enough internal content for readlist on '{$description}', searching web", [
                    'found_content_count' => count($internalContent)
                ]);
                
                // Get web content using GPT search
                $searchService = app(\App\Services\GPTSearchService::class);
                // Log that we're attempting a web search
                \Log::info("Attempting web search for readlist content", [
                    'query' => $description,
                    'gpt_configured' => $searchService->isConfigured()
                ]);
                
                // Determine if this is an educational, technical, or general query
                $queryType = $this->analyzeReadlistQueryType($description);
                
                // Educational websites to prioritize
                $includeDomains = [
                    'edu', // Educational institutions
                    'gov', // Government resources
                    'org', // Non-profit organizations
                    'coursera.org',
                    'khanacademy.org',
                    'edx.org',
                    'udemy.com',
                    'medium.com',
                    'dev.to',
                    'openculture.com'
                ];
                
                // Common spam or inappropriate domains to exclude
                $excludeDomains = [
                    'pinterest.com', // Often contains low-quality content
                    'quora.com',     // Can contain unverified information
                    'reddit.com',    // May contain inappropriate content
                    'twitter.com',   // May contain unverified information
                    'facebook.com',  // May contain unverified information
                    'instagram.com', // May contain inappropriate content
                ];
                
                // Define content options for better results
                $contentsOptions = [
                    'highlights' => true, // Get relevant snippets
                    'text' => true,       // Get full text
                    'summary' => true     // Get AI-generated summaries when available
                ];
                
                // Use GPT search
                $webSearchResults = $searchService->search(
                    $description . " " . $queryType['additional_terms'],
                    10, 
                    $includeDomains, 
                    true, 
                    $excludeDomains,
                    $queryType['search_type'],
                    $queryType['category'],
                    [] // No date range filter
                );
                
                // Log search results for debugging
                \Log::info("Web search results", [
                    'success' => $webSearchResults['success'] ?? false,
                    'result_count' => count($webSearchResults['results'] ?? []),
                    'error' => $webSearchResults['message'] ?? 'No error message'
                ]);
                
                if ($webSearchResults['success'] && !empty($webSearchResults['results'])) {
                    // Add web content to readlist as external items
                    $webItems = [];
                    foreach ($webSearchResults['results'] as $result) {
                        $webItems[] = [
                            'title' => $result['title'],
                            'description' => substr($result['text'], 0, 200) . '...',
                            'url' => $result['url'],
                            'type' => 'external',
                            'notes' => 'From web search: ' . $result['domain']
                        ];
                    }
                    
                    // Create a readlist combining internal and external content
                    // If we have too few internal items, create a mostly external-based readlist
                    $readlistTitle = 'Readlist: ' . ucfirst($description);
                    $readlistDescription = 'A collection of resources about ' . $description;
                    
                    // Add additional description text based on content sources
                    if (count($internalContent) < 2 && !empty($webItems)) {
                        $readlistDescription .= ' curated primarily from the web.';
                    } elseif (!empty($webItems)) {
                        $readlistDescription .= ' curated from platform content and supplemented with web resources.';
                    } else {
                        $readlistDescription .= ' curated from platform content.';
                    }
                    
                    // Combine internal and external content
                    $allItems = [];
                    
                    // Add internal content first (if any)
                    foreach ($internalContent as $item) {
                        $allItems[] = [
                            'id' => $item['id'],
                            'type' => $item['type'],
                            'notes' => 'Internal content: ' . ($item['title'] ?? 'Untitled')
                        ];
                    }
                    
                    // Add external content
                    foreach ($webItems as $item) {
                        $allItems[] = $item;
                    }
                    
                    // Create the readlist
                    $readlistData = [
                        'title' => $readlistTitle,
                        'description' => $readlistDescription,
                        'items' => $allItems
                    ];
                    
                    $readlist = $this->createReadlistInDatabase($user, $readlistData);
                    
                    if ($readlist) {
                        // Create appropriate response based on content sources
                        if (count($internalContent) < 2) {
                            $response = "I found limited internal content about " . $description . ", so I've created a readlist with " . 
                                      count($internalContent) . " internal resources and " . count($webItems) . 
                                      " additional resources from the web.";
                        } else {
                            $response = "I've created a readlist about " . $description . " with " . 
                                      count($internalContent) . " internal resources and " . count($webItems) . 
                                      " supplementary resources from the web.";
                        }
                        
                        $this->storeConversationInDatabase($user, $conversationId, $question, $response);
                        
                        return response()->json([
                            'success' => true,
                            'answer' => $response,
                            'conversation_id' => $conversationId,
                            'readlist' => $readlist->load('items')
                        ]);
                    }
                }
            }
            
            // Generate readlist with available content (internal + potentially external)
            $result = $this->cogniService->generateReadlistFromDescription(
                $description, 
                $internalContent, 
                $itemCount, 
                $externalCount
            );
            
            if ($result['success'] && isset($result['readlist'])) {
                // Check if there are any items in the readlist
                if (empty($result['readlist']['items'])) {
                    $noItemsMsg = "I tried to create a readlist about \"{$description}\" but couldn't find any relevant content. Would you like me to try a different topic?";
                    
                    $debugLogs['process_steps'][] = "Empty items array in readlist data";
                    $debugLogs['validation']['result_readlist_items_empty'] = true;
                    $debugLogs['validation']['readlist_data'] = isset($result['readlist']) ? [
                        'title' => $result['readlist']['title'] ?? 'No title',
                        'description' => $result['readlist']['description'] ?? 'No description',
                        'items_count' => 0
                    ] : 'No readlist data';
                    
                    // Add search results if available
                    if (isset($webSearchResults)) {
                        $debugLogs['search_results'] = [
                            'success' => $webSearchResults['success'] ?? false,
                            'count' => count($webSearchResults['results'] ?? []),
                            'search_type' => $webSearchResults['search_type'] ?? 'unknown',
                            'items' => array_map(function($item) {
                                return [
                                    'title' => $item['title'] ?? 'Untitled',
                                    'url' => $item['url'] ?? 'No URL',
                                    'domain' => $item['domain'] ?? 'Unknown'
                                ];
                            }, $webSearchResults['results'] ?? [])
                        ];
                    }
                    
                    // Add specific diagnosis for "the Davinci" and similar queries
                    if (stripos($description, 'davinci') !== false) {
                        $debugLogs['special_analysis'] = [
                            'is_davinci_query' => true,
                            'exact_match' => strcasecmp($description, 'the davinci') === 0,
                            'contains_davinci' => true,
                            'keywords_before_filter' => array_values(preg_split('/[\s,]+/', $description)),
                            'keywords_matching' => array_filter(preg_split('/[\s,]+/', $description), function($word) {
                                return stripos($word, 'davinci') !== false;
                            })
                        ];
                        
                        // Try special case check for Davinci in database
                        $specialSearch = \App\Models\Course::where('title', 'like', '%davinci%')
                            ->orWhere('description', 'like', '%davinci%')
                            ->get(['id', 'title', 'description']);
                            
                        $specialSearchPosts = \App\Models\Post::where('title', 'like', '%davinci%')
                            ->orWhere('body', 'like', '%davinci%')
                            ->get(['id', 'title', 'body']);
                            
                        $debugLogs['special_analysis']['direct_davinci_search'] = [
                            'course_results' => $specialSearch->count(),
                            'post_results' => $specialSearchPosts->count(),
                            'course_titles' => $specialSearch->pluck('title')->toArray(),
                            'post_titles' => $specialSearchPosts->pluck('title')->toArray(),
                        ];
                    }
                    
                    // Check for specific web search issues with this query
                    if (!empty($webSearchResults)) {
                        $debugLogs['search_analysis'] = [
                            'search_results_count' => count($webSearchResults['results'] ?? []),
                            'search_query' => $description . " " . ($queryType['additional_terms'] ?? ''),
                            'search_type' => $queryType['search_type'] ?? 'unknown',
                            'success' => $webSearchResults['success'] ?? false,
                            'error_message' => $webSearchResults['message'] ?? 'No error message',
                            'included_domains' => $includeDomains,
                            'excluded_domains' => $excludeDomains
                        ];
                    }
                    
                    // Log detailed debug information with process timestamp
                    $debugLogs['timestamp'] = date('Y-m-d H:i:s');
                    $debugLogs['query_info'] = [
                        'raw_query' => $question,
                        'extracted_description' => $description,
                        'search_terms' => array_values(preg_split('/[\s,]+/', $description))
                    ];
                    
                    \Log::warning("Empty readlist items array - Detailed diagnostics", $debugLogs);
                    
                    // Store in conversation history
                    $this->storeConversationInDatabase($user, $conversationId, $question, $noItemsMsg);
                    
                    return response()->json([
                        'success' => false,
                        'answer' => $noItemsMsg,
                        'conversation_id' => $conversationId,
                        'debug_info' => $debugLogs
                    ]);
                }
                
                // Create readlist in database
                $readlist = $this->createReadlistInDatabase($user, $result['readlist']);
                
                if ($readlist) {
                    // Get the actual item count
                    $itemCount = $readlist->items()->count();
                    
                    // Create a user-friendly response
                    $response = "I've created a readlist titled \"" . $readlist->title . "\" for you. ";
                    
                    if ($itemCount > 0) {
                        $response .= "It contains " . $itemCount . " item" . ($itemCount != 1 ? "s" : "") . " related to " . $description . ".";
                    } else {
                        // This shouldn't happen with our updated createReadlistInDatabase, but just in case
                        $response .= "However, I couldn't add any items to it. Would you like me to try a different topic?";
                    }
                    
                    // Store in conversation history
                    $this->storeConversationInDatabase($user, $conversationId, $question, $response);
                    
                    return response()->json([
                        'success' => true,
                        'answer' => $response,
                        'conversation_id' => $conversationId,
                        'readlist' => $readlist->load('items.item'),
                        'item_count' => $itemCount
                    ]);
                } else {
                    // Readlist creation failed
                    $failureMsg = "I couldn't create a readlist about \"{$description}\" because I couldn't find enough relevant content. Would you like me to try a different topic?";
                    
                    // Store in conversation history
                    $this->storeConversationInDatabase($user, $conversationId, $question, $failureMsg);
                    
                    return response()->json([
                        'success' => false,
                        'answer' => $failureMsg,
                        'conversation_id' => $conversationId
                    ]);
                }
            }
            
            // If we get here, something went wrong with readlist generation
            // Try one more time with web search if we haven't already used it
            if (count($internalContent) >= $minRequiredContent) {
                // We had enough internal content but still failed, try with web search
                $searchService = app(\App\Services\GPTSearchService::class);
                // Log that we're attempting a web search as fallback
                \Log::info("Attempting web search as fallback for readlist generation", [
                    'query' => $description,
                    'gpt_configured' => $searchService->isConfigured()
                ]);
                
                // Determine if this is an educational, technical, or general query
                $queryType = $this->analyzeReadlistQueryType($description);
                
                // For fallback, try a broader search with fewer restrictions
                $includeDomains = []; // No domain restrictions for fallback
                
                // Common spam or inappropriate domains to exclude
                $excludeDomains = [
                    'pinterest.com',
                    'quora.com',
                    'reddit.com',
                    'twitter.com',
                    'facebook.com',
                    'instagram.com',
                ];
                
                // Define content options for better results
                $contentsOptions = [
                    'highlights' => true,
                    'text' => true,
                    'summary' => true
                ];
                
                // Use a more generic search as fallback
                $webSearchResults = $searchService->search(
                    $description . " " . $queryType['additional_terms'],
                    10, 
                    $includeDomains, 
                    true, 
                    $excludeDomains,
                    'keyword', // Use keyword parameter for consistent interface
                    '',        // No category filter for fallback
                    []         // No date restrictions
                );
                
                // Log search results for debugging
                \Log::info("Web search fallback results", [
                    'success' => $webSearchResults['success'] ?? false,
                    'result_count' => count($webSearchResults['results'] ?? []),
                    'error' => $webSearchResults['message'] ?? 'No error message'
                ]);
                
                if ($webSearchResults['success'] && !empty($webSearchResults['results'])) {
                    // Create a readlist with web content
                    $webItems = [];
                    foreach ($webSearchResults['results'] as $result) {
                        // Only add items with valid URLs
                        if (!empty($result['url'])) {
                            // Use summary if available, otherwise use text with ellipsis
                            $description = isset($result['summary']) 
                                ? $result['summary'] 
                                : (isset($result['text']) ? substr($result['text'], 0, 250) . '...' : '');
                                
                            $webItems[] = [
                                'title' => $result['title'] ?? 'Educational Resource',
                                'description' => $description,
                                'url' => $result['url'],
                                'type' => 'external',
                                'notes' => 'From web search: ' . ($result['domain'] ?? parse_url($result['url'], PHP_URL_HOST))
                            ];
                        }
                    }
                    
                    // Check if we have any valid web items
                    if (empty($webItems)) {
                        $noItemsMsg = "I tried to create a readlist about \"{$description}\" but couldn't find any relevant content, even after searching the web. Would you like me to try a different topic?";
                        
                        // Store in conversation history
                        $this->storeConversationInDatabase($user, $conversationId, $question, $noItemsMsg);
                        
                        return response()->json([
                            'success' => false,
                            'answer' => $noItemsMsg,
                            'conversation_id' => $conversationId
                        ]);
                    }
                    
                    // Create a combined readlist with priority on internal content
                    $readlistTitle = 'Readlist: ' . ucfirst($description);
                    $readlistDescription = 'A collection of resources about ' . $description . ' combining platform content with web resources.';
                    
                    // Combine all content
                    $allItems = [];
                    
                    // Add internal content first
                    foreach ($internalContent as $item) {
                        $allItems[] = [
                            'id' => $item['id'],
                            'type' => $item['type'],
                            'notes' => 'Internal content: ' . ($item['title'] ?? 'Untitled')
                        ];
                    }
                    
                    // Add external content
                    foreach ($webItems as $item) {
                        $allItems[] = $item;
                    }
                    
                    $readlistData = [
                        'title' => $readlistTitle,
                        'description' => $readlistDescription,
                        'items' => $allItems
                    ];
                    
                    $readlist = $this->createReadlistInDatabase($user, $readlistData);
                    
                    if ($readlist) {
                        // Get the actual item count
                        $internalItemCount = $readlist->items()->whereNotNull('item_id')->count();
                        $externalItemCount = $readlist->items()->whereNull('item_id')->whereNotNull('url')->count();
                        $totalCount = $internalItemCount + $externalItemCount;
                        
                        $response = "I've created a readlist about " . $description . " with ";
                        
                        if ($internalItemCount > 0) {
                            $response .= $internalItemCount . " internal resource" . ($internalItemCount != 1 ? "s" : "");
                            if ($externalItemCount > 0) {
                                $response .= " and ";
                            }
                        }
                        
                        if ($externalItemCount > 0) {
                            $response .= $externalItemCount . " resource" . ($externalItemCount != 1 ? "s" : "") . " from the web";
                        }
                        
                        $response .= ".";
                        
                        $this->storeConversationInDatabase($user, $conversationId, $question, $response);
                        
                        return response()->json([
                            'success' => true,
                            'answer' => $response,
                            'conversation_id' => $conversationId,
                            'readlist' => $readlist->load('items'),
                            'item_count' => $totalCount
                        ]);
                    } else {
                        // Readlist creation failed
                        $failureMsg = "I tried to create a readlist about \"{$description}\" but encountered an issue. Would you like me to try a different topic?";
                        
                        // Store in conversation history
                        $this->storeConversationInDatabase($user, $conversationId, $question, $failureMsg);
                        
                        return response()->json([
                            'success' => false,
                            'answer' => $failureMsg,
                            'conversation_id' => $conversationId
                        ]);
                    }
                } else {
                    // No web results found
                    $noResultsMsg = "I tried to create a readlist about \"{$description}\" but couldn't find any relevant content on the web. Would you like me to try a different topic?";
                    
                    // Store in conversation history
                    $this->storeConversationInDatabase($user, $conversationId, $question, $noResultsMsg);
                    
                    return response()->json([
                        'success' => false,
                        'answer' => $noResultsMsg,
                        'conversation_id' => $conversationId
                    ]);
                }
            }
            
            // If all else fails, return a helpful error message with diagnostics
            $errorMsg = "I tried to create a readlist about \"" . $description . "\" but ";
            
            // Check if GPT search is properly configured
            $searchService = app(\App\Services\GPTSearchService::class);
            $searchIsConfigured = $searchService->isConfigured();
            
            // Create diagnostic info about search configuration
            $diagnostics = [
                'gpt_search_configured' => $searchIsConfigured,
                'api_key_set' => !empty(config('services.openai.api_key')),
                'api_key_length' => !empty(config('services.openai.api_key')) ? strlen(config('services.openai.api_key')) : 0,
                'api_endpoint' => config('services.openai.endpoint', 'https://api.openai.com/v1'),
                'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                'internal_content_count' => count($internalContent)
            ];
            
            // Log diagnostics
            \Log::warning("Readlist creation failed - GPT search diagnostics", $diagnostics);
            
            // Direct error message based on actual issue
            if (count($internalContent) < 2) {
                $errorMsg .= "I couldn't find enough relevant content in our platform. ";
                
                if (!$searchIsConfigured) {
                    $errorMsg .= "Additionally, our web search capability isn't available at the moment. ";
                } else {
                    // Test if web search is working
                    $testSearchResult = $searchService->search("test query", 1, []);
                    $diagnostics['test_search'] = [
                        'success' => $testSearchResult['success'] ?? false,
                        'message' => $testSearchResult['message'] ?? 'No message',
                        'result_count' => count($testSearchResult['results'] ?? []),
                        'details' => $testSearchResult['details'] ?? null
                    ];
                    
                    // Try another format
                    $testSearchResult2 = $searchService->search("educational resources", 1, []);
                    $diagnostics['alternate_test_search'] = [
                        'success' => $testSearchResult2['success'] ?? false,
                        'message' => $testSearchResult2['message'] ?? 'No message',
                        'query' => "educational resources"
                    ];
                    
                    if ($testSearchResult['success'] && !empty($testSearchResult['results'])) {
                        $errorMsg .= "I tried searching the web, but couldn't find suitable content about this topic. ";
                    } else {
                        $errorMsg .= "I tried to search the web, but encountered connectivity issues. ";
                    }
                }
            } else {
                $errorMsg .= "I had trouble creating a coherent readlist with the available content. ";
                
                if ($searchIsConfigured) {
                    $errorMsg .= "I also tried supplementing with web content but couldn't find relevant additional resources. ";
                }
            }
            
            // Suggest alternatives
            $errorMsg .= "Would you like me to try a different topic? Popular topics include technology, science, history, or business.";
            
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            // Include diagnostics in the response
            return response()->json([
                'success' => true,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId,
                'debug_info' => $diagnostics
            ]);
            
        } catch (\Exception $e) {
            // Enhanced error logging with more context
            $errorId = uniqid('readlist_error_');
            $errorContext = [
                'error_id' => $errorId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'description' => $description ?? 'No description extracted',
                'query' => $question,
                'user_id' => $user ? $user->id : 'not_authenticated',
                'exception_type' => get_class($e)
            ];
            
            // Log with all available diagnostic information
            if (isset($debugLogs)) {
                $errorContext['debug_logs'] = $debugLogs;
            }
            
            \Log::error('Error in handleReadlistCreationRequest: ' . $e->getMessage(), $errorContext);
            
            $errorMsg = "I'm sorry, I encountered an error while trying to create your readlist. Please try again later.";
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            // Return more detailed information for debugging
            return response()->json([
                'success' => false,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId,
                'error_details' => [
                    'error_id' => $errorId,
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'error_location' => $e->getFile() . ':' . $e->getLine(),
                    'description' => $description ?? 'No description extracted'
                ]
            ]);
        }
    }
    
    /**
     * Find relevant content for readlist creation
     * 
     * @param string $description
     * @param array &$debugLogs Reference to debug logs array
     * @return array
     */
    private function findRelevantContent($description, &$debugLogs = null)
    {
        $internalContent = [];
        
        // Log the search description for debugging
        \Log::info("Searching for relevant content for readlist", [
            'description' => $description,
            'length' => strlen($description)
        ]);
        
        // Track timing for performance analysis
        $startTime = microtime(true);
        
        // Extract keywords with more detailed logging
        $keywords = preg_split('/[\s,]+/', $description);
        $originalKeywordCount = count($keywords);
        
        // Filter keywords with more detailed logging
        $keywords = array_filter($keywords, function($word) {
            return strlen($word) > 3; // Only use words longer than 3 characters
        });
        $filteredKeywordCount = count($keywords);
        
        if ($debugLogs !== null) {
            $debugLogs['process_steps'][] = 'Searching for internal content with keywords: ' . implode(', ', $keywords);
            $debugLogs['internal_content']['keyword_analysis'] = [
                'original_count' => $originalKeywordCount,
                'filtered_count' => $filteredKeywordCount,
                'original_keywords' => array_values(preg_split('/[\s,]+/', $description)),
                'filtered_keywords' => array_values($keywords)
            ];
        }
        
        // If no good keywords, use the whole description
        if (empty($keywords)) {
            $keywords = [$description];
            if ($debugLogs !== null) {
                $debugLogs['process_steps'][] = 'No good keywords found, using full description as keyword';
                $debugLogs['internal_content']['keyword_fallback'] = true;
            }
            
            \Log::warning("No suitable keywords found for content search, using full description", [
                'description' => $description
            ]);
        }
        
        // Track exact query used (case sensitive)
        if ($debugLogs !== null) {
            $debugLogs['internal_content']['exact_search_terms'] = [
                'description' => $description,
                'keywords_case_sensitive' => array_values($keywords)
            ];
        }
        
        // Search for relevant courses with enhanced logging
        $courseQuery = \App\Models\Course::query();
        foreach ($keywords as $keyword) {
            $courseQuery->orWhere('title', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
        }
        
        // Record exact SQL for debugging
        $courseQuerySql = $courseQuery->toSql();
        $courseQueryBindings = $courseQuery->getBindings();
        
        \Log::debug("Course search query", [
            'sql' => $courseQuerySql,
            'bindings' => $courseQueryBindings
        ]);
        
        $courses = $courseQuery->limit(30)->get(['id', 'title', 'description', 'user_id', 'created_at']);
        
        if ($debugLogs !== null) {
            $debugLogs['internal_content']['courses_found'] = count($courses);
            $debugLogs['internal_content']['course_query'] = [
                'keywords' => $keywords,
                'sql' => $courseQuerySql,
                'bindings' => $courseQueryBindings
            ];
            $debugLogs['internal_content']['course_titles'] = $courses->pluck('title')->toArray();
            
            // Add detailed match analysis for each course
            $courseMatches = [];
            foreach ($courses as $course) {
                $matchedKeywords = [];
                foreach ($keywords as $keyword) {
                    if (stripos($course->title, $keyword) !== false || stripos($course->description, $keyword) !== false) {
                        $matchedKeywords[] = $keyword;
                    }
                }
                
                $courseMatches[] = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'matched_keywords' => $matchedKeywords,
                    'match_count' => count($matchedKeywords)
                ];
            }
            $debugLogs['internal_content']['course_match_details'] = $courseMatches;
        }
        
        foreach ($courses as $course) {
            $internalContent[] = [
                'id' => $course->id,
                'type' => 'course',
                'title' => $course->title,
                'description' => $course->description,
                'user_id' => $course->user_id,
                'created_at' => $course->created_at
            ];
        }
        
        // Search for relevant posts with enhanced logging
        $postQuery = \App\Models\Post::query();
        foreach ($keywords as $keyword) {
            $postQuery->orWhere('title', 'like', "%{$keyword}%")
                ->orWhere('body', 'like', "%{$keyword}%");
        }
        
        // Record exact SQL for debugging
        $postQuerySql = $postQuery->toSql();
        $postQueryBindings = $postQuery->getBindings();
        
        \Log::debug("Post search query", [
            'sql' => $postQuerySql,
            'bindings' => $postQueryBindings
        ]);
        
        $posts = $postQuery->limit(30)->get(['id', 'title', 'body', 'user_id', 'created_at']);
        
        if ($debugLogs !== null) {
            $debugLogs['internal_content']['posts_found'] = count($posts);
            $debugLogs['internal_content']['post_query'] = [
                'keywords' => $keywords,
                'sql' => $postQuerySql,
                'bindings' => $postQueryBindings
            ];
            $debugLogs['internal_content']['post_titles'] = $posts->pluck('title')->toArray();
            
            // Add detailed match analysis for each post
            $postMatches = [];
            foreach ($posts as $post) {
                $matchedKeywords = [];
                foreach ($keywords as $keyword) {
                    if (stripos($post->title, $keyword) !== false || stripos($post->body, $keyword) !== false) {
                        $matchedKeywords[] = $keyword;
                    }
                }
                
                $postMatches[] = [
                    'id' => $post->id,
                    'title' => $post->title ?? 'Untitled Post',
                    'matched_keywords' => $matchedKeywords,
                    'match_count' => count($matchedKeywords)
                ];
            }
            $debugLogs['internal_content']['post_match_details'] = $postMatches;
        }
        
        foreach ($posts as $post) {
            $internalContent[] = [
                'id' => $post->id,
                'type' => 'post',
                'title' => $post->title ?? 'Untitled Post',
                'description' => substr(strip_tags($post->body), 0, 200),
                'user_id' => $post->user_id,
                'created_at' => $post->created_at
            ];
        }
        
        // Calculate search time
        $endTime = microtime(true);
        $searchTime = round(($endTime - $startTime) * 1000, 2); // in milliseconds
        
        if ($debugLogs !== null) {
            $debugLogs['internal_content']['total_found'] = count($internalContent);
            $debugLogs['internal_content']['search_time_ms'] = $searchTime;
            $debugLogs['process_steps'][] = 'Found ' . count($internalContent) . ' internal content items in ' . $searchTime . 'ms';
            
            // Add information about content distribution
            $types = [];
            foreach ($internalContent as $item) {
                $type = $item['type'] ?? 'unknown';
                if (!isset($types[$type])) {
                    $types[$type] = 0;
                }
                $types[$type]++;
            }
            $debugLogs['internal_content']['type_distribution'] = $types;
        }
        
        // Log empty results for debugging
        if (count($internalContent) == 0) {
            \Log::warning("No internal content found for readlist query", [
                'description' => $description,
                'keywords' => $keywords,
                'search_time_ms' => $searchTime
            ]);
        }
        
        return $internalContent;
    }
    
    /**
     * Create a readlist in the database
     * 
     * @param \App\Models\User $user
     * @param array $readlistData
     * @return \App\Models\Readlist|null
     */
    private function createReadlistInDatabase($user, $readlistData)
    {
        try {
            // Track timing for performance analysis
            $startTime = microtime(true);
            
            // Log the initial readlist data for debugging
            $logContext = [
                'title' => $readlistData['title'] ?? 'Unknown title',
                'description' => substr($readlistData['description'] ?? 'No description', 0, 100),
                'user_id' => $user->id,
                'total_items_provided' => count($readlistData['items'] ?? [])
            ];
            \Log::info('Starting readlist creation', $logContext);
            
            // Check if there are any items to add to the readlist
            if (empty($readlistData['items'])) {
                \Log::warning('Attempted to create empty readlist - no items array provided', $logContext);
                return null; // Return null for empty readlists
            }
            
            // Count valid items to make sure we have at least one
            $validItemCount = 0;
            $externalItems = [];
            $internalItems = [];
            $invalidItems = [];
            
            // Pre-check items to verify we have at least one valid item
            foreach ($readlistData['items'] as $index => $item) {
                $itemValidity = [
                    'index' => $index,
                    'is_valid' => false,
                    'reason' => 'Unknown validation failure'
                ];
                
                if (isset($item['type']) && $item['type'] === 'external') {
                    // Check that external item has valid URL
                    if (!empty($item['url'])) {
                        // Validate URL format
                        if (filter_var($item['url'], FILTER_VALIDATE_URL)) {
                            $validItemCount++;
                            $externalItems[] = $item;
                            $itemValidity['is_valid'] = true;
                            $itemValidity['reason'] = 'Valid external item with URL';
                        } else {
                            $itemValidity['reason'] = 'Invalid URL format: ' . ($item['url'] ?? 'empty');
                            $invalidItems[] = [
                                'item' => $item,
                                'reason' => 'Invalid URL format'
                            ];
                        }
                    } else {
                        $itemValidity['reason'] = 'External item missing URL';
                        $invalidItems[] = [
                            'item' => $item,
                            'reason' => 'Missing URL'
                        ];
                    }
                } else {
                    // Check if internal item exists
                    $itemId = $item['id'] ?? null;
                    $itemExists = false;
                    $checkResults = [];
                    
                    if ($itemId) {
                        $itemType = $item['type'] ?? 'unknown';
                        
                        if (strpos($itemType, 'course') !== false) {
                            $exists = \App\Models\Course::where('id', $itemId)->exists();
                            $checkResults['course_check'] = $exists;
                            $itemExists = $exists;
                        } elseif (strpos($itemType, 'post') !== false) {
                            $exists = \App\Models\Post::where('id', $itemId)->exists();
                            $checkResults['post_check'] = $exists;
                            $itemExists = $exists;
                        } else {
                            // Try both types
                            $courseExists = \App\Models\Course::where('id', $itemId)->exists();
                            $postExists = \App\Models\Post::where('id', $itemId)->exists();
                            $checkResults['course_check'] = $courseExists;
                            $checkResults['post_check'] = $postExists;
                            $itemExists = $courseExists || $postExists;
                        }
                        
                        if ($itemExists) {
                            $validItemCount++;
                            $internalItems[] = $item;
                            $itemValidity['is_valid'] = true;
                            $itemValidity['reason'] = 'Valid internal item';
                            $itemValidity['checks'] = $checkResults;
                        } else {
                            $itemValidity['reason'] = 'Internal item not found in database';
                            $itemValidity['checks'] = $checkResults;
                            $invalidItems[] = [
                                'item' => $item,
                                'reason' => 'Item not found',
                                'checks' => $checkResults
                            ];
                        }
                    } else {
                        $itemValidity['reason'] = 'Internal item missing ID';
                        $invalidItems[] = [
                            'item' => $item,
                            'reason' => 'Missing ID'
                        ];
                    }
                }
                
                // Log item validation for debugging specific items
                if (!$itemValidity['is_valid']) {
                    \Log::debug('Readlist item validation failed', $itemValidity);
                }
            }
            
            // Log validation summary
            \Log::info('Readlist items validation summary', [
                'title' => $readlistData['title'] ?? 'Unknown title',
                'total_items' => count($readlistData['items'] ?? []),
                'valid_items' => $validItemCount,
                'external_items' => count($externalItems),
                'internal_items' => count($internalItems),
                'invalid_items' => count($invalidItems)
            ]);
            
            // If no valid items were found, return null
            if ($validItemCount === 0) {
                \Log::warning('No valid items for readlist creation - all items failed validation', [
                    'title' => $readlistData['title'] ?? 'Unknown title',
                    'description' => substr($readlistData['description'] ?? 'No description', 0, 100),
                    'item_count' => count($readlistData['items']),
                    'user_id' => $user->id,
                    'invalid_items_sample' => array_slice($invalidItems, 0, 5), // Log up to 5 invalid items
                    'validation_failure_count' => count($invalidItems)
                ]);
                return null;
            }
            
            // Create the readlist
            \DB::beginTransaction();
            
            $readlist = new \App\Models\Readlist([
                'user_id' => $user->id,
                'title' => $readlistData['title'],
                'description' => $readlistData['description'],
                'is_public' => true,
            ]);
            
            $readlist->save();
            
            // Add items to the readlist
            $order = 1;
            $addedItems = 0;
            $failedItems = [];
            
            // Add all external items
            foreach ($externalItems as $item) {
                try {
                    $readlistItem = new \App\Models\ReadlistItem([
                        'readlist_id' => $readlist->id,
                        'title' => $item['title'] ?? 'Educational Resource',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'type' => 'external',
                        'order' => $order++,
                        'notes' => $item['notes'] ?? null
                    ]);
                    
                    $readlistItem->save();
                    $addedItems++;
                } catch (\Exception $e) {
                    \Log::error('Failed to add external item to readlist', [
                        'readlist_id' => $readlist->id,
                        'item' => $item,
                        'error' => $e->getMessage()
                    ]);
                    $failedItems[] = [
                        'item' => $item,
                        'error' => $e->getMessage(),
                        'type' => 'external'
                    ];
                }
            }
            
            // Add all internal items
            foreach ($internalItems as $item) {
                try {
                    $itemId = $item['id'];
                    $notes = $item['notes'] ?? null;
                    $itemType = null;
                    $itemModel = null;
                    $lookupResults = [];
                    
                    // Check if this is a course
                    if (strpos($item['type'] ?? '', 'course') !== false) {
                        $course = \App\Models\Course::find($itemId);
                        if ($course) {
                            $itemType = \App\Models\Course::class;
                            $itemModel = $course;
                            $lookupResults['course_found'] = true;
                        } else {
                            $lookupResults['course_found'] = false;
                        }
                    } elseif (strpos($item['type'] ?? '', 'post') !== false) {
                        $post = \App\Models\Post::find($itemId);
                        if ($post) {
                            $itemType = \App\Models\Post::class;
                            $itemModel = $post;
                            $lookupResults['post_found'] = true;
                        } else {
                            $lookupResults['post_found'] = false;
                        }
                    } else {
                        // Try to determine type automatically
                        $course = \App\Models\Course::find($itemId);
                        if ($course) {
                            $itemType = \App\Models\Course::class;
                            $itemModel = $course;
                            $lookupResults['course_found'] = true;
                            $lookupResults['post_checked'] = false;
                        } else {
                            $lookupResults['course_found'] = false;
                            $post = \App\Models\Post::find($itemId);
                            if ($post) {
                                $itemType = \App\Models\Post::class;
                                $itemModel = $post;
                                $lookupResults['post_found'] = true;
                            } else {
                                $lookupResults['post_found'] = false;
                            }
                        }
                    }
                    
                    // If we found a valid internal item, add it to the readlist
                    if ($itemModel) {
                        $readlistItem = new \App\Models\ReadlistItem([
                            'readlist_id' => $readlist->id,
                            'item_id' => $itemId,
                            'item_type' => $itemType,
                            'order' => $order++,
                            'notes' => $notes
                        ]);
                        
                        $readlistItem->save();
                        $addedItems++;
                    } else {
                        \Log::warning('Internal item not found during readlist creation', [
                            'item' => $item,
                            'lookup_results' => $lookupResults
                        ]);
                        $failedItems[] = [
                            'item' => $item,
                            'error' => 'Item not found during save',
                            'lookup_results' => $lookupResults,
                            'type' => 'internal'
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to add internal item to readlist', [
                        'readlist_id' => $readlist->id,
                        'item' => $item,
                        'error' => $e->getMessage()
                    ]);
                    $failedItems[] = [
                        'item' => $item,
                        'error' => $e->getMessage(),
                        'type' => 'internal'
                    ];
                }
            }
            
            // If no items were added (all failed validation), roll back
            if ($addedItems === 0) {
                \DB::rollBack();
                \Log::warning('Created readlist but no items could be added - all items failed during save', [
                    'title' => $readlistData['title'],
                    'description' => substr($readlistData['description'] ?? 'No description', 0, 100),
                    'failed_items' => $failedItems
                ]);
                return null;
            }
            
            \DB::commit();
            
            // Calculate time spent
            $endTime = microtime(true);
            $createTime = round(($endTime - $startTime) * 1000, 2); // in milliseconds
            
            // Log success with detailed metrics
            \Log::info('Successfully created readlist', [
                'id' => $readlist->id,
                'title' => $readlist->title,
                'item_count' => $addedItems,
                'external_items' => count($externalItems),
                'internal_items' => count($internalItems),
                'failed_items' => count($failedItems),
                'time_ms' => $createTime
            ]);
            
            return $readlist;
            
        } catch (\Exception $e) {
            if (isset($readlist) && \DB::transactionLevel() > 0) {
                \DB::rollBack();
            }
            
            \Log::error('Error creating readlist in database: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'title' => $readlistData['title'] ?? 'Unknown',
                'item_count' => count($readlistData['items'] ?? []),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);
            return null;
        }
    }
    
    /**
     * Handle web search requests using the enhanced Exa.ai integration
     *
     * @param \App\Models\User $user
     * @param string $question
     * @param array $context
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleWebSearchRequest($user, $question, $context, $conversationId)
    {
        try {
            // Get GPT search service (replacing Exa)
            $searchService = app(\App\Services\GPTSearchService::class);
            
            // Content moderation check
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            $moderationResult = $contentModerationService->analyzeText($question);
            
            if (!$moderationResult['isAllowed']) {
                $errorMsg = "I'm sorry, but I can't search for that topic as it contains inappropriate content.";
                
                // Store in conversation history
                $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
                
                // Add to context
                $context[] = [
                    'role' => 'assistant',
                    'content' => $errorMsg
                ];
                
                // Store updated context
                $conversationKey = 'cogni_conversation_' . $conversationId;
                Session::put($conversationKey, $context);
                
                return response()->json([
                    'success' => false,
                    'answer' => $errorMsg,
                    'conversation_id' => $conversationId
                ], 400);
            }
            
            // Analyze the query to determine search parameters
            $searchParams = $this->analyzeSearchQuery($question);
            
            // Common spam or inappropriate domains to exclude
            $excludeDomains = [
                'pinterest.com', // Often contains low-quality content
                'quora.com',     // Can contain unverified information
                'reddit.com',    // May contain inappropriate content
                'twitter.com',   // May contain unverified information
                'facebook.com',  // May contain unverified information
                'instagram.com', // May contain inappropriate content
            ];
            
            // Define content options for better results
            $contentsOptions = [
                'highlights' => true, // Get relevant snippets
                'text' => true,       // Get full text
                'summary' => true     // Get AI-generated summaries when available
            ];
            
            // Determine if this is a recent events query
            $dateRange = [];
            if ($searchParams['recent_events']) {
                $dateRange = [
                    'start' => date('Y-m-d', strtotime('-3 months'))
                ];
            }
            
            // Perform web search with GPT
            $searchResults = $searchService->search(
                $searchParams['query'], 
                $searchParams['num_results'], 
                $searchParams['include_domains'], 
                true, 
                $excludeDomains,
                $searchParams['search_type'],
                $searchParams['category'],
                $dateRange
            );
            
            if (!$searchResults['success'] || empty($searchResults['results'])) {
                // Retry with broader parameters if no results
                if ($searchParams['search_type'] === 'neural') {
                    // Try more general search as fallback
                    $searchResults = $searchService->search(
                        $searchParams['query'], 
                        $searchParams['num_results'], 
                        [], // No domain restrictions
                        true, 
                        $excludeDomains,
                        'keyword', // Use keyword parameter (GPT ignores this but keeps consistent interface)
                        '',        // No category filter
                        []         // No date restrictions
                    );
                }
                
                // If still no results
                if (!$searchResults['success'] || empty($searchResults['results'])) {
                    $noResultsMsg = "I searched the web for information about your query, but couldn't find relevant results. Could you try rephrasing your question?";
                    
                    // Store in conversation
                    $this->storeConversationInDatabase($user, $conversationId, $question, $noResultsMsg);
                    
                    // Add to context
                    $context[] = [
                        'role' => 'assistant',
                        'content' => $noResultsMsg
                    ];
                    
                    // Store updated context
                    $conversationKey = 'cogni_conversation_' . $conversationId;
                    Session::put($conversationKey, $context);
                    
                    return response()->json([
                        'success' => true,
                        'answer' => $noResultsMsg,
                        'conversation_id' => $conversationId
                    ]);
                }
            }
            
            // Format search results for the AI with improved formatting
            $formattedResults = "I found the following information from searching the web";
            if (isset($searchResults['search_type'])) {
                $formattedResults .= " using " . $searchResults['search_type'] . " search";
            }
            $formattedResults .= ":\n\n";
            
            foreach ($searchResults['results'] as $index => $result) {
                $formattedResults .= "[" . ($index + 1) . "] " . $result['title'] . "\n";
                $formattedResults .= "Source: " . $result['url'] . "\n";
                
                // Use summary if available, otherwise use highlights or text
                if (isset($result['summary'])) {
                    $formattedResults .= "Summary: " . $result['summary'] . "\n";
                } elseif (isset($result['highlights']) && !empty($result['highlights'])) {
                    $formattedResults .= "Highlights: " . implode(" ... ", $result['highlights']) . "\n";
                } else {
                    $formattedResults .= "Content: " . substr($result['text'], 0, 500) . "...\n";
                }
                
                if (isset($result['published_date']) && !empty($result['published_date'])) {
                    $formattedResults .= "Published: " . $result['published_date'] . "\n";
                }
                
                $formattedResults .= "\n";
            }
            
            // Create an improved prompt for the AI to synthesize the search results
            $synthesisPrompt = "Based on the web search results above, please provide a comprehensive answer to the user's question: \"" . $question . "\". ";
            $synthesisPrompt .= "Include relevant information from the search results, and cite your sources using the [1], [2], etc. notation from the results. ";
            $synthesisPrompt .= "If the search results don't fully answer the question, acknowledge that and provide what information is available. ";
            $synthesisPrompt .= "If the sources provide conflicting information, note the discrepancies and explain the different perspectives. ";
            $synthesisPrompt .= "Prioritize recent and authoritative sources when available.";
            
            // Add search results to the system context for this question
            $newContext = $context;
            array_unshift($newContext, [
                'role' => 'system',
                'content' => $formattedResults . "\n" . $synthesisPrompt
            ]);
            
            // Get AI synthesis of the search results
            $result = $this->cogniService->askQuestion("", $newContext);
            
            if ($result['success'] && isset($result['answer'])) {
                // Store in conversation
                $this->storeConversationInDatabase($user, $conversationId, $question, $result['answer']);
                
                // Add to context, but without the search results to keep context size reasonable
                $context[] = [
                    'role' => 'assistant',
                    'content' => $result['answer']
                ];
                
                // Store updated context
                $conversationKey = 'cogni_conversation_' . $conversationId;
                Session::put($conversationKey, $context);
                
                // Prepare web results with more useful information
                $enrichedWebResults = array_map(function($result) {
                    return [
                        'title' => $result['title'] ?? 'Untitled',
                        'url' => $result['url'] ?? '',
                        'domain' => $result['domain'] ?? parse_url($result['url'] ?? '', PHP_URL_HOST),
                        'summary' => $result['summary'] ?? null,
                        'highlights' => $result['highlights'] ?? [],
                        'published_date' => $result['published_date'] ?? null,
                    ];
                }, $searchResults['results']);
                
                return response()->json([
                    'success' => true,
                    'answer' => $result['answer'],
                    'conversation_id' => $conversationId,
                    'has_web_results' => true,
                    'web_results' => $enrichedWebResults,
                    'search_metadata' => [
                        'search_type' => $searchResults['search_type'] ?? null,
                        'total_results' => $searchResults['total_results'] ?? count($searchResults['results']),
                        'query' => $searchParams['query']
                    ]
                ]);
            }
            
            // Fallback if synthesis fails
            $fallbackMsg = "I found information from the web about your query, but couldn't synthesize it completely. Here are the most relevant sources I found:\n\n";
            
            foreach ($searchResults['results'] as $index => $result) {
                $fallbackMsg .= ($index + 1) . ". " . $result['title'] . "\n";
                $fallbackMsg .= "   URL: " . $result['url'] . "\n";
                
                // Add a brief highlight if available
                if (isset($result['highlights']) && !empty($result['highlights'])) {
                    $fallbackMsg .= "   Highlight: " . $result['highlights'][0] . "\n";
                } elseif (isset($result['summary'])) {
                    $fallbackMsg .= "   Summary: " . substr($result['summary'], 0, 150) . "...\n";
                }
                
                $fallbackMsg .= "\n";
            }
            
            $fallbackMsg .= "You can click on these links to explore further. Would you like me to try analyzing any specific aspect of these results?";
            
            // Store in conversation
            $this->storeConversationInDatabase($user, $conversationId, $question, $fallbackMsg);
            
            // Add to context
            $context[] = [
                'role' => 'assistant',
                'content' => $fallbackMsg
            ];
            
            // Store updated context
            $conversationKey = 'cogni_conversation_' . $conversationId;
            Session::put($conversationKey, $context);
            
            return response()->json([
                'success' => true,
                'answer' => $fallbackMsg,
                'conversation_id' => $conversationId,
                'has_web_results' => true,
                'web_results' => $searchResults['results']
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in handleWebSearchRequest: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMsg = "I encountered an error while searching the web. Could you try again later?";
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            return response()->json([
                'success' => true,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId
            ]);
        }
    }
    
    /**
     * Analyze a search query to determine appropriate search parameters
     *
     * @param string $query The user's query
     * @return array Search parameters
     */
    private function analyzeSearchQuery($query)
    {
        $params = [
            'query' => $query,
            'num_results' => 7,              // Slightly more results for better synthesis
            'search_type' => 'auto',         // Default to auto
            'include_domains' => [],         // No domain restrictions by default
            'category' => '',                // No category filter by default
            'recent_events' => false         // Not a recent events query by default
        ];
        
        // Check for specific query types
        
        // Educational query
        if (preg_match('/how\s+to|learn|tutorial|guide|explain|what\s+is|understanding|beginner|course/i', $query)) {
            $params['search_type'] = 'neural';  // Better for conceptual understanding
            $params['category'] = 'educational';
            $params['include_domains'] = [
                'edu',
                'org',
                'coursera.org',
                'khanacademy.org',
                'youtube.com',
                'developer.mozilla.org',
                'stackoverflow.com',
                'github.com'
            ];
        }
        
        // News or current events query
        if (preg_match('/latest|current|recent|news|update|today|this week|this month|what happened|event/i', $query)) {
            $params['search_type'] = 'keyword'; // Better for news retrieval
            $params['category'] = 'news';
            $params['recent_events'] = true;
            $params['include_domains'] = [
                'reuters.com',
                'apnews.com',
                'nytimes.com',
                'bbc.com',
                'bloomberg.com',
                'cnn.com',
                'washingtonpost.com',
                'theguardian.com',
                'news.google.com'
            ];
        }
        
        // Research or academic query
        if (preg_match('/research|study|paper|journal|academic|science|scientific|evidence|data|statistics|analysis/i', $query)) {
            $params['search_type'] = 'neural'; // Better for research content
            $params['category'] = 'research';
            $params['include_domains'] = [
                'edu',
                'gov',
                'org',
                'scholar.google.com',
                'arxiv.org',
                'researchgate.net',
                'ncbi.nlm.nih.gov',
                'science.org',
                'nature.com',
                'sciencedirect.com'
            ];
        }
        
        // Technology or programming query
        if (preg_match('/code|programming|software|developer|api|framework|library|tech|technology|app|application/i', $query)) {
            $params['search_type'] = 'neural';
            $params['include_domains'] = [
                'github.com',
                'stackoverflow.com',
                'developer.mozilla.org',
                'docs.python.org',
                'dev.to',
                'medium.com',
                'hackernoon.com',
                'npmjs.com',
                'pypi.org'
            ];
        }
        
        return $params;
    }
    
    /**
     * Get an explanation for a topic
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function explain(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'level' => 'nullable|string|in:basic,intermediate,advanced',
            'conversation_id' => 'nullable|string'
        ]);

        $topic = $request->input('topic');
        $level = $request->input('level', 'intermediate');
        $conversationId = $request->input('conversation_id');

        if (empty($conversationId)) {
            // Generate a new conversation ID if none provided
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }

        // Get conversation history
        $conversationKey = 'cogni_conversation_' . $conversationId;
        $context = Session::get($conversationKey, []);

        // Create a prompt for explanation
        $prompt = "Explain the concept of '{$topic}' at a {$level} level. Include key points, examples, and any relevant background information.";
        
        // Add to context
        $context[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        // Get the explanation
        $result = $this->cogniService->askQuestion($prompt, $context);

        if ($result['success'] && isset($result['answer'])) {
            // Add assistant's response to context
            $context[] = [
                'role' => 'assistant',
                'content' => $result['answer']
            ];
            
            // Store updated conversation in session
            if (count($context) > 10) {
                if ($context[0]['role'] === 'system') {
                    $context = array_merge(
                        [$context[0]],
                        array_slice($context, -9)
                    );
                } else {
                    $context = array_slice($context, -10);
                }
            }
            Session::put($conversationKey, $context);
            
            return response()->json([
                'success' => true,
                'explanation' => $result['answer'],
                'conversation_id' => $conversationId
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to get an explanation from Cogni'
        ], $result['code'] ?? 500);
    }

    /**
     * Generate a quiz on a topic
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateQuiz(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'question_count' => 'nullable|integer|min:1|max:10',
            'conversation_id' => 'nullable|string'
        ]);

        $topic = $request->input('topic');
        $questionCount = $request->input('question_count', 5);
        $conversationId = $request->input('conversation_id');

        if (empty($conversationId)) {
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }

        // Generate quiz without using conversation history
        // Since quiz generation is standalone and doesn't need context
        $result = $this->cogniService->generateQuiz($topic, $questionCount);

        if ($result['success']) {
            if (isset($result['quiz'])) {
                return response()->json([
                    'success' => true,
                    'quiz' => $result['quiz'],
                    'conversation_id' => $conversationId
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'answer' => $result['answer'],
                    'conversation_id' => $conversationId
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to generate a quiz'
        ], $result['code'] ?? 500);
    }

    /**
     * Clear conversation history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearConversation(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|string'
        ]);

        $conversationId = $request->input('conversation_id');
        $conversationKey = 'cogni_conversation_' . $conversationId;
        
        // Clear the conversation from session
        Session::forget($conversationKey);
        
        // Optional: Mark as cleared in database
        
        return response()->json([
            'success' => true,
            'message' => 'Conversation history cleared'
        ]);
    }
    
    /**
     * Generate a personalized "Cognition" readlist for the user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCognitionReadlist(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        try {
            // Use the CognitionService to generate the readlist
            $cognitionService = app(\App\Services\CognitionService::class);
            
            // Get or create the Cognition readlist
            $readlist = $cognitionService->getCognitionReadlist($user);
            
            // Update the readlist with personalized content
            $maxItems = $request->input('max_items', 5);
            $result = $cognitionService->updateCognitionReadlist($user, $maxItems);
            
            // Get the updated readlist with items
            $readlist->load('items');
            
            return response()->json([
                'success' => true,
                'message' => $result ? 'Cognition readlist updated successfully' : 'No new recommendations found',
                'readlist' => $readlist
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Cognition readlist: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Cognition readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a readlist for a specific topic
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateTopicReadlist(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'item_count' => 'nullable|integer|min:1|max:20',
            'content_types' => 'nullable|array',
            'content_types.*' => 'string|in:course,post,both'
        ]);

        $user = Auth::user();
        $topic = $request->input('topic');
        $itemCount = $request->input('item_count', 5);
        $contentTypes = $request->input('content_types', ['both']);
        
        // Determine which content types to include
        $includePosts = in_array('post', $contentTypes) || in_array('both', $contentTypes);
        $includeCourses = in_array('course', $contentTypes) || in_array('both', $contentTypes);
        
        // Collect available content from database
        $availableContent = [];
        $availableContentCount = 0;
        
        // Get relevant courses if requested
        if ($includeCourses) {
            $courses = \App\Models\Course::when($topic, function ($query, $topic) {
                    return $query->where('name', 'like', "%{$topic}%")
                        ->orWhere('description', 'like', "%{$topic}%");
                })
                ->limit(50)
                ->get(['id', 'name', 'description', 'user_id', 'created_at']);
            
            foreach ($courses as $course) {
                $availableContent[] = [
                    'id' => $course->id,
                    'type' => 'course',
                    'title' => $course->name,
                    'description' => $course->description,
                    'user_id' => $course->user_id,
                    'created_at' => $course->created_at
                ];
                $availableContentCount++;
            }
        }
        
        // Get relevant posts if requested
        if ($includePosts) {
            $posts = \App\Models\Post::when($topic, function ($query, $topic) {
                    return $query->where('title', 'like', "%{$topic}%")
                        ->orWhere('body', 'like', "%{$topic}%");
                })
                ->limit(50)
                ->get(['id', 'title', 'body', 'user_id', 'created_at']);
            
            foreach ($posts as $post) {
                $availableContent[] = [
                    'id' => $post->id,
                    'type' => 'post',
                    'title' => $post->title ?? 'Untitled Post',
                    'description' => substr(strip_tags($post->body), 0, 200),
                    'user_id' => $post->user_id,
                    'created_at' => $post->created_at
                ];
                $availableContentCount++;
            }
        }
        
        // If no content is found, return an error
        if ($availableContentCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No relevant content found for the topic "' . $topic . '"'
            ], 404);
        }
        
        // Generate readlist using Cogni service
        $result = $this->cogniService->generateReadlist($topic, $availableContent, $itemCount);
        
        if ($result['success'] && isset($result['readlist'])) {
            // Create the readlist in the database
            try {
                \DB::beginTransaction();
                
                $readlist = new \App\Models\Readlist([
                    'user_id' => $user->id,
                    'title' => $result['readlist']['title'],
                    'description' => $result['readlist']['description'],
                    'is_public' => true,
                ]);
                
                $readlist->save();
                
                // Add items to the readlist
                $order = 1;
                foreach ($result['readlist']['items'] as $item) {
                    $itemId = $item['id'];
                    $notes = $item['notes'] ?? null;
                    
                    // Determine the item type and get the model
                    $itemType = null;
                    $itemModel = null;
                    
                    // Check if this is a course
                    $course = \App\Models\Course::find($itemId);
                    if ($course) {
                        $itemType = \App\Models\Course::class;
                        $itemModel = $course;
                    } else {
                        // Check if this is a post
                        $post = \App\Models\Post::find($itemId);
                        if ($post) {
                            $itemType = \App\Models\Post::class;
                            $itemModel = $post;
                        }
                    }
                    
                    // If we found a valid item, add it to the readlist
                    if ($itemModel) {
                        $readlistItem = new \App\Models\ReadlistItem([
                            'readlist_id' => $readlist->id,
                            'item_id' => $itemId,
                            'item_type' => $itemType,
                            'order' => $order++,
                            'notes' => $notes
                        ]);
                        
                        $readlistItem->save();
                    }
                }
                
                \DB::commit();
                
                // Return the created readlist with its items
                $readlistWithItems = \App\Models\Readlist::with('items.item')->find($readlist->id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Readlist generated successfully',
                    'readlist' => $readlistWithItems
                ]);
                
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error('Error creating readlist: ' . $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating readlist: ' . $e->getMessage()
                ], 500);
            }
        } elseif ($result['success'] && isset($result['answer'])) {
            // Return the raw answer if JSON parsing failed
            return response()->json([
                'success' => true,
                'message' => 'Readlist generated, but couldn\'t be automatically created',
                'cogni_response' => $result['answer']
            ]);
        }
        
        // Return error if generation failed
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to generate a readlist'
        ], $result['code'] ?? 500);
    }
    
    /**
     * Generate a readlist from a description
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateDescriptionReadlist(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:1000',
            'item_count' => 'nullable|integer|min:1|max:20',
            'include_external' => 'nullable|boolean',
            'external_count' => 'nullable|integer|min:0|max:10'
        ]);

        $user = Auth::user();
        $description = $request->input('description');
        $itemCount = $request->input('item_count', 8);
        $includeExternal = $request->input('include_external', true);
        $externalCount = $request->input('external_count', 3);
        
        if (!$includeExternal) {
            $externalCount = 0;
        }
        
        // Check for inappropriate content in the description
        $contentModerationService = app(\App\Services\ContentModerationService::class);
        $moderationResult = $contentModerationService->analyzeText($description);
        
        if (!$moderationResult['isAllowed']) {
            return response()->json([
                'success' => false,
                'message' => 'Your request contains inappropriate content. Please modify and try again.',
                'details' => $moderationResult['reason']
            ], 400);
        }
        
        // First, search for relevant internal content
        $internalContent = [];
        
        // Search for relevant courses
        $courseKeywords = explode(' ', $description);
        $courseQuery = \App\Models\Course::query();
        
        foreach ($courseKeywords as $keyword) {
            if (strlen($keyword) > 3) {
                $courseQuery->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }
        }
        
        $courses = $courseQuery->limit(30)->get(['id', 'name', 'description', 'user_id', 'created_at']);
        
        foreach ($courses as $course) {
            $internalContent[] = [
                'id' => $course->id,
                'type' => 'course',
                'title' => $course->name,
                'description' => $course->description,
                'user_id' => $course->user_id,
                'created_at' => $course->created_at
            ];
        }
        
        // Search for relevant posts
        $postKeywords = explode(' ', $description);
        $postQuery = \App\Models\Post::query();
        
        foreach ($postKeywords as $keyword) {
            if (strlen($keyword) > 3) {
                $postQuery->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('body', 'like', "%{$keyword}%");
            }
        }
        
        $posts = $postQuery->limit(30)->get(['id', 'title', 'body', 'user_id', 'created_at']);
        
        foreach ($posts as $post) {
            $internalContent[] = [
                'id' => $post->id,
                'type' => 'post',
                'title' => $post->title ?? 'Untitled Post',
                'description' => substr(strip_tags($post->body), 0, 200),
                'user_id' => $post->user_id,
                'created_at' => $post->created_at
            ];
        }
        
        // Generate readlist using enhanced CogniService method
        $result = $this->cogniService->generateReadlistFromDescription(
            $description, 
            $internalContent, 
            $itemCount, 
            $externalCount
        );
        
        if ($result['success'] && isset($result['readlist'])) {
            // Final check of readlist content for appropriateness
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            $titleCheck = $contentModerationService->analyzeText($result['readlist']['title'] ?? '');
            $descriptionCheck = $contentModerationService->analyzeText($result['readlist']['description'] ?? '');
            
            if (!$titleCheck['isAllowed'] || !$descriptionCheck['isAllowed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'The generated readlist contains inappropriate content. Please try with different wording.',
                    'code' => 400
                ], 400);
            }
            
            // Create the readlist in the database
            try {
                \DB::beginTransaction();
                
                $readlist = new \App\Models\Readlist([
                    'user_id' => $user->id,
                    'title' => $result['readlist']['title'],
                    'description' => $result['readlist']['description'],
                    'is_public' => true,
                ]);
                
                $readlist->save();
                
                // Add items to the readlist
                $order = 1;
                foreach ($result['readlist']['items'] as $item) {
                    if (isset($item['type']) && $item['type'] === 'external') {
                        // Final check for external item content
                        $extTitleCheck = $contentModerationService->analyzeText($item['title'] ?? '');
                        $extDescCheck = $contentModerationService->analyzeText($item['description'] ?? '');
                        
                        // Validate URL
                        $urlIsValid = true;
                        if (!empty($item['url'])) {
                            try {
                                $parsedUrl = parse_url($item['url']);
                                if (isset($parsedUrl['host'])) {
                                    $domain = $parsedUrl['host'];
                                    $domainCheck = $contentModerationService->analyzeText($domain);
                                    if (!$domainCheck['isAllowed']) {
                                        $urlIsValid = false;
                                    }
                                }
                            } catch (\Exception $e) {
                                $urlIsValid = false;
                                \Log::error('Error parsing URL in readlist item', [
                                    'url' => $item['url'],
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        if ($extTitleCheck['isAllowed'] && $extDescCheck['isAllowed'] && $urlIsValid) {
                            // Handle external item
                            $readlistItem = new \App\Models\ReadlistItem([
                                'readlist_id' => $readlist->id,
                                'title' => $item['title'] ?? 'Educational Resource',
                                'description' => $item['description'] ?? '',
                                'url' => $item['url'] ?? '',
                                'type' => 'external',
                                'order' => $order++,
                                'notes' => $item['notes'] ?? null
                            ]);
                            
                            $readlistItem->save();
                        } else {
                            \Log::warning('Skipped external resource in readlist due to content moderation', [
                                'title' => $item['title'] ?? '',
                                'url' => $item['url'] ?? ''
                            ]);
                        }
                    } else {
                        // Handle internal item (course or post)
                        $itemId = $item['id'];
                        $notes = $item['notes'] ?? null;
                        $itemType = null;
                        $itemModel = null;
                        
                        // Check if internal item exists
                        if (strpos($item['type'] ?? '', 'internal_course') !== false) {
                            $course = \App\Models\Course::find($itemId);
                            if ($course) {
                                $itemType = \App\Models\Course::class;
                                $itemModel = $course;
                            }
                        } elseif (strpos($item['type'] ?? '', 'internal_post') !== false) {
                            $post = \App\Models\Post::find($itemId);
                            if ($post) {
                                $itemType = \App\Models\Post::class;
                                $itemModel = $post;
                            }
                        } else {
                            // Try to determine type automatically
                            $course = \App\Models\Course::find($itemId);
                            if ($course) {
                                $itemType = \App\Models\Course::class;
                                $itemModel = $course;
                            } else {
                                $post = \App\Models\Post::find($itemId);
                                if ($post) {
                                    $itemType = \App\Models\Post::class;
                                    $itemModel = $post;
                                }
                            }
                        }
                        
                        // If we found a valid internal item, add it to the readlist
                        if ($itemModel) {
                            $readlistItem = new \App\Models\ReadlistItem([
                                'readlist_id' => $readlist->id,
                                'item_id' => $itemId,
                                'item_type' => $itemType,
                                'order' => $order++,
                                'notes' => $notes
                            ]);
                            
                            $readlistItem->save();
                        }
                    }
                }
                
                \DB::commit();
                
                // Return the created readlist with its items
                $readlistWithItems = \App\Models\Readlist::with('items.item')->find($readlist->id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Readlist generated successfully from your description',
                    'readlist' => $readlistWithItems
                ]);
                
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error('Error creating description-based readlist: ' . $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating readlist: ' . $e->getMessage()
                ], 500);
            }
        } elseif ($result['success'] && isset($result['answer'])) {
            // Return the raw answer if JSON parsing failed
            return response()->json([
                'success' => true,
                'message' => 'Readlist generated from your description, but couldn\'t be automatically created',
                'cogni_response' => $result['answer']
            ]);
        }
        
        // Return error if generation failed
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to generate a readlist from your description'
        ], $result['code'] ?? 500);
    }
    
    /**
     * Get all chats for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChats()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        try {
            $chats = CogniChat::where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function($chat) {
                    // Get the last message for preview
                    $lastMessage = $chat->messages()->latest()->first();
                    
                    return [
                        'id' => $chat->id,
                        'title' => $chat->title,
                        'share_key' => $chat->share_key,
                        'is_public' => $chat->is_public,
                        'created_at' => $chat->created_at,
                        'updated_at' => $chat->updated_at,
                        'message_count' => $chat->messages()->count(),
                        'last_message' => $lastMessage ? [
                            'content_type' => $lastMessage->content_type,
                            'sender_type' => $lastMessage->sender_type,
                            'preview' => $this->getMessagePreview($lastMessage),
                            'created_at' => $lastMessage->created_at
                        ] : null
                    ];
                });
            
            return response()->json([
                'success' => true,
                'chats' => $chats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get chats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chats: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a preview of a message based on its content type
     *
     * @param CogniChatMessage $message
     * @return string
     */
    private function getMessagePreview($message)
    {
        switch ($message->content_type) {
            case 'text':
                // Return a shortened version of the text
                return strlen($message->content) > 100 
                    ? substr($message->content, 0, 97) . '...' 
                    : $message->content;
                
            case 'link':
                return 'Shared a link: ' . parse_url($message->content, PHP_URL_HOST);
                
            case 'image':
                return 'Shared an image';
                
            case 'document':
                $filename = $message->metadata['filename'] ?? 'document';
                return 'Shared a document: ' . $filename;
                
            default:
                return 'Message';
        }
    }
    
    /**
     * Get a specific chat with its messages
     *
     * @param string $shareKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChat($shareKey)
    {
        $user = Auth::user();
        
        try {
            $chat = CogniChat::where('share_key', $shareKey)->first();
            
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat not found'
                ], 404);
            }
            
            // Check if user has access to this chat
            if (!$chat->is_public && (!$user || $chat->user_id !== $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this chat'
                ], 403);
            }
            
            // Get messages with proper formatting
            $messages = $chat->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($message) {
                    return $message->formattedContent();
                });
            
            return response()->json([
                'success' => true,
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                    'share_key' => $chat->share_key,
                    'is_public' => $chat->is_public,
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                    'messages' => $messages
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chat: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a new chat
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createChat(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'initial_message' => 'nullable|string',
            'is_public' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Create a new chat
            $chat = new CogniChat();
            $chat->user_id = $user->id;
            $chat->title = $request->input('title');
            $chat->is_public = $request->input('is_public', false);
            $chat->save();
            
            // Add initial message if provided
            $initialMessage = $request->input('initial_message');
            if (!empty($initialMessage)) {
                $message = new CogniChatMessage();
                $message->chat_id = $chat->id;
                $message->sender_type = 'user';
                $message->content_type = 'text';
                $message->content = $initialMessage;
                $message->save();
                
                // Get response from Cogni
                $cogniResponse = $this->cogniService->askQuestion($initialMessage);
                
                if ($cogniResponse['success'] && isset($cogniResponse['answer'])) {
                    $responseMessage = new CogniChatMessage();
                    $responseMessage->chat_id = $chat->id;
                    $responseMessage->sender_type = 'cogni';
                    $responseMessage->content_type = 'text';
                    $responseMessage->content = $cogniResponse['answer'];
                    $responseMessage->save();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Chat created successfully',
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                    'share_key' => $chat->share_key,
                    'is_public' => $chat->is_public,
                    'created_at' => $chat->created_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create chat: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a new chat from shared content (text, link, image, document)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createChatFromShared(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'content_type' => 'required|string|in:text,link,image,document,youtube',
            'content' => 'required_if:content_type,text,link,youtube|string',
            'file' => 'required_if:content_type,image,document|file|max:10240',
            'metadata' => 'nullable|array',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $contentType = $request->input('content_type');
            $content = null;
            $metadata = $request->input('metadata', []);
            
            // Process the content based on type
            switch ($contentType) {
                case 'text':
                case 'link':
                    $content = $request->input('content');
                    break;
                    
                case 'youtube':
                    $content = $request->input('content');
                    $videoId = $this->youtubeService->extractVideoId($content);
                    
                    if (!$videoId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid YouTube URL'
                        ], 422);
                    }
                    
                    // Fetch video info and captions
                    $videoInfo = $this->youtubeService->getVideoInfo($videoId);
                    
                    if (!$videoInfo['success']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Could not retrieve YouTube video information: ' . 
                                        ($videoInfo['message'] ?? 'Unknown error')
                        ], $videoInfo['code'] ?? 500);
                    }
                    
                    // Add video information to metadata
                    $metadata['video_id'] = $videoId;
                    $metadata['video_title'] = $videoInfo['video']['title'] ?? 'Unknown';
                    $metadata['video_channel'] = $videoInfo['video']['channel'] ?? 'Unknown';
                    $metadata['video_duration'] = $videoInfo['video']['duration'] ?? 'Unknown';
                    $metadata['video_thumbnail'] = $videoInfo['video']['thumbnail'] ?? null;
                    
                    // Switch the content type to a special 'youtube' type
                    $contentType = 'youtube';
                    break;
                    
                case 'image':
                case 'document':
                    if ($request->hasFile('file')) {
                        $file = $request->file('file');
                        $fileName = time() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs(
                            'cogni_chat_' . $contentType . 's', 
                            $fileName, 
                            'public'
                        );
                        
                        $content = Storage::url($path);
                        $metadata['filename'] = $file->getClientOriginalName();
                        $metadata['file_type'] = $file->getClientMimeType();
                        $metadata['size'] = $file->getSize();
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'No file provided'
                        ], 422);
                    }
                    break;
            }
            
            if (empty($content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content is required'
                ], 422);
            }
            
            // Create the chat with the shared content
            $chat = CogniChat::createFromSharedContent($user->id, $contentType, $content, $metadata);
            
            // Generate a Cogni response for the shared content
            $cogniResponse = null;
            
            // If it's a YouTube link, get a summary of the video
            if ($contentType === 'youtube' && isset($metadata['video_id'])) {
                $videoSummary = $this->youtubeService->summarizeVideo($metadata['video_id'], $this->cogniService);
                
                if ($videoSummary['success']) {
                    // Create a response message with the video summary
                    $responseMessage = new CogniChatMessage();
                    $responseMessage->chat_id = $chat->id;
                    $responseMessage->sender_type = 'cogni';
                    $responseMessage->content_type = 'text';
                    $responseMessage->content = "📺 **Video Analysis: " . $metadata['video_title'] . "**\n\n" . 
                                                $videoSummary['summary'] . "\n\n" . 
                                                "What would you like to know about this video? I'm ready to answer your questions.";
                    $responseMessage->save();
                    
                    // Add the captions or transcript to the chat metadata for later reference
                    if (isset($videoSummary['captions_available']) && $videoSummary['captions_available']) {
                        $captionsResult = $this->youtubeService->getCaptions($metadata['video_id']);
                        
                        if ($captionsResult['success']) {
                            // Store captions as a system message that won't be displayed to the user
                            // but can be referenced by Cogni for context
                            $captionsMessage = new CogniChatMessage();
                            $captionsMessage->chat_id = $chat->id;
                            $captionsMessage->sender_type = 'system';
                            $captionsMessage->content_type = 'text';
                            $captionsMessage->content = "TRANSCRIPT:\n\n" . $captionsResult['captions']['content'];
                            $captionsMessage->metadata = [
                                'is_transcript' => true,
                                'video_id' => $metadata['video_id']
                            ];
                            $captionsMessage->save();
                        }
                    }
                } else {
                    // Fall back to a generic prompt if video analysis fails
                    $prompt = "I'd like to discuss this YouTube video: " . $content . 
                             "\nTitle: " . ($metadata['video_title'] ?? 'Unknown') . 
                             "\nChannel: " . ($metadata['video_channel'] ?? 'Unknown');
                             
                    $cogniResponse = $this->cogniService->askQuestion($prompt);
                    
                    if ($cogniResponse['success'] && isset($cogniResponse['answer'])) {
                        $responseMessage = new CogniChatMessage();
                        $responseMessage->chat_id = $chat->id;
                        $responseMessage->sender_type = 'cogni';
                        $responseMessage->content_type = 'text';
                        $responseMessage->content = $cogniResponse['answer'];
                        $responseMessage->save();
                    }
                }
            } else {
                // Handle other content types as before
                $promptMap = [
                    'text' => 'I want to discuss this text: ' . $content,
                    'link' => 'I want to discuss this link: ' . $content,
                    'image' => 'I\'ve shared an image with you. Can you help me analyze or discuss it?',
                    'document' => 'I\'ve shared a document named "' . ($metadata['filename'] ?? 'document') . '". Can you help me understand or discuss it?'
                ];
                
                $prompt = $promptMap[$contentType] ?? 'I want to discuss this content with you.';
                
                $cogniResponse = $this->cogniService->askQuestion($prompt);
                
                if ($cogniResponse['success'] && isset($cogniResponse['answer'])) {
                    $responseMessage = new CogniChatMessage();
                    $responseMessage->chat_id = $chat->id;
                    $responseMessage->sender_type = 'cogni';
                    $responseMessage->content_type = 'text';
                    $responseMessage->content = $cogniResponse['answer'];
                    $responseMessage->save();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Chat created from shared content',
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                    'share_key' => $chat->share_key,
                    'is_public' => $chat->is_public,
                    'created_at' => $chat->created_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create chat from shared content: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create chat from shared content: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a new message to an existing chat
     *
     * @param Request $request
     * @param string $shareKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMessage(Request $request, $shareKey)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'content_type' => 'required|string|in:text,link,image,document',
            'content' => 'required_if:content_type,text,link|string',
            'file' => 'required_if:content_type,image,document|file|max:10240',
            'metadata' => 'nullable|array',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find the chat by share key
            $chat = CogniChat::where('share_key', $shareKey)->first();
            
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat not found'
                ], 404);
            }
            
            // Check if user has permission to add messages
            if ($chat->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add messages to this chat'
                ], 403);
            }
            
            $contentType = $request->input('content_type');
            $content = null;
            $metadata = $request->input('metadata', []);
            
            // Process the content based on type
            switch ($contentType) {
                case 'text':
                case 'link':
                    $content = $request->input('content');
                    break;
                    
                case 'image':
                case 'document':
                    if ($request->hasFile('file')) {
                        $file = $request->file('file');
                        $fileName = time() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs(
                            'cogni_chat_' . $contentType . 's', 
                            $fileName, 
                            'public'
                        );
                        
                        $content = Storage::url($path);
                        $metadata['filename'] = $file->getClientOriginalName();
                        $metadata['file_type'] = $file->getClientMimeType();
                        $metadata['size'] = $file->getSize();
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'No file provided'
                        ], 422);
                    }
                    break;
            }
            
            if (empty($content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content is required'
                ], 422);
            }
            
            // Add the user message
            $message = new CogniChatMessage();
            $message->chat_id = $chat->id;
            $message->sender_type = 'user';
            $message->content_type = $contentType;
            $message->content = $content;
            $message->metadata = $metadata;
            $message->save();
            
            // Update chat timestamp
            $chat->touch();
            
            // Generate a Cogni response for the new message
            $cogniResponse = null;
            
            if ($contentType === 'text' || $contentType === 'link') {
                // Check if the message contains a YouTube link
                if (preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $content, $matches)) {
                    // Extract YouTube video ID
                    $videoId = $matches[1];
                    
                    // Update the message metadata to indicate it contains a YouTube link
                    $message->metadata = array_merge($message->metadata ?? [], [
                        'contains_youtube' => true,
                        'video_id' => $videoId
                    ]);
                    $message->save();
                    
                    // Get video info
                    $videoInfo = $this->youtubeService->getVideoInfo($videoId);
                    
                    if ($videoInfo['success']) {
                        // Get video summary with captions
                        $videoSummary = $this->youtubeService->summarizeVideo($videoId, $this->cogniService);
                        
                        if ($videoSummary['success']) {
                            // Store captions as a system message for context
                            $captionsResult = $this->youtubeService->getCaptions($videoId);
                            
                            if ($captionsResult['success']) {
                                $captionsMessage = new CogniChatMessage();
                                $captionsMessage->chat_id = $chat->id;
                                $captionsMessage->sender_type = 'system';
                                $captionsMessage->content_type = 'text';
                                $captionsMessage->content = "TRANSCRIPT:\n\n" . $captionsResult['captions']['content'];
                                $captionsMessage->metadata = [
                                    'is_transcript' => true,
                                    'video_id' => $videoId
                                ];
                                $captionsMessage->save();
                            }
                            
                            // Prepare response with video analysis
                            $responseMessage = new CogniChatMessage();
                            $responseMessage->chat_id = $chat->id;
                            $responseMessage->sender_type = 'cogni';
                            $responseMessage->content_type = 'text';
                            $responseMessage->content = "📺 **Video Analysis: " . ($videoInfo['video']['title'] ?? 'YouTube Video') . "**\n\n" . 
                                                      $videoSummary['summary'] . "\n\n" . 
                                                      "What would you like to know about this video?";
                            $responseMessage->save();
                            
                            // Return early since we've created the response
                            $responseData = [
                                'user_message' => $message->formattedContent(),
                                'cogni_message' => $responseMessage->formattedContent(),
                                'video_info' => $videoInfo['video']
                            ];
                            
                            return response()->json([
                                'success' => true,
                                'message' => 'Message with YouTube video added successfully',
                                'data' => $responseData
                            ]);
                        }
                    }
                    
                    // If video analysis fails, continue with normal processing
                }
                
                // If it's regular text or link, we can send the content directly to Cogni
                $prompt = $content;
                
                // Get previous conversation context
                $context = [];
                $previousMessages = $chat->messages()
                    ->where('id', '!=', $message->id)
                    ->where('sender_type', '!=', 'system') // Exclude system messages
                    ->orderBy('created_at', 'asc')
                    ->take(10)
                    ->get();
                
                foreach ($previousMessages as $prevMsg) {
                    if ($prevMsg->content_type === 'text') {
                        $context[] = [
                            'role' => $prevMsg->sender_type === 'user' ? 'user' : 'assistant',
                            'content' => $prevMsg->content
                        ];
                    }
                }
                
                // If there's a system message with transcript for this chat, add that context
                $transcript = $chat->messages()
                    ->where('sender_type', 'system')
                    ->whereJsonContains('metadata->is_transcript', true)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($transcript) {
                    // Add the transcript as system message to provide context
                    array_unshift($context, [
                        'role' => 'system',
                        'content' => "If the user asks about the YouTube video, you can reference this information: " . $transcript->content
                    ]);
                }
                
                $cogniResponse = $this->cogniService->askQuestion($prompt, $context);
            } else if ($contentType === 'youtube') {
                // Handle YouTube links explicitly shared as youtube content type
                $videoId = $this->youtubeService->extractVideoId($content);
                
                if ($videoId) {
                    // Get video summary and captions
                    $videoSummary = $this->youtubeService->summarizeVideo($videoId, $this->cogniService);
                    
                    if ($videoSummary['success']) {
                        // Create YouTube-specific response
                        $responseMessage = new CogniChatMessage();
                        $responseMessage->chat_id = $chat->id;
                        $responseMessage->sender_type = 'cogni';
                        $responseMessage->content_type = 'text';
                        $responseMessage->content = "📺 **Video Analysis**\n\n" . 
                                                  $videoSummary['summary'] . "\n\n" . 
                                                  "What aspects of this video would you like me to elaborate on?";
                        $responseMessage->save();
                        
                        // Also store captions for future reference
                        $captionsResult = $this->youtubeService->getCaptions($videoId);
                        
                        if ($captionsResult['success']) {
                            $captionsMessage = new CogniChatMessage();
                            $captionsMessage->chat_id = $chat->id;
                            $captionsMessage->sender_type = 'system';
                            $captionsMessage->content_type = 'text';
                            $captionsMessage->content = "TRANSCRIPT:\n\n" . $captionsResult['captions']['content'];
                            $captionsMessage->metadata = [
                                'is_transcript' => true,
                                'video_id' => $videoId
                            ];
                            $captionsMessage->save();
                        }
                        
                        $responseData = [
                            'user_message' => $message->formattedContent(),
                            'cogni_message' => $responseMessage->formattedContent()
                        ];
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'YouTube video analysis added successfully',
                            'data' => $responseData
                        ]);
                    }
                }
                
                // If the video analysis failed, fall back to generic handling
                $prompt = "I'd like to discuss this YouTube video: " . $content;
                $cogniResponse = $this->cogniService->askQuestion($prompt);
            } else {
                // For images and documents, use a generic prompt
                $promptMap = [
                    'image' => 'I\'ve shared an image with you. Can you help me analyze or discuss it?',
                    'document' => 'I\'ve shared a document named "' . ($metadata['filename'] ?? 'document') . '". Can you help me understand or discuss it?'
                ];
                
                $prompt = $promptMap[$contentType] ?? 'I want to discuss this content with you.';
                $cogniResponse = $this->cogniService->askQuestion($prompt);
            }
            
            // Add Cogni's response if successful
            $responseData = ['user_message' => $message->formattedContent()];
            
            if ($cogniResponse && isset($cogniResponse['success']) && $cogniResponse['success'] && isset($cogniResponse['answer'])) {
                $responseMessage = new CogniChatMessage();
                $responseMessage->chat_id = $chat->id;
                $responseMessage->sender_type = 'cogni';
                $responseMessage->content_type = 'text';
                $responseMessage->content = $cogniResponse['answer'];
                $responseMessage->save();
                
                $responseData['cogni_message'] = $responseMessage->formattedContent();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message added successfully',
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add message to chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add message to chat: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a chat
     *
     * @param string $shareKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteChat($shareKey)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        try {
            $chat = CogniChat::where('share_key', $shareKey)->first();
            
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat not found'
                ], 404);
            }
            
            // Check if user has permission to delete the chat
            if ($chat->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this chat'
                ], 403);
            }
            
            // Delete chat and all its messages
            $chat->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Chat deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete chat: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get interesting facts for the user based on their interests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInterestingFacts(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $topic = $request->input('topic');
        $count = $request->input('count', 1);
        
        // Limit count to reasonable number
        $count = min(max(1, $count), 5);
        
        // Get facts based on user interests or specified topic - optimized for widgets
        $result = $this->factsService->getInterestingFacts($user, $topic, $count);
        
        if ($result['success']) {
            // Format optimized for iOS widgets - flat structure for easy consumption
            $widgetFriendlyResponse = [
                'success' => true,
                'topic' => $result['topic'],
                'facts' => array_map(function($fact) {
                    return [
                        'fact' => mb_substr($fact['fact'] ?? '', 0, 100), // Ensure widget-friendly length
                        'explanation' => mb_substr($fact['explanation'] ?? '', 0, 150)
                    ];
                }, $result['facts'])
            ];
            
            return response()->json($widgetFriendlyResponse);
        }
        
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to retrieve interesting facts'
        ], $result['code'] ?? 500);
    }
    
    /**
     * Get a daily interesting fact for the user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyFact()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $result = $this->factsService->getDailyFact($user);
        
        if ($result['success']) {
            $fact = $result['facts'][0] ?? null;
            
            // Format optimized for iOS widgets
            return response()->json([
                'success' => true,
                'topic' => $result['topic'],
                'fact' => $fact ? [
                    'fact' => mb_substr($fact['fact'] ?? '', 0, 100), // Ensure widget-friendly length
                    'explanation' => mb_substr($fact['explanation'] ?? '', 0, 150)
                ] : null
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to retrieve daily fact'
        ], $result['code'] ?? 500);
    }
    
    /**
     * Analyze a readlist query to determine the best search approach
     *
     * @param string $description The readlist description
     * @return array Parameters for search
     */
    private function analyzeReadlistQueryType($description)
    {
        $result = [
            'search_type' => 'neural',           // Default to neural for better semantic understanding
            'category' => 'educational',         // Default to educational content
            'additional_terms' => 'learning resources guides tutorials educational' // Default additional terms
        ];
        
        // Check for educational/academic content
        if (preg_match('/learn|study|course|education|school|university|college|academic|lecture|tutorial|lesson|class/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'educational';
            $result['additional_terms'] = 'educational resources tutorials guides courses learning materials';
        }
        
        // Check for technical/programming content
        else if (preg_match('/code|program|develop|software|app|tech|api|library|framework|web|mobile|computer|language|script/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'technical';
            $result['additional_terms'] = 'programming resources documentation tutorials technical guides examples';
        }
        
        // Check for science/research content
        else if (preg_match('/science|research|study|experiment|lab|data|analysis|physics|chemistry|biology|math|statistics|journal|paper/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'research';
            $result['additional_terms'] = 'scientific papers research studies academic journals educational resources';
        }
        
        // Check for business/professional content
        else if (preg_match('/business|company|corporate|entrepreneur|marketing|finance|management|professional|career|industry|market/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'business';
            $result['additional_terms'] = 'business resources professional guides case studies best practices';
        }
        
        // Check for arts/humanities content
        else if (preg_match('/art|music|literature|history|philosophy|culture|language|writing|creative|design|film|theater|humanities/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'arts';
            $result['additional_terms'] = 'arts humanities educational resources guides creative works';
        }
        
        // Check for news/current events
        else if (preg_match('/news|current|recent|today|update|event|latest|development|trend/i', $description)) {
            $result['search_type'] = 'keyword'; // Better for news
            $result['category'] = 'news';
            $result['additional_terms'] = 'news articles recent developments analysis reports';
        }
        
        // Check for health/medical content
        else if (preg_match('/health|medical|medicine|doctor|disease|condition|treatment|therapy|wellness|fitness|nutrition|diet/i', $description)) {
            $result['search_type'] = 'neural';
            $result['category'] = 'medical';
            $result['additional_terms'] = 'health medical information educational resources reputable sources';
        }
        
        return $result;
    }
}