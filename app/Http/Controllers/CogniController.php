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
        try {
            // Extract the topic from the question
            // Remove common phrases like "create a readlist about" to get just the topic
            $description = preg_replace('/^(cogni,?\s*)?(please\s*)?(create|make|build)(\s+a|\s+me\s+a)?\s+(readlist|reading list)(\s+for\s+me)?(\s+about|\s+on)?\s*/i', '', $question);
            $description = trim($description);
            
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
            $internalContent = $this->findRelevantContent($description);
            
            // Check if we have enough internal content before proceeding
            $minRequiredContent = 3; // Minimum number of internal items needed
            if (count($internalContent) < $minRequiredContent) {
                // Not enough internal content, try to get some from the web
                \Log::info("Not enough internal content for readlist on '{$description}', searching web", [
                    'found_content_count' => count($internalContent)
                ]);
                
                // Get web content using Exa
                $exaService = app(\App\Services\ExaSearchService::class);
                $webSearchResults = $exaService->search($description . " educational resources", 10, true, true);
                
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
                    
                    // If we have literally no internal content, try to generate a readlist structure with just external content
                    if (empty($internalContent)) {
                        $readlistData = [
                            'title' => 'Readlist: ' . ucfirst($description),
                            'description' => 'A collection of resources about ' . $description . ' curated from the web.',
                            'items' => $webItems
                        ];
                        
                        $readlist = $this->createReadlistInDatabase($user, $readlistData);
                        
                        if ($readlist) {
                            $response = "I couldn't find any internal content about " . $description . ", so I've created a readlist with " . 
                                        count($webItems) . " resources from the web.";
                            
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
            }
            
            // Generate readlist with available content (internal + potentially external)
            $result = $this->cogniService->generateReadlistFromDescription(
                $description, 
                $internalContent, 
                $itemCount, 
                $externalCount
            );
            
            if ($result['success'] && isset($result['readlist'])) {
                // Create readlist in database
                $readlist = $this->createReadlistInDatabase($user, $result['readlist']);
                
                if ($readlist) {
                    // Create a user-friendly response
                    $response = "I've created a readlist titled \"" . $readlist->title . "\" for you. " .
                               "It contains " . $readlist->items()->count() . " items related to " . $description . ".";
                               
                    // Store in conversation history
                    $this->storeConversationInDatabase($user, $conversationId, $question, $response);
                    
                    return response()->json([
                        'success' => true,
                        'answer' => $response,
                        'conversation_id' => $conversationId,
                        'readlist' => $readlist->load('items.item')
                    ]);
                }
            }
            
            // If we get here, something went wrong with readlist generation
            // Try one more time with web search if we haven't already used it
            if (count($internalContent) >= $minRequiredContent) {
                // We had enough internal content but still failed, try with web search
                $exaService = app(\App\Services\ExaSearchService::class);
                $webSearchResults = $exaService->search($description . " educational resources", 10, true, true);
                
                if ($webSearchResults['success'] && !empty($webSearchResults['results'])) {
                    // Create a readlist with web content
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
                    
                    $readlistData = [
                        'title' => 'Readlist: ' . ucfirst($description),
                        'description' => 'A collection of resources about ' . $description . ' curated from the web.',
                        'items' => $webItems
                    ];
                    
                    $readlist = $this->createReadlistInDatabase($user, $readlistData);
                    
                    if ($readlist) {
                        $response = "I couldn't create a readlist with our internal content about " . $description . 
                                    ", so I've created one with " . count($webItems) . " resources from the web instead.";
                        
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
            
            // If all else fails, return a helpful error message
            $errorMsg = "I tried to create a readlist about " . $description . " but couldn't find enough relevant content. " .
                        "Would you like me to try a different topic?";
                        
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            return response()->json([
                'success' => true,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in handleReadlistCreationRequest: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMsg = "I'm sorry, I encountered an error while trying to create your readlist. Please try again later.";
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            return response()->json([
                'success' => true,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId
            ]);
        }
    }
    
    /**
     * Find relevant content for readlist creation
     * 
     * @param string $description
     * @return array
     */
    private function findRelevantContent($description)
    {
        $internalContent = [];
        
        // Extract keywords
        $keywords = preg_split('/[\s,]+/', $description);
        $keywords = array_filter($keywords, function($word) {
            return strlen($word) > 3; // Only use words longer than 3 characters
        });
        
        // If no good keywords, use the whole description
        if (empty($keywords)) {
            $keywords = [$description];
        }
        
        // Search for relevant courses
        $courseQuery = \App\Models\Course::query();
        foreach ($keywords as $keyword) {
            $courseQuery->orWhere('title', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
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
        $postQuery = \App\Models\Post::query();
        foreach ($keywords as $keyword) {
            $postQuery->orWhere('title', 'like', "%{$keyword}%")
                ->orWhere('body', 'like', "%{$keyword}%");
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
            foreach ($readlistData['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'external') {
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
                    // Handle internal item (course or post)
                    $itemId = $item['id'];
                    $notes = $item['notes'] ?? null;
                    $itemType = null;
                    $itemModel = null;
                    
                    // Check if internal item exists
                    if (strpos($item['type'] ?? '', 'internal_course') !== false || strpos($item['type'] ?? '', 'course') !== false) {
                        $course = \App\Models\Course::find($itemId);
                        if ($course) {
                            $itemType = \App\Models\Course::class;
                            $itemModel = $course;
                        }
                    } elseif (strpos($item['type'] ?? '', 'internal_post') !== false || strpos($item['type'] ?? '', 'post') !== false) {
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
            return $readlist;
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error creating readlist in database: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Handle web search requests using Exa.ai
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
            // Get Exa search service
            $exaService = app(\App\Services\ExaSearchService::class);
            
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
            
            // Extract search query
            $searchQuery = $question;
            
            // Perform web search
            $searchResults = $exaService->search($searchQuery, 5, true, true);
            
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
            
            // Format search results for the AI
            $formattedResults = "I found the following information from searching the web:\n\n";
            
            foreach ($searchResults['results'] as $index => $result) {
                $formattedResults .= "[" . ($index + 1) . "] " . $result['title'] . "\n";
                $formattedResults .= "Source: " . $result['url'] . "\n";
                $formattedResults .= "Content: " . substr($result['text'], 0, 500) . "...\n\n";
            }
            
            // Create a prompt for the AI to synthesize the search results
            $synthesisPrompt = "Based on the web search results above, please provide a comprehensive answer to the user's question: \"" . $question . "\". ";
            $synthesisPrompt .= "Include relevant information from the search results, and cite your sources using the [1], [2], etc. notation from the results. ";
            $synthesisPrompt .= "If the search results don't fully answer the question, acknowledge that and provide what information is available.";
            
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
                
                return response()->json([
                    'success' => true,
                    'answer' => $result['answer'],
                    'conversation_id' => $conversationId,
                    'has_web_results' => true,
                    'web_results' => $searchResults['results']
                ]);
            }
            
            // Fallback if synthesis fails
            $fallbackMsg = "I found some information from the web, but couldn't synthesize it properly. Here are some relevant sources:\n\n";
            
            foreach ($searchResults['results'] as $index => $result) {
                $fallbackMsg .= "- " . $result['title'] . ": " . $result['url'] . "\n";
            }
            
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
}