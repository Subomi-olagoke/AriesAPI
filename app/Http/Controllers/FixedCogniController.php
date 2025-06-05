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
        
        $description = '';
        
        try {
            $debugLogs['process_steps'][] = 'Starting readlist creation process';
            
            // Extract the topic from the question
            $description = preg_replace('/^(cogni,?\s*)?(please\s*)?(create|make|build)(\s+a|\s+me\s+a)?\s+(readlist|reading list)(\s+for\s+me)?(\s+about|\s+on)?\s*/i', '', $question);
            $description = trim($description);
            
            // Your implementation logic here
            
            // For example:
            $errorMsg = "I'm currently unable to create a readlist about \"{$description}\". This feature is being fixed.";
            
            $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
            
            return response()->json([
                'success' => true,
                'answer' => $errorMsg,
                'conversation_id' => $conversationId
            ]);
        } catch (\Exception $e) {
            return $this->handleReadlistError($e, $description ?? '', $question, $user, $conversationId);
        }
    }
    
    /**
     * Handle readlist generation error
     * 
     * @param \Exception $e
     * @param string $description
     * @param string $question
     * @param \App\Models\User $user
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
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
        
        \Log::error('Error in handleReadlistCreationRequest: ' . $e->getMessage(), $errorContext);
        
        $errorMsg = "I'm sorry, I encountered an error while trying to create your readlist. Please try again later.";
        $this->storeConversationInDatabase($user, $conversationId, $question, $errorMsg);
        
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
     * Handle web search request
     * 
     * @param \App\Models\User $user
     * @param string $question
     * @param array $context
     * @param string $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleWebSearchRequest($user, $question, $context, $conversationId)
    {
        // Implementation for handling web search requests
        // This is a placeholder
        
        $answer = "I'm sorry, web search functionality is currently being fixed.";
        
        // Store in conversation history
        $this->storeConversationInDatabase($user, $conversationId, $question, $answer);
        
        return response()->json([
            'success' => true,
            'answer' => $answer,
            'conversation_id' => $conversationId
        ]);
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
}