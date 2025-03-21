<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Post;
use App\Models\Readlist;
use App\Models\ReadlistItem;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class EnhancedCogniService extends CogniService
{
    /**
     * Generate a readlist based on a topic or learning goal
     *
     * @param array $params Parameters for readlist generation
     * @param User $user The current user
     * @return array Response with success/error status and readlist data
     */
    public function generateReadlist(array $params, User $user): array
    {
        try {
            // Extract parameters
            $topic = $params['topic'] ?? null;
            $skill = $params['skill'] ?? null;
            $level = $params['level'] ?? 'intermediate';
            $title = $params['title'] ?? ($topic ? "Learn $topic" : "Skill building: $skill");
            $description = $params['description'] ?? "Automatically generated readlist by Cogni";
            $maxItems = $params['max_items'] ?? 10;
            
            // Validate basic parameters
            if (!$topic && !$skill) {
                return [
                    'success' => false,
                    'message' => 'Either topic or skill is required',
                    'code' => 400
                ];
            }
            
            // Find relevant content
            $courses = [];
            $posts = [];
            
            // Topic-based search
            if ($topic) {
                // Find topic by name (case-insensitive)
                $topicModel = Topic::where(DB::raw('LOWER(name)'), strtolower($topic))->first();
                
                if ($topicModel) {
                    // Filter courses by difficulty level if specified
                    $coursesQuery = Course::where('topic_id', $topicModel->id);
                    if ($level && $level !== 'all') {
                        $coursesQuery->where('difficulty_level', $level);
                    }
                    
                    // Get courses with their metadata
                    $courses = $coursesQuery->with('user')
                        ->limit(max(2, $maxItems / 2))
                        ->get()
                        ->toArray();
                }
                
                // Find relevant posts that mention the topic
                $posts = Post::where('title', 'like', "%$topic%")
                    ->orWhere('body', 'like', "%$topic%")
                    ->with('user')
                    ->limit(max(2, $maxItems / 2))
                    ->get()
                    ->toArray();
            }
            
            // Skill-based search (more generic)
            if ($skill && (count($courses) + count($posts)) < $maxItems) {
                // Find additional courses mentioning the skill
                $additionalCourses = Course::where('title', 'like', "%$skill%")
                    ->orWhere('description', 'like', "%$skill%")
                    ->with('user')
                    ->limit(max(2, $maxItems / 2) - count($courses))
                    ->get()
                    ->toArray();
                
                $courses = array_merge($courses, $additionalCourses);
                
                // Find additional posts mentioning the skill
                $additionalPosts = Post::where('title', 'like', "%$skill%")
                    ->orWhere('body', 'like', "%$skill%")
                    ->with('user')
                    ->limit(max(2, $maxItems / 2) - count($posts))
                    ->get()
                    ->toArray();
                
                $posts = array_merge($posts, $additionalPosts);
            }
            
            // If we found enough content, create a readlist
            if (count($courses) + count($posts) > 0) {
                // Create a new readlist
                $readlist = new Readlist();
                $readlist->user_id = $user->id;
                $readlist->title = $title;
                $readlist->description = $description;
                $readlist->is_public = true;
                $readlist->share_key = Str::random(10);
                $readlist->save();
                
                // Add courses to the readlist
                $order = 0;
                foreach ($courses as $course) {
                    $this->addItemToReadlist($readlist, Course::find($course['id']), $order, 
                        "Recommended course for learning " . ($topic ?? $skill));
                    $order++;
                }
                
                // Add posts to the readlist
                foreach ($posts as $post) {
                    $this->addItemToReadlist($readlist, Post::find($post['id']), $order,
                        "Supplementary resource for " . ($topic ?? $skill));
                    $order++;
                }
                
                // Return the created readlist with share URL
                return [
                    'success' => true,
                    'message' => 'Readlist generated successfully',
                    'readlist' => [
                        'id' => $readlist->id,
                        'title' => $readlist->title,
                        'description' => $readlist->description,
                        'items_count' => $readlist->items()->count(),
                        'share_url' => url("/readlists/shared/{$readlist->share_key}")
                    ],
                    'code' => 201
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Could not find enough relevant content for the readlist',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Readlist generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate readlist: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Helper method to add an item to a readlist
     */
    private function addItemToReadlist(Readlist $readlist, $item, int $order, string $notes = null)
    {
        if (!$item) return;
        
        $readlistItem = new ReadlistItem();
        $readlistItem->readlist_id = $readlist->id;
        $readlistItem->item_id = $item->id;
        $readlistItem->item_type = get_class($item);
        $readlistItem->order = $order;
        $readlistItem->notes = $notes;
        $readlistItem->save();
    }
    
    /**
     * Analyze a readlist and provide enhanced insights
     *
     * @param int $readlistId The readlist ID to analyze
     * @return array Response with success/error status and analysis data
     */
    public function analyzeReadlist(int $readlistId): array
    {
        try {
            // Get the readlist with its items
            $readlist = Readlist::with('items.item')->findOrFail($readlistId);
            
            // Initialize analysis data
            $analysis = [
                'readlist_id' => $readlist->id,
                'title' => $readlist->title,
                'item_count' => $readlist->items->count(),
                'topics' => [],
                'difficulty_distribution' => [
                    'beginner' => 0,
                    'intermediate' => 0,
                    'advanced' => 0,
                    'unspecified' => 0
                ],
                'content_types' => [
                    'courses' => 0,
                    'posts' => 0
                ],
                'estimated_completion_time' => 0, // In minutes
                'key_concepts' => [],
                'summaries' => []
            ];
            
            // Analyze each item
            foreach ($readlist->items as $item) {
                // Track content types
                if ($item->item_type === 'App\\Models\\Course') {
                    $analysis['content_types']['courses']++;
                    
                    // Add course topic to topics list
                    $course = $item->item;
                    if ($course && $course->topic) {
                        $topicName = $course->topic->name;
                        if (!isset($analysis['topics'][$topicName])) {
                            $analysis['topics'][$topicName] = 0;
                        }
                        $analysis['topics'][$topicName]++;
                    }
                    
                    // Track difficulty level
                    $difficultyLevel = $course->difficulty_level ?? 'unspecified';
                    $analysis['difficulty_distribution'][$difficultyLevel]++;
                    
                    // Add to completion time
                    $analysis['estimated_completion_time'] += $course->duration_minutes ?? 120;
                    
                    // Extract key concepts using our AI prompt
                    if ($course) {
                        $conceptPrompt = "Extract 3-5 key concepts from this course: Title: {$course->title}, Description: {$course->description}";
                        $conceptResult = $this->askQuestion($conceptPrompt);
                        
                        if ($conceptResult['success']) {
                            $concepts = $this->parseConceptsFromResponse($conceptResult['answer']);
                            $analysis['key_concepts'] = array_merge($analysis['key_concepts'], $concepts);
                        }
                        
                        // Generate a summary
                        $analysis['summaries'][] = [
                            'item_id' => $course->id,
                            'item_type' => 'course',
                            'title' => $course->title,
                            'summary' => $this->generateSummary($course)
                        ];
                    }
                } elseif ($item->item_type === 'App\\Models\\Post') {
                    $analysis['content_types']['posts']++;
                    
                    $post = $item->item;
                    
                    // Extract concepts from post
                    if ($post) {
                        $conceptPrompt = "Extract 2-3 key concepts from this post: Title: {$post->title}, Content: " . substr($post->body, 0, 300);
                        $conceptResult = $this->askQuestion($conceptPrompt);
                        
                        if ($conceptResult['success']) {
                            $concepts = $this->parseConceptsFromResponse($conceptResult['answer']);
                            $analysis['key_concepts'] = array_merge($analysis['key_concepts'], $concepts);
                        }
                        
                        // Add to completion time (estimate reading time)
                        $wordCount = str_word_count(strip_tags($post->body));
                        $readingTimeMinutes = ceil($wordCount / 200); // Average reading speed
                        $analysis['estimated_completion_time'] += $readingTimeMinutes;
                        
                        // Generate a summary
                        $analysis['summaries'][] = [
                            'item_id' => $post->id,
                            'item_type' => 'post',
                            'title' => $post->title,
                            'summary' => $this->generateSummary($post)
                        ];
                    }
                }
            }
            
            // Format topics for output
            $formattedTopics = [];
            foreach ($analysis['topics'] as $topic => $count) {
                $formattedTopics[] = [
                    'name' => $topic,
                    'count' => $count
                ];
            }
            $analysis['topics'] = $formattedTopics;
            
            // Remove duplicate concepts and limit to 15
            $analysis['key_concepts'] = array_unique($analysis['key_concepts']);
            $analysis['key_concepts'] = array_slice($analysis['key_concepts'], 0, 15);
            
            // Format estimated completion time
            $totalMinutes = $analysis['estimated_completion_time'];
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            
            $analysis['estimated_completion_time_formatted'] = ($hours > 0 ? "$hours hour" . ($hours > 1 ? "s" : "") : "") .
                ($hours > 0 && $minutes > 0 ? " and " : "") .
                ($minutes > 0 ? "$minutes minute" . ($minutes > 1 ? "s" : "") : "");
            
            if ($totalMinutes <= 0) {
                $analysis['estimated_completion_time_formatted'] = "Less than 1 hour";
            }
            
            return [
                'success' => true,
                'analysis' => $analysis,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Readlist analysis failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to analyze readlist: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Parse concepts from an AI response
     */
    private function parseConceptsFromResponse(string $response): array
    {
        $concepts = [];
        
        // Check for list formats (numbered or bulleted)
        if (preg_match_all('/[\d\*\-•]+\.?\s*([^:\n]+)/', $response, $matches)) {
            foreach ($matches[1] as $match) {
                $concepts[] = trim($match);
            }
        } 
        // If no list format, split by sentences and take the first few
        else {
            $sentences = preg_split('/[.!?]/', $response, -1, PREG_SPLIT_NO_EMPTY);
            foreach (array_slice($sentences, 0, 5) as $sentence) {
                $concepts[] = trim($sentence);
            }
        }
        
        return $concepts;
    }
    
    /**
     * Generate a brief summary of content
     */
    private function generateSummary($item): string
    {
        if ($item instanceof Course) {
            return $item->description;
        } elseif ($item instanceof Post) {
            // For posts, generate a summary based on the first few paragraphs
            $text = strip_tags($item->body);
            $words = str_word_count($text, 1);
            
            if (count($words) <= 50) {
                return $text;
            }
            
            // Try to return first 50 words
            return implode(' ', array_slice($words, 0, 50)) . '...';
        }
        
        return 'Summary not available';
    }
    
    /**
     * Recommend additional content for a readlist
     *
     * @param int $readlistId The readlist ID
     * @param User $user The current user
     * @return array Response with success/error status and recommendations
     */
    public function recommendForReadlist(int $readlistId, User $user): array
    {
        try {
            $readlist = Readlist::with('items.item')->findOrFail($readlistId);
            
            // Extract topics and concepts from existing readlist items
            $topicIds = [];
            $keywords = [];
            $existingItemIds = [
                'courses' => [],
                'posts' => []
            ];
            
            foreach ($readlist->items as $item) {
                if ($item->item_type === Course::class && $item->item) {
                    $existingItemIds['courses'][] = $item->item->id;
                    
                    if ($item->item->topic_id) {
                        $topicIds[] = $item->item->topic_id;
                    }
                    
                    // Extract keywords from title and description
                    $courseText = $item->item->title . ' ' . $item->item->description;
                    $courseKeywords = $this->extractKeywords($courseText);
                    $keywords = array_merge($keywords, $courseKeywords);
                }
                elseif ($item->item_type === Post::class && $item->item) {
                    $existingItemIds['posts'][] = $item->item->id;
                    
                    // Extract keywords from title and body
                    $postText = $item->item->title . ' ' . $item->item->body;
                    $postKeywords = $this->extractKeywords($postText);
                    $keywords = array_merge($keywords, $postKeywords);
                }
            }
            
            // Unique arrays
            $topicIds = array_unique($topicIds);
            $keywords = array_unique($keywords);
            
            // Prepare recommendations
            $recommendations = [
                'courses' => [],
                'posts' => [],
                'optimal_order' => [],
                'gaps' => []
            ];
            
            // Find similar courses based on topics
            if (!empty($topicIds)) {
                $recommendedCourses = Course::whereIn('topic_id', $topicIds)
                    ->whereNotIn('id', $existingItemIds['courses'])
                    ->with('topic', 'user')
                    ->limit(5)
                    ->get();
                    
                foreach ($recommendedCourses as $course) {
                    $recommendations['courses'][] = [
                        'id' => $course->id,
                        'title' => $course->title,
                        'description' => $course->description,
                        'topic' => $course->topic ? $course->topic->name : null,
                        'author' => $course->user ? $course->user->username : null,
                        'reason' => 'Related to topics in your readlist'
                    ];
                }
            }
            
            // Find posts based on keywords
            if (!empty($keywords)) {
                $keywordConditions = [];
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 4) { // Only use meaningful keywords
                        $keywordConditions[] = ['title', 'like', "%$keyword%"];
                        $keywordConditions[] = ['body', 'like', "%$keyword%"];
                    }
                }
                
                if (!empty($keywordConditions)) {
                    $recommendedPosts = Post::where(function($query) use ($keywordConditions) {
                            foreach ($keywordConditions as $condition) {
                                $query->orWhere($condition[0], $condition[1], $condition[2]);
                            }
                        })
                        ->whereNotIn('id', $existingItemIds['posts'])
                        ->with('user')
                        ->limit(5)
                        ->get();
                        
                    foreach ($recommendedPosts as $post) {
                        $recommendations['posts'][] = [
                            'id' => $post->id,
                            'title' => $post->title,
                            'author' => $post->user ? $post->user->username : null,
                            'reason' => 'Contains concepts relevant to your readlist'
                        ];
                    }
                }
            }
            
            // Identify potential knowledge gaps
            $topicModels = Topic::whereIn('id', $topicIds)->get();
            $topicNames = $topicModels->pluck('name')->toArray();
            
            // Use the AI service to suggest knowledge gaps
            if (!empty($topicNames)) {
                $gapPrompt = "Based on these topics: " . implode(", ", $topicNames) . ", what are 3 important related concepts that might be missing from a study plan?";
                $gapResult = $this->askQuestion($gapPrompt);
                
                if ($gapResult['success']) {
                    $gapResponse = $gapResult['answer'];
                    $gaps = $this->parseConceptsFromResponse($gapResponse);
                    
                    foreach ($gaps as $gap) {
                        $recommendations['gaps'][] = [
                            'concept' => $gap,
                            'reason' => 'Important related knowledge to understand the topics fully'
                        ];
                    }
                }
            }
            
            // Suggest optimal learning order (if more than 3 items)
            if (count($readlist->items) > 3) {
                $orderPrompt = "I have a readlist with these items:";
                
                foreach ($readlist->items as $index => $item) {
                    $title = $item->item ? $item->item->title : "Unknown item";
                    $type = str_replace('App\\Models\\', '', $item->item_type);
                    $orderPrompt .= "\n" . ($index + 1) . ". " . $title . " (Type: " . $type . ")";
                }
                
                $orderPrompt .= "\nWhat would be the optimal learning order for these items? Just provide the new order as a numbered list.";
                
                $orderResult = $this->askQuestion($orderPrompt);
                
                if ($orderResult['success']) {
                    // Extract the suggested order from the response
                    preg_match_all('/\d+\.\s*([^\n]+)/', $orderResult['answer'], $matches);
                    if (!empty($matches[1])) {
                        $recommendations['optimal_order'] = $matches[1];
                    }
                }
            }
            
            return [
                'success' => true,
                'recommendations' => $recommendations,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Readlist recommendations failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate recommendations: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Extract keywords from text
     *
     * @param string $text The text to extract keywords from
     * @return array Array of keywords
     */
    private function extractKeywords(string $text): array
    {
        // Remove common words and special characters
        $stopWords = ['the', 'and', 'a', 'of', 'to', 'in', 'is', 'it', 'that', 'for', 'on', 'with'];
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        // Filter out stop words and short words
        $words = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) >= 4;
        });
        
        // Count word frequencies
        $wordCounts = array_count_values($words);
        
        // Sort by frequency
        arsort($wordCounts);
        
        // Return top keywords
        return array_slice(array_keys($wordCounts), 0, 10);
    }
    
    /**
     * Generate assessments based on readlist content
     *
     * @param int $readlistId The readlist ID
     * @return array Response with success/error status and assessments
     */
    public function generateAssessments(int $readlistId): array
    {
        try {
            $readlist = Readlist::with('items.item')->findOrFail($readlistId);
            
            if ($readlist->items->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Readlist has no items to generate assessments from',
                    'code' => 400
                ];
            }
            
            $assessments = [
                'readlist_id' => $readlist->id,
                'title' => $readlist->title,
                'quiz' => [
                    'title' => 'Knowledge Check: ' . $readlist->title,
                    'questions' => []
                ],
                'comprehension_questions' => [],
                'reflection_prompts' => []
            ];
            
            // Combine content from all items to create a comprehensive context
            $context = "This assessment is based on a readlist titled '{$readlist->title}' with the following items:\n";
            
            foreach ($readlist->items as $index => $item) {
                if (!$item->item) continue;
                
                $title = $item->item->title ?? 'Untitled';
                $context .= ($index + 1) . ". $title\n";
                
                if ($item->item instanceof Course) {
                    $context .= "   Description: " . substr($item->item->description, 0, 250) . "...\n";
                    
                    // Generate item-specific comprehension questions
                    $questionPrompt = "Generate 2 comprehension questions (not multiple choice) about this course: '{$item->item->title}' with description: '{$item->item->description}'";
                    $questionResult = $this->askQuestion($questionPrompt);
                    
                    if ($questionResult['success']) {
                        $questions = $this->parseComprehensionQuestions($questionResult['answer']);
                        foreach ($questions as $question) {
                            $assessments['comprehension_questions'][] = [
                                'item_id' => $item->item->id,
                                'item_title' => $item->item->title,
                                'question' => $question
                            ];
                        }
                    }
                } 
                elseif ($item->item instanceof Post) {
                    $excerpt = strip_tags(substr($item->item->body, 0, 250)) . "...";
                    $context .= "   Content: $excerpt\n";
                    
                    // Generate item-specific comprehension questions
                    $questionPrompt = "Generate 1 comprehension question (not multiple choice) about this content: Title: '{$item->item->title}', Content: '$excerpt'";
                    $questionResult = $this->askQuestion($questionPrompt);
                    
                    if ($questionResult['success']) {
                        $questions = $this->parseComprehensionQuestions($questionResult['answer']);
                        foreach ($questions as $question) {
                            $assessments['comprehension_questions'][] = [
                                'item_id' => $item->item->id,
                                'item_title' => $item->item->title,
                                'question' => $question
                            ];
                        }
                    }
                }
            }
            
            // Generate a quiz based on all content in the readlist
            $quizPrompt = "Based on this readlist content:\n$context\n\nCreate a quiz with 5 multiple choice questions. For each question, provide 4 answer options and indicate the correct answer index (0-3). Format as JSON with this structure: { \"questions\": [ { \"question\": \"Question text\", \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"], \"correctAnswer\": 0 } ] }";
            
            $quizResult = $this->askQuestion($quizPrompt);
            
            if ($quizResult['success']) {
                try {
                    // Extract JSON from the answer
                    $jsonStart = strpos($quizResult['answer'], '{');
                    $jsonEnd = strrpos($quizResult['answer'], '}') + 1;
                    
                    if ($jsonStart !== false && $jsonEnd !== false) {
                        $jsonStr = substr($quizResult['answer'], $jsonStart, $jsonEnd - $jsonStart);
                        $quiz = json_decode($jsonStr, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && isset($quiz['questions'])) {
                            $assessments['quiz']['questions'] = $quiz['questions'];
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Quiz parsing failed: ' . $e->getMessage());
                }
            }
            
            // Generate reflection prompts
            $reflectionPrompt = "Based on this readlist content:\n$context\n\nGenerate 3 reflection prompts that would help a learner integrate and apply this knowledge. Each prompt should be a paragraph.";
            
            $reflectionResult = $this->askQuestion($reflectionPrompt);
            
            if ($reflectionResult['success']) {
                $reflections = $this->parseReflectionPrompts($reflectionResult['answer']);
                $assessments['reflection_prompts'] = $reflections;
            }
            
            return [
                'success' => true,
                'assessments' => $assessments,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Assessment generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate assessments: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Parse comprehension questions from AI response
     */
    private function parseComprehensionQuestions(string $response): array
    {
        $questions = [];
        
        // Match numbered or bulleted lists
        if (preg_match_all('/[\d\*\-•]+\.?\s*([^\n]+)/', $response, $matches)) {
            foreach ($matches[1] as $match) {
                $questions[] = trim($match);
            }
        } 
        // If no list format, split by sentences and filter for questions
        else {
            $sentences = preg_split('/[.!?]/', $response, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                // Only include if it looks like a question
                if (strpos($sentence, '?') !== false || 
                    preg_match('/^(what|how|why|when|where|who|explain|describe|discuss)/i', $sentence)) {
                    $questions[] = $sentence . (strpos($sentence, '?') === false ? '?' : '');
                }
            }
        }
        
        return $questions;
    }
    
    /**
     * Parse reflection prompts from AI response
     */
    private function parseReflectionPrompts(string $response): array
    {
        $prompts = [];
        
        // Try to extract numbered prompts first
        if (preg_match_all('/[\d\*\-•]+\.?\s*([^\n]+(?:\n[^\d\*\-•][^\n]+)*)/', $response, $matches)) {
            foreach ($matches[1] as $match) {
                $prompts[] = trim($match);
            }
        } 
        // If that fails, split by double newlines
        else {
            $paragraphs = preg_split('/\n\s*\n/', $response, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($paragraphs as $paragraph) {
                $prompts[] = trim($paragraph);
            }
        }
        
        return $prompts;
    }
    
    /**
     * Create a study plan for a readlist
     *
     /**
     * Create a study plan for a readlist
     *
     * @param int $readlistId The readlist ID
     * @param array $params Additional parameters for the plan
     * @return array Response with success/error status and study plan
     */
    public function createStudyPlan(int $readlistId, array $params = []): array
    {
        try {
            $readlist = Readlist::with('items.item')->findOrFail($readlistId);
            
            if ($readlist->items->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Readlist has no items to create a study plan from',
                    'code' => 400
                ];
            }
            
            // Extract plan parameters
            $daysAvailable = $params['days_available'] ?? 7;
            $hoursPerDay = $params['hours_per_day'] ?? 2;
            $learningStyle = $params['learning_style'] ?? 'balanced';
            $includeAssessments = $params['include_assessments'] ?? true;
            
            // Calculate total study time available in minutes
            $totalAvailableMinutes = $daysAvailable * $hoursPerDay * 60;
            
            // Calculate estimated time needed for all items
            $itemEstimates = [];
            $totalEstimatedMinutes = 0;
            
            foreach ($readlist->items as $item) {
                if (!$item->item) continue;
                
                $estimatedMinutes = 0;
                
                if ($item->item instanceof Course) {
                    // Use course duration if available, otherwise estimate
                    $estimatedMinutes = $item->item->duration_minutes ?? 120;
                }
                elseif ($item->item instanceof Post) {
                    // Estimate reading time based on word count
                    $wordCount = str_word_count(strip_tags($item->item->body));
                    $estimatedMinutes = ceil($wordCount / 200); // Average reading speed
                }
                
                // Adjust based on learning style
                if ($learningStyle === 'deep') {
                    $estimatedMinutes *= 1.3; // 30% more time for deep learning
                } elseif ($learningStyle === 'quick') {
                    $estimatedMinutes *= 0.8; // 20% less time for quick overview
                }
                
                $itemEstimates[] = [
                    'item_id' => $item->item->id,
                    'item_type' => get_class($item->item),
                    'title' => $item->item->title,
                    'estimated_minutes' => $estimatedMinutes
                ];
                
                $totalEstimatedMinutes += $estimatedMinutes;
            }
            
            // Add assessment time if requested
            if ($includeAssessments) {
                $assessmentTime = max(30, $totalEstimatedMinutes * 0.1); // At least 30 minutes or 10% of content time
                $totalEstimatedMinutes += $assessmentTime;
            }
            
            // Check if the plan fits into available time
            $feasible = $totalEstimatedMinutes <= $totalAvailableMinutes;
            
            // Create the schedule by distributing content across available days
            $schedule = [];
            $remainingMinutes = $totalEstimatedMinutes;
            $currentDayMinutes = 0;
            $currentDay = 1;
            $currentItems = [];
            
            // Distribute items across days
            foreach ($itemEstimates as $item) {
                // If adding this item would exceed daily limit, move to next day
                if ($currentDayMinutes + $item['estimated_minutes'] > ($hoursPerDay * 60) && !empty($currentItems)) {
                    $schedule[] = [
                        'day' => $currentDay,
                        'items' => $currentItems,
                        'total_minutes' => $currentDayMinutes
                    ];
                    
                    $currentDay++;
                    $currentItems = [];
                    $currentDayMinutes = 0;
                }
                
                // If this item alone exceeds daily limit, split it across days
                if ($item['estimated_minutes'] > ($hoursPerDay * 60)) {
                    $remainingItemMinutes = $item['estimated_minutes'];
                    $partNumber = 1;
                    
                    while ($remainingItemMinutes > 0) {
                        $partMinutes = min($remainingItemMinutes, $hoursPerDay * 60);
                        
                        $currentItems[] = [
                            'id' => $item['item_id'],
                            'type' => $item['item_type'],
                            'title' => $item['title'] . " (Part $partNumber)",
                            'minutes' => $partMinutes
                        ];
                        
                        $currentDayMinutes += $partMinutes;
                        $remainingItemMinutes -= $partMinutes;
                        $remainingMinutes -= $partMinutes;
                        $partNumber++;
                        
                        if ($remainingItemMinutes > 0) {
                            $schedule[] = [
                                'day' => $currentDay,
                                'items' => $currentItems,
                                'total_minutes' => $currentDayMinutes
                            ];
                            
                            $currentDay++;
                            $currentItems = [];
                            $currentDayMinutes = 0;
                        }
                    }
                } else {
                    // Add item to current day
                    $currentItems[] = [
                        'id' => $item['item_id'],
                        'type' => $item['item_type'],
                        'title' => $item['title'],
                        'minutes' => $item['estimated_minutes']
                    ];
                    
                    $currentDayMinutes += $item['estimated_minutes'];
                    $remainingMinutes -= $item['estimated_minutes'];
                }
                
                // If we've reached the maximum days, stop
                if ($currentDay > $daysAvailable) {
                    break;
                }
            }
            
            // Add final day if it has items
            if (!empty($currentItems)) {
                $schedule[] = [
                    'day' => $currentDay,
                    'items' => $currentItems,
                    'total_minutes' => $currentDayMinutes
                ];
            }
            
            // Add assessments to the last day if requested
            if ($includeAssessments && $currentDay <= $daysAvailable) {
                $lastDay = end($schedule);
                
                if ($lastDay['total_minutes'] + $assessmentTime <= ($hoursPerDay * 60)) {
                    // Add to the last day if there's room
                    $lastDayIndex = count($schedule) - 1;
                    $schedule[$lastDayIndex]['items'][] = [
                        'id' => 'assessment',
                        'type' => 'assessment',
                        'title' => 'Review and Self-Assessment',
                        'minutes' => $assessmentTime
                    ];
                    $schedule[$lastDayIndex]['total_minutes'] += $assessmentTime;
                } else {
                    // Create a new day for assessment
                    $schedule[] = [
                        'day' => $currentDay + 1,
                        'items' => [[
                            'id' => 'assessment',
                            'type' => 'assessment',
                            'title' => 'Review and Self-Assessment',
                            'minutes' => $assessmentTime
                        ]],
                        'total_minutes' => $assessmentTime
                    ];
                }
            }
            
            // Generate spaced repetition schedule
            $spacedRepetition = [];
            if (count($schedule) >= 3) {
                // Get a sampling of important items for review
                $itemPool = [];
                foreach ($itemEstimates as $item) {
                    $itemPool[] = [
                        'id' => $item['item_id'],
                        'type' => $item['item_type'],
                        'title' => $item['title'],
                    ];
                }
                
                // Schedule reviews at increasing intervals (1 day, 3 days, 7 days, etc.)
                $reviewIntervals = [1, 3, 7, 14, 30];
                foreach ($reviewIntervals as $interval) {
                    $reviewDate = date('Y-m-d', strtotime("+$interval days"));
                    $reviewItems = array_slice($itemPool, 0, min(3, count($itemPool)));
                    
                    $spacedRepetition[] = [
                        'date' => $reviewDate,
                        'days_after' => $interval,
                        'items' => $reviewItems,
                        'duration' => '15-30 minutes'
                    ];
                }
            }
            
            // Create study plan object
            $studyPlan = [
                'readlist_id' => $readlist->id,
                'title' => 'Study Plan: ' . $readlist->title,
                'total_estimated_minutes' => $totalEstimatedMinutes,
                'total_estimated_hours' => round($totalEstimatedMinutes / 60, 1),
                'days_required' => $currentDay,
                'hours_per_day' => $hoursPerDay,
                'is_feasible' => $feasible,
                'schedule' => $schedule,
                'spaced_repetition' => $spacedRepetition,
                'learning_style' => $learningStyle,
                'recommendations' => []
            ];
            
            // Add recommendations if plan is not feasible
            if (!$feasible) {
                $studyPlan['recommendations'][] = [
                    'type' => 'time_extension',
                    'message' => "This plan requires " . ceil($totalEstimatedMinutes / ($hoursPerDay * 60)) . 
                                " days at " . $hoursPerDay . " hours per day. Consider extending your timeline."
                ];
                
                // Suggest priority items if there's a shortage of time
                if ($currentDay > $daysAvailable) {
                    $studyPlan['recommendations'][] = [
                        'type' => 'priority_focus',
                        'message' => "Focus on the first " . count($schedule) . " days of content as your highest priority items."
                    ];
                }
            }
            
            return [
                'success' => true,
                'study_plan' => $studyPlan,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Study plan creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create study plan: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Recommend educators based on user profile and interests
     *
     * @param User $user The user to recommend educators for
     * @param array $params Additional parameters for recommendations
     * @return array Response with success/error status and educator recommendations
     */
    public function recommendEducators(User $user, array $params = []): array
    {
        try {
            // Extract parameters
            $count = $params['count'] ?? 5;
            $topicId = $params['topic_id'] ?? null;
            $difficultyLevel = $params['difficulty_level'] ?? null;
            
            // Get user's topics of interest
            $userTopicIds = $user->topic()->pluck('topic_id')->toArray();
            
            // If specific topic provided, use that instead
            if ($topicId) {
                $topicIds = [$topicId];
            } else {
                $topicIds = $userTopicIds;
                
                // If user has no topics, don't filter by topics
                if (empty($topicIds)) {
                    $topicIds = Topic::pluck('id')->toArray();
                }
            }
            
            // Start building query for educators
            $educatorQuery = User::where('role', User::ROLE_EDUCATOR)
                ->where('id', '!=', $user->id); // Exclude the user themselves
                
            // Get all educators first
            $educators = $educatorQuery->get();
            $scoredEducators = [];
            
            foreach ($educators as $educator) {
                // Calculate base score (even for educators with no courses)
                $score = 0;
                
                // Topic match score
                $educatorTopicIds = $educator->topic()->pluck('topic_id')->toArray();
                $topicMatches = array_intersect($topicIds, $educatorTopicIds);
                $topicMatchScore = count($topicMatches) * 20;
                
                // Add some points even if the educator has no courses yet
                // This ensures new educators still get recommended
                $courseCount = $educator->courses()->count();
                $courseCountScore = min(30, $courseCount * 5);
                
                // If educator has no courses, give them a small base score
                if ($courseCount == 0) {
                    $courseCountScore = 5;
                }
                
                // Course quality/difficulty match score
                $difficultyMatchScore = 0;
                $qualityCourseCount = 0;
                
                // Only evaluate courses if the educator has them
                if ($courseCount > 0) {
                    $courses = $educator->courses;
                    
                    foreach ($courses as $course) {
                        // Does the course match requested difficulty?
                        if (!$difficultyLevel || $course->difficulty_level === $difficultyLevel) {
                            $difficultyMatchScore += 5;
                            $qualityCourseCount++;
                        }
                        
                        // Add points for courses in user's topics of interest
                        if (in_array($course->topic_id, $topicIds)) {
                            $difficultyMatchScore += 10;
                        }
                    }
                    
                    // Cap difficulty match score
                    $difficultyMatchScore = min(50, $difficultyMatchScore);
                }
                
                // Calculate total score
                $score = $topicMatchScore + $courseCountScore + $difficultyMatchScore;
                
                // Include all educators with a minimum score or who have matching topics
                // Modified to be more inclusive
                if ($score > 0 || !empty($topicMatches)) {
                    $scoredEducators[] = [
                        'educator' => $educator,
                        'score' => $score,
                        'topic_match_count' => count($topicMatches),
                        'quality_course_count' => $qualityCourseCount
                    ];
                }
            }
            
            // Sort by score
            usort($scoredEducators, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Take top results
            $scoredEducators = array_slice($scoredEducators, 0, $count);
            
            // Format results
            $recommendations = [];
            foreach ($scoredEducators as $scoredEducator) {
                $educator = $scoredEducator['educator'];
                
                // Generate personalized recommendation reason
                $reason = $this->generateEducatorRecommendationReason(
                    $educator, 
                    $scoredEducator['topic_match_count'],
                    $scoredEducator['quality_course_count'],
                    $topicIds
                );
                
                // Get top courses by this educator (if any)
                $topCourses = [];
                if ($educator->courses()->count() > 0) {
                    $topCourses = $educator->courses()
                        ->when($difficultyLevel, function($query) use ($difficultyLevel) {
                            return $query->where('difficulty_level', $difficultyLevel);
                        })
                        ->when($topicIds, function($query) use ($topicIds) {
                            return $query->whereIn('topic_id', $topicIds);
                        })
                        ->orderBy('id', 'desc') // Using ID as a proxy for recency
                        ->limit(3)
                        ->get()
                        ->map(function($course) {
                            return [
                                'id' => $course->id,
                                'title' => $course->title,
                                'topic' => $course->topic ? $course->topic->name : null,
                                'difficulty_level' => $course->difficulty_level
                            ];
                        })
                        ->toArray();
                }
                
                $recommendations[] = [
                    'id' => $educator->id,
                    'username' => $educator->username,
                    'first_name' => $educator->first_name,
                    'last_name' => $educator->last_name,
                    'avatar' => $educator->avatar,
                    'bio' => $educator->profile ? $educator->profile->bio : null,
                    'relevance_score' => $scoredEducator['score'],
                    'recommendation_reason' => $reason,
                    'top_courses' => $topCourses,
                    'topics' => $educator->topic()->pluck('name')->toArray()
                ];
            }
            
            return [
                'success' => true,
                'recommendations' => $recommendations,
                'code' => 200
            ];
            
        } catch (\Exception $e) {
            Log::error('Educator recommendations failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to recommend educators: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Generate a personalized recommendation reason for an educator
     */
    private function generateEducatorRecommendationReason(User $educator, int $topicMatchCount, int $courseCount, array $userTopicIds): string
    {
        // Get topic names
        $educatorTopics = $educator->topic()->pluck('name')->toArray();
        $matchedTopicNames = [];
        
        if ($topicMatchCount > 0 && !empty($educatorTopics)) {
            // Get only the first 2 topics to keep it concise
            $matchedTopicNames = array_slice($educatorTopics, 0, 2);
        }
        
        // Generate different reason types based on available data
        if (!empty($matchedTopicNames)) {
            return "Specializes in " . implode(" and ", $matchedTopicNames) . 
                   ($courseCount > 0 ? " with " . $courseCount . " relevant " . ($courseCount == 1 ? "course" : "courses") : "");
        } elseif ($courseCount >= 3) {
            return "Experienced educator with " . $courseCount . " well-structured courses";
        } elseif ($courseCount > 0) {
            return "Creates educational content that may interest you";
        } else {
            return "Educator with expertise in " . (!empty($educatorTopics) ? implode(", ", array_slice($educatorTopics, 0, 2)) : "relevant topics");
        }
    }
}