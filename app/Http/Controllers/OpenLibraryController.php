<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\Course;
use App\Models\Post;
use App\Models\Topic;
use App\Services\OpenLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpenLibraryController extends Controller
{
    protected $libraryService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(OpenLibraryService $libraryService)
    {
        $this->libraryService = $libraryService;
    }
    
    /**
     * Display a listing of libraries.
     */
    public function index()
    {
        // Only show approved libraries to regular users
        $libraries = OpenLibrary::where('is_approved', true)
                              ->where('approval_status', 'approved')
                              ->orderBy('created_at', 'desc')
                              ->get();
        
        return response()->json([
            'libraries' => $libraries
        ]);
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
            }
            
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