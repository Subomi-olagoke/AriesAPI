<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\LibraryContent;
use App\Models\Post;
use App\Models\User;
use App\Services\LibraryApiService;
use App\Services\OpenLibraryService;
use App\Services\AICoverImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminLibraryController extends Controller
{
    /**
     * The library API service instance
     */
    protected $libraryApiService;
    
    /**
     * The open library service for AI-powered functions
     */
    protected $openLibraryService;
    
    /**
     * The AI cover image service
     */
    protected $coverImageService;
    
    /**
     * Create a new controller instance
     */
    public function __construct(
        LibraryApiService $libraryApiService,
        OpenLibraryService $openLibraryService,
        AICoverImageService $coverImageService
    ) {
        $this->libraryApiService = $libraryApiService;
        $this->openLibraryService = $openLibraryService;
        $this->coverImageService = $coverImageService;
    }
    
    /**
     * Display a listing of libraries with filtering options
     */
    public function listLibraries(Request $request)
    {
        $query = OpenLibrary::with('contents');
        
        // Apply filters if provided
        if ($request->has('status')) {
            $query->where('approval_status', $request->status);
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }
        
        // Apply sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);
        
        // Get libraries with content count
        $libraries = $query->withCount('contents')->paginate(10);
        
        return view('admin.libraries.index', [
            'libraries' => $libraries,
            'filters' => [
                'status' => $request->status,
                'search' => $request->search,
                'sort_by' => $sortField,
                'sort_dir' => $sortDir
            ]
        ]);
    }
    
    /**
     * Show the form for creating a new library
     */
    public function create()
    {
        // Get courses for dropdown and content selection
        $courses = \App\Models\Course::select('id', 'title', 'description', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
        
        // Get posts for content selection
        $posts = \App\Models\Post::select('id', 'title', 'body', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
        
        return view('admin.libraries.create', [
            'courses' => $courses,
            'posts' => $posts
        ]);
    }
    
    /**
     * Store a newly created library in storage
     */
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'type' => 'required|string|in:curated,dynamic,course',
            'course_id' => 'nullable|required_if:type,course',
            'thumbnail_url' => 'nullable|url|max:2000',
            'generate_cover' => 'nullable|boolean',
            'selected_content' => 'nullable|array',
            'selected_content.*.id' => 'required_with:selected_content|string',
            'selected_content.*.type' => 'required_with:selected_content|string|in:Course,Post',
            'selected_content.*.relevance_score' => 'nullable|numeric|min:0|max:1',
        ]);
        
        try {
            // Create library data
            $libraryData = [
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'thumbnail_url' => $request->thumbnail_url,
                'approval_status' => 'pending',
                'is_approved' => false,
            ];
            
            if ($request->type === 'course' && $request->course_id) {
                $libraryData['course_id'] = $request->course_id;
            }
            
            // Check if AI cover generation was requested
            $generateCover = $request->has('generate_cover') && $request->generate_cover == 1;
            
            // Create the library via API
            $response = $this->libraryApiService->createLibrary($libraryData);
            
            if (!$response['success']) {
                // If the API request failed, log the error and create the library locally
                Log::error('Failed to create library via API', [
                    'message' => $response['message'] ?? 'Unknown error',
                    'errors' => $response['errors'] ?? []
                ]);
                
                // Create locally as fallback
                $library = new OpenLibrary();
                $library->name = $request->name;
                $library->description = $request->description;
                $library->type = $request->type;
                $library->course_id = $request->course_id;
                $library->thumbnail_url = $request->thumbnail_url;
                $library->approval_status = 'pending';
                $library->is_approved = false;
                $library->save();
                
                // Process content selection for curated libraries
                if ($request->type === 'curated' && $request->has('selected_content')) {
                    $this->addInitialContentToLibrary($library, $request->selected_content);
                }
                
                // Generate cover if requested
                if ($generateCover) {
                    $coverUrl = $this->generateCoverImage($library);
                    if ($coverUrl) {
                        $library->cover_image_url = $coverUrl;
                        $library->thumbnail_url = $coverUrl;
                        $library->has_ai_cover = true;
                        $library->save();
                    }
                }
                
                // Return with warning
                return redirect()->route('admin.libraries.view', $library->id)
                    ->with('warning', 'Library created locally but failed to sync with API. Some features may be limited.');
            }
            
            // If successful, retrieve the library ID from the API response
            $libraryId = $response['data']['library']['id'] ?? null;
            
            if (!$libraryId) {
                throw new \Exception('Library ID not found in API response');
            }
            
            // Get the created library
            $library = OpenLibrary::findOrFail($libraryId);
            
            // Process content selection for curated libraries
            if ($request->type === 'curated' && $request->has('selected_content')) {
                $this->addInitialContentToLibrary($library, $request->selected_content);
            }
            
            // Generate cover if requested
            if ($generateCover) {
                $coverUrl = $this->generateCoverImage($library);
                if ($coverUrl) {
                    $library->cover_image_url = $coverUrl;
                    $library->thumbnail_url = $coverUrl;
                    $library->has_ai_cover = true;
                    $library->save();
                }
            }
            
            // Redirect to the library view page
            return redirect()->route('admin.libraries.view', $libraryId)
                ->with('success', 'Library created successfully with ' . count($request->selected_content ?? []) . ' content items.');
                
        } catch (\Exception $e) {
            Log::error('Error creating library: ' . $e->getMessage());
            
            // Create locally as fallback
            $library = new OpenLibrary();
            $library->name = $request->name;
            $library->description = $request->description;
            $library->type = $request->type;
            $library->course_id = $request->course_id;
            $library->thumbnail_url = $request->thumbnail_url;
            
            // Check if approval_status column exists before trying to use it
            if (Schema::hasColumn('open_libraries', 'approval_status')) {
                $library->approval_status = 'pending';
            }
            
            $library->is_approved = false;
            $library->save();
            
            // Process content selection for curated libraries
            if ($request->type === 'curated' && $request->has('selected_content')) {
                $this->addInitialContentToLibrary($library, $request->selected_content);
            }
            
            // Generate cover if requested
            if ($generateCover) {
                $coverUrl = $this->generateCoverImage($library);
                if ($coverUrl) {
                    $library->cover_image_url = $coverUrl;
                    $library->thumbnail_url = $coverUrl;
                    $library->has_ai_cover = true;
                    $library->save();
                }
            }
            
            return redirect()->route('admin.libraries.view', $library->id)
                ->with('warning', 'Library created locally due to API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Add initial content items to a newly created library
     *
     * @param OpenLibrary $library The library to add content to
     * @param array $selectedContent Array of selected content items
     * @return void
     */
    private function addInitialContentToLibrary(OpenLibrary $library, array $selectedContent)
    {
        // Map content types to model classes
        $contentTypeMap = [
            'Course' => \App\Models\Course::class,
            'Post' => \App\Models\Post::class
        ];
        
        foreach ($selectedContent as $content) {
            // Skip if id or type is missing
            if (!isset($content['id']) || !isset($content['type'])) {
                continue;
            }
            
            $contentType = $contentTypeMap[$content['type']] ?? null;
            
            // Skip if content type is not recognized
            if (!$contentType) {
                continue;
            }
            
            // Check if content exists
            $contentItem = $contentType::find($content['id']);
            if (!$contentItem) {
                continue;
            }
            
            // Check for duplicates
            $existingContent = LibraryContent::where('library_id', $library->id)
                ->where('content_type', $contentType)
                ->where('content_id', $content['id'])
                ->first();
                
            if ($existingContent) {
                continue;
            }
            
            // Set relevance score (default to 0.7 if not provided)
            $relevanceScore = isset($content['relevance_score']) ? 
                floatval($content['relevance_score']) : 0.7;
            
            // Create the library content association
            LibraryContent::create([
                'library_id' => $library->id,
                'content_id' => $content['id'],
                'content_type' => $contentType,
                'relevance_score' => $relevanceScore
            ]);
        }
    }
    
    /**
     * Display a specific library with its contents
     */
    public function viewLibrary($id)
    {
        $library = OpenLibrary::with(['contents.content'])->findOrFail($id);
        
        // Get content items formatted for display
        $contentItems = [];
        
        foreach ($library->contents as $content) {
            $contentType = $content->content_type;
            $contentModel = $content->content;
            
            if ($contentModel) {
                $contentItems[] = [
                    'id' => $contentModel->id,
                    'title' => $contentModel->title ?? 'Untitled',
                    'type' => Str::afterLast($contentType, '\\'),
                    'user' => $contentModel->user->name ?? 'Unknown',
                    'created_at' => $contentModel->created_at,
                    'relevance_score' => $content->relevance_score,
                    'url' => $this->getContentUrl($contentType, $contentModel->id)
                ];
            }
        }
        
        return view('admin.libraries.view', [
            'library' => $library,
            'contentItems' => $contentItems
        ]);
    }
    
    /**
     * Approve a library
     */
    public function approveLibrary(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        $generateCover = $request->has('generate_cover');
        
        // For admins, we don't enforce a minimum content requirement
        // Get content count for information purposes only
        $contentCount = $library->contents()->count();
        
        try {
            // Approve via API
            $response = $this->libraryApiService->approveLibrary($id, $generateCover);
            
            if (!$response['success']) {
                Log::error('Failed to approve library via API', [
                    'message' => $response['message'] ?? 'Unknown error',
                    'errors' => $response['errors'] ?? []
                ]);
                
                // Fallback to local operation
                // Generate AI cover image if requested and not already present
                if ($generateCover && !$library->has_ai_cover) {
                    $coverImageUrl = $this->generateCoverImage($library);
                    if ($coverImageUrl) {
                        $library->cover_image_url = $coverImageUrl;
                        $library->has_ai_cover = true;
                    }
                }
                
                // Update library status
                $library->is_approved = true;
                $library->approval_status = 'approved';
                $library->approval_date = now();
                $library->approved_by = Auth::id();
                $library->save();
                
                return redirect()->route('admin.libraries.index')
                    ->with('warning', 'Library has been approved locally but failed to sync with API.');
            }
            
            // If successful, update the local record to match the remote state
            $library->is_approved = true;
            $library->approval_status = 'approved';
            $library->approval_date = now();
            $library->approved_by = Auth::id();
            
            // Update cover image info if it was generated
            if ($generateCover && isset($response['data']['library']['cover_image_url'])) {
                $library->cover_image_url = $response['data']['library']['cover_image_url'];
                $library->has_ai_cover = true;
            }
            
            $library->save();
            
            return redirect()->route('admin.libraries.index')
                ->with('success', 'Library has been approved successfully.');
                
        } catch (\Exception $e) {
            Log::error('Error approving library: ' . $e->getMessage());
            
            // Fallback to local operation
            // Generate AI cover image if requested and not already present
            if ($generateCover && !$library->has_ai_cover) {
                $coverImageUrl = $this->generateCoverImage($library);
                if ($coverImageUrl) {
                    $library->cover_image_url = $coverImageUrl;
                    $library->has_ai_cover = true;
                }
            }
            
            // Update library status
            $library->is_approved = true;
            $library->approval_status = 'approved';
            $library->approval_date = now();
            $library->approved_by = Auth::id();
            $library->save();
            
            return redirect()->route('admin.libraries.index')
                ->with('warning', 'Library has been approved locally but failed to sync with API due to error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reject a library
     */
    public function rejectLibrary(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);
        
        $library = OpenLibrary::findOrFail($id);
        
        try {
            // Reject via API
            $response = $this->libraryApiService->rejectLibrary($id, $request->rejection_reason);
            
            if (!$response['success']) {
                Log::error('Failed to reject library via API', [
                    'message' => $response['message'] ?? 'Unknown error',
                    'errors' => $response['errors'] ?? []
                ]);
                
                // Fallback to local operation
                $library->is_approved = false;
                $library->approval_status = 'rejected';
                $library->rejection_reason = $request->rejection_reason;
                $library->save();
                
                return redirect()->route('admin.libraries.index')
                    ->with('warning', 'Library has been rejected locally but failed to sync with API.');
            }
            
            // Update local record to match remote state
            $library->is_approved = false;
            $library->approval_status = 'rejected';
            $library->rejection_reason = $request->rejection_reason;
            $library->save();
            
            return redirect()->route('admin.libraries.index')
                ->with('success', 'Library has been rejected.');
                
        } catch (\Exception $e) {
            Log::error('Error rejecting library: ' . $e->getMessage());
            
            // Fallback to local operation
            $library->is_approved = false;
            $library->approval_status = 'rejected';
            $library->rejection_reason = $request->rejection_reason;
            $library->save();
            
            return redirect()->route('admin.libraries.index')
                ->with('warning', 'Library has been rejected locally but failed to sync with API due to error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate a cover image using GPT-4o vision
     */
    private function generateCoverImage(OpenLibrary $library)
    {
        try {
            // Create a prompt based on library content
            $prompt = $this->createCoverImagePrompt($library);
            $library->cover_prompt = $prompt;
            
            // Configure OpenAI API request
            $apiKey = config('services.openai.api_key');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'natural'
            ]);
            
            if ($response->successful()) {
                $imageUrl = $response->json('data.0.url');
                return $imageUrl;
            }
            
            return null;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }
    
    /**
     * Create a prompt for the cover image based on library content
     */
    private function createCoverImagePrompt(OpenLibrary $library)
    {
        $basePrompt = "Create a subtle, abstract, and minimal cover image for a content library about ";
        
        // Use library name and description
        $content = $library->name . ": " . $library->description;
        
        // Add topics and themes from library content if available
        $topics = [];
        $contents = $library->contents()->with('content')->get();
        
        foreach ($contents as $item) {
            if ($item->content && isset($item->content->title)) {
                $topics[] = $item->content->title;
            }
        }
        
        if (count($topics) > 0) {
            $content .= ". Topics include: " . implode(", ", array_slice($topics, 0, 5));
        }
        
        // Complete the prompt with style guidance
        $fullPrompt = $basePrompt . $content . ". Use a light color palette with subtle gradients. The design should be modern, clean, and professional without being too busy or distracting. Do not include any text or words in the image.";
        
        return $fullPrompt;
    }
    
    /**
     * Get the appropriate URL for a content item
     */
    private function getContentUrl($contentType, $contentId)
    {
        if (Str::contains($contentType, 'Course')) {
            return route('course.show', $contentId);
        } elseif (Str::contains($contentType, 'Post')) {
            return route('post.deep-link', $contentId);
        }
        
        return '#';
    }
    
    /**
     * Show form to add content to a library
     */
    public function addContentForm($id)
    {
        $library = OpenLibrary::with('contents')->findOrFail($id);
        
        // Get content that can be added to the library
        $courses = \App\Models\Course::select('id', 'title', 'description', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
            
        $posts = \App\Models\Post::select('id', 'title', 'body', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
        
        // Format for display
        $availableContent = [
            'courses' => $courses->map(function($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description ?? '',
                    'type' => 'Course',
                    'created_at' => $course->created_at
                ];
            }),
            'posts' => $posts->map(function($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title ?? 'Untitled Post',
                    'description' => substr($post->body ?? '', 0, 100),
                    'type' => 'Post',
                    'created_at' => $post->created_at
                ];
            }),
        ];
        
        return view('admin.libraries.add-content', [
            'library' => $library,
            'availableContent' => $availableContent
        ]);
    }
    
    /**
     * Add content to a library
     */
    public function addContent(Request $request, $id)
    {
        $request->validate([
            'content_id' => 'required',
            'content_type' => 'required|in:Course,Post',
            'relevance_score' => 'nullable|numeric|min:0|max:1'
        ]);
        
        $library = OpenLibrary::findOrFail($id);
        $relevanceScore = $request->relevance_score ?? 0.7;
        
        try {
            // Add content via API
            $response = $this->libraryApiService->addContent(
                $id,
                $request->content_type,
                $request->content_id,
                $relevanceScore
            );
            
            if (!$response['success']) {
                Log::error('Failed to add content to library via API', [
                    'message' => $response['message'] ?? 'Unknown error',
                    'errors' => $response['errors'] ?? []
                ]);
                
                // Fallback to local operation
                // Map content type to full namespace
                $contentTypeMap = [
                    'Course' => \App\Models\Course::class,
                    'Post' => \App\Models\Post::class
                ];
                
                $contentType = $contentTypeMap[$request->content_type];
                
                // Check if content exists
                $content = $contentType::find($request->content_id);
                if (!$content) {
                    return redirect()->back()->with('error', 'Content not found');
                }
                
                // Check if content is already in the library
                $exists = $library->contents()
                    ->where('content_type', $contentType)
                    ->where('content_id', $request->content_id)
                    ->exists();
                    
                if ($exists) {
                    return redirect()->back()->with('error', 'This content is already in the library');
                }
                
                // Add content to library
                $libraryContent = new \App\Models\LibraryContent();
                $libraryContent->library_id = $library->id;
                $libraryContent->content_type = $contentType;
                $libraryContent->content_id = $request->content_id;
                $libraryContent->relevance_score = $relevanceScore;
                $libraryContent->save();
                
                return redirect()->route('admin.libraries.view', $library->id)
                    ->with('warning', 'Content added to library locally but failed to sync with API. Some features may be limited.');
            }
            
            return redirect()->route('admin.libraries.view', $library->id)
                ->with('success', 'Content added to library successfully');
                
        } catch (\Exception $e) {
            Log::error('Error adding content to library: ' . $e->getMessage());
            
            // Fallback to local operation
            // Map content type to full namespace
            $contentTypeMap = [
                'Course' => \App\Models\Course::class,
                'Post' => \App\Models\Post::class
            ];
            
            $contentType = $contentTypeMap[$request->content_type];
            
            // Check if content exists
            $content = $contentType::find($request->content_id);
            if (!$content) {
                return redirect()->back()->with('error', 'Content not found');
            }
            
            // Check if content is already in the library
            $exists = $library->contents()
                ->where('content_type', $contentType)
                ->where('content_id', $request->content_id)
                ->exists();
                
            if ($exists) {
                return redirect()->back()->with('error', 'This content is already in the library');
            }
            
            // Add content to library
            $libraryContent = new \App\Models\LibraryContent();
            $libraryContent->library_id = $library->id;
            $libraryContent->content_type = $contentType;
            $libraryContent->content_id = $request->content_id;
            $libraryContent->relevance_score = $relevanceScore;
            $libraryContent->save();
            
            return redirect()->route('admin.libraries.view', $library->id)
                ->with('warning', 'Content added to library locally due to API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Remove content from a library
     */
    public function removeContent(Request $request, $id)
    {
        $request->validate([
            'content_id' => 'required',
        ]);
        
        $libraryContent = \App\Models\LibraryContent::findOrFail($request->content_id);
        
        // Check if the content belongs to the specified library
        if ($libraryContent->library_id != $id) {
            return redirect()->back()->with('error', 'Content does not belong to this library');
        }
        
        try {
            // Remove content via API
            $response = $this->libraryApiService->removeContent($id, $request->content_id);
            
            if (!$response['success']) {
                Log::error('Failed to remove content from library via API', [
                    'message' => $response['message'] ?? 'Unknown error',
                    'errors' => $response['errors'] ?? []
                ]);
                
                // Fallback to local operation
                $libraryContent->delete();
                
                return redirect()->route('admin.libraries.view', $id)
                    ->with('warning', 'Content removed from library locally but failed to sync with API.');
            }
            
            // Delete local record to match remote state
            $libraryContent->delete();
            
            return redirect()->route('admin.libraries.view', $id)
                ->with('success', 'Content removed from library successfully');
                
        } catch (\Exception $e) {
            Log::error('Error removing content from library: ' . $e->getMessage());
            
            // Fallback to local operation
            $libraryContent->delete();
            
            return redirect()->route('admin.libraries.view', $id)
                ->with('warning', 'Content removed from library locally but failed to sync with API due to error: ' . $e->getMessage());
        }
    }
    
    /**
     * Show the form for generating AI libraries from posts
     */
    public function showGenerateLibrariesForm()
    {
        // Get total post count for reference
        $totalPostCount = Post::count();
        $recentPostCount = Post::where('created_at', '>=', now()->subDays(30))->count();
        
        // Get recent posts with high engagement for preview
        $topPosts = Post::where('created_at', '>=', now()->subDays(30))
            ->withCount(['likes', 'comments'])
            ->orderByRaw('likes_count + comments_count DESC')
            ->take(10)
            ->get();
        
        return view('admin.libraries.generate', [
            'totalPostCount' => $totalPostCount,
            'recentPostCount' => $recentPostCount,
            'topPosts' => $topPosts
        ]);
    }
    
    /**
     * Generate libraries from posts using AI
     */
    public function generateLibraries(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'min_posts' => 'required|integer|min:5|max:100',
            'auto_approve' => 'nullable|boolean'
        ]);
        
        $days = $request->input('days', 30);
        $minPosts = $request->input('min_posts', 10);
        $autoApprove = $request->boolean('auto_approve', false);
        
        try {
            // Get posts based on filters
            $posts = Post::where('created_at', '>=', now()->subDays($days));
            
            // Add additional filters if provided
            if ($request->has('min_likes')) {
                $posts->has('likes', '>=', $request->min_likes);
            }
            
            if ($request->has('min_comments')) {
                $posts->has('comments', '>=', $request->min_comments);
            }
            
            $posts = $posts->orderBy('created_at', 'desc')
                ->limit(200)
                ->get();
            
            // Check if we have enough posts
            if ($posts->count() < $minPosts) {
                return redirect()->back()->with('error', 
                    "Not enough posts found with the current filters. Found {$posts->count()}, but need at least {$minPosts}."
                );
            }
            
            // Generate libraries
            $result = $this->openLibraryService->createLibrariesFromPosts(
                $posts,
                $minPosts,
                true, // Generate covers
                $autoApprove
            );
            
            if (!$result['success']) {
                return redirect()->back()->with('error', 
                    "Failed to generate libraries: " . ($result['message'] ?? 'Unknown error')
                );
            }
            
            $librariesCount = count($result['libraries'] ?? []);
            return redirect()->route('admin.libraries.index')
                ->with('success', "Successfully generated {$librariesCount} libraries from posts!");
                
        } catch (\Exception $e) {
            Log::error('Error generating libraries from posts: ' . $e->getMessage());
            return redirect()->back()->with('error', 
                "An error occurred while generating libraries: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Regenerate a cover image for a library
     */
    public function regenerateCoverImage($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        try {
            // Generate a new cover image
            $coverUrl = $this->coverImageService->generateCoverImage($library);
            
            if (!$coverUrl) {
                return redirect()->back()->with('error', 'Failed to generate cover image');
            }
            
            // Update the library
            $library->cover_image_url = $coverUrl;
            $library->thumbnail_url = $coverUrl;
            $library->has_ai_cover = true;
            $library->save();
            
            return redirect()->route('admin.libraries.view', $id)
                ->with('success', 'Cover image regenerated successfully');
                
        } catch (\Exception $e) {
            Log::error('Error regenerating library cover: ' . $e->getMessage());
            return redirect()->back()->with('error', 
                "An error occurred while regenerating the cover image: " . $e->getMessage()
            );
        }
    }
}