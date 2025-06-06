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
     * Analyze any post with AI, including direct analysis of media content
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
            $postContext = $content; // Save for image analysis
            
            // STEP 1: First, identify the key topics in the post
            $topicsPrompt = "Analyze this post and identify the 3-5 main educational topics or concepts it covers. " .
                            "Be specific, academic, and educational in your identification. " .
                            "Return only a comma-separated list of key educational topics with no explanations.";
            
            $topicsResult = $this->cogniService->askQuestion($topicsPrompt . "\n\nHere's the content:\n" . $content);
            
            $topics = "";
            if ($topicsResult['success']) {
                $topics = $topicsResult['answer'];
                Log::info('Identified post topics', ['topics' => $topics]);
            }
            
            // STEP 2: Find educational resources related to the post topics
            $relatedResources = [];
            if (!empty($topics) && $this->exaSearchService->isConfigured()) {
                $searchResult = $this->exaSearchService->findRelatedContent($topics, 5);
                if ($searchResult['success'] && !empty($searchResult['results'])) {
                    $relatedResources = $searchResult['results'];
                }
            }
            
            // STEP 3: Process each media item
            $mediaAnalyses = [];
            $hasMediaToAnalyze = $post->media && $post->media->count() > 0;
            
            if ($hasMediaToAnalyze) {
                $content .= "\n\nThis post includes the following media:\n";
                
                foreach ($post->media as $index => $media) {
                    $mediaType = $media->media_type ?? 'file';
                    $mediaUrl = $media->media_link ?? 'Not available';
                    $mediaName = $media->original_filename ?? 'Unnamed';
                    $mediaMimeType = $media->mime_type ?? '';
                    $mediaId = $media->id;
                    
                    // Initialize media analysis entry
                    $mediaAnalyses[$mediaId] = [
                        'media_id' => $mediaId,
                        'media_type' => $mediaType,
                        'media_name' => $mediaName,
                        'media_url' => $mediaUrl,
                        'analysis' => null
                    ];
                    
                    // Add media info to context
                    if (strpos($mediaType, 'image') !== false || strpos($mediaMimeType, 'image/') !== false) {
                        $content .= "- Image: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        
                        // Analyze images with educational focus
                        try {
                            $prompt = "Analyze this image in the context of an educational post. " . 
                                      "First, describe what's shown in the image clearly. " .
                                      "Then, explain its educational significance and how it relates to the post topic. " .
                                      "If the image contains any diagrams, charts, or educational elements, explain them in detail. " .
                                      "Focus on making your analysis educational and informative.";
                            
                            $imageResult = $this->cogniService->analyzeImage($mediaUrl, $prompt, $postContext);
                            
                            if ($imageResult['success']) {
                                $mediaAnalyses[$mediaId]['analysis'] = $imageResult['answer'];
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to analyze image: ' . $e->getMessage(), ['media_id' => $mediaId]);
                        }
                    } 
                    else if (strpos($mediaType, 'video') !== false || strpos($mediaMimeType, 'video/') !== false) {
                        $content .= "- Video: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        
                        // Comprehensive video analysis
                        $videoPrompt = "Perform a professional, detailed analysis of this video in the context of the post. " .
                                      "In a system-like tone, provide: " .
                                      "1. Likely content based on post context and video title/name " .
                                      "2. Educational concepts that may be covered " .
                                      "3. Potential learning outcomes from watching this video " .
                                      "4. How it complements the post's educational value " .
                                      "Format as a concise, factual analysis using professional language. " .
                                      "If the video appears to be a demonstration, tutorial, lecture, or educational content, " .
                                      "analyze what skills or knowledge viewers would gain.";
                        
                        // Try to extract video ID if it's a YouTube URL
                        $videoId = null;
                        $videoThumbnail = null;
                        $videoTitle = $mediaName;
                        $frameAnalyses = [];
                        
                        if (strpos($mediaUrl, 'youtube.com') !== false || strpos($mediaUrl, 'youtu.be') !== false) {
                            // Extract YouTube video ID
                            preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $mediaUrl, $matches);
                            
                            if (!empty($matches[1])) {
                                $videoId = $matches[1];
                                $videoThumbnail = "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
                                
                                // Try to get YouTube video title and other metadata
                                try {
                                    // Use YouTube API if configured
                                    $youtubeApiKey = config('services.youtube.api_key');
                                    if (!empty($youtubeApiKey)) {
                                        $videoInfoUrl = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&key={$youtubeApiKey}&part=snippet,contentDetails";
                                        $response = Http::get($videoInfoUrl);
                                        if ($response->successful()) {
                                            $data = $response->json();
                                            if (!empty($data['items'][0]['snippet']['title'])) {
                                                $videoTitle = $data['items'][0]['snippet']['title'];
                                            }
                                            
                                            // Get video duration if available for frame timestamps
                                            $duration = null;
                                            if (!empty($data['items'][0]['contentDetails']['duration'])) {
                                                // Parse ISO 8601 duration format
                                                $durationStr = $data['items'][0]['contentDetails']['duration'];
                                                $interval = new \DateInterval($durationStr);
                                                $duration = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                                            }
                                            
                                            // Analyze video frames at different timestamps if duration is available
                                            if ($duration) {
                                                // Get frames at 25%, 50%, and 75% of the video
                                                $frameTimestamps = [
                                                    round($duration * 0.25),
                                                    round($duration * 0.5),
                                                    round($duration * 0.75)
                                                ];
                                                
                                                foreach ($frameTimestamps as $index => $timestamp) {
                                                    // YouTube thumbnails at specific timestamps
                                                    $frameUrl = "https://img.youtube.com/vi/{$videoId}/0.jpg"; // Default thumbnail
                                                    
                                                    // For custom timestamps, we would need a service that generates thumbnails
                                                    // This is a placeholder - in production you would use a video frame extraction service
                                                    // or YouTube's storyboard API (which requires additional implementation)
                                                    
                                                    // Since we can't dynamically generate frame images here, we'll use the available thumbnails
                                                    $availableThumbnails = [
                                                        "https://img.youtube.com/vi/{$videoId}/0.jpg",
                                                        "https://img.youtube.com/vi/{$videoId}/1.jpg",
                                                        "https://img.youtube.com/vi/{$videoId}/2.jpg",
                                                        "https://img.youtube.com/vi/{$videoId}/3.jpg",
                                                    ];
                                                    
                                                    if (isset($availableThumbnails[$index])) {
                                                        $frameUrl = $availableThumbnails[$index];
                                                    }
                                                    
                                                    // Analyze the frame with GPT Vision
                                                    try {
                                                        $framePrompt = "Analyze this frame from a YouTube video titled '{$videoTitle}'. " .
                                                                      "Describe what's shown in the frame and how it relates to the educational topic. " .
                                                                      "Focus on visual elements that provide educational value.";
                                                        
                                                        $frameResult = $this->cogniService->analyzeImage($frameUrl, $framePrompt, $postContext);
                                                        
                                                        if ($frameResult['success']) {
                                                            $frameAnalyses[] = [
                                                                'timestamp' => $timestamp,
                                                                'timestamp_formatted' => gmdate('H:i:s', $timestamp),
                                                                'frame_url' => $frameUrl,
                                                                'analysis' => $frameResult['answer']
                                                            ];
                                                        }
                                                    } catch (\Exception $e) {
                                                        Log::warning('Failed to analyze video frame: ' . $e->getMessage(), [
                                                            'video_id' => $videoId,
                                                            'timestamp' => $timestamp
                                                        ]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to fetch YouTube video info: ' . $e->getMessage());
                                }
                            }
                        }
                        
                        // Add video metadata to the analysis entry
                        $mediaAnalyses[$mediaId]['video_id'] = $videoId;
                        $mediaAnalyses[$mediaId]['video_thumbnail'] = $videoThumbnail;
                        $mediaAnalyses[$mediaId]['video_title'] = $videoTitle;
                        
                        // Add frame analyses if available
                        if (!empty($frameAnalyses)) {
                            $mediaAnalyses[$mediaId]['frame_analyses'] = $frameAnalyses;
                        }
                        
                        // Include video metadata in the prompt if available
                        $contextAddition = "\n\nVideo file: " . $mediaName;
                        if ($videoTitle !== $mediaName) {
                            $contextAddition .= "\nVideo title: " . $videoTitle;
                        }
                        
                        // Include frame analyses in the prompt to enhance the overall video analysis
                        if (!empty($frameAnalyses)) {
                            $contextAddition .= "\n\nFrame analyses:";
                            foreach ($frameAnalyses as $frameAnalysis) {
                                $contextAddition .= "\n- Frame at " . $frameAnalysis['timestamp_formatted'] . ": " . 
                                                   substr($frameAnalysis['analysis'], 0, 100) . "...";
                            }
                        }
                        
                        $videoResult = $this->cogniService->askQuestion($videoPrompt . "\n\nPost context:\n" . $postContext . $contextAddition);
                        
                        if ($videoResult['success']) {
                            $mediaAnalyses[$mediaId]['analysis'] = $videoResult['answer'];
                        }
                    }
                    else if (strpos($mediaType, 'audio') !== false || strpos($mediaMimeType, 'audio/') !== false) {
                        $content .= "- Audio: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        
                        // Enhanced audio analysis
                        $prompt = "Based on the post topics ({$topics}), provide an educational analysis of what this audio file likely contains. " .
                                 "What concepts might it explain? What educational value does it provide? " .
                                 "How does it support learning about the post topics?";
                        
                        $audioResult = $this->cogniService->askQuestion($prompt . "\n\nPost context:\n" . $postContext . "\n\nAudio file: " . $mediaName);
                        
                        if ($audioResult['success']) {
                            $mediaAnalyses[$mediaId]['analysis'] = $audioResult['answer'];
                        }
                    }
                    else {
                        // For other file types
                        $content .= "- {$mediaType}: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        
                        $prompt = "Based on the post topics ({$topics}), explain the educational value this file might provide. " .
                                 "How does it enhance understanding of the post topics?";
                        
                        $fileResult = $this->cogniService->askQuestion($prompt . "\n\nPost context:\n" . $postContext . "\n\nFile: " . $mediaName . " (Type: " . $mediaType . ")");
                        
                        if ($fileResult['success']) {
                            $mediaAnalyses[$mediaId]['analysis'] = $fileResult['answer'];
                        }
                    }
                }
            }
            
            // STEP 4: Create a concise educational summary that includes interesting facts
            $summaryPrompt = "Based on this post about '{$topics}', provide a concise educational summary in a professional, system-like tone. " .
                             "In 3-5 sentences total, include: " .
                             "1) A clear, factual explanation of what the post is about " .
                             "2) 1-2 educational facts related to the topics " .
                             "Make it informative and educational without being verbose or overly friendly. " .
                             "Maintain a neutral, professional tone throughout. Avoid exclamations, casual language, or first-person references. " .
                             "This should read like a system-generated educational analysis.";
            
            $summaryResult = $this->cogniService->askQuestion($summaryPrompt . "\n\nHere's the content:\n" . $content);
            
            if (!$summaryResult['success']) {
                return response()->json([
                    'message' => 'Analysis failed: ' . ($summaryResult['message'] ?? 'Unknown error')
                ], 500);
            }
            
            // Prepare a simplified educational response
            $response = [
                'success' => true,
                'analysis' => $summaryResult['answer'],
                'has_media' => $hasMediaToAnalyze
            ];
            
            // Add media analyses - but only include the ones that were successfully analyzed
            if (!empty($mediaAnalyses)) {
                $validMediaAnalyses = [];
                foreach ($mediaAnalyses as $mediaId => $analysis) {
                    if (!empty($analysis['analysis'])) {
                        $validMediaAnalyses[] = $analysis;
                    }
                }
                
                if (!empty($validMediaAnalyses)) {
                    $response['media_analyses'] = $validMediaAnalyses;
                }
            }
            
            // Add relevant educational resources only if we found good results
            if (!empty($relatedResources)) {
                // Just take the top 2 most relevant resources to keep it simple
                $response['related_resources'] = array_slice($relatedResources, 0, 2);
            }
            
            return response()->json($response);
            
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
        $post = Post::with('media')->findOrFail($postId);
        
        // Only post owner can get recommendations for their own posts
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'You can only get recommendations for your own posts'
            ], 403);
        }
        
        try {
            // Prepare post content for analysis
            $content = $post->title . "\n\n" . $post->body;
            
            // Add media information for analysis
            if ($post->media && $post->media->count() > 0) {
                $content .= "\n\nThis post includes the following media:\n";
                
                foreach ($post->media as $index => $media) {
                    $mediaType = $media->media_type ?? 'file';
                    $mediaUrl = $media->media_link ?? 'Not available';
                    $mediaName = $media->original_filename ?? 'Unnamed';
                    $mediaMimeType = $media->mime_type ?? '';
                    
                    // Add more detailed description based on media type
                    if (strpos($mediaType, 'image') !== false || strpos($mediaMimeType, 'image/') !== false) {
                        $content .= "- Image: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  Consider providing title suggestions that incorporate what this image might show\n";
                    } 
                    else if (strpos($mediaType, 'video') !== false || strpos($mediaMimeType, 'video/') !== false) {
                        $content .= "- Video: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  Consider suggesting ways to improve the video context or description\n";
                    }
                    else if (strpos($mediaType, 'audio') !== false || strpos($mediaMimeType, 'audio/') !== false) {
                        $content .= "- Audio: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  Consider suggesting ways to improve the audio context or description\n";
                    }
                    else {
                        $content .= "- {$mediaType}: {$mediaName}\n";
                    }
                }
            }
            
            // Request specific recommendations
            $prompt = "Analyze this post and provide specific recommendations to improve engagement and reach. " .
                     "Focus on: " .
                     "1) How to make the title more engaging (2-3 alternate title suggestions), " .
                     "2) Structure improvements (how to organize content better), " .
                     "3) Style enhancements (tone, clarity, readability), " .
                     "4) Additional content suggestions (what's missing or could be expanded), " .
                     "5) Media improvements (how to better utilize or describe the images/videos, or what media to add if none). " .
                     "Format as JSON with fields: title_suggestions (array), structure_tips (array), style_tips (array), content_suggestions (array), media_recommendations (array)";
            
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
            
            // Add media information for analysis
            if ($post->media && $post->media->count() > 0) {
                $content .= "\n\nThis post includes the following media:\n";
                
                foreach ($post->media as $index => $media) {
                    $mediaType = $media->media_type ?? 'file';
                    $mediaUrl = $media->media_link ?? 'Not available';
                    $mediaName = $media->original_filename ?? 'Unnamed';
                    $mediaMimeType = $media->mime_type ?? '';
                    
                    // Add more detailed description based on media type
                    if (strpos($mediaType, 'image') !== false || strpos($mediaMimeType, 'image/') !== false) {
                        $content .= "- Image: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  This image may provide visual information relevant to the post topic\n";
                    } 
                    else if (strpos($mediaType, 'video') !== false || strpos($mediaMimeType, 'video/') !== false) {
                        $content .= "- Video: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  This video may contain educational content related to the post topic\n";
                    }
                    else if (strpos($mediaType, 'audio') !== false || strpos($mediaMimeType, 'audio/') !== false) {
                        $content .= "- Audio: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                        $content .= "  This audio may contain explanations or discussions related to the post topic\n";
                    }
                    else {
                        $content .= "- {$mediaType}: {$mediaName}\n";
                        $content .= "  URL: {$mediaUrl}\n";
                    }
                }
            }
            
            // STEP 1: Generate a summary of the post first
            $summaryPrompt = "Provide a concise summary (3-5 sentences) of this post in a helpful, informative tone. " . 
                            "Focus on the main topic, key points, and any important context. " .
                            "If there are media attachments (images, videos, etc.), include their likely content based on the post context. " .
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
                     "Include topics related to any media content (images, videos, etc.) that appear in the post. " .
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
    
    /**
     * Analyze specific media in a post (premium feature)
     * 
     * @param int $postId The ID of the post
     * @param int $mediaId The ID of the media to analyze
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzePostMedia($postId, $mediaId)
    {
        $user = Auth::user();
        
        // Check if user has premium access
        if (!$user->canAnalyzePosts()) {
            return response()->json([
                'message' => 'AI media analysis is a premium feature. Please upgrade your subscription.',
                'premium_required' => true
            ], 403);
        }
        
        // Find the post
        $post = Post::findOrFail($postId);
        
        // Find the specific media
        $media = $post->media()->where('id', $mediaId)->firstOrFail();
        
        // Extract the post context
        $postContext = $post->title . "\n\n" . $post->body;
        
        try {
            $mediaType = $media->media_type ?? '';
            $mediaUrl = $media->media_link ?? '';
            $mediaName = $media->original_filename ?? '';
            $mediaMimeType = $media->mime_type ?? '';
            
            // Determine what type of analysis to perform based on media type
            if (strpos($mediaType, 'image') !== false || strpos($mediaMimeType, 'image/') !== false) {
                // For images, use the vision capabilities
                $prompt = "Analyze this image in the context of the post. Describe what's shown, how it relates to the post content, and any educational value it provides. Focus on accuracy and educational context.";
                
                // Use the new analyzeImage method
                $result = $this->cogniService->analyzeImage($mediaUrl, $prompt, $postContext);
            } 
            else if (strpos($mediaType, 'video') !== false || strpos($mediaMimeType, 'video/') !== false) {
                // For videos, we'll use text-based analysis since we can't process the video directly
                $prompt = "Based on the post context, what would you expect this video to show or explain? What educational value might it provide? How does it relate to the post content?";
                
                $result = $this->cogniService->askQuestion($prompt . "\n\nPost context:\n" . $postContext . "\n\nVideo file: " . $mediaName);
            }
            else if (strpos($mediaType, 'audio') !== false || strpos($mediaMimeType, 'audio/') !== false) {
                // For audio files
                $prompt = "Based on the post context, what would you expect this audio file to contain? What educational value might it provide? How does it relate to the post content?";
                
                $result = $this->cogniService->askQuestion($prompt . "\n\nPost context:\n" . $postContext . "\n\nAudio file: " . $mediaName);
            }
            else {
                // For other file types
                $prompt = "Based on the post context, what educational value might this file provide? How does it relate to the post content?";
                
                $result = $this->cogniService->askQuestion($prompt . "\n\nPost context:\n" . $postContext . "\n\nFile: " . $mediaName . " (Type: " . $mediaType . ")");
            }
            
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Analysis failed: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'media_id' => $mediaId,
                'media_type' => $mediaType,
                'analysis' => $result['answer']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Post media analysis failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while analyzing the media',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}