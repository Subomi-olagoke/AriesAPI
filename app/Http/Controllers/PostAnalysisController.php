<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\CogniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostAnalysisController extends Controller
{
    protected $cogniService;

    public function __construct(CogniService $cogniService)
    {
        $this->cogniService = $cogniService;
    }

    /**
     * Analyze a post with AI (premium feature)
     * 
     * @param int $postId The ID of the post to analyze
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzePost($postId)
    {
        $user = Auth::user();
        
        // Check if user has premium access to analyze posts
        if (!$user->canAnalyzePosts()) {
            return response()->json([
                'message' => 'AI post analysis is a premium feature. Please upgrade your subscription.',
                'premium_required' => true
            ], 403);
        }
        
        // Find the post
        $post = Post::findOrFail($postId);
        
        // Only post owner can analyze their own posts
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'You can only analyze your own posts'
            ], 403);
        }
        
        try {
            // Prepare post content for analysis
            $content = $post->title . "\n\n" . $post->body;
            
            // Request analysis from Cogni
            $prompt = "Analyze this post and provide insights. Include: " .
                      "1) Main topics and keywords (extract 3-5 key topics), " .
                      "2) Writing style assessment (formal/informal, technical/casual, etc), " . 
                      "3) Potential audience that would be interested in this content, " .
                      "4) Suggestions for improvements or increasing engagement (2-3 actionable tips), " .
                      "5) Overall strengths. " .
                      "Format as JSON with fields: topics (array), style (string), audience (string), suggestions (array), strengths (array)";
            
            $result = $this->cogniService->askQuestion($prompt . "\n\nHere's the content:\n" . $content);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Analysis failed: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }
            
            // Try to parse JSON response, but fallback to raw text if needed
            try {
                $rawResponse = $result['answer'];
                
                // Extract JSON if surrounded by other text
                preg_match('/{.*}/s', $rawResponse, $matches);
                $jsonStr = $matches[0] ?? $rawResponse;
                
                $analysis = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => true,
                        'analysis' => $analysis
                    ]);
                }
                
                // Fallback to raw response
                return response()->json([
                    'success' => true,
                    'analysis_text' => $rawResponse
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error parsing post analysis: ' . $e->getMessage());
                
                return response()->json([
                    'success' => true,
                    'analysis_text' => $result['answer']
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Post analysis failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while analyzing the post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get recommendations for improving a post (premium feature)
     */
    public function getPostRecommendations($postId)
    {
        $user = Auth::user();
        
        // Check if user has premium access
        if (!$user->canAnalyzePosts()) {
            return response()->json([
                'message' => 'AI post recommendations is a premium feature. Please upgrade your subscription.',
                'premium_required' => true
            ], 403);
        }
        
        // Find the post
        $post = Post::findOrFail($postId);
        
        // Only post owner can get recommendations for their own posts
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'You can only get recommendations for your own posts'
            ], 403);
        }
        
        try {
            // Prepare post content for analysis
            $content = $post->title . "\n\n" . $post->body;
            
            // Request specific recommendations
            $prompt = "Analyze this post and provide specific recommendations to improve engagement and reach. " .
                     "Focus on: " .
                     "1) How to make the title more engaging (2-3 alternate title suggestions), " .
                     "2) Structure improvements (how to organize content better), " .
                     "3) Style enhancements (tone, clarity, readability), " .
                     "4) Additional content suggestions (what's missing or could be expanded). " .
                     "Format as JSON with fields: title_suggestions (array), structure_tips (array), style_tips (array), content_suggestions (array)";
            
            $result = $this->cogniService->askQuestion($prompt . "\n\nHere's the content:\n" . $content);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Recommendation generation failed: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }
            
            // Try to parse JSON response, but fallback to raw text if needed
            try {
                $rawResponse = $result['answer'];
                
                // Extract JSON if surrounded by other text
                preg_match('/{.*}/s', $rawResponse, $matches);
                $jsonStr = $matches[0] ?? $rawResponse;
                
                $recommendations = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => true,
                        'recommendations' => $recommendations
                    ]);
                }
                
                // Fallback to raw response
                return response()->json([
                    'success' => true,
                    'recommendations_text' => $rawResponse
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error parsing post recommendations: ' . $e->getMessage());
                
                return response()->json([
                    'success' => true,
                    'recommendations_text' => $result['answer']
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Post recommendations failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while generating recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}