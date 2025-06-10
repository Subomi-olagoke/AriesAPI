<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\ExaTrendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TrendingTopicsController extends Controller
{
    protected $exaTrendService;
    
    public function __construct(ExaTrendService $exaTrendService)
    {
        $this->exaTrendService = $exaTrendService;
    }
    
    /**
     * Get trending topics based primarily on internal platform data,
     * with Exa.ai as fallback only when needed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrendingTopics(Request $request)
    {
        // Get parameters with defaults
        $limit = (int) $request->input('limit', 10);
        $timeframe = $request->input('timeframe', 'week');
        $minTopics = (int) $request->input('min_topics', 5); // Minimum number of topics before using external search
        
        // Validate timeframe parameter
        if (!in_array($timeframe, ['day', 'week', 'month'])) {
            $timeframe = 'week'; // Default to week if invalid
        }
        
        // Determine date range based on timeframe
        $startDate = null;
        switch ($timeframe) {
            case 'day':
                $startDate = Carbon::now()->subDay();
                break;
            case 'month':
                $startDate = Carbon::now()->subMonth();
                break;
            case 'week':
            default:
                $startDate = Carbon::now()->subWeek();
                break;
        }
        
        // Cache key for this request
        $cacheKey = "trending_topics_{$timeframe}_{$limit}_{$minTopics}";
        
        // Return cached data if available (cached for 1 hour)
        return Cache::remember($cacheKey, 3600, function () use ($startDate, $limit, $timeframe, $minTopics) {
            // Step 1: Get our internal trending topics
            $internalTopics = $this->getInternalTrendingTopics($startDate, $limit);
            
            // Check if we have enough internal topics
            if (count($internalTopics) >= $minTopics) {
                // We have enough internal topics - no need for external search
                \Log::info('Using internal topics only: ' . count($internalTopics) . ' found');
                
                return response()->json([
                    'message' => 'Trending topics retrieved successfully',
                    'trending_topics' => $internalTopics->take($limit)->values(),
                    'source' => 'internal'
                ]);
            }
            
            // If we don't have enough internal topics, supplement with external data
            \Log::info('Not enough internal topics: ' . count($internalTopics) . ', using external search to supplement');
            
            // How many additional topics we need
            $additionalTopicsNeeded = $limit - count($internalTopics);
            
            // Step 2: Discover trending topics from external sources
            $discoveredTopics = $this->exaTrendService->discoverTrendingTopics($additionalTopicsNeeded * 2);
            
            // Get names of internal topics to avoid duplicates
            $internalTopicNames = $internalTopics->pluck('name')->map(function($name) {
                return strtolower($name);
            })->toArray();
            
            // Step 3: Format and filter discovered topics
            $externalTopics = $this->formatDiscoveredTopics($discoveredTopics, $internalTopicNames);
            
            // Step 4: Combine internal and external topics
            $combinedTopics = $internalTopics->toArray();
            foreach ($externalTopics as $topic) {
                $combinedTopics[] = $topic;
            }
            
            // Ensure we don't exceed the requested limit
            $finalTopics = array_slice($combinedTopics, 0, $limit);
            
            return response()->json([
                'message' => 'Trending topics retrieved successfully',
                'trending_topics' => $finalTopics,
                'source' => 'combined'
            ]);
        });
    }
    
    /**
     * Get internally trending topics based on platform data
     * Simplified to focus on core metrics without external data
     */
    private function getInternalTrendingTopics($startDate, $limit)
    {
        try {
            $trendingTopics = Topic::select(
                    'topics.id',
                    'topics.name',
                    DB::raw('COUNT(DISTINCT courses.id) as course_count'),
                    DB::raw('COALESCE(COUNT(course_enrollments.id), 0) as enrollment_count'),
                    DB::raw('(COALESCE(COUNT(course_enrollments.id), 0) * 1.0 + COUNT(DISTINCT courses.id) * 5.0) as score')
                )
                ->leftJoin('courses', 'topics.id', '=', 'courses.topic_id')
                ->leftJoin('course_enrollments', function ($join) use ($startDate) {
                    $join->on('courses.id', '=', 'course_enrollments.course_id');
                    if ($startDate) {
                        $join->where('course_enrollments.created_at', '>=', $startDate);
                    }
                })
                ->groupBy('topics.id', 'topics.name')
                ->orderBy('score', 'desc')
                ->limit($limit)
                ->get();
                
            // Format topics with simplified structure
            $formattedTopics = $trendingTopics->map(function($topic) {
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'score' => $topic->score,
                    'course_count' => $topic->course_count,
                    'enrollment_count' => $topic->enrollment_count,
                    'source' => 'internal'
                ];
            });
            
            return collect($formattedTopics);
        } catch (\Exception $e) {
            // If there's an error with the query (e.g., missing tables), return empty collection
            \Log::error('Error getting internal trending topics: ' . $e->getMessage());
            return collect([]);
        }
    }
    
    /**
     * Format discovered topics from Exa.ai to match our internal format
     * 
     * @param array $discoveredTopics Discovered topics from Exa.ai
     * @param array $existingTopics Array of existing topic names to avoid duplicates
     * @return array Formatted topics
     */
    private function formatDiscoveredTopics($discoveredTopics, $existingTopics)
    {
        $formattedTopics = [];
        
        foreach ($discoveredTopics['topics'] as $topic) {
            // Skip if this topic already exists in our internal topics
            if (in_array(strtolower($topic['name']), $existingTopics)) {
                continue;
            }
            
            $formattedTopics[] = [
                'id' => null, // Will be filled in if added to database
                'name' => $topic['name'],
                'description' => $topic['description'] ?? "A trending topic in education and technology.",
                'score' => 50, // Default score for external topics
                'source' => 'external',
                'mention_count' => $topic['mention_count'] ?? 0
            ];
        }
        
        return $formattedTopics;
    }
}