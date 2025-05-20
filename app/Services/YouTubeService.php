<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YouTubeService
{
    protected $apiKey;
    protected $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.api_key');
        
        // Log warning if API key is not set
        if (empty($this->apiKey)) {
            Log::warning('YouTube API key is not configured in environment');
        }
    }

    /**
     * Extract YouTube video ID from various YouTube URL formats
     *
     * @param string $url
     * @return string|null
     */
    public function extractVideoId(string $url): ?string
    {
        // Match YouTube URL patterns
        preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
        
        return $matches[1] ?? null;
    }

    /**
     * Get video information including title, description, duration
     *
     * @param string $videoId
     * @return array
     */
    public function getVideoInfo(string $videoId): array
    {
        $cacheKey = "youtube_video_info_{$videoId}";
        
        // Check if cached result exists
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'YouTube API key is not configured',
                'code' => 500
            ];
        }
        
        try {
            $response = Http::get("{$this->baseUrl}/videos", [
                'part' => 'snippet,contentDetails,statistics',
                'id' => $videoId,
                'key' => $this->apiKey
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (empty($data['items'])) {
                    return [
                        'success' => false,
                        'message' => 'Video not found',
                        'code' => 404
                    ];
                }
                
                $videoInfo = $data['items'][0];
                $result = [
                    'success' => true,
                    'video' => [
                        'id' => $videoId,
                        'title' => $videoInfo['snippet']['title'],
                        'description' => $videoInfo['snippet']['description'],
                        'channel' => $videoInfo['snippet']['channelTitle'],
                        'published_at' => $videoInfo['snippet']['publishedAt'],
                        'duration' => $this->formatDuration($videoInfo['contentDetails']['duration']),
                        'raw_duration' => $videoInfo['contentDetails']['duration'],
                        'view_count' => $videoInfo['statistics']['viewCount'] ?? 0,
                        'thumbnail' => $videoInfo['snippet']['thumbnails']['high']['url'],
                    ]
                ];
                
                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);
                
                return $result;
            }
            
            Log::error('YouTube API request failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to get video information from YouTube API',
                'code' => $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('YouTube API error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error connecting to YouTube API: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Get captions for a YouTube video
     *
     * @param string $videoId
     * @return array
     */
    public function getCaptions(string $videoId): array
    {
        $cacheKey = "youtube_captions_{$videoId}";
        
        // Check if cached result exists
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'YouTube API key is not configured',
                'code' => 500
            ];
        }
        
        try {
            // First, get the caption tracks
            $response = Http::get("{$this->baseUrl}/captions", [
                'part' => 'snippet',
                'videoId' => $videoId,
                'key' => $this->apiKey
            ]);
            
            if (!$response->successful()) {
                Log::error('YouTube Captions API request failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to get captions from YouTube API',
                    'code' => $response->status()
                ];
            }
            
            $data = $response->json();
            
            // If no caption tracks exist
            if (empty($data['items'])) {
                // Try to use other methods to get transcript
                return $this->getCaptionsAlternative($videoId);
            }
            
            // Prefer English captions
            $captionTrack = null;
            foreach ($data['items'] as $item) {
                $lang = $item['snippet']['language'];
                if ($lang === 'en') {
                    $captionTrack = $item;
                    break;
                }
            }
            
            // If no English captions, use the first available
            if ($captionTrack === null && !empty($data['items'])) {
                $captionTrack = $data['items'][0];
            }
            
            if ($captionTrack === null) {
                return $this->getCaptionsAlternative($videoId);
            }
            
            // Now get the actual caption content
            $captionId = $captionTrack['id'];
            $captionResponse = Http::get("{$this->baseUrl}/captions/{$captionId}", [
                'tfmt' => 'srt',
                'key' => $this->apiKey
            ]);
            
            if (!$captionResponse->successful()) {
                return $this->getCaptionsAlternative($videoId);
            }
            
            $captionContent = $captionResponse->body();
            
            // Parse SRT format to text
            $captions = $this->parseSrtToText($captionContent);
            
            $result = [
                'success' => true,
                'captions' => [
                    'language' => $captionTrack['snippet']['language'],
                    'content' => $captions
                ]
            ];
            
            // Cache for 1 day
            Cache::put($cacheKey, $result, 86400);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('YouTube Captions API error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error connecting to YouTube Captions API: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Get captions using alternative methods when official captions are not available
     *
     * @param string $videoId
     * @return array
     */
    private function getCaptionsAlternative(string $videoId): array
    {
        try {
            // For educational purposes, we'll describe the approach but not implement actual scraping
            // A real implementation might use YouTube's auto-generated captions or a third-party service
            
            // Fallback to video description as a substitute
            $videoInfo = $this->getVideoInfo($videoId);
            
            if (!$videoInfo['success']) {
                return [
                    'success' => false,
                    'message' => 'No captions available and could not get video info',
                    'code' => 404
                ];
            }
            
            return [
                'success' => true,
                'captions' => [
                    'language' => 'en',
                    'content' => "Captions not available. Video description: \n\n" . 
                                $videoInfo['video']['description'],
                    'is_fallback' => true
                ],
                'video_info' => $videoInfo['video']
            ];
            
        } catch (\Exception $e) {
            Log::error('Alternative caption method error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Could not retrieve captions using alternative methods',
                'code' => 500
            ];
        }
    }
    
    /**
     * Parse SRT format captions to plain text
     *
     * @param string $srtContent
     * @return string
     */
    private function parseSrtToText(string $srtContent): string
    {
        // Remove timing information and sequence numbers, keep only the text
        $lines = explode("\n", $srtContent);
        $textOnly = [];
        $isTextLine = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                $isTextLine = false;
                continue;
            }
            
            // Skip sequence numbers
            if (is_numeric($line)) {
                continue;
            }
            
            // Skip timing lines
            if (preg_match('/^\d{2}:\d{2}:\d{2},\d{3} --> \d{2}:\d{2}:\d{2},\d{3}$/', $line)) {
                $isTextLine = true;
                continue;
            }
            
            if ($isTextLine) {
                $textOnly[] = $line;
            }
        }
        
        return implode("\n", $textOnly);
    }
    
    /**
     * Format ISO 8601 duration to human readable format
     *
     * @param string $isoDuration (e.g. PT1H30M15S)
     * @return string
     */
    private function formatDuration(string $isoDuration): string
    {
        $interval = new \DateInterval($isoDuration);
        
        $hours = $interval->h + ($interval->d * 24);
        $minutes = $interval->i;
        $seconds = $interval->s;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }

    /**
     * Generate a summary of a YouTube video based on its content
     *
     * @param string $videoId
     * @param CogniService $cogniService
     * @return array
     */
    public function summarizeVideo(string $videoId, CogniService $cogniService): array
    {
        $cacheKey = "youtube_summary_{$videoId}";
        
        // Check if cached result exists
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // First get video info and captions
            $videoInfo = $this->getVideoInfo($videoId);
            if (!$videoInfo['success']) {
                return $videoInfo;
            }
            
            $captionsResult = $this->getCaptions($videoId);
            $captions = $captionsResult['success'] ? $captionsResult['captions']['content'] : "No captions available.";
            $isFallback = $captionsResult['captions']['is_fallback'] ?? false;
            
            // Prepare content for AI to analyze
            $title = $videoInfo['video']['title'];
            $description = $videoInfo['video']['description'];
            $channel = $videoInfo['video']['channel'];
            
            // Create prompt for Cogni
            $prompt = "Please analyze this YouTube video:\n\n";
            $prompt .= "Title: {$title}\n";
            $prompt .= "Channel: {$channel}\n";
            $prompt .= "Description: {$description}\n\n";
            
            if ($isFallback) {
                $prompt .= "Note: Full captions were not available for this video.\n\n";
            } else {
                // We need to truncate captions if they're too long to avoid token limits
                $captionsExcerpt = substr($captions, 0, 6000); // Taking first 6000 chars
                $prompt .= "Transcript/Captions (excerpt):\n{$captionsExcerpt}\n\n";
                
                if (strlen($captions) > 6000) {
                    $prompt .= "[Transcript truncated due to length...]\n\n";
                }
            }
            
            $prompt .= "Based on this information, please provide:\n";
            $prompt .= "1. A concise summary of the video content (2-3 paragraphs)\n";
            $prompt .= "2. The key topics or subjects covered\n";
            $prompt .= "3. Main points or takeaways\n";
            
            // Use Cogni service to analyze the content
            $cogniResponse = $cogniService->askQuestion($prompt);
            
            if (!$cogniResponse['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate video summary: ' . ($cogniResponse['message'] ?? 'Unknown error'),
                    'code' => 500
                ];
            }
            
            $result = [
                'success' => true,
                'video_info' => $videoInfo['video'],
                'summary' => $cogniResponse['answer'],
                'captions_available' => $captionsResult['success'],
                'captions_are_fallback' => $isFallback
            ];
            
            // Cache for 1 day
            Cache::put($cacheKey, $result, 86400);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('YouTube summarization error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error generating YouTube video summary: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}