<?php

/*
i want you to run curl requests to test the app, especially creating a readlist from cogni, the server is hosted at @https://ariesmvp-9903a26b3095.herokuapp.com/  and you'd need to login, the details for that is: {
    "login": "subomi",
     "password": "Muideenid1$"
}
 
*/

namespace App\Http\Controllers;

use App\Models\CogniChat;
use App\Models\CogniChatMessage;
use App\Services\CogniService;
use App\Services\PersonalizedFactsService;
use App\Services\YouTubeService;
use App\Services\EnhancedTopicExtractionService;
use App\Services\AIReadlistImageService;
use App\Services\ExaSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ProcessCogniQuestion; // Import the job

class CogniController extends Controller
{
    protected $cogniService;
    protected $youtubeService;
    protected $factsService;
    protected $topicExtractionService;
    protected $aiImageService;
    protected $exaSearchService;

    public function __construct(
        CogniService $cogniService, 
        YouTubeService $youtubeService,
        PersonalizedFactsService $factsService,
        EnhancedTopicExtractionService $topicExtractionService,
        AIReadlistImageService $aiImageService,
        ExaSearchService $exaSearchService
    ) {
        $this->cogniService = $cogniService;
        $this->youtubeService = $youtubeService;
        $this->factsService = $factsService;
        $this->topicExtractionService = $topicExtractionService;
        $this->aiImageService = $aiImageService;
        $this->exaSearchService = $exaSearchService;
    }

