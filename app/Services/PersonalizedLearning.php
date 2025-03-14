<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PersonalizedLearningPathService
{
    protected $aiService;
    
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * Generate a personalized learning path for a user
     *
     * @param User $user The user to generate the path for
     * @param bool $force Force regeneration of path
     * @return array The personalized learning path data
     */
    public function generateLearningPath(User $user, $force = false)
    {
        $cacheKey = 'learning_path_' . $user->id;
        $cacheTtl = config('ai.features.personalized_learning_paths.cache_ttl', 86400);
        
        // Return cached path if available and not forcing regeneration
        if (!$force && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Get user learning profile
            $learningProfile = $this->aiService->getUserLearningProfile($user);
            
            // Get course recommendations based on profile
            $recommendedCourses = $this->getRecommendedCourses($user, $learningProfile);
            
            // Generate skills gap analysis
            $skillsGapAnalysis = $this->generateSkillsGapAnalysis($user, $learningProfile);
            
            // Generate milestones for learning path
            $milestones = $this->generateMilestones($user, $recommendedCourses, $skillsGapAnalysis);
            
            // Create the learning path
            $learningPath = [
                'user_id' => $user->id,
                'learning_style' => $learningProfile['learning_style'],
                'recommended_courses' => $recommendedCourses,
                'skills_gap_analysis' => $skillsGapAnalysis,
                'milestones' => $milestones,
                'generated_at' => now(),
                'next_update' => now()->addSeconds(config('ai.features.personalized_learning_paths.refresh_interval', 604800)),
            ];
            
            // Cache the learning path
            Cache::put($cacheKey, $learningPath, $cacheTtl);
            
            return $learningPath;
        } catch (\Exception $e) {
            Log::error('Error generating learning path: ' . $e->getMessage());
            
            // Return a basic path if generation fails
            return [
                'user_id' => $user->id,
                'error' => 'Could not generate personalized learning path',
                'recommended_courses' => $this->getFallbackRecommendations($user),
                'generated_at' => now(),
            ];
        }
    }
    
    /**
     * Get recommended courses based on user profile
     *
     * @param User $user The user to get recommendations for
     * @param array $learningProfile The user's learning profile
     * @return array Recommended courses
     */
    protected function getRecommendedCourses(User $user, array $learningProfile)
    {
        // Get user's completed course IDs to exclude
        $completedCourseIds = $user->enrollments()
            ->where('status', 'completed')
            ->pluck('course_id')
            ->toArray();
            
        // Get user's in-progress course IDs to exclude
        $inProgressCourseIds = $user->enrollments()
            ->where('status', 'active')
            ->pluck('course_id')
            ->toArray();
            
        // Get topics of interest
        $topicIds = $user->topic()->pluck('topics.id')->toArray();
        
        // Get courses from topics of interest that user hasn't enrolled in
        $topicCourses = Course::whereIn('topic_id', $topicIds)
            ->whereNotIn('id', array_merge($completedCourseIds, $inProgressCourseIds))
            ->take(20)
            ->get();
            
        // Get some popular courses from other topics for diversity
        $otherCourses = Course::whereNotIn('topic_id', $topicIds)
            ->whereNotIn('id', array_merge($completedCourseIds, $inProgressCourseIds))
            ->orderBy('id', 'desc') // Using ID as a proxy for popularity (newer courses)
            ->take(10)
            ->get();
            
        // Combine and prepare courses
        $allPotentialCourses = $topicCourses->merge($otherCourses);
        $scoredCourses = [];
        
        foreach ($allPotentialCourses as $course) {
            // Calculate base relevance score (0-10)
            $relevanceScore = in_array($course->topic_id, $topicIds) ? 7 : 3;
            
            // Adjust score based on course difficulty vs user profile
            if ($course->difficulty_level) {
                // Assume user is beginner for new topics, or one level higher than completed courses
                $userLevel = $this->estimateUserLevel($user, $course->topic_id);
                
                $difficultyMatch = match ($course->difficulty_level) {
                    'beginner' => $userLevel === 'beginner' ? 3 : ($userLevel === 'intermediate' ? 1 : -1),
                    'intermediate' => $userLevel === 'intermediate' ? 3 : ($userLevel === 'beginner' ? 2 : 1),
                    'advanced' => $userLevel === 'advanced' ? 3 : ($userLevel === 'intermediate' ? 2 : 0),
                    default => 1,
                };
                
                $relevanceScore += $difficultyMatch;
            }
            
            // Learning style adjustments
            if ($learningProfile['learning_style'] === 'methodical' && $course->duration_minutes > 180) {
                $relevanceScore += 1; // Methodical learners prefer comprehensive courses
            } elseif ($learningProfile['learning_style'] === 'efficient' && $course->duration_minutes < 120) {
                $relevanceScore += 1; // Efficient learners prefer concise courses
            }
            
            $scoredCourses[] = [
                'course' => $course,
                'score' => $relevanceScore
            ];
        }
        
        // Sort by score and take top courses
        usort($scoredCourses, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Take top courses
        $maxCourses = config('ai.features.personalized_learning_paths.max_courses_to_suggest', 10);
        $scoredCourses = array_slice($scoredCourses, 0, $maxCourses);
        
        // Format for output
        $recommendedCourses = [];
        foreach ($scoredCourses as $scoredCourse) {
            $course = $scoredCourse['course'];
            $recommendedCourses[] = [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'topic' => $course->topic->name ?? 'Uncategorized',
                'topic_id' => $course->topic_id,
                'difficulty' => $course->difficulty_level,
                'relevance_score' => $scoredCourse['score'],
                'reason' => $this->generateRecommendationReason($course, $learningProfile, $scoredCourse['score']),
                'estimated_completion_time' => $this->estimateCompletionTime($course, $learningProfile),
            ];
        }
        
        return $recommendedCourses;
    }
    
    /**
     * Estimate user's level in a specific topic
     *
     * @param User $user The user to evaluate
     * @param int $topicId The topic ID
     * @return string The estimated level (beginner, intermediate, advanced)
     */
    protected function estimateUserLevel(User $user, $topicId)
    {
        // Count completed courses in this topic
        $completedCount = $user->enrollments()
            ->where('status', 'completed')
            ->whereHas('course', function ($query) use ($topicId) {
                $query->where('topic_id', $topicId);
            })
            ->count();
            
        // Check difficulty levels of completed courses
        $completedAdvanced = $user->enrollments()
            ->where('status', 'completed')
            ->whereHas('course', function ($query) use ($topicId) {
                $query->where('topic_id', $topicId)
                    ->where('difficulty_level', 'advanced');
            })
            ->count();
            
        $completedIntermediate = $user->enrollments()
            ->where('status', 'completed')
            ->whereHas('course', function ($query) use ($topicId) {
                $query->where('topic_id', $topicId)
                    ->where('difficulty_level', 'intermediate');
            })
            ->count();
            
        // Determine level
        if ($completedAdvanced > 0) {
            return 'advanced';
        } elseif ($completedIntermediate > 0 || $completedCount > 2) {
            return 'intermediate';
        } else {
            return 'beginner';
        }
    }
    
    /**
     * Generate a reason for recommending a course
     *
     * @param Course $course The recommended course
     * @param array $learningProfile The user's learning profile
     * @param int $score The relevance score
     * @return string The recommendation reason
     */
    protected function generateRecommendationReason(Course $course, array $learningProfile, $score)
    {
        // Base reason on topic match
        if (in_array($course->topic->name ?? '', $learningProfile['topics_of_interest'])) {
            $reason = "This course aligns with your interest in " . ($course->topic->name ?? 'this topic');
        } else {
            $reason = "This course could expand your knowledge beyond your current interests";
        }
        
        // Add learning style context
        switch ($learningProfile['learning_style']) {
            case 'methodical':
                if ($course->duration_minutes > 180) {
                    $reason .= " and offers comprehensive coverage suitable for your methodical learning style";
                }
                break;
            case 'explorer':
                if ($course->topic->name && !in_array($course->topic->name, $learningProfile['topics_of_interest'])) {
                    $reason .= " and supports your explorer learning style by introducing new subject areas";
                }
                break;
            case 'efficient':
                if ($course->duration_minutes < 120) {
                    $reason .= " and is concise enough for your efficient learning style";
                }
                break;
        }
        
        // Add difficulty context
        $userLevel = $this->estimateUserLevel(User::find($learningProfile['user_id']), $course->topic_id);
        if ($course->difficulty_level === $userLevel) {
            $reason .= ". The difficulty level is appropriate for your current knowledge";
        } elseif ($course->difficulty_level === 'beginner' && $userLevel !== 'beginner') {
            $reason .= ". This could serve as a good refresher of fundamentals";
        } elseif ($course->difficulty_level === 'advanced' && $userLevel !== 'advanced') {
            $reason .= ". This course will challenge you to grow your skills";
        }
        
        return $reason;
    }
    
    /**
     * Estimate completion time for a course based on user profile
     *
     * @param Course $course The course to estimate for
     * @param array $learningProfile The user's learning profile
     * @return string Estimated completion time
     */
    protected function estimateCompletionTime(Course $course, array $learningProfile)
    {
        // Base duration on course duration_minutes
        $baseDurationMinutes = $course->duration_minutes ?? 60;
        
        // Adjust based on learning style
        $adjustmentFactor = 1.0;
        switch ($learningProfile['learning_style']) {
            case 'efficient':
                $adjustmentFactor = 0.8; // 20% faster
                break;
            case 'methodical':
                $adjustmentFactor = 1.3; // 30% slower (more thorough)
                break;
            case 'explorer':
                $adjustmentFactor = 1.1; // 10% slower (explores tangents)
                break;
        }
        
        // Adjust for average completion time from profile
        if (!empty($learningProfile['progress_patterns']['average_completion_days'])) {
            $avgCompletionDays = $learningProfile['progress_patterns']['average_completion_days'];
            if ($avgCompletionDays < 7) {
                $adjustmentFactor *= 0.9; // Faster than average
            } elseif ($avgCompletionDays > 21) {
                $adjustmentFactor *= 1.2; // Slower than average
            }
        }
        
        // Calculate estimated duration
        $estimatedMinutes = $baseDurationMinutes * $adjustmentFactor;
        
        // Convert to a human-readable format
        if ($estimatedMinutes < 60) {
            return "Less than 1 hour";
        } elseif ($estimatedMinutes < 1440) { // less than a day
            $hours = round($estimatedMinutes / 60);
            return "{$hours} " . ($hours == 1 ? "hour" : "hours");
        } else {
            $days = round($estimatedMinutes / 1440);
            return "About {$days} " . ($days == 1 ? "day" : "days");
        }
    }
    
    /**
     * Generate a skills gap analysis for the user
     *
     * @param User $user The user to analyze
     * @param array $learningProfile The user's learning profile
     * @return array Skills gap analysis
     */
    protected function generateSkillsGapAnalysis(User $user, array $learningProfile)
    {
        // Identify skills user already has from completed courses
        $completedCourses = $user->enrollments()
            ->where('status', 'completed')
            ->with('course')
            ->get();
            
        // Extract topics and skills from completed courses
        $acquiredTopics = [];
        foreach ($completedCourses as $enrollment) {
            $course = $enrollment->course;
            if ($course && $course->topic) {
                $acquiredTopics[$course->topic_id] = $course->topic->name;
            }
        }
        
        // Analyze target/desired topics from user interests
        $targetTopics = [];
        foreach ($learningProfile['topics_of