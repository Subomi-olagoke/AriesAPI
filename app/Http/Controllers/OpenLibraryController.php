<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\Course;
use App\Models\Post;
use App\Models\Topic;
use App\Models\LibraryUrl;
use App\Services\OpenLibraryService;
use App\Services\UrlFetchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class OpenLibraryController extends Controller
{
    protected $libraryService;
    protected $urlFetchService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(OpenLibraryService $libraryService, UrlFetchService $urlFetchService)
    {
        $this->libraryService = $libraryService;
        $this->urlFetchService = $urlFetchService;
    }
    
    /**
     * Display a listing of libraries.
     */
    public function index()
    {
        try {
            // Check if the is_approved column exists in the schema
            $hasIsApprovedColumn = Schema::hasColumn('open_libraries', 'is_approved');
            $hasApprovalStatusColumn = Schema::hasColumn('open_libraries', 'approval_status');
            
            // Build query based on available columns
            $query = OpenLibrary::query();
            
            if ($hasIsApprovedColumn) {
                $query->where('is_approved', true);
            }
            
            if ($hasApprovalStatusColumn) {
                $query->where('approval_status', 'approved');
            }
            
            // Get results ordered by creation date
            $libraries = $query->orderBy('created_at', 'desc')->get();
            
            // Format the response to match iOS expectations
            $formattedLibraries = $libraries->map(function ($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url,
                    'courseId' => $library->course_id,
                    'criteria' => $library->criteria,
                    'keywords' => $library->keywords,
                    'isApproved' => $library->is_approved,
                    'approvalStatus' => $library->approval_status,
                    'approvalDate' => $library->approval_date,
                    'approvedBy' => $library->approved_by,
                    'rejectionReason' => $library->rejection_reason,
                    'coverPrompt' => $library->cover_prompt,
                    'aiGenerated' => $library->ai_generated,
                    'aiGenerationDate' => $library->ai_generation_date,
                    'aiModelUsed' => $library->ai_model_used,
                    'hasAiCover' => $library->has_ai_cover,
                    'createdAt' => $library->created_at,
                    'updatedAt' => $library->updated_at,
                    'contents' => $library->contents ? $library->contents->map(function ($content) {
                        return [
                            'id' => $content->id,
                            'libraryId' => $content->library_id,
                            'contentId' => $content->content_id,
                            'contentType' => $content->content_type,
                            'relevanceScore' => $content->relevance_score,
                            'createdAt' => $content->created_at,
                            'updatedAt' => $content->updated_at,
                            'contentData' => $content->content ? [
                                'id' => $content->content->id,
                                'title' => $content->content->title ?? null,
                                'body' => $content->content->body ?? null,
                                'mediaType' => $content->content->media_type ?? null,
                                'mediaLink' => $content->content->media_link ?? null,
                                'mediaThumbnail' => $content->content->media_thumbnail ?? null,
                                'visibility' => $content->content->visibility ?? null,
                                'user' => $content->content->user ? [
                                    'id' => $content->content->user->id,
                                    'firstName' => $content->content->user->first_name,
                                    'lastName' => $content->content->user->last_name,
                                    'username' => $content->content->user->username,
                                    'avatar' => $content->content->user->avatar,
                                    'isVerified' => $content->content->user->is_verified ?? false,
                                    'role' => $content->content->user->role,
                                    'alex_points' => $content->content->user->alex_points
                                ] : null
                            ] : null
                        ];
                    }) : [],
                    'user' => null,
                    'userId' => null
                ];
            });
            
            return response()->json([
                'libraries' => $formattedLibraries
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving libraries: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store a newly created library.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:auto,course',
            'course_id' => 'required_if:type,course|exists:courses,id',
            'topic_id' => 'required_if:type,auto|exists:topics,id',
            'max_items' => 'nullable|integer|min:5|max:100',
        ]);
        
        try {
            if ($request->type === 'course') {
                $course = Course::findOrFail($request->course_id);
                $library = $this->libraryService->createCourseLibrary($course);
                
                return response()->json([
                    'message' => 'Course library created successfully',
                    'library' => $library
                ], 201);
            } 
            elseif ($request->type === 'auto' && $request->has('topic_id')) {
                $topic = Topic::findOrFail($request->topic_id);
                $maxItems = $request->input('max_items', 50);
                
                $library = $this->libraryService->createTopicLibrary($topic, $maxItems);
                
                return response()->json([
                    'message' => 'Topic library created successfully',
                    'library' => $library
                ], 201);
            }
            
            return response()->json([
                'message' => 'Invalid library configuration'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Library creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a dynamic library based on a piece of content.
     */
    public function createDynamicLibrary(Request $request)
    {
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
            'name' => 'nullable|string|max:255',
            'max_items' => 'nullable|integer|min:5|max:50',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            $content = $contentType::findOrFail($contentId);
            $name = $request->input('name');
            $maxItems = $request->input('max_items', 20);
            
            $library = $this->libraryService->createDynamicLibrary($content, $name, $maxItems);
            
            return response()->json([
                'message' => 'Dynamic library created successfully',
                'library' => $library
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Dynamic library creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Dynamic library creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified library with its content.
     */
    public function show($id)
    {
        try {
            $library = OpenLibrary::findOrFail($id);
            
            // Get content with appropriate relationships
            $contents = DB::table('library_content')
                ->where('library_id', $library->id)
                ->orderBy('relevance_score', 'desc')
                ->get();
                
            $formattedContents = [];
            foreach ($contents as $content) {
                $contentItem = null;
                
                if ($content->content_type === Course::class) {
                    $contentItem = Course::with('user', 'topic')->find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'description' => $contentItem->description,
                            'thumbnail_url' => $contentItem->thumbnail_url,
                            'user' => $contentItem->user ? [
                                'id' => $contentItem->user->id,
                                'username' => $contentItem->user->username
                            ] : null,
                            'topic' => $contentItem->topic ? [
                                'id' => $contentItem->topic->id,
                                'name' => $contentItem->topic->name
                            ] : null,
                            'type' => 'course',
                            'relevance_score' => $content->relevance_score
                        ];
                    }
                } 
                elseif ($content->content_type === Post::class) {
                    $contentItem = Post::with('user')->find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'body' => substr($contentItem->body, 0, 200) . '...',
                            'media_link' => $contentItem->media_link,
                            'media_type' => $contentItem->media_type,
                            'user' => $contentItem->user ? [
                                'id' => $contentItem->user->id,
                                'username' => $contentItem->user->username
                            ] : null,
                            'type' => 'post',
                            'relevance_score' => $content->relevance_score
                        ];
                    }
                }
                elseif ($content->content_type === LibraryUrl::class) {
                    $contentItem = LibraryUrl::find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'url' => $contentItem->url,
                            'description' => $contentItem->summary,
                            'notes' => $contentItem->notes,
                            'type' => 'url',
                            'relevance_score' => $content->relevance_score,
                            'created_at' => $contentItem->created_at
                        ];
                    }
                }
            }
            
            // For backward compatibility, also add URLs from the url_items array
            // This ensures we don't miss any URLs that were added before the migration
            $urlItems = $library->url_items ?? [];
            
            foreach ($urlItems as $urlItem) {
                // Check if this URL is already included (based on the URL itself)
                $url = $urlItem['url'] ?? '';
                $alreadyIncluded = false;
                
                foreach ($formattedContents as $content) {
                    if ($content['type'] === 'url' && isset($content['url']) && $content['url'] === $url) {
                        $alreadyIncluded = true;
                        break;
                    }
                }
                
                // Only add if not already included
                if (!$alreadyIncluded && !empty($url)) {
                    $formattedContents[] = [
                        'id' => $urlItem['id'] ?? uniqid('url_'),
                        'title' => $urlItem['title'] ?? 'No title',
                        'url' => $url,
                        'description' => $urlItem['summary'] ?? $urlItem['description'] ?? '',
                        'notes' => $urlItem['notes'] ?? '',
                        'type' => 'url',
                        'relevance_score' => $urlItem['relevance_score'] ?? 0.5,
                        'created_at' => $urlItem['created_at'] ?? now()->toIso8601String()
                    ];
                }
            }
            
            // Sort all contents by relevance score
            usort($formattedContents, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            return response()->json([
                'library' => $library,
                'contents' => $formattedContents
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving library: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the specified library.
     */
    public function update(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        try {
            if ($request->has('name')) {
                $library->name = $request->name;
            }
            
            if ($request->has('description')) {
                $library->description = $request->description;
            }
            
            $library->save();
            
            return response()->json([
                'message' => 'Library updated successfully',
                'library' => $library
            ]);
            
        } catch (\Exception $e) {
            Log::error('Library update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library update failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified library.
     */
    public function destroy($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        try {
            // Delete all content associations first
            DB::table('library_content')
                ->where('library_id', $library->id)
                ->delete();
                
            // Delete the library
            $library->delete();
            
            return response()->json([
                'message' => 'Library deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Library deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Refresh the content in an auto-generated library.
     */
    public function refreshLibrary($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        if ($library->type !== 'auto') {
            return response()->json([
                'message' => 'Only auto-generated libraries can be refreshed'
            ], 400);
        }
        
        try {
            $success = $this->libraryService->refreshLibraryContent($library);
            
            if ($success) {
                return response()->json([
                    'message' => 'Library content refreshed successfully',
                    'library' => $library->fresh()
                ]);
            }
            
            return response()->json([
                'message' => 'Failed to refresh library content'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Library refresh failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add content to a library manually.
     */
    public function addContent(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            // Check if content exists
            $content = $contentType::findOrFail($contentId);
            
            // Content moderation check
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            
            // Check content title
            $titleCheck = $contentModerationService->analyzeText($content->title ?? '');
            if (!$titleCheck['isAllowed']) {
                return response()->json([
                    'message' => 'Content contains inappropriate material and cannot be added to the library',
                    'reason' => $titleCheck['reason']
                ], 400);
            }
            
            // Check content body/description
            $bodyCheck = $contentModerationService->analyzeText($content->body ?? $content->description ?? '');
            if (!$bodyCheck['isAllowed']) {
                return response()->json([
                    'message' => 'Content contains inappropriate material and cannot be added to the library',
                    'reason' => $bodyCheck['reason']
                ], 400);
            }
            
            // Check if content is already in library
            $existing = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $contentId)
                ->where('content_type', $contentType)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'message' => 'Content already exists in this library'
                ], 400);
            }
            
            // Add to library
            DB::table('library_content')->insert([
                'library_id' => $library->id,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'relevance_score' => $request->input('relevance_score', 0.5),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'message' => 'Content added to library successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Adding content to library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Adding content to library failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a URL to a library with automatic content fetching and summarization.
     * Stores URLs inline with other content types.
     */
    public function addUrl(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'notes' => 'nullable|string|max:1000',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find the library
            $library = OpenLibrary::findOrFail($id);
            
            // Fetch and summarize the URL content
            $url = $request->url;
            $urlData = $this->urlFetchService->fetchAndSummarize($url);
            
            if (!$urlData['success']) {
                throw new \Exception('Failed to fetch URL content: ' . ($urlData['error'] ?? 'Unknown error'));
            }
            
            // Content moderation check for URL content
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            
            // Check URL itself for inappropriate content
            $urlCheck = $contentModerationService->analyzeText($url);
            if (!$urlCheck['isAllowed']) {
                return response()->json([
                    'message' => 'URL contains inappropriate content and cannot be added to the library',
                    'reason' => $urlCheck['reason']
                ], 400);
            }
            
            // Check fetched title
            $titleCheck = $contentModerationService->analyzeText($urlData['title'] ?? '');
            if (!$titleCheck['isAllowed']) {
                return response()->json([
                    'message' => 'URL content contains inappropriate material and cannot be added to the library',
                    'reason' => $titleCheck['reason']
                ], 400);
            }
            
            // Check fetched summary
            $summaryCheck = $contentModerationService->analyzeText($urlData['summary'] ?? '');
            if (!$summaryCheck['isAllowed']) {
                return response()->json([
                    'message' => 'URL content contains inappropriate material and cannot be added to the library',
                    'reason' => $summaryCheck['reason']
                ], 400);
            }
            
            // First, check if this URL already exists in our database
            $existingUrl = LibraryUrl::where('url', $url)->first();
            
            if (!$existingUrl) {
                // Create a new LibraryUrl record
                $existingUrl = LibraryUrl::create([
                    'url' => $url,
                    'title' => $urlData['title'] ?? 'No title',
                    'summary' => $urlData['summary'] ?? 'No summary available',
                    'notes' => $request->notes,
                    'created_by' => Auth::id()
                ]);
            }
            
            // Check if this URL is already in the library
            $existing = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $existingUrl->id)
                ->where('content_type', LibraryUrl::class)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'message' => 'URL already exists in this library',
                    'item' => $existingUrl
                ], 400);
            }
            
            // Add to library content table with the appropriate relevance score
            DB::table('library_content')->insert([
                'library_id' => $library->id,
                'content_id' => $existingUrl->id,
                'content_type' => LibraryUrl::class,
                'relevance_score' => $request->input('relevance_score', 0.8),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // For backward compatibility, also maintain the url_items field
            // This can be removed in a future version after all clients are updated
            $urlItem = [
                'id' => 'url_' . $existingUrl->id,
                'url' => $url,
                'title' => $urlData['title'] ?? 'No title',
                'summary' => $urlData['summary'] ?? 'No summary available',
                'notes' => $request->notes,
                'relevance_score' => $request->input('relevance_score', 0.8),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String()
            ];
            
            // Get current URL items or initialize empty array
            $urlItems = $library->url_items ?? [];
            
            // Add new URL item
            $urlItems[] = $urlItem;
            
            // Update the library with the new URL item (for backward compatibility)
            $library->url_items = $urlItems;
            $library->save();
            
            return response()->json([
                'message' => 'URL added to library successfully',
                'item' => [
                    'id' => $existingUrl->id,
                    'url' => $existingUrl->url,
                    'title' => $existingUrl->title,
                    'summary' => $existingUrl->summary,
                    'notes' => $existingUrl->notes,
                    'relevance_score' => $request->input('relevance_score', 0.8),
                    'created_at' => $existingUrl->created_at,
                    'updated_at' => $existingUrl->updated_at,
                    'type' => 'url'
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Adding URL to library failed: ' . $e->getMessage(), [
                'url' => $request->url,
                'library_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to add URL to library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a URL from a library.
     */
    public function removeUrl(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'url_id' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find the library
            $library = OpenLibrary::findOrFail($id);
            $urlId = $request->url_id;
            
            // Handle both formats - numeric IDs (new) and string IDs with url_ prefix (old)
            $numericId = $urlId;
            if (strpos($urlId, 'url_') === 0) {
                $numericId = substr($urlId, 4); // Remove 'url_' prefix
            }
            
            // Remove from library_content table
            $deleted = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $numericId)
                ->where('content_type', LibraryUrl::class)
                ->delete();
            
            // For backward compatibility, also remove from url_items array
            $urlItems = $library->url_items ?? [];
            $foundInLegacy = false;
            
            // Find the URL item by ID in the legacy storage
            $urlItemIndex = null;
            foreach ($urlItems as $index => $item) {
                if (isset($item['id']) && ($item['id'] === $urlId || $item['id'] === 'url_' . $numericId)) {
                    $urlItemIndex = $index;
                    $foundInLegacy = true;
                    break;
                }
            }
            
            // If found in legacy storage, remove it
            if ($foundInLegacy && $urlItemIndex !== null) {
                array_splice($urlItems, $urlItemIndex, 1);
                $library->url_items = $urlItems;
                $library->save();
            }
            
            // If we didn't delete anything from either storage system
            if (!$deleted && !$foundInLegacy) {
                return response()->json([
                    'message' => 'URL not found in this library'
                ], 404);
            }
            
            return response()->json([
                'message' => 'URL removed from library successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Removing URL from library failed: ' . $e->getMessage(), [
                'url_id' => $request->url_id,
                'library_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to remove URL from library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove content from a library.
     */
    public function removeContent(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            // Remove from library
            $deleted = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $contentId)
                ->where('content_type', $contentType)
                ->delete();
                
            if ($deleted) {
                return response()->json([
                    'message' => 'Content removed from library successfully'
                ]);
            }
            
            return response()->json([
                'message' => 'Content not found in this library'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Removing content from library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Removing content from library failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all libraries for a specific course.
     */
    public function getCourseLibraries($courseId)
    {
        try {
            $course = Course::findOrFail($courseId);
            
            // Get course-specific libraries
            $courseLibraries = OpenLibrary::where('course_id', $courseId)->get();
            
            // Get auto libraries containing this course
            $autoLibraryIds = DB::table('library_content')
                ->where('content_id', $courseId)
                ->where('content_type', Course::class)
                ->pluck('library_id');
                
            $autoLibraries = OpenLibrary::whereIn('id', $autoLibraryIds)
                ->where('type', 'auto')
                ->get();
                
            $libraries = $courseLibraries->merge($autoLibraries);
            
            return response()->json([
                'libraries' => $libraries
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving course libraries: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving course libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get libraries by content similarity.
     */
    public function getSimilarLibraries(Request $request)
    {
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            $content = $contentType::findOrFail($contentId);
            $limit = $request->input('limit', 5);
            
            // Extract keywords from the content
            $keywords = $this->libraryService->extractKeywords($content);
            
            // Find libraries containing similar content
            $libraryIds = DB::table('library_content')
                ->where('content_type', $contentType)
                ->whereIn('content_id', function($query) use ($contentType, $contentId) {
                    // Get IDs of similar content items
                    $query->select('content_id')
                        ->from('library_content')
                        ->where('content_type', $contentType)
                        ->where('content_id', '!=', $contentId)
                        ->orderBy('relevance_score', 'desc')
                        ->limit(50);
                })
                ->distinct()
                ->pluck('library_id');
                
            $libraries = OpenLibrary::whereIn('id', $libraryIds)
                ->limit($limit)
                ->get();
                
            return response()->json([
                'libraries' => $libraries
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error finding similar libraries: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error finding similar libraries: ' . $e->getMessage()
            ], 500);
        }
    }
}