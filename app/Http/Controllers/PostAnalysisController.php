<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\CogniService;
use App\Services\ExaSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostAnalysisController extends Controller
{
    protected $cogniService;
    protected $exaSearchService;

    public function __construct(CogniService $cogniService, ExaSearchService $exaSearchService)
    {
        $this->cogniService = $cogniService;
        $this->exaSearchService = $exaSearchService;
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
            $prompt = "Provide a brief, concise summary of this post in 2-3 sentences. " .
                      "Mention any media attachments that are part of the post. " .
                      "Make it conversational and easy to understand. " .
                      "Keep your response very short and to the point.";
            
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

    /**
     * Get a summary and learning resources related to a post
     * 
     * @param int $postId The ID of the post to find resources for
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLearningResources($postId)
    {
        $user = Auth::user();
        
        // Find the post with media
        $post = Post::with('media')->findOrFail($postId);
        
        try {
            // Check if Exa service is configured
            if (!$this->exaSearchService->isConfigured()) {
                return response()->json([
                    'message' => 'Learning resources unavailable - missing configuration',
                    'success' => false
                ], 500);
            }
            
            // Prepare post content for analysis
            $content = $post->title . "\n\n" . $post->body;
            
            // STEP 1: Generate a summary of the post first
            $summaryPrompt = "Provide a concise summary (3-5 sentences) of this post in a helpful, informative tone. " . 
                            "Focus on the main topic, key points, and any important context. " .
                            "If the post is about a medical condition, educational concept, or technical topic, " .
                            "provide a clear, accurate explanation that would help someone understand the basics.";
            
            $summaryResult = $this->cogniService->askQuestion($summaryPrompt . "\n\nHere's the content:\n" . $content);
            
            if (!$summaryResult['success']) {
                return response()->json([
                    'message' => 'Failed to generate post summary',
                    'success' => false
                ], 500);
            }
            
            $summary = $summaryResult['answer'];
            
            // STEP 2: Identify key topics in the post
            $topicsPrompt = "Analyze this post and identify the 3-5 main educational topics or concepts it covers. " .
                     "Return these topics as a simple comma-separated list. Be specific and focused.";
            
            $topicsResult = $this->cogniService->askQuestion($topicsPrompt . "\n\nHere's the content:\n" . $content);
            
            if (!$topicsResult['success']) {
                return response()->json([
                    'message' => 'Failed to analyze post topics',
                    'success' => false
                ], 500);
            }
            
            // Extract topics from AI response
            $topics = $topicsResult['answer'];
            
            // STEP 3: Use Exa to find learning resources based on these topics
            $resourcesResult = $this->exaSearchService->findRelatedContent($topics, 7);
            
            if (!$resourcesResult['success'] || empty($resourcesResult['results'])) {
                // Fallback to direct post content search if topic extraction didn't yield good results
                $resourcesResult = $this->exaSearchService->findRelatedContent($content, 7);
            }
            
            // STEP 4: Get categorized resources for a more structured response
            $categorizedResources = [];
            
            // Try to get categorized resources for more specific learning paths
            if (!empty($topics)) {
                $categorizedResult = $this->exaSearchService->getCategorizedResources($topics);
                if ($categorizedResult['success']) {
                    $categorizedResources = $categorizedResult['categories'];
                }
            }
            
            return response()->json([
                'success' => true,
                'post_id' => $postId,
                'summary' => $summary,
                'topics' => $topics,
                'resources' => $resourcesResult['results'],
                'categorized_resources' => $categorizedResources
            ]);
            
        } catch (\Exception $e) {
            Log::error('Post summary and learning resources lookup failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while analyzing the post',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}