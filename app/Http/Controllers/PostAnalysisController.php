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
     * Analyze any post with AI
     * 
     * @param int $postId The ID of the post to analyze
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzePost($postId)
    {
        // Find the post with media
        $post = Post::with('media')->findOrFail($postId);
        
        try {
            // Prepare post content for analysis
            $content = $post->title . "\n\n" . $post->body;
            
            // Add media descriptions if available
            if ($post->media && $post->media->count() > 0) {
                $content .= "\n\nThis post includes the following media:\n";
                
                foreach ($post->media as $index => $media) {
                    $mediaType = $media->type ?? 'file';
                    $mediaUrl = $media->url ?? 'Not available';
                    $mediaName = $media->name ?? 'Unnamed';
                    
                    $content .= "- {$mediaType}: {$mediaName}\n";
                }
            }
            
            // Request analysis from Cogni
            $prompt = "Analyze this post and provide a clear breakdown in a conversational style. Include: " .
                      "1) A brief summary of the main points, " .
                      "2) Key topics discussed, " . 
                      "3) The general tone and perspective of the post, " .
                      "4) Any notable information, facts, or insights shared. " .
                      "If the post mentions it includes images or documents, acknowledge those in your analysis. " .
                      "Write in a natural, helpful tone as if explaining to someone what this post is about.";
            
            $result = $this->cogniService->askQuestion($prompt . "\n\nHere's the content:\n" . $content);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Analysis failed: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }
            
            // Return the analysis directly as text
            return response()->json([
                'success' => true,
                'analysis' => $result['answer']
            ]);
            
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