    /**
     * Ask a question to Cogni
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:1000',
                'conversation_id' => 'nullable|string|max:255'
            ]);

            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $question = $request->input('question');
            $conversationId = $request->input('conversation_id');
            if (empty($conversationId)) {
                // Generate a new conversation ID if none provided
                $conversationId = 'conv_' . uniqid() . '_' . time();
            }

            \Log::info('Cogni question received', [
                'user_id' => $user->id,
                'question' => substr($question, 0, 100),
                'conversation_id' => $conversationId,
                'timestamp' => now()
            ]);

            // Check if this is a readlist creation request
            if ($this->isReadlistRequest($question)) {
                return $this->handleReadlistCreationRequest($user, $question, $conversationId);
            }

            // Check if this is a web search request
            if ($this->isWebSearchRequest($question)) {
                return $this->handleWebSearchRequest($user, $question, [], $conversationId);
            }

            // Default to regular Cogni processing
            $response = $this->cogniService->askQuestionInternal($question, []);

            // Ensure $response is a string for logging/length
            $responseStr = $response;
            if (is_array($response)) {
                if (isset($response['answer'])) {
                    $responseStr = $response['answer'];
                } else {
                    $responseStr = json_encode($response);
                }
            }

            \Log::info('Cogni question processed successfully', [
                'user_id' => $user->id,
                'question_length' => strlen($question),
                'response_length' => strlen($responseStr),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => true,
                'response' => $response,
                'conversation_id' => $conversationId
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Validation error in Cogni ask', [
                'errors' => $e->errors(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Invalid request data',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in Cogni ask endpoint', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth()->id(),
                'question' => substr($request->input('question', ''), 0, 100)
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while processing your request. Please try again.',
                'debug_info' => config('app.debug') ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
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
            'history' => $formattedHistory
        ]);
    }

    /**
     * Handle readlist creation request from chat interface
     * 
     * This method processes natural language requests to create readlists from chat messages.
     * It extracts the topic, finds relevant content, and creates a readlist with a mix of
     * internal and external resources.
     *
     * @param \App\Models\User $user The authenticated user
     * @param string $question The user's message/query
     * @param string $conversationId The current conversation ID
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleReadlistCreationRequest($user, $question, $conversationId)
    {
        if (empty($conversationId)) {
            // Generate a new conversation ID if none provided
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }
        
        // Create an array to collect debug information throughout the process
        $debugLogs = [
            'process_steps' => [],
            'internal_content' => [],
            'search_results' => [],
            'validation' => [],
            'error_details' => [],
            'user_query' => $question
        ];
        
        $description = '';
        $enhancedTopicInfo = [];
        $isConversationContext = false;
        $conversationContext = [];
        
        try {
            \Log::info('[Cogni] Starting enhanced readlist creation', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'question' => $question
            ]);
            $debugLogs['process_steps'][] = 'Starting enhanced readlist creation process';
            
            // Get user conversation history for better context understanding
            $conversationHistory = $this->getUserConversationHistory($user, $conversationId);
            
            // Check if this is part of an ongoing conversation
            if (count($conversationHistory) > 1) {
                $isConversationContext = true;
                $conversationContext = array_slice($conversationHistory, -5); // Get last 5 messages for context
                $debugLogs['process_steps'][] = 'Using conversation context with ' . count($conversationContext) . ' previous messages';
                \Log::info('[Cogni] Using conversation context', [
                    'context' => $conversationContext
                ]);
            }
            
            // Analyze user interests from conversation history
            $userInterests = $this->topicExtractionService->analyzeUserInterests($conversationHistory);
            $debugLogs['user_interests'] = $userInterests;
            \Log::info('[Cogni] User interests extracted', [
                'user_id' => $user->id,
                'user_interests' => $userInterests
            ]);
            
            // Always pass recent conversation context for topic extraction
            $recentContext = $conversationContext;
            if (!$isConversationContext) {
                // If not enough context, just use the current question
                $recentContext = [
                    ['role' => 'user', 'content' => $question]
                ];
            }
            $enhancedTopicInfo = $this->topicExtractionService->extractAndEnhanceTopic(
                $question,
                $userInterests,
                $recentContext
            );
            \Log::info('[Cogni] Enhanced topic extraction', [
                'enhanced_topic_info' => $enhancedTopicInfo
            ]);
            
            // Use the enhanced topic or fall back to the original question
            $description = $enhancedTopicInfo['primary_topic'] ?? $question;
            
            // If we're in a conversation context, try to extract more specific topic
            if ($isConversationContext && empty($enhancedTopicInfo['is_explicit_topic'])) {
                $conversationTopic = $this->extractTopicFromConversation($conversationContext, $question);
                if ($conversationTopic) {
                    $description = $conversationTopic;
                    $enhancedTopicInfo['is_conversation_topic'] = true;
                    $enhancedTopicInfo['conversation_topic'] = $conversationTopic;
                    \Log::info('[Cogni] Topic extracted from conversation context', [
                        'conversation_topic' => $conversationTopic
                    ]);
                }
            }
            
            $debugLogs['process_steps'][] = 'Enhanced topic extraction result: ' . json_encode($enhancedTopicInfo);
            
            if (empty($description)) {
                $description = $question; // Use the full question if extraction failed
            }
            
            // Default values
            $itemCount = 8;
            $includeExternal = true;
            $externalCount = 3;
            
            // Try to extract specific instructions from the user's message
            if (preg_match('/(\d+)\s*(items|resources|articles|videos)/i', $question, $matches)) {
                $requestedCount = (int)$matches[1];
                if ($requestedCount > 0 && $requestedCount <= 20) { // Limit to 20 items max for performance
                    $itemCount = $requestedCount;
                    $debugLogs['process_steps'][] = "User requested $itemCount items";
                }
            }
            
            // Check if user wants only internal or external content
            if (preg_match('/(only|just)\s+(internal|our|your)/i', $question)) {
                $includeExternal = false;
                $debugLogs['process_steps'][] = 'User requested only internal content';
                \Log::info('[Cogni] User requested only internal content');
            } elseif (preg_match('/(find|search|look up|get).*online|from the web/i', $question)) {
                $externalCount = min(10, $itemCount); // Allow more external results if specifically requested
                $debugLogs['process_steps'][] = 'User requested web search for content';
                \Log::info('[Cogni] User requested web search for content');
            }
            
            // Content moderation check with more detailed error messages
            try {
                $contentModerationService = app(\App\Services\ContentModerationService::class);
                $moderationResult = $contentModerationService->analyzeText($description);
                
                if (!$moderationResult['isAllowed']) {
                    \Log::warning('[Cogni] Content moderation blocked request', [
                        'description' => $description,
                        'moderation_result' => $moderationResult
                    ]);
                    $errorMessage = 'Your request contains content that cannot be processed. ';
                    $errorMessage .= $moderationResult['reason'] ?? 'Please modify your request and try again.';
                    
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'conversation_id' => $conversationId,
                        'error_details' => [
                            'error_type' => 'ContentModeration',
                            'reason' => $moderationResult['reason'] ?? 'Content not allowed',
                            'flagged_terms' => $moderationResult['flagged_terms'] ?? []
                        ]
                    ], 400);
                }
            } catch (\Exception $e) {
                \Log::error('[Cogni] Content moderation service error', [
                    'description' => $description,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $debugLogs['warnings'][] = 'Content moderation check failed: ' . $e->getMessage();
            }
            
            // Search for relevant internal content using enhanced keywords
            $searchKeywords = $enhancedTopicInfo['search_keywords'] ?? [$description];
            
            // Add the main topic as a search keyword if not already present
            if (!in_array(strtolower($description), array_map('strtolower', $searchKeywords))) {
                array_unshift($searchKeywords, $description);
            }
            
            $debugLogs['search_keywords'] = $searchKeywords;
            \Log::info('[Cogni] Search keywords for content', [
                'search_keywords' => $searchKeywords
            ]);
            
            // Find relevant internal content
            $internalContent = $this->findRelevantContentWithKeywords($description, $searchKeywords, $debugLogs);
            \Log::info('[Cogni] Internal content search results', [
                'count' => count($internalContent),
                'items' => array_slice($internalContent, 0, 5) // log only first 5 for brevity
            ]);
            
            // Log the internal content found for debugging
            $debugLogs['internal_content_found'] = array_map(function($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'type' => $item['type'] ?? null,
                    'title' => $item['title'] ?? 'No title',
                    'relevance_score' => $item['relevance_score'] ?? 0
                ];
            }, $internalContent);
            
            // Check if we have enough internal content before proceeding
            $minRequiredContent = 3; // Minimum number of internal items needed
            $hasEnoughContent = count($internalContent) >= $minRequiredContent;
            
            // If we don't have enough content, try to find more with broader search
            if (!$hasEnoughContent) {
                $debugLogs['process_steps'][] = "Initial search found " . count($internalContent) . " items, trying broader search";
                
                // Try with just the main topic if we were using multiple keywords
                if (count($searchKeywords) > 1) {
                    $broaderResults = $this->findRelevantContentWithKeywords($description, [$description], $debugLogs, true);
                    
                    // Merge results, removing duplicates
                    $internalContent = $this->mergeContentResults($internalContent, $broaderResults);
                    $debugLogs['broader_search_results'] = count($broaderResults);
                    $debugLogs['merged_results_count'] = count($internalContent);
                }
                
                $hasEnoughContent = count($internalContent) >= $minRequiredContent;
            }
            
            if (!$hasEnoughContent) {
                // Not enough internal content, try to get some from the web
                $debugLogs['process_steps'][] = "Not enough internal content (found " . count($internalContent) . ", need " . $minRequiredContent . "), searching web";
                \Log::info("[Cogni] Not enough internal content, searching web", [
                    'found_content_count' => count($internalContent),
                    'enhanced_topic_info' => $enhancedTopicInfo
                ]);
                
                // Get web content using enhanced search with better query construction
                $searchService = app(\App\Services\GPTSearchService::class);
                
                // Construct a more effective search query
                $searchQuery = $this->constructSearchQuery($description, $searchKeywords, $enhancedTopicInfo);
                
                \Log::info("[Cogni] Attempting web search for readlist content", [
                    'original_query' => $description,
                    'enhanced_query' => $searchQuery,
                    'enhanced_keywords' => $searchKeywords,
                    'gpt_configured' => $searchService->isConfigured(),
                    'topic_info' => $enhancedTopicInfo
                ]);
                
                // Determine content types and domains based on query context
                $contentConfig = $this->getContentTypeConfiguration($description, $enhancedTopicInfo);
                
                // Use our enhanced search query construction
                $enhancedSearchQuery = $searchQuery; // From our earlier construction
                
                // Log the search configuration
                $debugLogs['search_configuration'] = [
                    'query' => $enhancedSearchQuery,
                    'content_types' => $contentConfig['content_types'],
                    'include_domains' => $contentConfig['include_domains'],
                    'exclude_domains' => $contentConfig['exclude_domains'],
                    'is_educational' => $contentConfig['is_educational']
                ];
                
                // Add educational context if relevant
                if ($contentConfig['is_educational']) {
                    $enhancedSearchQuery .= ' ' . implode(' ', $contentConfig['educational_terms']);
                }
                
                // Build search parameters with enhanced configuration
                $searchParams = [
                    'num' => $externalCount,
                    'safe' => 'active',
                    'lr' => 'lang_en',
                    'cr' => 'US',
                    'gl' => 'us',
                    'hl' => 'en',
                    'tbs' => 'qdr:y' // Limit to past year by default
                ];
                
                // Add content type specific parameters
                $contentTypes = $contentConfig['content_types'];
                $searchQuery = $enhancedSearchQuery;
                
                // Add content type filters to query
                $typeFilters = array_map(function($type) {
                    return "intitle:$type OR inurl:$type";
                }, $contentTypes);
                
                if (!empty($typeFilters)) {
                    $searchQuery .= " (" . implode(' OR ', $typeFilters) . ")";
                }
                
                // Perform the search
                $webSearchResults = $searchService->findLearningArticlesAndVideos(
                    $searchQuery,
                    $externalCount,
                    '', // level
                    $contentTypes,
                    $contentConfig['include_domains'],
                    $contentConfig['exclude_domains']
                );
                
                // If search failed or returned no results, try a more generic approach
                if (!$webSearchResults['success'] || empty($webSearchResults['results'])) {
                    $debugLogs['fallback_search'] = 'Initial search returned no results, trying broader search';
                    
                    // Try a more generic search with fewer restrictions
                    $fallbackQuery = $description . ' ' . implode(' ', array_slice($contentConfig['educational_terms'] ?? [], 0, 2));
                    
                    $webSearchResults = $searchService->findLearningArticlesAndVideos(
                        $fallbackQuery,
                        $externalCount,
                        '', // level
                        ['article', 'video'], // Basic content types
                        [], // No domain restrictions
                        $contentConfig['exclude_domains'] // Still exclude spam
                    );
                    
                    $debugLogs['fallback_query'] = $fallbackQuery;
                }
                
                // Log search results for debugging
                \Log::info("Web search results", [
                    'success' => $webSearchResults['success'] ?? false,
                    'result_count' => count($webSearchResults['results'] ?? []),
                    'error' => $webSearchResults['message'] ?? 'No error message',
                    'enhanced_query' => $enhancedSearchQuery
                ]);
                
                // Add web search results to debug logs
                $debugLogs['web_search'] = [
                    'success' => $webSearchResults['success'] ?? false,
                    'result_count' => count($webSearchResults['results'] ?? []),
                    'error' => $webSearchResults['message'] ?? 'No error message',
                    'search_type' => $webSearchResults['search_type'] ?? 'unknown',
                    'used_fallback' => $webSearchResults['used_fallback'] ?? false,
                    'enhanced_query' => $enhancedSearchQuery,
                    'results' => $webSearchResults['results'] ?? []
                ];
                
                if ($webSearchResults['success'] && !empty($webSearchResults['results'])) {
                    // Process web search results with enhanced metadata and relevance scoring
                    $webItems = $this->processWebSearchResultsToItems(
                        $webSearchResults, 
                        $description,
                        $enhancedTopicInfo
                    );
                    
                    // Log the processed web items for debugging
                    $debugLogs['processed_web_items'] = array_map(function($item) {
                        return [
                            'title' => $item['title'] ?? 'No title',
                            'url' => $item['url'] ?? null,
                            'type' => $item['type'] ?? 'unknown',
                            'source' => $item['source'] ?? 'unknown',
                            'relevance' => $item['relevance_score'] ?? 0
                        ];
                    }, $webItems);
                    
                    // Combine internal and external content with prioritization
                    $allItems = [];
                    $usedUrls = [];
                    
                    // Function to add item if not a duplicate
                    $addItem = function($item) use (&$allItems, &$usedUrls) {
                        $url = $item['url'] ?? '';
                        $urlKey = md5(strtolower(trim($url)));
                        
                        // Skip duplicates
                        if (isset($usedUrls[$urlKey]) || empty($url)) {
                            return false;
                        }
                        
                        // Add default values if missing
                        $item['type'] = $item['type'] ?? 'article';
                        $item['title'] = $item['title'] ?? 'Untitled';
                        $item['description'] = $item['description'] ?? '';
                        $item['relevance_score'] = $item['relevance_score'] ?? 0;
                        
                        // Add to results and mark URL as used
                        $allItems[] = $item;
                        $usedUrls[$urlKey] = true;
                        return true;
                    };
                    
                    // First add high-relevance internal content
                    foreach ($internalContent as $item) {
                        $addItem([
                            'id' => $item['id'] ?? null,
                            'type' => $item['type'] ?? 'internal',
                            'title' => $item['title'] ?? 'Untitled',
                            'description' => $item['description'] ?? '',
                            'url' => $item['url'] ?? null,
                            'source' => 'internal',
                            'relevance_score' => $item['relevance_score'] ?? 0.9, // High priority for internal content
                            'notes' => 'Internal content: ' . ($item['title'] ?? 'Untitled')
                        ]);
                    }
                    
                    // Add web items with deduplication
                    foreach ($webItems as $item) {
                        $addItem($item);
                    }
                    
                    // Sort all items by relevance score (highest first)
                    usort($allItems, function($a, $b) {
                        return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
                    });
                    
                    // Limit to the requested number of items
                    $allItems = array_slice($allItems, 0, $itemCount);
                    
                    // Log final item selection
                    $debugLogs['final_items'] = array_map(function($item) {
                        return [
                            'title' => $item['title'] ?? 'No title',
                            'type' => $item['type'] ?? 'unknown',
                            'source' => $item['source'] ?? 'unknown',
                            'relevance' => $item['relevance_score'] ?? 0
                        ];
                    }, $allItems);
                    
                    // Generate intelligent title and description
                    $readlistTitle = $this->generateIntelligentTitle($enhancedTopicInfo, $allItems);
                    $readlistDescription = $this->topicExtractionService->generateIntelligentDescription(
                        $description, 
                        $allItems, 
                        $userInterests
                    );
                    
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
                        
                        // EARLY RETURN: respond immediately after readlist creation and job dispatch
                        return response()->json([
                            'success' => true,
                            'answer' => $response,
                            'conversation_id' => $conversationId,
                            'readlist' => $readlist->load('items'),
                            'enhanced_topic_info' => $enhancedTopicInfo
                        ]);
                    }
                }
            }
            
            // Generate readlist with available content (internal + potentially external)
            $readlistTitle = $this->generateIntelligentTitle($enhancedTopicInfo, $internalContent);
            $readlistDescription = $this->topicExtractionService->generateIntelligentDescription(
                $description, 
                $internalContent, 
                $userInterests
            );
            
            $readlistData = [
                'title' => $readlistTitle,
                'description' => $readlistDescription,
                'items' => $internalContent
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
                
                // EARLY RETURN: respond immediately after readlist creation and job dispatch
                return response()->json([
                    'success' => true,
                    'answer' => $response,
                    'conversation_id' => $conversationId,
                    'readlist' => $readlist->load('items'),
                    'item_count' => $totalCount,
                    'enhanced_topic_info' => $enhancedTopicInfo
                ]);
            }
            
            // If createReadlistInDatabase returned null, handle it gracefully
            \Log::warning('createReadlistInDatabase returned null - readlist creation failed', [
                'title' => $readlistData['title'] ?? 'Unknown title',
                'description' => substr($readlistData['description'] ?? 'No description', 0, 100),
                'user_id' => $user->id,
                'conversation_id' => $conversationId
            ]);
            
            $errorMsg = "I'm sorry, I encountered an error while trying to create your readlist. Please try again later.";
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            return response()->json([
                'success' => false,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId,
                'error_details' => [
                    'error_id' => uniqid('readlist_error_'),
                    'error_type' => 'DatabaseError',
                    'error_message' => 'Failed to create readlist in database',
                    'error_location' => 'createReadlistInDatabase returned null',
                    'description' => $description ?? 'No description extracted'
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->handleReadlistError($e, $description, $question, $user, $conversationId);
        }
    }

    /**
     * Handle readlist generation error
     */
    private function handleReadlistError(\Exception $e, $description, $question, $user, $conversationId)
    {
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
    
    /**
     * Process web search results into readlist items with enhanced scoring and metadata
     * 
     * @param array $webSearchResults The search results from GPT/fallback search
     * @param string $description The description/query used for the search
     * @param array $topicInfo Enhanced topic information for better scoring
     * @return array Array of web items formatted for readlist with relevance scores
     */
    /**
     * Determine content type from search result
     */
    private function determineContentTypeFromResult(array $result): string
    {
        // Check URL for common patterns
        $url = strtolower($result['url'] ?? '');
        
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'video';
        }
        
        if (strpos($url, '.pdf') !== false) {
            return 'document';
        }
        
        // Check type field
        $type = strtolower($result['type'] ?? 'article');
        
        // Map to our standard types
        $typeMap = [
            'video' => 'video',
            'article' => 'article',
            'tutorial' => 'tutorial',
            'course' => 'course',
            'document' => 'document',
            'pdf' => 'document',
            'blog' => 'article',
            'guide' => 'tutorial',
            'howto' => 'tutorial'
        ];
        
        return $typeMap[$type] ?? 'article';
    }
    
