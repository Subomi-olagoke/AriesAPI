<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\User;
use App\Services\AlexPointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\LibraryFollowNotification;

class LibraryFollowController extends Controller
{
    protected $alexPointsService;
    
    public function __construct(AlexPointsService $alexPointsService)
    {
        $this->alexPointsService = $alexPointsService;
    }
    
    public function listLibraries(Request $request)
    {
        try {
            $user = Auth::user();
            // Temporary: raise default page size so older libraries appear without pagination in the client.
            $perPage = (int) $request->get('per_page', 500);
            // Guard against runaway values while keeping the larger default.
            $perPage = max(1, min($perPage, 500));
            $search = $request->get('search');
            
            $query = OpenLibrary::query()->whereNull('deleted_at');
            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }
            
            $libraries = $query
                ->orderByDesc('created_at')
                ->paginate($perPage);
            
            // Preload follow state for current user
            $libraryIds = $libraries->pluck('id');
            $followed = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->whereIn('library_id', $libraryIds)
                ->pluck('library_id')
                ->toArray();
            
            $payload = $libraries->getCollection()->map(function ($library) use ($followed) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'thumbnail_url' => $library->thumbnail_url ?? $library->cover_image_url,
                    'cover_image_url' => $library->cover_image_url,
                    'followers_count' => DB::table('library_follows')->where('library_id', $library->id)->count(),
                    'is_following' => in_array($library->id, $followed),
                ];
            });
            
            return response()->json([
                // Keep a flat array for clients expecting `libraries`
                'libraries' => $payload,
                // Also return pagination metadata for future use
                'meta' => [
                    'current_page' => $libraries->currentPage(),
                    'last_page' => $libraries->lastPage(),
                    'per_page' => $libraries->perPage(),
                    'total' => $libraries->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('List libraries failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to list libraries.'
            ], 500);
        }
    }

    public function followToggle(Request $request, $libraryId)
    {
        try {
            $user = Auth::user();
            $library = OpenLibrary::findOrFail($libraryId);
            
            $desired = $request->boolean('follow', null);
            $exists = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->where('library_id', $library->id);
            
            if ($desired === true) {
                if (!$exists->exists()) {
                    $exists->delete(); // no-op safety
                    DB::table('library_follows')->insert([
                        'user_id' => $user->id,
                        'library_id' => $library->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Send notification to library creator
                    if ($library->user_id && $library->user_id !== $user->id) {
                        $creator = User::find($library->user_id);
                        if ($creator) {
                            $creator->notify(new LibraryFollowNotification($user, $library));
                        }
                    }
                    
                    // Award points for following a library
                    try {
                        $this->alexPointsService->addPoints(
                            $user,
                            'follow_library',
                            OpenLibrary::class,
                            $library->id,
                            "Followed library: {$library->name}"
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to award points for following library: ' . $e->getMessage());
                    }
                }
                $following = true;
            } elseif ($desired === false) {
                $exists->delete();
                $following = false;
            } else {
                // toggle
                if ($exists->exists()) {
                    $exists->delete();
                    $following = false;
                } else {
                    DB::table('library_follows')->insert([
                        'user_id' => $user->id,
                        'library_id' => $library->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Send notification to library creator
                    if ($library->user_id && $library->user_id !== $user->id) {
                        $creator = User::find($library->user_id);
                        if ($creator) {
                            $creator->notify(new LibraryFollowNotification($user, $library));
                        }
                    }
                    
                    // Award points for following a library
                    try {
                        $this->alexPointsService->addPoints(
                            $user,
                            'follow_library',
                            OpenLibrary::class,
                            $library->id,
                            "Followed library: {$library->name}"
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to award points for following library: ' . $e->getMessage());
                    }
                    
                    $following = true;
                }
            }
            
            $count = DB::table('library_follows')->where('library_id', $library->id)->count();
            
            return response()->json([
                'message' => $following ? 'Library followed.' : 'Library unfollowed.',
                'is_following' => $following,
                'followers_count' => $count,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Follow toggle failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update follow status.'
            ], 500);
        }
    }

    public function getFollowedLibraries(Request $request)
    {
        try {
            $user = Auth::user();
            
            $libraries = OpenLibrary::whereIn('id', function ($q) use ($user) {
                $q->select('library_id')
                  ->from('library_follows')
                  ->where('user_id', $user->id);
            })->orderByDesc('created_at')->get()->map(function ($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'thumbnail_url' => $library->thumbnail_url ?? $library->cover_image_url,
                    'cover_image_url' => $library->cover_image_url,
                    'followers_count' => DB::table('library_follows')->where('library_id', $library->id)->count(),
                    'is_following' => true,
                ];
            });
            
            return response()->json([
                'libraries' => $libraries
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Get followed libraries failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get followed libraries: ' . $e->getMessage()
            ], 500);
        }
    }
}

