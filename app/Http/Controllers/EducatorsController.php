<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Educators;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EducatorsController extends Controller {

	//post courses
	public function createCourse(Request $request) {
        $user = auth()->user();
        if($user->role != User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'you are not allowed to use this'
            ], 403);
        }

		$request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'file' => 'required|file|mimes:mp4,avi,mkv,pdf,docx|max:1048576',
        ]);

        try {
            DB::beginTransaction();
            
            // Use our file upload service
            $fileUploadService = app(FileUploadService::class);
            $filePath = $fileUploadService->uploadFile(
                $request->file('file'),
                'courses/' . date('Y-m-d')
            );

            $course = new Course();
            $course->user_id = $user->id;
            $course->name = $request->name;
            $course->description = $request->description;
            $course->file = $filePath;
            $saved = $course->save();

            DB::commit();
            
            if($saved) {
                return response()->json([
                    'message' => 'Course uploaded successfully',
                    'course' => $course
                ], 200);
            }
            
            return response()->json([
                'message' => 'Failed to save course'
            ], 500);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course upload failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Course upload failed: ' . $e->getMessage()
            ], 500);
        }
	}

	public function showEducatorCourses(Request $request) {
        $user = $request->user();
		$courses = $user->courses()->get();
        if($courses) {
            return response()->json([
                'courses' => $courses,
            ], 201);
        }
	}

	public function download(Request $request, $file) {
        // This method needs to be replaced with S3 file download
        // S3 files are accessible via their URLs directly, so this method may no longer be needed
        // or it could be refactored to generate temporary signed URLs for private S3 files
        
        $filePath = 'courses/' . $file;
        
        if (Storage::disk('s3')->exists($filePath)) {
            $url = Storage::disk('s3')->temporaryUrl(
                $filePath,
                now()->addMinutes(5)
            );
            
            return response()->json([
                'download_url' => $url
            ]);
        }
        
        return response()->json(['error' => 'File not found'], 404);
    }

	public function view($id) {
		$data = Course::find($id);

        if (!$data) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        return response()->json(['data' => $data]);
	}
    
    /**
     * Get all educators on the platform
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllEducators(Request $request)
    {
        // Define pagination parameters with defaults
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        
        // Query for all users with educator role
        $educators = User::where('role', User::ROLE_EDUCATOR)
            ->with(['profile', 'topic']) // Include relationships
            ->select(['id', 'username', 'first_name', 'last_name', 'avatar', 'created_at'])
            ->withCount(['courses', 'followers']) // Count relationships
            ->orderBy('followers_count', 'desc') // Order by popularity
            ->paginate($perPage);
            
        // Transform the data to include additional info
        $educators->getCollection()->transform(function($user) {
            // Add profile data if available
            if ($user->profile) {
                $user->bio = $user->profile->bio;
            }
            
            // Add topics/interests
            $user->topics = $user->topic->pluck('name');
            
            // Add calculated fields
            $user->full_name = $user->first_name . ' ' . $user->last_name;
            
            // Remove unnecessary relationship data
            unset($user->profile);
            unset($user->topic);
            
            return $user;
        });
        
        return response()->json([
            'educators' => $educators,
            'total' => $educators->total(),
            'per_page' => $educators->perPage(),
            'current_page' => $educators->currentPage(),
            'last_page' => $educators->lastPage()
        ]);
    }

    /**
     * Get all educators with information about whether the current user follows them
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllEducatorsWithFollowStatus(Request $request)
    {
        // Define pagination parameters with defaults
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        
        // Get the current user's ID
        $currentUserId = auth()->id();
        
        // Get the IDs of users the current user follows
        $followedUserIds = auth()->user()->following()->pluck('followeduser')->toArray();
        
        // Query for all users with educator role
        $educators = User::where('role', User::ROLE_EDUCATOR)
            ->with(['profile', 'topic']) // Include relationships
            ->select(['id', 'username', 'first_name', 'last_name', 'avatar', 'created_at', 'is_verified', 'verification_status'])
            ->withCount(['courses', 'followers']) // Count relationships
            ->orderBy('followers_count', 'desc') // Order by popularity
            ->paginate($perPage);
            
        // Transform the data to include additional info
        $educators->getCollection()->transform(function($user) use ($followedUserIds, $currentUserId) {
            // Add profile data if available
            if ($user->profile) {
                $user->bio = $user->profile->bio;
                $user->hire_rate = $user->profile->hire_rate;
                $user->hire_currency = $user->profile->hire_currency;
            }
            
            // Add topics/interests
            $user->topics = $user->topic->pluck('name');
            
            // Add calculated fields
            $user->full_name = $user->first_name . ' ' . $user->last_name;
            
            // Add follow status (if current user follows this educator)
            $user->is_followed = in_array($user->id, $followedUserIds);
            
            // Is this the current user?
            $user->is_current_user = ($user->id === $currentUserId);
            
            // Can be hired?
            $user->can_be_hired = $user->is_verified && 
                                 ($user->profile && !empty($user->profile->hire_rate));
            
            // Remove unnecessary relationship data
            unset($user->profile);
            unset($user->topic);
            
            return $user;
        });
        
        return response()->json([
            'educators' => $educators,
            'total' => $educators->total(),
            'per_page' => $educators->perPage(),
            'current_page' => $educators->currentPage(),
            'last_page' => $educators->lastPage()
        ]);
    }
    
    /**
     * Get all verified educators that can be hired
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerifiedEducators(Request $request)
    {
        // Define pagination parameters with defaults
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        
        // Get the current user's ID
        $currentUserId = auth()->id();
        
        // Get the IDs of users the current user follows
        $followedUserIds = auth()->user()->following()->pluck('followeduser')->toArray();
        
        // Base query for verified educators with hire rates
        $query = User::where('role', User::ROLE_EDUCATOR)
            ->where('is_verified', true)
            ->whereHas('profile', function($q) {
                $q->whereNotNull('hire_rate')
                  ->where('hire_rate', '>', 0);
            })
            ->with(['profile', 'topic', 'ratingsReceived']) // Include relationships
            ->select(['id', 'username', 'first_name', 'last_name', 'avatar', 'created_at', 'is_verified'])
            ->withCount(['courses', 'followers']); // Count relationships
            
        // Apply filters if provided
        if ($request->has('topic_id')) {
            $query->whereHas('topic', function($q) use ($request) {
                $q->where('topics.id', $request->topic_id);
            });
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhereHas('profile', function($subQ) use ($search) {
                      $subQ->where('bio', 'like', "%{$search}%");
                  });
            });
        }
        
        // Apply sorting
        if ($request->has('sort_by')) {
            switch ($request->sort_by) {
                case 'price_low':
                    $query->join('profiles', 'users.id', '=', 'profiles.user_id')
                          ->orderBy('profiles.hire_rate', 'asc')
                          ->select('users.*');
                    break;
                case 'price_high':
                    $query->join('profiles', 'users.id', '=', 'profiles.user_id')
                          ->orderBy('profiles.hire_rate', 'desc')
                          ->select('users.*');
                    break;
                case 'rating':
                    $query->withCount(['ratingsReceived as average_rating' => function($q) {
                        $q->select(DB::raw('coalesce(avg(rating), 0)'));
                    }])
                    ->orderBy('average_rating', 'desc');
                    break;
                case 'popularity':
                    $query->orderBy('followers_count', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            // Default sort by popularity
            $query->orderBy('followers_count', 'desc');
        }
        
        // Execute the query with pagination
        $educators = $query->paginate($perPage);
        
        // Transform the data to include additional info
        $educators->getCollection()->transform(function($user) use ($followedUserIds, $currentUserId) {
            // Calculate average rating
            $ratingsCount = $user->ratingsReceived->count();
            $averageRating = $ratingsCount > 0 ? 
                             $user->ratingsReceived->avg('rating') : 0;
            
            // Add profile data
            if ($user->profile) {
                $user->bio = $user->profile->bio;
                $user->hire_rate = $user->profile->hire_rate;
                $user->hire_currency = $user->profile->hire_currency ?? 'NGN';
            }
            
            // Add topics/interests
            $user->topics = $user->topic->pluck('name');
            
            // Add calculated fields
            $user->full_name = $user->first_name . ' ' . $user->last_name;
            
            // Add follow status
            $user->is_followed = in_array($user->id, $followedUserIds);
            
            // Is this the current user?
            $user->is_current_user = ($user->id === $currentUserId);
            
            // Add rating info
            $user->average_rating = round($averageRating, 1);
            $user->ratings_count = $ratingsCount;
            
            // Remove unnecessary relationship data
            unset($user->profile);
            unset($user->topic);
            unset($user->ratingsReceived);
            
            return $user;
        });
        
        return response()->json([
            'educators' => $educators,
            'total' => $educators->total(),
            'per_page' => $educators->perPage(),
            'current_page' => $educators->currentPage(),
            'last_page' => $educators->lastPage()
        ]);
    }
}