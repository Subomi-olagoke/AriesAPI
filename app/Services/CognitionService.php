<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Readlist;
use App\Models\ReadlistItem;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CognitionService
{
    protected $cogniService;
    
    public function __construct(CogniService $cogniService)
    {
        $this->cogniService = $cogniService;
    }
    
    /**
     * Create or get a user's Cognition readlist
     *
     * @param User $user The user to create/get the readlist for
     * @return Readlist The user's Cognition readlist
     */
    public function getCognitionReadlist(User $user): Readlist
    {
        // Try to find an existing Cognition readlist
        $readlist = $user->readlists()
            ->where('title', 'Cognition')
            ->where('is_system', true)
            ->first();
            
        // Create a new one if it doesn't exist
        if (!$readlist) {
            $readlist = new Readlist([
                'title' => 'Cognition',
                'description' => 'Your personalized learning feed powered by Cogni AI',
                'is_public' => false,
                'is_system' => true,
                'share_key' => Str::random(10),
            ]);
            
            $user->readlists()->save($readlist);
        }
        
        return $readlist;
    }
    
    /**
     * Generate a user interest profile based on their activity
     *
     * @param User $user The user to profile
     * @return array An array of topics with confidence scores
     */
    public function generateUserInterestProfile(User $user): array
    {
        // Collect data about the user's activity
        $data = $this->collectUserActivityData($user);
        
        // Use Cogni to analyze the data and generate a profile
        $response = $this->cogniService->analyzeUserInterests($data);
        
        if (!$response['success']) {
            Log::error('Failed to generate user interest profile', ['user_id' => $user->id, 'error' => $response['message'] ?? 'Unknown error']);
            return ['interests' => []]; 
        }
        
        try {
            // Extract JSON from the response
            $jsonStart = strpos($response['answer'], '{');
            $jsonEnd = strrpos($response['answer'], '}') + 1;
            $jsonStr = substr($response['answer'], $jsonStart, $jsonEnd - $jsonStart);
            
            $profile = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($profile['interests'])) {
                return $profile;
            }
            
            Log::error('Failed to parse interest profile JSON', ['user_id' => $user->id, 'response' => $response['answer']]);
            return ['interests' => []];
        } catch (\Exception $e) {
            Log::error('Error processing interest profile: ' . $e->getMessage(), ['user_id' => $user->id]);
            return ['interests' => []];
        }
    }
    
    /**
     * Collect data about a user's activity to generate their profile
     *
     * @param User $user The user to collect data for
     * @return array Array of user activity data
     */
    private function collectUserActivityData(User $user): array
    {
        $data = [
            'selected_topics' => [],
            'posts' => [],
            'comments' => [],
            'likes' => [],
            'bookmarks' => [],
            'enrolled_courses' => [],
            'readlists' => [],
        ];
        
        // Get user's selected topics
        $userTopics = $user->topic()->with('category')->get();
        foreach ($userTopics as $topic) {
            $data['selected_topics'][] = [
                'name' => $topic->name,
                'category' => $topic->category->name ?? 'Unknown',
            ];
        }
        
        // Get user's posts (max 10 most recent)
        $posts = $user->posts()->latest()->take(10)->get();
        foreach ($posts as $post) {
            $data['posts'][] = [
                'title' => $post->title,
                'content' => Str::limit(strip_tags($post->content), 200),
            ];
        }
        
        // Get user's comments (max 10 most recent)
        $comments = $user->comments()->latest()->take(10)->get();
        foreach ($comments as $comment) {
            $postTitle = $comment->post->title ?? 'Unknown post';
            $data['comments'][] = [
                'post_title' => $postTitle,
                'content' => Str::limit(strip_tags($comment->content), 100),
            ];
        }
        
        // Get posts the user has liked (max 10 most recent)
        $likes = $user->likes()->where('likeable_type', Post::class)->latest()->take(10)->get();
        foreach ($likes as $like) {
            $post = Post::find($like->likeable_id);
            if ($post) {
                $data['likes'][] = [
                    'title' => $post->title,
                    'content_snippet' => Str::limit(strip_tags($post->content), 100),
                ];
            }
        }
        
        // Get bookmarked content (max 10 most recent)
        $bookmarks = $user->bookmarks()->latest()->take(10)->get();
        foreach ($bookmarks as $bookmark) {
            if ($bookmark->bookmarkable_type === Post::class) {
                $post = Post::find($bookmark->bookmarkable_id);
                if ($post) {
                    $data['bookmarks'][] = [
                        'type' => 'post',
                        'title' => $post->title,
                    ];
                }
            }
        }
        
        // Get enrolled courses (max 5 most recent)
        $enrollments = $user->enrollments()->with('course')->latest()->take(5)->get();
        foreach ($enrollments as $enrollment) {
            if ($enrollment->course) {
                $data['enrolled_courses'][] = [
                    'title' => $enrollment->course->title,
                    'description' => Str::limit($enrollment->course->description, 150),
                    'topics' => $enrollment->course->topics->pluck('name')->toArray(),
                ];
            }
        }
        
        // Get user's readlists (max 5 most recent, excluding system ones)
        $readlists = $user->readlists()->where('is_system', false)->latest()->take(5)->get();
        foreach ($readlists as $readlist) {
            $data['readlists'][] = [
                'title' => $readlist->title,
                'description' => $readlist->description,
                'items_count' => $readlist->items->count(),
            ];
        }
        
        return $data;
    }
    
    /**
     * Fetch web resources based on user interests and add them to their Cognition readlist
     *
     * @param User $user The user to fetch resources for
     * @param array $interests User interest profile
     * @param int $maxItems Maximum number of items to add per update (default: 5)
     * @return bool Success status
     */
    public function fetchAndAddResources(User $user, array $interests, int $maxItems = 5): bool
    {
        // Get or create the Cognition readlist
        $readlist = $this->getCognitionReadlist($user);
        
        // Extract main topics from interests
        $topics = [];
        foreach ($interests['interests'] as $interest) {
            $topics[] = $interest['topic'];
            
            // Add a few subtopics for more variety if available
            if (!empty($interest['subtopics']) && $interest['confidence'] > 70) {
                $subtopics = array_slice($interest['subtopics'], 0, 2);
                foreach ($subtopics as $subtopic) {
                    $topics[] = $subtopic;
                }
            }
        }
        
        // Limit to the most relevant topics
        $topics = array_slice($topics, 0, 5);
        
        if (empty($topics)) {
            Log::warning('No topics found for user\'s Cognition readlist', ['user_id' => $user->id]);
            return false;
        }
        
        // Fetch resources for the topics
        $resources = $this->fetchWebResources($topics, $maxItems);
        
        if (empty($resources)) {
            Log::warning('No resources found for user\'s Cognition readlist', ['user_id' => $user->id, 'topics' => $topics]);
            return false;
        }
        
        // Add resources to the readlist
        $addedCount = 0;
        foreach ($resources as $resource) {
            // Check if this URL is already in the readlist
            $exists = $readlist->items()
                ->where('url', $resource['url'])
                ->exists();
                
            if ($exists) {
                continue;
            }
            
            // Create a new readlist item
            $item = new ReadlistItem([
                'title' => $resource['title'],
                'description' => $resource['description'],
                'url' => $resource['url'],
                'type' => 'link',
                'notes' => $resource['notes'] ?? '',
                'order' => $readlist->items()->count() + 1,
            ]);
            
            $readlist->items()->save($item);
            $addedCount++;
            
            if ($addedCount >= $maxItems) {
                break;
            }
        }
        
        Log::info('Added resources to Cognition readlist', [
            'user_id' => $user->id, 
            'readlist_id' => $readlist->id,
            'added_count' => $addedCount
        ]);
        
        return true;
    }
    
    /**
     * Fetch web resources for given topics
     *
     * @param array $topics Topics to fetch resources for
     * @param int $maxItems Maximum number of items to fetch
     * @return array Array of resource items
     */
    private function fetchWebResources(array $topics, int $maxItems): array
    {
        // Use Cogni service to find web resources
        $response = $this->cogniService->findWebResources($topics, $maxItems);
        
        if (!$response['success']) {
            Log::error('Failed to generate web resources', ['topics' => $topics, 'error' => $response['message'] ?? 'Unknown error']);
            return [];
        }
        
        try {
            // Extract JSON from the response
            $jsonStart = strpos($response['answer'], '[');
            $jsonEnd = strrpos($response['answer'], ']') + 1;
            
            if ($jsonStart === false || $jsonEnd === false) {
                // Try for object format if array format isn't found
                $jsonStart = strpos($response['answer'], '{');
                $jsonEnd = strrpos($response['answer'], '}') + 1;
                
                if ($jsonStart === false || $jsonEnd === false) {
                    throw new \Exception('Cannot find JSON in response');
                }
            }
            
            $jsonStr = substr($response['answer'], $jsonStart, $jsonEnd - $jsonStart);
            $resources = json_decode($jsonStr, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
            
            // If we got an object instead of an array, check if it has a resources property
            if (isset($resources['resources']) && is_array($resources['resources'])) {
                $resources = $resources['resources'];
            }
            
            // Ensure the resources array has the expected structure
            $validResources = [];
            foreach ($resources as $resource) {
                if (isset($resource['title'], $resource['url'])) {
                    // Ensure description exists
                    if (!isset($resource['description'])) {
                        $resource['description'] = 'A resource about ' . $resource['title'];
                    }
                    
                    // Ensure notes exist
                    if (!isset($resource['notes'])) {
                        $resource['notes'] = 'Added by Cogni to your personalized learning feed';
                    }
                    
                    $validResources[] = $resource;
                }
            }
            
            return $validResources;
        } catch (\Exception $e) {
            Log::error('Error processing web resources: ' . $e->getMessage(), ['response' => $response['answer']]);
            return [];
        }
    }
    
    /**
     * Update a user's Cognition readlist
     *
     * @param User $user The user to update the readlist for
     * @param int $maxItems Maximum number of items to add per update (default: 5)
     * @return bool Success status
     */
    public function updateCognitionReadlist(User $user, int $maxItems = 5): bool
    {
        try {
            // Generate the user interest profile
            $profile = $this->generateUserInterestProfile($user);
            
            if (empty($profile['interests'])) {
                Log::info('No interests found for user', ['user_id' => $user->id]);
                return false;
            }
            
            // Fetch and add resources based on the profile
            return $this->fetchAndAddResources($user, $profile, $maxItems);
        } catch (\Exception $e) {
            Log::error('Error updating Cognition readlist: ' . $e->getMessage(), ['user_id' => $user->id]);
            return false;
        }
    }
}