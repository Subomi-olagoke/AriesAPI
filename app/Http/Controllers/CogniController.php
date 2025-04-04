<?php

namespace App\Http\Controllers;

use App\Services\CogniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CogniController extends Controller
{
    protected $cogniService;

    public function __construct(CogniService $cogniService)
    {
        $this->cogniService = $cogniService;
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
            'conversation_id' => 'nullable|string'
        ]);

        $user = Auth::user();
        $question = $request->input('question');
        $conversationId = $request->input('conversation_id');

        if (empty($conversationId)) {
            // Generate a new conversation ID if none provided
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }

        // Get conversation history from session or initialize new one
        $conversationKey = 'cogni_conversation_' . $conversationId;
        $context = Session::get($conversationKey, []);

        // Add the new user question to context
        $context[] = [
            'role' => 'user',
            'content' => $question
        ];

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
}