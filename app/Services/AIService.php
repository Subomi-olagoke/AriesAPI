<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;

class AIService
{
    protected $apiKey;
    protected $apiEndpoint;
    
    public function __construct()
    {
        $this->apiKey = config('ai.openai.key');
        $this->apiEndpoint = config('ai.openai.endpoint');
    }
    
    /**
     * Generate a response using OpenAI's API
     *
     * @param string $prompt The prompt to send to the AI
     * @param string $model The model to use (default: gpt-3.5-turbo)
     * @param float $temperature Controls randomness (0-1)
     * @param int $maxTokens Maximum tokens to generate 
     * @return string|null The AI generated response
     */
    public function generateResponse($prompt, $model = 'gpt-3.5-turbo', $temperature = 0.7, $maxTokens = 500)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiEndpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant for an educational platform.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            } else {
                Log::error('AI API request failed: ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('AI service error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user learning profile based on activities and preferences
     *
     * @param User $user The user to analyze
     * @return array User learning profile data
     */
    public function getUserLearningProfile(User $user)
    {
        // Get completed courses
        $completedCourses = $user->enrollments()
            ->where('status', 'completed')
            ->with('course.topic')
            ->get();
            
        // Get in-progress courses
        $inProgressCourses = $user->enrollments()
            ->where('status', 'active')
            ->with('course.topic')
            ->get();
            
        // Get topics of interest from user profile
        $topicsOfInterest = $user->topic()->pluck('name')->toArray();
        
        // Calculate progress patterns
        $progressData = $this->calculateProgressPatterns($user);
        
        // Calculate average engagement
        $averageEngagement = $this->calculateAverageEngagement($user);
        
        // Determine learning style based on behavior
        $learningStyle = $this->determineLearningStyle($user);
        
        // Compile learning profile
        return [
            'topics_of_interest' => $topicsOfInterest,
            'completed_courses' => $completedCourses->map(function ($enrollment) {
                return [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'topic' => $enrollment->course->topic->name ?? 'Uncategorized',
                    'completion_date' => $enrollment->updated_at
                ];
            })->toArray(),
            'in_progress_courses' => $inProgressCourses->map(function ($enrollment) {
                return [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'topic' => $enrollment->course->topic->name ?? 'Uncategorized',
                    'progress' => $enrollment->progress
                ];
            })->toArray(),
            'learning_style' => $learningStyle,
            'average_engagement' => $averageEngagement,
            'progress_patterns' => $progressData
        ];
    }
    
    /**
     * Calculate user progress patterns
     *
     * @param User $user The user to analyze
     * @return array Progress pattern data
     */
    protected function calculateProgressPatterns(User $user)
    {
        $lessonProgress = $user->lessonProgress()
            ->with('lesson')
            ->orderBy('updated_at')
            ->get();
            
        // Simple analysis of when user typically completes lessons
        $timeDistribution = [];
        foreach ($lessonProgress as $progress) {
            if ($progress->completed && $progress->last_watched_at) {
                $hour = (int) $progress->last_watched_at->format('H');
                $timeDistribution[$hour] = ($timeDistribution[$hour] ?? 0) + 1;
            }
        }
        
        // Finding peak learning hours
        $peakHours = [];
        if (!empty($timeDistribution)) {
            arsort($timeDistribution);
            $peakHours = array_slice(array_keys($timeDistribution), 0, 3);
        }
        
        // Calculate completion speed
        $completionSpeeds = [];
        foreach ($user->enrollments as $enrollment) {
            if ($enrollment->status === 'completed' && $enrollment->created_at && $enrollment->updated_at) {
                $days = $enrollment->created_at->diffInDays($enrollment->updated_at);
                $completionSpeeds[] = $days;
            }
        }
        
        $averageCompletionDays = count($completionSpeeds) > 0 
            ? array_sum($completionSpeeds) / count($completionSpeeds) 
            : null;
            
        return [
            'peak_learning_hours' => $peakHours,
            'average_completion_days' => $averageCompletionDays,
            'time_distribution' => $timeDistribution
        ];
    }
    
    /**
     * Calculate average user engagement
     *
     * @param User $user The user to analyze
     * @return float Engagement score (0-1)
     */
    protected function calculateAverageEngagement(User $user)
    {
        $totalLessons = 0;
        $watchedLessons = 0;
        $completedCourses = 0;
        $abandonedCourses = 0;
        
        foreach ($user->enrollments as $enrollment) {
            if ($enrollment->status === 'completed') {
                $completedCourses++;
            } elseif ($enrollment->status === 'active' && $enrollment->updated_at && $enrollment->updated_at->diffInDays(now()) > 30) {
                // Consider a course abandoned if no activity for 30 days
                $abandonedCourses++;
            }
            
            // Count lessons watched vs total lessons
            $course = $enrollment->course;
            if ($course) {
                $courseLessons = $course->lessons()->count();
                $totalLessons += $courseLessons;
                
                $watchedInCourse = $user->lessonProgress()
                    ->whereIn('lesson_id', $course->lessons()->pluck('id'))
                    ->count();
                    
                $watchedLessons += $watchedInCourse;
            }
        }
        
        // Calculate engagement score (simple version)
        if ($totalLessons === 0) {
            return 0;
        }
        
        $watchRate = $watchedLessons / $totalLessons;
        $completionRate = ($completedCourses + $abandonedCourses) > 0 
            ? $completedCourses / ($completedCourses + $abandonedCourses) 
            : 0;
            
        // Combined engagement score (70% watch rate, 30% completion rate)
        return ($watchRate * 0.7) + ($completionRate * 0.3);
    }
    
    /**
     * Determine user's learning style based on behavior
     *
     * @param User $user The user to analyze
     * @return string The identified learning style
     */
    protected function determineLearningStyle(User $user)
    {
        // Simple implementation - could be expanded with more sophisticated analysis
        $lessonProgress = $user->lessonProgress;
        
        $linearProgression = true;
        $lastLessonId = null;
        $jumpCount = 0;
        
        // Check if user follows courses in order or jumps around
        foreach ($lessonProgress as $progress) {
            if ($lastLessonId && $progress->lesson_id !== $lastLessonId + 1) {
                $linearProgression = false;
                $jumpCount++;
            }
            $lastLessonId = $progress->lesson_id;
        }
        
        // Check completion time patterns
        $quickCompletions = 0;
        $thoroughReviews = 0;
        
        foreach ($lessonProgress as $progress) {
            $lesson = $progress->lesson;
            if ($lesson && $progress->completed) {
                $expectedTimeMinutes = $lesson->duration_minutes ?? 30;
                $actualTimeMinutes = $progress->watched_seconds ? $progress->watched_seconds / 60 : 0;
                
                if ($actualTimeMinutes < $expectedTimeMinutes * 0.7) {
                    $quickCompletions++;
                } elseif ($actualTimeMinutes > $expectedTimeMinutes * 1.3) {
                    $thoroughReviews++;
                }
            }
        }
        
        // Determine predominant style
        if ($linearProgression && $thoroughReviews > $quickCompletions) {
            return 'methodical';
        } elseif (!$linearProgression && $jumpCount > 5) {
            return 'explorer';
        } elseif ($quickCompletions > $thoroughReviews) {
            return 'efficient';
        } else {
            return 'balanced';
        }
    }
    
    /**
     * Get a text embedding for content using the OpenAI API
     *
     * @param string $content The text to generate embeddings for
     * @return array|null The embedding vector
     */
    public function getContentEmbedding($content)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(config('ai.openai.embedding_endpoint'), [
                'model' => config('ai.openai.embedding_model'),
                'input' => $content
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            } else {
                Log::error('Embedding API request failed: ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Embedding service error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate the similarity between two embedding vectors
     *
     * @param array $embedding1 First embedding vector
     * @param array $embedding2 Second embedding vector
     * @return float Cosine similarity score (0-1)
     */
    public function calculateSimilarity($embedding1, $embedding2)
    {
        if (empty($embedding1) || empty($embedding2)) {
            return 0;
        }
        
        // Cosine similarity calculation
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        foreach ($embedding1 as $i => $val1) {
            $val2 = $embedding2[$i] ?? 0;
            $dotProduct += $val1 * $val2;
            $magnitude1 += $val1 * $val1;
            $magnitude2 += $val2 * $val2;
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 * $magnitude2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}