    /**
     * Determine source from result
     */
    private function determineSource(array $result): string
    {
        $url = $result['url'] ?? '';
        $source = $result['source'] ?? '';
        
        if (empty($source) && !empty($url)) {
            $host = parse_url($url, PHP_URL_HOST);
            $source = $host ? preg_replace('/^www\./', '', $host) : 'web';
        }
        
        return $source ?: 'web';
    }
    
    /**
     * Calculate relevance score for a search result item
     */
    private function calculateRelevanceScore(array &$item, array $queryTerms, array $topicInfo): void
    {
        $score = 0.5; // Base score
        
        // 1. Title match (most important)
        $title = strtolower($item['title']);
        $titleMatches = 0;
        
        foreach ($queryTerms as $term) {
            if (strlen($term) < 3) continue; // Skip very short terms
            if (strpos($title, strtolower($term)) !== false) {
                $titleMatches++;
                $item['matches'][] = "title_contains_$term";
                $score += 0.1; // Bonus for each matching term in title
            }
        }
        
        // 2. Description match
        $description = strtolower($item['description']);
        $descriptionMatches = 0;
        
        foreach ($queryTerms as $term) {
            if (strlen($term) < 3) continue; // Skip very short terms
            if (strpos($description, strtolower($term)) !== false) {
                $descriptionMatches++;
                $item['matches'][] = "description_contains_$term";
                $score += 0.05; // Smaller bonus for description matches
            }
        }
        
        // 3. Content type bonus
        $contentType = $item['type'] ?? '';
        if (in_array($contentType, ['tutorial', 'guide', 'course', 'documentation'])) {
            $score += 0.15;
            $item['matches'][] = 'high_quality_content_type';
        }
        
        // 4. Source quality
        $source = strtolower($item['source']);
        $qualitySources = ['edu', 'gov', 'wikipedia', 'medium', 'dev.to', 'freecodecamp'];
        foreach ($qualitySources as $qualitySource) {
            if (strpos($source, $qualitySource) !== false) {
                $score += 0.1;
                $item['matches'][] = 'quality_source';
                break;
            }
        }
        
        // 5. Recency (if we have publication date)
        if (!empty($item['published_at'])) {
            try {
                $publishedAt = is_numeric($item['published_at']) 
                    ? \Carbon\Carbon::createFromTimestamp($item['published_at'])
                    : \Carbon\Carbon::parse($item['published_at']);
                
                $daysOld = $publishedAt->diffInDays(now());
                
                if ($daysOld < 30) {
                    $score += 0.15; // Very recent
                    $item['matches'][] = 'recent_content';
                } elseif ($daysOld < 365) {
                    $score += 0.05; // Less than a year old
                }
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }
        
        // 6. Content length (longer is generally better for articles)
        $contentLength = strlen($item['description']);
        if ($contentLength > 500) {
            $score += 0.1;
            $item['matches'][] = 'detailed_content';
        } elseif ($contentLength > 200) {
            $score += 0.05;
        }
        
        // 7. Topic relevance from enhanced topic info
        if (!empty($topicInfo['primary_topic'])) {
            $primaryTopic = strtolower($topicInfo['primary_topic']);
            if (strpos($title, $primaryTopic) !== false || strpos($description, $primaryTopic) !== false) {
                $score += 0.1;
                $item['matches'][] = 'matches_primary_topic';
            }
        }
        
        // Ensure score is between 0 and 1
        $item['relevance_score'] = min(1.0, max(0.1, $score));
        $item['quality_score'] = $this->calculateQualityScore($item);
    }
    
    /**
     * Calculate a quality score based on various signals
     */
    private function calculateQualityScore(array $item): float
    {
        $score = 0.5; // Base score
        
        // Positive signals
        if (in_array('quality_source', $item['matches'] ?? [])) $score += 0.2;
        if (in_array('detailed_content', $item['matches'] ?? [])) $score += 0.1;
        if (in_array('recent_content', $item['matches'] ?? [])) $score += 0.1;
        if (in_array('high_quality_content_type', $item['matches'] ?? [])) $score += 0.1;
        
        // Negative signals
        if (strpos(strtolower($item['title'] ?? ''), 'sponsor') !== false) $score -= 0.2;
        if (strpos(strtolower($item['description'] ?? ''), 'advertisement') !== false) $score -= 0.1;
        
        return min(1.0, max(0.1, $score));
    }
    
    /**
     * Clean text by removing extra whitespace and special characters
     */
    private function cleanText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim and return
        return trim($text);
    }
    
