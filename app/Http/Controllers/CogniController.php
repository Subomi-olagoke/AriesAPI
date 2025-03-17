<?php

namespace App\Http\Controllers;

use App\Services\CogniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Get conversation history if a conversation_id is provided
        $context = [];
        if ($conversationId) {
            // Here you could retrieve previous messages from a database
            // For now, we'll just use an empty context
        }

        // Ask the question
        $result = $this->cogniService->askQuestion($question, $context);

        // If successful, store the conversation in your database if needed
        if ($result['success'] && isset($result['answer'])) {
            // Optional: Log or store the conversation
            
            return response()->json([
                'success' => true,
                'answer' => $result['answer'],
                'conversation_id' => $conversationId ?? $this->generateConversationId()
            ]);
        }

        // Return error response
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to get an answer from Cogni'
        ], $result['code'] ?? 500);
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
            'level' => 'nullable|string|in:basic,intermediate,advanced'
        ]);

        $topic = $request->input('topic');
        $level = $request->input('level', 'intermediate');

        $result = $this->cogniService->explainTopic($topic, $level);

        if ($result['success'] && isset($result['answer'])) {
            return response()->json([
                'success' => true,
                'explanation' => $result['answer']
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
            'question_count' => 'nullable|integer|min:1|max:10'
        ]);

        $topic = $request->input('topic');
        $questionCount = $request->input('question_count', 5);

        $result = $this->cogniService->generateQuiz($topic, $questionCount);

        if ($result['success']) {
            if (isset($result['quiz'])) {
                return response()->json([
                    'success' => true,
                    'quiz' => $result['quiz']
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'answer' => $result['answer']
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to generate a quiz'
        ], $result['code'] ?? 500);
    }

    /**
     * Generate a unique conversation ID
     *
     * @return string
     */
    private function generateConversationId()
    {
        return 'conv_' . uniqid() . '_' . time();
    }
}