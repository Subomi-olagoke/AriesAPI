<?php

namespace App\Services;

use App\Models\OpenLibrary;
use App\Models\LibraryContent;
use App\Models\Course;
use App\Models\Post;
use App\Models\Topic;
use App\Services\CogniService;
use App\Services\AICoverImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpenLibraryService
{
    /**
     * The Cogni service instance for AI analysis
     */
    protected $cogniService;
    
    /**
     * The AI cover image service for generating library covers
     */
    protected $coverImageService;
    
    /**
     * Create a new service instance
     */
    public function __construct(CogniService $cogniService, AICoverImageService $coverImageService)
    {
        $this->cogniService = $cogniService;
        $this->coverImageService = $coverImageService;
    }
    /**
     * Create a course-specific library that contains all materials from a course
     *
     * @param Course $course The course to create a library for
     * @return OpenLibrary The created library
     */
    public function createCourseLibrary(Course $course)
    {
        try {
            DB::beginTransaction();
            
            // Create the library
            $library = OpenLibrary::create([
                'name' => $course->title,
                'description' => "All content from the course: {$course->title}",
                'type' => 'course',
                'thumbnail_url' => $course->thumbnail_url,
                'course_id' => $course->id,
                'is_approved' => false,
                'approval_status' => 'pending'
            ]);
            
            // Add all course lessons to the library
            $course->load('sections.lessons');
            
            foreach ($course->sections as $section) {
                foreach ($section->lessons as $lesson) {
                    LibraryContent::create([
                        'library_id' => $library->id,
                        'content_id' => $lesson->id,
                        'content_type' => get_class($lesson),
                        'relevance_score' => 1.0 // Maximum relevance for course's own content
                    ]);
                }
            }
            
            DB::commit();
            return $library;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create course library: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create an automatic library based on a topic
     *
     * @param Topic $topic The topic to create a library for
     * @param int $maxItems Maximum number of items to include
     * @return OpenLibrary The created library
     */
    public function createTopicLibrary(Topic $topic, $maxItems = 50)
    {
        try {
            DB::beginTransaction();
            
            // Create the library
            $library = OpenLibrary::create([
                'name' => "Learn {$topic->name}",
                'description' => "Curated content about {$topic->name}",
                'type' => 'auto',
                'criteria' => [
                    'topic_id' => $topic->id,
                    'maxItems' => $maxItems
                ],
                'is_approved' => false,
                'approval_status' => 'pending'
            ]);
            
            // Find courses in this topic
            $courses = Course::where('topic_id', $topic->id)
                ->take($maxItems / 2) // Half the items should be courses
                ->get();
                
            foreach ($courses as $course) {
                LibraryContent::create([
                    'library_id' => $library->id,
                    'content_id' => $course->id,
                    'content_type' => get_class($course),
                    'relevance_score' => 1.0
                ]);
            }
            
            // Find posts that mention this topic
            $posts = Post::where('body', 'like', "%{$topic->name}%")
                ->orWhere('title', 'like', "%{$topic->name}%")
                ->take($maxItems / 2) // Other half should be posts
                ->get();
                
            foreach ($posts as $post) {
                LibraryContent::create([
                    'library_id' => $library->id,
                    'content_id' => $post->id,
                    'content_type' => get_class($post),
                    'relevance_score' => 0.8 // Slightly lower score for related posts
                ]);
            }
            
            DB::commit();
            return $library;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create topic library: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Analyze content similarity and group related items
     *
     * @param mixed $content The content item to find similar content for
     * @param string $contentType The type of content
     * @param int $maxItems Maximum number of similar items to find
     * @return array Array of similar content items with scores
     */
    public function findSimilarContent($content, $contentType, $maxItems = 10)
    {
        // Extract keywords from content
        $keywords = $this->extractKeywords($content);
        
        $similarItems = [];
        
        if ($contentType == Course::class) {
            // Find similar courses based on topic and keywords
            $similarCourses = Course::where('id', '!=', $content->id)
                ->where(function($query) use ($content, $keywords) {
                    // Match by same topic
                    $query->where('topic_id', $content->topic_id);
                    
                    // Or match by keywords in title/description
                    foreach ($keywords as $keyword) {
                        $query->orWhere('title', 'like', "%{$keyword}%")
                              ->orWhere('description', 'like', "%{$keyword}%");
                    }
                })
                ->take($maxItems)
                ->get();
                
            foreach ($similarCourses as $course) {
                $score = $this->calculateSimilarityScore($content, $course);
                $similarItems[] = [
                    'content' => $course,
                    'type' => Course::class,
                    'score' => $score
                ];
            }
        } elseif ($contentType == Post::class) {
            // Find similar posts based on keywords
            $similarPosts = Post::where('id', '!=', $content->id)
                ->where(function($query) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $query->orWhere('title', 'like', "%{$keyword}%")
                              ->orWhere('body', 'like', "%{$keyword}%");
                    }
                })
                ->take($maxItems)
                ->get();
                
            foreach ($similarPosts as $post) {
                $score = $this->calculateSimilarityScore($content, $post);
                $similarItems[] = [
                    'content' => $post,
                    'type' => Post::class,
                    'score' => $score
                ];
            }
        }
        
        // Sort by similarity score
        usort($similarItems, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($similarItems, 0, $maxItems);
    }
    
    /**
     * Extract keywords from content
     *
     * @param mixed $content The content to extract keywords from
     * @return array Array of keywords
     */
    private function extractKeywords($content)
    {
        $text = '';
        
        if ($content instanceof Course) {
            $text = $content->title . ' ' . $content->description;
            
            // Get topic name if available
            if ($content->topic) {
                $text .= ' ' . $content->topic->name;
            }
            
            // Add learning outcomes if available
            if (is_array($content->learning_outcomes)) {
                $text .= ' ' . implode(' ', $content->learning_outcomes);
            }
        } elseif ($content instanceof Post) {
            $text = $content->title . ' ' . $content->body;
        }
        
        // Remove common words and special characters
        $stopWords = ['the', 'and', 'a', 'of', 'to', 'in', 'is', 'it', 'that', 'for', 'on', 'with'];
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        // Filter out stop words and short words
        $words = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) > 3;
        });
        
        // Count word frequencies
        $wordCounts = array_count_values($words);
        
        // Sort by frequency
        arsort($wordCounts);
        
        // Return top 10 keywords
        return array_slice(array_keys($wordCounts), 0, 10);
    }
    
    /**
     * Calculate similarity score between two content items
     *
     * @param mixed $content1 First content item
     * @param mixed $content2 Second content item
     * @return float Similarity score between 0 and 1
     */
    private function calculateSimilarityScore($content1, $content2)
    {
        // Base score
        $score = 0.0;
        
        // If both are courses
        if ($content1 instanceof Course && $content2 instanceof Course) {
            // Same topic is a strong indicator
            if ($content1->topic_id === $content2->topic_id) {
                $score += 0.5;
            }
            
            // Same difficulty level
            if ($content1->difficulty_level === $content2->difficulty_level) {
                $score += 0.2;
            }
            
            // Same instructor
            if ($content1->user_id === $content2->user_id) {
                $score += 0.2;
            }
        }
        
        // Compare keywords
        $keywords1 = $this->extractKeywords($content1);
        $keywords2 = $this->extractKeywords($content2);
        
        $commonKeywords = array_intersect($keywords1, $keywords2);
        $keywordScore = count($commonKeywords) / max(count($keywords1), count($keywords2));
        
        $score += $keywordScore * 0.5; // Keyword match accounts for up to 0.5 of the score
        
        return min(1.0, $score); // Cap at 1.0
    }
    
    /**
     * Create a dynamic library from a piece of content
     *
     * @param mixed $content The content to base the library on
     * @param string $name Optional name for the library
     * @param int $maxItems Maximum number of items to include
     * @return OpenLibrary The created library
     */
    public function createDynamicLibrary($content, $name = null, $maxItems = 20)
    {
        try {
            DB::beginTransaction();
            
            $contentType = get_class($content);
            
            // Create a name if none provided
            if (!$name) {
                if ($contentType == Course::class) {
                    $name = "More courses like: {$content->title}";
                } else {
                    $name = "Related content to: {$content->title}";
                }
            }
            
            // Extract keywords
            $keywords = $this->extractKeywords($content);
            
            // Create the library
            $library = OpenLibrary::create([
                'name' => $name,
                'description' => "Similar content automatically grouped",
                'type' => 'auto',
                'thumbnail_url' => $contentType == Course::class ? $content->thumbnail_url : null,
                'criteria' => [
                    'baseContentId' => $content->id,
                    'baseContentType' => $contentType,
                    'keywords' => $keywords,
                    'maxItems' => $maxItems
                ],
                'is_approved' => false,
                'approval_status' => 'pending'
            ]);
            
            // Add the base content itself
            LibraryContent::create([
                'library_id' => $library->id,
                'content_id' => $content->id,
                'content_type' => $contentType,
                'relevance_score' => 1.0 // Maximum score for the seed content
            ]);
            
            // Find similar content
            $similarItems = $this->findSimilarContent($content, $contentType, $maxItems - 1);
            
            // Add similar items to library
            foreach ($similarItems as $item) {
                LibraryContent::create([
                    'library_id' => $library->id,
                    'content_id' => $item['content']->id,
                    'content_type' => $item['type'],
                    'relevance_score' => $item['score']
                ]);
            }
            
            DB::commit();
            return $library;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create dynamic library: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Refresh the content in an auto-generated library
     *
     * @param OpenLibrary $library The library to refresh
     * @return bool Success indicator
     */
    public function refreshLibraryContent(OpenLibrary $library)
    {
        if ($library->type != 'auto') {
            return false;
        }
        
        try {
            DB::beginTransaction();
            
            // Clear existing content
            LibraryContent::where('library_id', $library->id)->delete();
            
            $criteria = $library->criteria;
            
            if (isset($criteria['topic_id'])) {
                // Topic-based library
                $topic = Topic::find($criteria['topic_id']);
                if (!$topic) {
                    throw new \Exception("Topic not found");
                }
                
                $this->populateTopicLibrary($library, $topic, $criteria['maxItems'] ?? 50);
            }
            elseif (isset($criteria['baseContentId']) && isset($criteria['baseContentType'])) {
                // Content similarity library
                $baseContentType = $criteria['baseContentType'];
                $baseContent = $baseContentType::find($criteria['baseContentId']);
                
                if (!$baseContent) {
                    throw new \Exception("Base content not found");
                }
                
                // Add base content
                LibraryContent::create([
                    'library_id' => $library->id,
                    'content_id' => $baseContent->id,
                    'content_type' => $criteria['baseContentType'],
                    'relevance_score' => 1.0
                ]);
                
                // Find and add similar content
                $similarItems = $this->findSimilarContent(
                    $baseContent, 
                    $criteria['baseContentType'],
                    ($criteria['maxItems'] ?? 20) - 1
                );
                
                foreach ($similarItems as $item) {
                    LibraryContent::create([
                        'library_id' => $library->id,
                        'content_id' => $item['content']->id,
                        'content_type' => $item['type'],
                        'relevance_score' => $item['score']
                    ]);
                }
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to refresh library content: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Populate a topic-based library with content
     */
    private function populateTopicLibrary(OpenLibrary $library, Topic $topic, $maxItems)
    {
        // Find courses in this topic
        $courses = Course::where('topic_id', $topic->id)
            ->take($maxItems / 2)
            ->get();
            
        foreach ($courses as $course) {
            LibraryContent::create([
                'library_id' => $library->id,
                'content_id' => $course->id,
                'content_type' => get_class($course),
                'relevance_score' => 1.0
            ]);
        }
        
        // Find posts that mention this topic
        $posts = Post::where('body', 'like', "%{$topic->name}%")
            ->orWhere('title', 'like', "%{$topic->name}%")
            ->take($maxItems / 2)
            ->get();
            
        foreach ($posts as $post) {
            LibraryContent::create([
                'library_id' => $library->id,
                'content_id' => $post->id,
                'content_type' => get_class($post),
                'relevance_score' => 0.8
            ]);
        }
    }
    
    /**
     * Create libraries from a collection of posts using AI categorization
     *
     * @param array|Collection $posts Collection of posts to categorize
     * @param int $minPostsPerLibrary Minimum number of posts required for a library (default: 10)
     * @param bool $generateCovers Whether to generate cover images for the libraries (default: true)
     * @param bool $autoApprove Whether to automatically approve the libraries (default: false)
     * @return array Response with success/error status and created libraries
     */
    public function createLibrariesFromPosts($posts, int $minPostsPerLibrary = 10, bool $generateCovers = true, bool $autoApprove = false)
    {
        try {
            // Prepare posts for analysis
            $postsForAnalysis = collect($posts)->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title ?? 'Untitled',
                    'body' => $post->body ?? $post->content ?? '',
                    'user_id' => $post->user_id,
                    'created_at' => $post->created_at
                ];
            })->toArray();
            
            // Skip if there aren't enough posts
            if (count($postsForAnalysis) < $minPostsPerLibrary) {
                return [
                    'success' => false,
                    'message' => 'Not enough posts to create libraries. Minimum required: ' . $minPostsPerLibrary,
                    'code' => 400
                ];
            }
            
            // Use Cogni to categorize posts into potential libraries
            $cogniResult = $this->cogniService->categorizePosts($postsForAnalysis, $minPostsPerLibrary);
            
            if (!$cogniResult['success'] || !isset($cogniResult['libraries'])) {
                Log::error('Failed to categorize posts with Cogni', [
                    'message' => $cogniResult['message'] ?? 'Unknown error',
                    'code' => $cogniResult['code'] ?? 500
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to categorize posts for libraries',
                    'code' => 500
                ];
            }
            
            // Start creating libraries based on Cogni's categorization
            $createdLibraries = [];
            
            DB::beginTransaction();
            
            foreach ($cogniResult['libraries'] as $libraryData) {
                // Create library
                $library = new OpenLibrary();
                $library->name = $libraryData['name'];
                $library->description = $libraryData['description'];
                $library->type = 'auto_cogni';
                $library->criteria = [
                    'post_ids' => $libraryData['post_ids'],
                    'rationale' => $libraryData['rationale'],
                    'keywords' => $libraryData['keywords'] ?? []
                ];
                $library->keywords = $libraryData['keywords'] ?? [];
                $library->ai_generated = true;
                $library->ai_generation_date = now();
                $library->is_approved = $autoApprove;
                $library->approval_status = $autoApprove ? 'approved' : 'pending';
                $library->has_ai_cover = false;
                $library->save();
                
                // Add posts to the library
                foreach ($libraryData['post_ids'] as $postId) {
                    $post = Post::find($postId);
                    if ($post) {
                        LibraryContent::create([
                            'library_id' => $library->id,
                            'content_id' => $post->id,
                            'content_type' => Post::class,
                            'relevance_score' => 1.0
                        ]);
                    }
                }
                
                // Generate cover image if requested
                if ($generateCovers) {
                    $coverUrl = $this->coverImageService->generateCoverImage($library);
                    if ($coverUrl) {
                        $library->thumbnail_url = $coverUrl;
                        $library->cover_image_url = $coverUrl;
                        $library->has_ai_cover = true;
                        $library->save();
                    }
                }
                
                $createdLibraries[] = $library;
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'libraries' => $createdLibraries,
                'count' => count($createdLibraries),
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create libraries from posts: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create libraries: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Check for recent popular posts and create libraries if threshold is met
     * 
     * @param int $days Number of days to look back for posts
     * @param int $minPosts Minimum number of posts required for categorization
     * @param bool $autoApprove Whether to automatically approve created libraries
     * @return array Result of the operation
     */
    public function checkAndCreateLibrariesFromRecentPosts(int $days = 7, int $minPosts = 10, bool $autoApprove = false)
    {
        try {
            // Get recent posts with sufficient interactions
            $recentPosts = Post::where('created_at', '>=', now()->subDays($days))
                ->where(function($query) {
                    // Posts with good interaction metrics
                    $query->has('likes', '>=', 5)
                          ->orHas('comments', '>=', 3);
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
            
            // If we have enough posts, create libraries
            if ($recentPosts->count() >= $minPosts) {
                return $this->createLibrariesFromPosts(
                    $recentPosts, 
                    $minPosts, 
                    true, // Generate covers
                    $autoApprove
                );
            }
            
            return [
                'success' => false,
                'message' => 'Not enough recent popular posts to create libraries',
                'count' => $recentPosts->count(),
                'min_required' => $minPosts,
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error checking for recent posts to create libraries: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error checking for recent posts: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}