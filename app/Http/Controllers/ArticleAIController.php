<?php

namespace App\Http\Controllers;

use App\Services\ArticleAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Article AI Controller
 * 
 * Handles AI-powered article features:
 * - Summarization
 * - Question answering
 * - Suggested questions
 */
class ArticleAIController extends Controller
{
    protected $aiService;

    public function __construct(ArticleAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Summarize an article
     * 
     * POST /api/v1/articles/summarize
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summarize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:100',
            'url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $content = $request->input('content');
        $url = $request->input('url');

        try {
            Log::info('Article summarization requested', [
                'user_id' => Auth::id(),
                'url' => $url,
                'content_length' => strlen($content)
            ]);

            $summary = $this->aiService->summarizeArticle($content, $url);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Summarization error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'url' => $url
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to summarize article',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Ask a question about an article
     * 
     * POST /api/v1/articles/ask
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:100',
            'question' => 'required|string|min:3|max:500',
            'conversation_history' => 'nullable|array',
            'conversation_history.*.question' => 'required_with:conversation_history|string',
            'conversation_history.*.answer' => 'required_with:conversation_history|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $content = $request->input('content');
        $question = $request->input('question');
        $conversationHistory = $request->input('conversation_history', []);

        try {
            Log::info('Article Q&A requested', [
                'user_id' => Auth::id(),
                'question' => $question,
                'history_length' => count($conversationHistory)
            ]);

            $result = $this->aiService->askAboutArticle($content, $question, $conversationHistory);

            return response()->json([
                'success' => true,
                'data' => [
                    'answer' => $result['answer'],
                    'confidence' => $result['confidence'],
                    'question' => $question,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Article Q&A error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'question' => $question
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to answer question',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get suggested questions for an article
     * 
     * POST /api/v1/articles/suggested-questions
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suggestedQuestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $content = $request->input('content');

        try {
            Log::info('Suggested questions requested', [
                'user_id' => Auth::id(),
                'content_length' => strlen($content)
            ]);

            $questions = $this->aiService->getSuggestedQuestions($content);

            return response()->json([
                'success' => true,
                'data' => [
                    'questions' => $questions
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Suggested questions error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate questions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get AI usage statistics for the authenticated user
     * 
     * GET /api/v1/articles/ai-stats
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        // This could be extended to track actual usage in the database
        // For now, return basic info
        
        return response()->json([
            'success' => true,
            'data' => [
                'features_available' => [
                    'summarization' => true,
                    'question_answering' => true,
                    'suggested_questions' => true
                ],
                'limits' => [
                    'daily_summaries' => 100,
                    'daily_questions' => 200
                ],
                'usage' => [
                    'summaries_today' => 0,
                    'questions_today' => 0
                ]
            ]
        ]);
    }
}