    /**
     * Generate notes for a readlist item
     */
    private function generateItemNotes(array $item, array $originalResult): string
    {
        $notes = [];
        
        // Add source information
        if (!empty($item['source'])) {
            $notes[] = 'Source: ' . ucfirst($item['source']);
        }
        
        // Add content type if not article
        if (($item['type'] ?? '') !== 'article') {
            $notes[] = 'Type: ' . ucfirst($item['type']);
        }
        
        // Add date if available
        if (!empty($item['published_at'])) {
            try {
                $date = is_numeric($item['published_at']) 
                    ? \Carbon\Carbon::createFromTimestamp($item['published_at'])->toFormattedDateString()
                    : \Carbon\Carbon::parse($item['published_at'])->toFormattedDateString();
                $notes[] = 'Published: ' . $date;
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }
        
        // Add quality indicator
        if (($item['quality_score'] ?? 0) > 0.7) {
            $notes[] = 'High Quality';
        }
        
        return implode(' • ', $notes);
    }
    
    private function processWebSearchResultsToItems($webSearchResults, $description, array $topicInfo = [])
    {
        $webItems = [];
        $queryTerms = array_merge(
            explode(' ', strtolower($description)),
            $topicInfo['search_keywords'] ?? [],
            $topicInfo['related_concepts'] ?? []
        );
        
        if (!empty($webSearchResults['results'])) {
            foreach ($webSearchResults['results'] as $result) {
                // Skip if we don't have required fields
                if (empty($result['url']) || empty($result['title'])) {
                    continue;
                }
                
                // Initialize item with basic information
                $item = [
                    'type' => $this->determineContentTypeFromResult($result),
                    'title' => $this->cleanText($result['title']),
                    'description' => $this->cleanText($result['text'] ?? $result['summary'] ?? $result['content'] ?? ''),
                    'url' => $result['url'],
                    'source' => $this->determineSource($result),
                    'published_at' => $result['published_date'] ?? $result['date'] ?? null,
                    'relevance_score' => 0.5, // Base score
                    'quality_score' => 0.5,   // Base quality score
                    'matches' => []           // Track what matched for debugging
                ];
                
                // Calculate relevance score based on various factors
                $this->calculateRelevanceScore($item, $queryTerms, $topicInfo);
                
                // Add additional metadata
                $item['notes'] = $this->generateItemNotes($item, $result);
                
                $webItems[] = $item;
            }
        }
        
        // Sort by relevance score (highest first)
        usort($webItems, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        // Log the processing results
        \Log::info("Processed web search results into readlist items", [
            'items_count' => count($webItems),
            'results_count' => count($webSearchResults['results'] ?? []),
            'search_type' => $webSearchResults['search_type'] ?? 'unknown',
            'used_fallback' => $webSearchResults['used_fallback'] ?? false,
            'top_items' => array_slice($webItems, 0, 3) // Log top 3 items for debugging
        ]);
        
        return $webItems;
    }

    /**
     * Find relevant content using enhanced keywords
     * 
     * @param string $description The main description
     * @param array $keywords Enhanced search keywords
     * @param array $debugLogs Debug logs array
     * @return array Found content
     */
    private function findRelevantContentWithKeywords(string $description, array $keywords, array &$debugLogs = null)
    {
        $internalContent = [];
        
        // Search for relevant courses using enhanced keywords
        $courseQuery = \App\Models\Course::query();
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 3) {
                $courseQuery->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }
        }
        
        $courses = $courseQuery->limit(20)->get(['id', 'title', 'description', 'user_id', 'created_at']);
        
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
        
        // Search for relevant posts using enhanced keywords
        $postQuery = \App\Models\Post::query();
        foreach ($keywords as $keyword) {
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
        
        if ($debugLogs !== null) {
            $debugLogs['internal_content'] = [
                'courses_found' => count($courses),
                'posts_found' => count($posts),
                'total_items' => count($internalContent),
                'keywords_used' => $keywords
            ];
        }
        
        return $internalContent;
    }
    
    /**
     * Generate intelligent title for readlist
     * 
     * @param array $enhancedTopicInfo Enhanced topic information
     * @param array $contentItems Content items
     * @return string Generated title
     */
    private function generateIntelligentTitle(array $enhancedTopicInfo, array $contentItems): string
    {
        $primaryTopic = $enhancedTopicInfo['primary_topic'] ?? 'Learning Resources';
        $category = $enhancedTopicInfo['category'] ?? 'other';
        $learningLevel = $enhancedTopicInfo['learning_level'] ?? 'beginner';
        
        // Create contextual title based on category and level
        $prefix = '';
        switch ($category) {
            case 'technology':
                $prefix = 'Tech Learning: ';
                break;
            case 'science':
                $prefix = 'Science & Discovery: ';
                break;
            case 'art':
                $prefix = 'Creative Arts: ';
                break;
            case 'business':
                $prefix = 'Business & Strategy: ';
                break;
            case 'health':
                $prefix = 'Health & Wellness: ';
                break;
            case 'history':
                $prefix = 'History & Culture: ';
                break;
            default:
                $prefix = 'Learning Journey: ';
        }
        
        // Add level indicator for advanced topics
        if ($learningLevel === 'advanced') {
            $prefix = 'Advanced ' . $prefix;
        } elseif ($learningLevel === 'intermediate') {
            $prefix = 'Intermediate ' . $prefix;
        }
        
        return $prefix . ucfirst($primaryTopic);
    }
    
    /**
     * Get user conversation history for context
     * 
     * @param \App\Models\User $user The user
     * @param string $conversationId The conversation ID
     * @return array Conversation history
     */
    private function getUserConversationHistory(\App\Models\User $user, string $conversationId): array
    {
        try {
            // Get recent conversation messages for context
            $messages = \App\Models\CogniConversation::getConversationHistory($conversationId);
            
            if ($messages->isNotEmpty()) {
                return $messages->map(function($message) {
                    return [
                        'content' => $message->message,
                        'role' => $message->role ?? 'user',
                        'created_at' => $message->created_at
                    ];
                })->toArray();
            }
            
            return [];
        } catch (\Exception $e) {
            \Log::warning('Failed to get user conversation history', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
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
                        // Log the URL being validated
                        \Log::debug('Validating external item URL', [
                            'url' => $item['url'],
                            'url_length' => strlen($item['url']),
                            'item_title' => $item['title'] ?? 'No title'
                        ]);
                        
                        // More lenient URL validation - check if it looks like a URL
                        $isValidUrl = filter_var($item['url'], FILTER_VALIDATE_URL);
                        
                        // If strict validation fails, try a more lenient approach
                        if (!$isValidUrl) {
                            // Check if it starts with http:// or https://
                            $isValidUrl = preg_match('/^https?:\/\/.+/', $item['url']);
                            \Log::debug('URL failed strict validation, trying lenient check', [
                                'url' => $item['url'],
                                'lenient_check_passed' => $isValidUrl
                            ]);
                        }
                        
                        // If still not valid, try even more lenient validation
                        if (!$isValidUrl) {
                            // Accept URLs that look like they could be valid
                            $isValidUrl = preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/', $item['url']) || 
                                         preg_match('/^https?:\/\/[^\s]+/', $item['url']) ||
                                         strpos($item['url'], 'http') === 0;
                            \Log::debug('URL failed lenient validation, trying very lenient check', [
                                'url' => $item['url'],
                                'very_lenient_check_passed' => $isValidUrl
                            ]);
                        }
                        
                        if ($isValidUrl) {
                            $validItemCount++;
                            $externalItems[] = $item;
                            $itemValidity['is_valid'] = true;
                            $itemValidity['reason'] = 'Valid external item with URL';
                            \Log::debug('External item validation passed', [
                                'url' => $item['url'],
                                'title' => $item['title'] ?? 'No title'
                            ]);
                        } else {
                            $itemValidity['reason'] = 'Invalid URL format: ' . ($item['url'] ?? 'empty');
                            $invalidItems[] = [
                                'item' => $item,
                                'reason' => 'Invalid URL format'
                            ];
                            \Log::warning('External item URL validation failed', [
                                'url' => $item['url'],
                                'title' => $item['title'] ?? 'No title',
                                'strict_validation' => filter_var($item['url'], FILTER_VALIDATE_URL),
                                'lenient_validation' => preg_match('/^https?:\/\/.+/', $item['url']),
                                'very_lenient_validation' => preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/', $item['url'])
                            ]);
                        }
                    } else {
                        $itemValidity['reason'] = 'External item missing URL';
                        $invalidItems[] = [
                            'item' => $item,
                            'reason' => 'Missing URL'
                        ];
                        \Log::warning('External item missing URL', [
                            'item' => $item
                        ]);
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
            
            // If no valid items were found, try to generate fallback results
            if ($validItemCount === 0) {
                $description = $readlistData['description'] ?? '';
                $title = $readlistData['title'] ?? '';
                $searchQuery = !empty($description) ? $description : $title;
                
                if (!empty($searchQuery)) {
                    \Log::info('No valid items found - attempting to generate fallback content', [
                        'title' => $title,
                        'description' => substr($description, 0, 100),
                        'search_query' => $searchQuery
                    ]);
                    
                    // Use the new article and video focused search for fallback
                    $searchService = app(\App\Services\GPTSearchService::class);
                    $fallbackResults = $searchService->findLearningArticlesAndVideos($searchQuery, 5, '', ['article', 'video']);
                    
                    if ($fallbackResults['success'] && !empty($fallbackResults['results'])) {
                        // Add each item as an external item
                        foreach ($fallbackResults['results'] as $result) {
                            if (!empty($result['url']) && !empty($result['title'])) {
                                $validItemCount++;
                                $externalItems[] = [
                                    'title' => $result['title'],
                                    'description' => $result['content'] ?? $result['summary'] ?? 'Resource about ' . $searchQuery,
                                    'url' => $result['url'],
                                    'type' => 'external',
                                    'notes' => 'From curated source: ' . ($result['domain'] ?? 'educational resource')
                                ];
                            }
                        }
                        
                        \Log::info('Added fallback items for readlist using article/video search', [
                            'added_items' => $validItemCount,
                            'search_query' => $searchQuery,
                            'search_type' => 'article_video_focused'
                        ]);
                    } else {
                        // If article/video search fails, try generic fallback
                        $fallbackResults = $searchService->generateFallbackResults($searchQuery);
                        
                        // Add each item as an external item
                        foreach ($fallbackResults as $result) {
                            if (!empty($result['url']) && !empty($result['title'])) {
                                $validItemCount++;
                                $externalItems[] = [
                                    'title' => $result['title'],
                                    'description' => $result['content'] ?? $result['summary'] ?? 'Resource about ' . $searchQuery,
                                    'url' => $result['url'],
                                    'type' => 'external',
                                    'notes' => 'From curated source: ' . ($result['domain'] ?? 'educational resource')
                                ];
                            }
                        }
                        
                        \Log::info('Added fallback items for readlist using generic fallback', [
                            'added_items' => $validItemCount,
                            'search_query' => $searchQuery,
                            'search_type' => 'generic_fallback'
                        ]);
                    }
                }
                
                // If still no valid items, create a minimal readlist with just the title and description
                if ($validItemCount === 0) {
                    \Log::warning('No valid items for readlist creation - creating minimal readlist', [
                        'title' => $readlistData['title'] ?? 'Unknown title',
                        'description' => substr($readlistData['description'] ?? 'No description', 0, 100),
                        'item_count' => count($readlistData['items']),
                        'user_id' => $user->id,
                        'invalid_items_sample' => array_slice($invalidItems, 0, 5)
                    ]);
                    
                    // Create a minimal readlist with at least one placeholder item
                    $externalItems[] = [
                        'title' => 'Learning Resources',
                        'description' => 'Explore resources about ' . $searchQuery,
                        'url' => 'https://www.google.com/search?q=' . urlencode($searchQuery),
                        'type' => 'external',
                        'notes' => 'Search for more resources about this topic'
                    ];
                    $validItemCount = 1;
                }
            }
            
            // Add debug information about the items processing
            // $debugLogs['items_processing'] = [
            //     'external_items_count' => count($externalItems),
            //     'internal_items_count' => count($internalItems),
            //     'external_items_sample' => array_slice($externalItems, 0, 2),
            //     'internal_items_sample' => array_slice($internalItems, 0, 2),
            //     'total_items_to_process' => count($externalItems) + count($internalItems)
            // ];
            
            // Create the readlist in the database
            \DB::beginTransaction();
            
            \Log::info('Starting database transaction for readlist creation', [
                'title' => $readlistData['title'],
                'valid_items_count' => $validItemCount,
                'external_items_count' => count($externalItems),
                'internal_items_count' => count($internalItems)
            ]);
            
            $readlist = new \App\Models\Readlist([
                'user_id' => $user->id,
                'title' => $readlistData['title'],
                'description' => $readlistData['description'],
                'is_public' => true,
            ]);
            
            $readlist->save();
            
            \Log::info('Readlist created successfully', [
                'readlist_id' => $readlist->id,
                'title' => $readlist->title
            ]);
            
            // Add items to the readlist
            $order = 1;
            $addedItems = 0;
            $failedItems = [];
            
            \Log::info('Starting to add items to readlist', [
                'readlist_id' => $readlist->id,
                'external_items_to_add' => count($externalItems),
                'internal_items_to_add' => count($internalItems),
                'external_items_sample' => array_slice($externalItems, 0, 2), // Log first 2 external items
                'internal_items_sample' => array_slice($internalItems, 0, 2)  // Log first 2 internal items
            ]);
            
            // Add all external items
            \Log::info('Starting external items processing', [
                'readlist_id' => $readlist->id,
                'external_items_count' => count($externalItems),
                'external_items_empty' => empty($externalItems)
            ]);
            
            foreach ($externalItems as $index => $item) {
                \Log::info('Processing external item', [
                    'readlist_id' => $readlist->id,
                    'item_index' => $index,
                    'item_title' => $item['title'] ?? 'No title',
                    'item_url' => $item['url'] ?? 'No URL',
                    'item_type' => $item['type'] ?? 'No type'
                ]);
                
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
                    
                    \Log::info('Successfully added external item to readlist', [
                        'readlist_id' => $readlist->id,
                        'item_id' => $readlistItem->id,
                        'title' => $readlistItem->title,
                        'url' => $readlistItem->url,
                        'order' => $readlistItem->order,
                        'total_added_so_far' => $addedItems
                    ]);
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
                
                // Return error response with debugging information
                throw new \Exception('Failed to save any items to readlist. External items: ' . count($externalItems) . ', Internal items: ' . count($internalItems) . ', Failed items: ' . json_encode($failedItems));
            }
            
            \DB::commit();
            
            \Log::info('Database transaction committed successfully', [
                'readlist_id' => $readlist->id,
                'total_items_added' => $addedItems,
                'transaction_committed' => true
            ]);
            
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
            
            // Add final results to debug info
            // $debugLogs['final_results'] = [
            //     'readlist_id' => $readlist->id,
            //     'items_added' => $addedItems,
            //     'external_items_processed' => count($externalItems),
            //     'internal_items_processed' => count($internalItems),
            //     'failed_items_count' => count($failedItems),
            //     'failed_items' => $failedItems
            // ];
            
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
            
            // Determine content types to prioritize based on search parameters
            $contentTypes = ['article', 'video', 'tutorial'];
            if (strpos($searchParams['category'], 'technical') !== false) {
                $contentTypes[] = 'documentation';
            }
            if (strpos($searchParams['category'], 'research') !== false) {
                $contentTypes[] = 'research';
            }
            if ($searchParams['recent_events']) {
                $contentTypes = ['article', 'video']; // For recent events, focus on articles and videos
            }
            
            // Perform web search with article and video focus
            $searchResults = $searchService->findLearningArticlesAndVideos(
                $searchParams['query'], 
                $searchParams['num_results'], 
                '', // level
                $contentTypes
            );
            
            if (!$searchResults['success'] || empty($searchResults['results'])) {
                // Retry with broader parameters if no results
                $searchResults = $searchService->findLearningArticlesAndVideos(
                    $searchParams['query'], 
                    $searchParams['num_results'], 
                    '', // level
                    ['article', 'video'] // Basic content types
                );
                
                // If still no results, try the original search method as fallback
                if (!$searchResults['success'] || empty($searchResults['results'])) {
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
                }
            }
            
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
            
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
                    return $query->where('title', 'like', "%{$topic}%")
                        ->orWhere('description', 'like', "%{$topic}%");
                })
                ->limit(50)
                ->get(['id', 'title', 'description', 'user_id', 'created_at']);
            
            foreach ($courses as $course) {
                $availableContent[] = [
                    'id' => $course->id,
                    'type' => 'course',
                    'title' => $course->title,
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
                
            }
    catch (\Exception $e) {
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
                $courseQuery->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }
        }
        
        $courses = $courseQuery->limit(30)->get(['id', 'title', 'description', 'user_id', 'created_at']);
        
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
                            }
    catch (\Exception $e) {
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
                
            }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
        }
    catch (\Exception $e) {
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
     * Check if the question is a readlist creation request
     */
    /**
     * Check if the user wants to create a readlist from their message
     * 
     * This method uses multiple strategies to detect readlist creation intent:
     * 1. Direct patterns (e.g., "create a readlist about...")
     * 2. Collection intent (e.g., "save these resources")
     * 3. Request patterns (e.g., "can you make a list of...")
     * 4. Natural language indicators (e.g., "I want to save this for later")
     */
    /**
     * Extract topic from conversation context
     * 
     * Analyzes the conversation history to determine the most relevant topic
     * when the current message is part of an ongoing discussion.
     *
     * @param array $conversationContext Array of previous messages in the conversation
     * @param string $currentMessage The current user message
     * @return string|null The extracted topic or null if none found
     */
    private function extractTopicFromConversation(array $conversationContext, string $currentMessage): ?string
    {
        try {
            // Prepare conversation text for analysis
            $conversationText = '';
            foreach ($conversationContext as $message) {
                $role = $message['role'] ?? 'user';
                $content = $message['content'] ?? '';
                $conversationText .= "$role: $content\n";
            }
            
            // Use the topic extraction service to analyze the conversation
            $topicInfo = $this->topicExtractionService->extractAndEnhanceTopic(
                $currentMessage,
                [], // No user interests needed here
                $conversationText
            );
            
            // If we have a good confidence score, return the topic
            $confidence = $topicInfo['confidence'] ?? 0;
            if (!empty($topicInfo['primary_topic']) && $confidence > 0.6) {
                return $topicInfo['primary_topic'];
            }
            
            // Fallback: Look for named entities or key phrases in the conversation
            $namedEntities = $topicInfo['named_entities'] ?? [];
            if (!empty($namedEntities)) {
                // Return the most frequently mentioned entity
                $entityCounts = array_count_values($namedEntities);
                arsort($entityCounts);
                return array_key_first($entityCounts);
            }
            
            return null;
            
        } catch (\Exception $e) {
            \Log::error('Error extracting topic from conversation: ' . $e->getMessage(), [
                'exception' => $e,
                'conversation_length' => count($conversationContext)
            ]);
            return null;
        }
    }
    
    /**
     * Check if the question is a readlist creation request
     */
    /**
     * Merge multiple content result arrays while removing duplicates
     * 
     * @param array $contentArrays One or more arrays of content items to merge
     * @return array Merged array of unique content items
     */
    /**
     * Construct an effective search query based on the topic and context
     * 
     * @param string $description The main topic/description
     * @param array $keywords Additional search keywords
     * @param array $topicInfo Enhanced topic information
     * @return string The constructed search query
     */
    private function constructSearchQuery(string $description, array $keywords, array $topicInfo): string
    {
        // Start with the main topic
        $queryParts = [trim($description)];
        
        // Add entity types if available (e.g., person, place, concept)
        if (!empty($topicInfo['entity_type'])) {
            $queryParts[] = $topicInfo['entity_type'];
        }
        
        // Add related concepts if available
        if (!empty($topicInfo['related_concepts']) && is_array($topicInfo['related_concepts'])) {
            // Take up to 2 related concepts to avoid query bloat
            $queryParts = array_merge($queryParts, array_slice($topicInfo['related_concepts'], 0, 2));
        }
        
        // Add any specific content type indicators
        $contentType = $this->determineContentType($description, $topicInfo);
        if ($contentType) {
            $queryParts[] = $contentType;
        }
        
        // Add high-quality keywords, avoiding duplicates
        $uniqueKeywords = array_unique(array_merge(
            array_slice($keywords, 0, 3), // Take top 3 keywords
            $queryParts // Include existing query parts to dedupe against
        ));
        
        // Construct the final query, removing any empty parts
        $query = implode(' ', array_filter($uniqueKeywords));
        
        // Add quality indicators if this is an educational/informational query
        if ($this->isEducationalQuery($description, $topicInfo)) {
            $query .= ' guide tutorial overview introduction';
        }
        
        return trim($query);
    }
    
    /**
     * Determine the most appropriate content type for the query
     */
    private function determineContentType(string $description, array $topicInfo): string
    {
        // Check for specific content type indicators in the topic info
        if (!empty($topicInfo['content_types'])) {
            $types = is_array($topicInfo['content_types']) 
                ? $topicInfo['content_types'] 
                : [$topicInfo['content_types']];
                
            // Prefer more specific content types
            foreach ($types as $type) {
                if (in_array(strtolower($type), ['tutorial', 'guide', 'course', 'documentation'])) {
                    return $type;
                }
            }
            return $types[0];
        }
        
        // Default to article for general topics
        return 'article';
    }
    
    /**
     * Check if this is an educational/informational query
     */
    /**
     * Get content type configuration for web searches
     * 
     * @param string $description The search query/topic
     * @param array $topicInfo Enhanced topic information
     * @return array Configuration for content type filtering
     */
    private function getContentTypeConfiguration(string $description, array $topicInfo): array
    {
        $isEducational = $this->isEducationalQuery($description, $topicInfo);
        
        // Default content types
        $contentTypes = ['article', 'video'];
        $includeDomains = [
            'edu', 'gov', 'org', // General TLDs
            'wikipedia.org', 'britannica.com', // Encyclopedias
            'khanacademy.org', 'coursera.org', 'edx.org', 'udemy.com', // Learning platforms
            'youtube.com', 'ted.com', // Video content
            'medium.com', 'dev.to', 'freecodecamp.org', // Developer/tech content
            'openculture.com' // Open educational resources
        ];
        
        // Common spam or low-quality domains to exclude
        $excludeDomains = [
            'pinterest.com', 'quora.com', 'reddit.com',
            'twitter.com', 'facebook.com', 'instagram.com',
            'tiktok.com', 'youtube.com/shorts', 't.co'
        ];
        
        // Adjust for educational queries
        $educationalTerms = [];
        if ($isEducational) {
            $contentTypes = array_merge($contentTypes, ['tutorial', 'guide', 'course', 'documentation']);
            $educationalTerms = ['guide', 'tutorial', 'learn', 'introduction', 'overview'];
            
            // Add more educational domains
            $includeDomains = array_merge($includeDomains, [
                'academic.oup.com', 'jstor.org', 'springer.com',
                'sciencedirect.com', 'ieeexplore.ieee.org', 'arxiv.org'
            ]);
        }
        
        // Add topic-specific domains if available
        if (!empty($topicInfo['recommended_domains']) && is_array($topicInfo['recommended_domains'])) {
            $includeDomains = array_merge($includeDomains, $topicInfo['recommended_domains']);
        }
        
        // Remove any duplicates
        $includeDomains = array_unique($includeDomains);
        $contentTypes = array_unique($contentTypes);
        
        return [
            'content_types' => $contentTypes,
            'include_domains' => $includeDomains,
            'exclude_domains' => $excludeDomains,
            'is_educational' => $isEducational,
            'educational_terms' => $educationalTerms
        ];
    }
    
    /**
     * Check if this is an educational/informational query
     */
    private function isEducationalQuery(string $description, array $topicInfo): bool
    {
        // Check if the topic is explicitly educational
        if (!empty($topicInfo['is_educational'])) {
            return true;
        }
        
        // Common educational query patterns
        $patterns = [
            '/how to/i',
            '/what is/i',
            '/guide to/i',
            '/tutorial/i',
            '/learn/i',
            '/introduction to/i',
            '/overview of/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Merge multiple content result arrays while removing duplicates
     */
    private function mergeContentResults(array ...$contentArrays): array
    {
        $merged = [];
        $seenIds = [];
        
        foreach ($contentArrays as $contentArray) {
            if (!is_array($contentArray)) continue;
            
            foreach ($contentArray as $item) {
                // Use ID if available, otherwise create a unique key from title and type
                $itemId = $item['id'] ?? null;
                $itemKey = $itemId ?: md5(($item['title'] ?? '') . '|' . ($item['type'] ?? ''));
                
                // Skip if we've already seen this item
                if (isset($seenIds[$itemKey])) {
                    // Keep the item with the higher relevance score
                    if (($item['relevance_score'] ?? 0) > ($seenIds[$itemKey]['relevance_score'] ?? 0)) {
                        $index = $seenIds[$itemKey]['index'];
                        $merged[$index] = $item;
                    }
                    continue;
                }
                
                // Add to merged results and mark as seen
                $seenIds[$itemKey] = [
                    'index' => count($merged),
                    'relevance_score' => $item['relevance_score'] ?? 0
                ];
                $merged[] = $item;
            }
        }
        
        // Sort by relevance score (highest first)
        usort($merged, function($a, $b) {
            $scoreA = $a['relevance_score'] ?? 0;
            $scoreB = $b['relevance_score'] ?? 0;
            return $scoreB <=> $scoreA; // Descending order
        });
        
        return $merged;
    }
    
    /**
     * Check if the question is a readlist creation request
     */
    private function isReadlistRequest(string $question): bool
    {
        // Convert to lowercase once for case-insensitive matching
        $lowerQuestion = strtolower(trim($question));
        
        // Direct patterns for readlist creation
        $directPatterns = [
            '/create\s+(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/make\s+(?:me\s+)?(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/build\s+(?:me\s+)?(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/generate\s+(?:me\s+)?(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/curate\s+(?:me\s+)?(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/put\s+together\s+(?:a\s+)?(?:reading\s+)?list(?:\s+of|\s+about|\s+for|\s+on)?/i',
            '/save\s+(?:this|these|that|those)\s+(?:as\s+)?(?:a\s+)?(?:reading\s+)?list/i',
            '/create\s+readlist/i',
        ];

        // Collection/save intent patterns
        $collectionPatterns = [
            '/save\s+(?:this|these|that|those)\s+(?:resources?|links?|articles?|videos?|posts?)/i',
            '/collect\s+(?:these|those|this|that)\s+(?:resources?|links?|articles?|videos?|posts?)/i',
            '/can you save (?:these|those|this|that) (?:resources?|links?|articles?)/i',
            '/i want to save (?:these|those|this|that) (?:resources?|links?|articles?)/i',
            '/add (?:these|those|this|that) to my (?:reading )?list/i',
        ];

        // Request patterns
        $requestPatterns = [
            '/can you make (?:me )?a (?:reading )?list (?:of|about|for|on)/i',
            '/could you create (?:me )?a (?:reading )?list (?:of|about|for|on)/i',
            '/i need (?:a|an) (?:reading )?list (?:of|about|for|on)/i',
            '/suggest (?:me )?(?:a|some) (?:reading )?list (?:of|about|for|on)/i',
        ];

        // Natural language indicators
        $nlPatterns = [
            '/i want to save this (?:for later|to read later)/i',
            '/i\'d like to save this (?:for later|to read later)/i',
            '/save (?:this|that) for (?:later|future reference)/i',
            '/make a collection (?:of|about|for|on)/i',
            '/create a collection (?:of|about|for|on)/i',
            '/i want to create a collection (?:of|about|for|on)/i',
        ];

        // Check direct patterns
        foreach ($directPatterns as $pattern) {
            if (preg_match($pattern, $lowerQuestion)) {
                return true;
            }
        }

        // If message is short, check for collection/request patterns
        if (str_word_count($lowerQuestion) < 15) {
            $allPatterns = array_merge($collectionPatterns, $requestPatterns, $nlPatterns);
            foreach ($allPatterns as $pattern) {
                if (preg_match($pattern, $lowerQuestion)) {
                    return true;
                }
            }
        } else {
            // For longer messages, be more strict to avoid false positives
            $strictPatterns = array_merge($collectionPatterns, $requestPatterns);
            foreach ($strictPatterns as $pattern) {
                if (preg_match($pattern, $lowerQuestion)) {
                    return true;
                }
            }
        }

        // Check for explicit readlist keywords in the message
        $readlistKeywords = [
            'readlist', 'reading list', 'save these', 'save this', 'collect these',
            'resource list', 'learning resources', 'study materials', 'reference list'
        ];

        foreach ($readlistKeywords as $keyword) {
            if (stripos($lowerQuestion, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the question is a web search request
     */
    private function isWebSearchRequest(string $question): bool
    {
        $searchPatterns = [
            '/search(\s+for|\s+the|\s+about)?\s+/i',
            '/find(\s+information|\s+about|\s+articles)?\s+/i',
            '/look\s+up/i',
            '/research/i',
            '/what\'s\s+the\s+latest/i',
            '/what\s+is\s+happening/i',
            '/current\s+news/i',
            '/recent\s+developments/i',
            '/latest\s+information/i',
            '/current\s+events/i'
        ];

        foreach ($searchPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze a post (text + media) and return learning resources
     *
     * @param int $postId
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzePost($postId)
    {
        try {
            $post = \App\Models\Post::with('media')->findOrFail($postId);
            $content = $post->title . "\n\n" . $post->body;
            $postContext = $content;
            $topicsPrompt = "Analyze this post and identify the 3-5 main educational topics or concepts it covers. Be specific, academic, and educational in your identification. Return only a comma-separated list of key educational topics with no explanations.";
            $topicsResult = $this->cogniService->askQuestionInternal($topicsPrompt . "\n\nHere's the content:\n" . $content);
            $topics = $topicsResult['success'] ? $topicsResult['answer'] : '';
            $relatedResources = [];
            if (!empty($topics) && $this->exaSearchService->isConfigured()) {
                $searchResult = $this->exaSearchService->findRelatedContent($topics, 5);
                if ($searchResult['success'] && !empty($searchResult['results'])) {
                    $relatedResources = $searchResult['results'];
                }
            }
            $mediaAnalyses = [];
            $hasMediaToAnalyze = $post->media && $post->media->count() > 0;
            if ($hasMediaToAnalyze) {
                foreach ($post->media as $media) {
                    $mediaType = $media->media_type ?? 'file';
                    $mediaUrl = $media->media_link ?? 'Not available';
                    $mediaName = $media->original_filename ?? 'Unnamed';
                    $mediaMimeType = $media->mime_type ?? '';
                    $mediaId = $media->id;
                    $mediaAnalyses[$mediaId] = [
                        'media_id' => $mediaId,
                        'media_type' => $mediaType,
                        'media_name' => $mediaName,
                        'media_url' => $mediaUrl,
                        'analysis' => null
                    ];
                    if (strpos($mediaType, 'image') !== false || strpos($mediaMimeType, 'image/') !== false) {
                        $prompt = "Analyze this image in the context of an educational post. First, describe what's shown in the image clearly. Then, explain its educational significance and how it relates to the post topic. If the image contains any diagrams, charts, or educational elements, explain them in detail. Focus on making your analysis educational and informative.";
                        $imageResult = $this->cogniService->analyzeImage($mediaUrl, $prompt, $postContext);
                        if ($imageResult['success']) {
                            $mediaAnalyses[$mediaId]['analysis'] = $imageResult['answer'];
                        }
                    } else if (strpos($mediaType, 'video') !== false || strpos($mediaMimeType, 'video/') !== false) {
                        $videoPrompt = "Perform a professional, detailed analysis of this video in the context of the post. In a system-like tone, provide: 1. Likely content based on post context and video title/name 2. Educational concepts that may be covered 3. Potential learning outcomes from watching this video 4. How it complements the post's educational value. Format as a concise, factual analysis using professional language. If the video appears to be a demonstration, tutorial, lecture, or educational content, analyze what skills or knowledge viewers would gain.";
                        $videoResult = $this->cogniService->askQuestionInternal($videoPrompt . "\n\nPost context:\n" . $postContext . "\n\nVideo file: " . $mediaName);
                        if ($videoResult['success']) {
                            $mediaAnalyses[$mediaId]['analysis'] = $videoResult['answer'];
                        }
                    }
                }
            }
            $postAnalysisPrompt = "Provide a detailed educational analysis of the following post, considering both the text and any associated media. Summarize the main points, highlight key educational concepts, and suggest what a learner might gain from this post.";
            $postAnalysisResult = $this->cogniService->askQuestionInternal($postAnalysisPrompt . "\n\nHere's the content:\n" . $content);
            $postAnalysis = $postAnalysisResult['success'] ? $postAnalysisResult['answer'] : '';
            return response()->json([
                'post_id' => $post->id,
                'analysis' => $postAnalysis,
                'media_analyses' => array_values($mediaAnalyses),
                'learning_resources' => $relatedResources
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Post not found',
                'message' => 'The specified post could not be found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error in analyzePost endpoint', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'post_id' => $postId
            ]);
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while analyzing the post'
            ], 500);
        }
    }

}
