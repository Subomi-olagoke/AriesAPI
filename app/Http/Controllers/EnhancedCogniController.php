<?php

namespace App\Http\Controllers;

use App\Services\EnhancedCogniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EnhancedCogniController extends Controller
{
    protected $cogniService;

    /**
     * Create a new controller instance.
     */
    public function __construct(EnhancedCogniService $cogniService)
    {
        $this->cogniService = $cogniService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Generate a readlist based on topic or learning goal
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReadlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required_without:skill|string|max:100',
            'skill' => 'required_without:topic|string|max:100',
            'level' => 'nullable|string|in:beginner,intermediate,advanced,all',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'max_items' => 'nullable|integer|min:3|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $result = $this->cogniService->generateReadlist($request->all(), $user);

        return response()->json($result, $result['code']);
    }

    /**
     * Analyze a readlist and provide enhanced insights
     *
     * @param Request $request
     * @param int $id Readlist ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeReadlist(Request $request, $id)
    {
        $result = $this->cogniService->analyzeReadlist($id);
        return response()->json($result, $result['code']);
    }

    /**
     * Recommend additional content for a readlist
     *
     * @param Request $request
     * @param int $id Readlist ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendForReadlist(Request $request, $id)
    {
        $user = Auth::user();
        $result = $this->cogniService->recommendForReadlist($id, $user);
        return response()->json($result, $result['code']);
    }

    /**
     * Generate assessments based on readlist content
     *
     * @param Request $request
     * @param int $id Readlist ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateAssessments(Request $request, $id)
    {
        $result = $this->cogniService->generateAssessments($id);
        return response()->json($result, $result['code']);
    }

    /**
     * Create a study plan for a readlist
     *
     * @param Request $request
     * @param int $id Readlist ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function createStudyPlan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'days_available' => 'nullable|integer|min:1|max:90',
            'hours_per_day' => 'nullable|numeric|min:0.5|max:12',
            'learning_style' => 'nullable|string|in:balanced,deep,quick',
            'include_assessments' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->cogniService->createStudyPlan($id, $request->all());
        return response()->json($result, $result['code']);
    }

    /**
     * Recommend educators based on user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendEducators(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'count' => 'nullable|integer|min:1|max:20',
            'topic_id' => 'nullable|exists:topics,id',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $result = $this->cogniService->recommendEducators($user, $request->all());
        return response()->json($result, $result['code']);
    }

    /**
     * Ask a general question to Cogni
     * (Original Cogni functionality preserved)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $question = $request->input('question');
        $conversationId = $request->input('conversation_id');

        if (empty($conversationId)) {
            // Generate a new conversation ID if none provided
            $conversationId = 'conv_' . uniqid() . '_' . time();
        }

        // Get conversation history from session
        $conversationKey = 'cogni_conversation_' . $conversationId;
        $context = session($conversationKey, []);

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
            session([$conversationKey => $context]);
            
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
}