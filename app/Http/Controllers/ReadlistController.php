<?php

namespace App\Http\Controllers;
use App\Models\Post;
use App\Models\Course;
use App\Models\ReadlistItem;
use App\Models\Readlist;
use App\Helpers\SimpleIdGenerator;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReadlistController extends Controller
{
    protected $fileUploadService;

    /**
     * Create a new controller instance.
     *
     * @param FileUploadService $fileUploadService
     * @return void
     */
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Store a newly created readlist.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|string',
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
        ]);
        
        $user = $request->user();
        
        // Log the user ID for debugging
        \Log::info('Creating readlist', [
            'user_id' => $user->id,
            'user_class' => get_class($user)
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create readlist with direct property assignment
            $readlist = new Readlist();
            $readlist->title = $request->title;
            $readlist->description = $request->description;
            $readlist->is_public = $request->is_public === 'true' || $request->is_public === '1' ? true : false;
            $readlist->user_id = $user->id;
            $readlist->share_key = Str::random(10);
            
            // Handle image upload (required)
            $imageUrl = $this->fileUploadService->uploadFile(
                $request->file('image'),
                'readlist_images',
                [
                    'process_image' => true,
                    'width' => 800,
                    'height' => 450,
                    'fit' => true
                ]
            );
            
            if (!$imageUrl) {
                throw new \Exception('Failed to upload image');
            }
            
            $readlist->image_url = $imageUrl;
            
            // Save the readlist
            $saveResult = $readlist->save();
            
            if (!$saveResult) {
                throw new \Exception('Failed to save readlist to database');
            }
            
            // Add debugging to see what's happening
            \Log::debug('Readlist after save:', [
                'id' => $readlist->id,
                'title' => $readlist->title,
                'description' => $readlist->description,
                'user_id' => $readlist->user_id,
                'share_key' => $readlist->share_key,
                'image_url' => $readlist->image_url,
            ]);
            
            // For the response, we'll use the model as is
            $shareUrl = url("/readlists/shared/{$readlist->share_key}");
            
            DB::commit();
            
            return response()->json([
                'message' => 'Readlist created successfully',
                'readlist' => $readlist,
                'share_url' => $shareUrl
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Readlist creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all readlists for the current user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserReadlists()
    {
        $user = auth()->user();
        
        try {
            $readlists = Readlist::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($readlist) {
                    // Add item count for each readlist
                    $readlist->items_count = $readlist->items()->count();
                    return $readlist;
                });
            
            return response()->json([
                'message' => 'User readlists retrieved successfully',
                'readlists' => $readlists
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving user readlists: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve readlists: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified readlist with its items.
     */
    public function show($id)
    {
        try {
            // Find the readlist by ID with its relations
            $readlist = Readlist::with([
                'user:id,username,first_name,last_name,avatar',
                'items.item'
            ])->find($id);
            
            // Return 404 if readlist doesn't exist
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Organize items by type
            $items = $readlist->items;
            $organizedItems = [];
            
            foreach ($items as $item) {
                if ($item->item_type === Course::class) {
                    $course = $item->item;
                    if ($course) {
                        $organizedItems[] = [
                            'id' => $item->id,
                            'type' => 'course',
                            'order' => $item->order,
                            'notes' => $item->notes,
                            'item' => [
                                'id' => $course->id,
                                'title' => $course->title,
                                'description' => $course->description,
                                'thumbnail_url' => $course->thumbnail_url,
                                'user_id' => $course->user_id,
                                'difficulty_level' => $course->difficulty_level ?? 'Not specified',
                                'price' => $course->price
                            ]
                        ];
                    }
                } elseif ($item->item_type === Post::class) {
                    $post = $item->item;
                    if ($post) {
                        $organizedItems[] = [
                            'id' => $item->id,
                            'type' => 'post',
                            'order' => $item->order,
                            'notes' => $item->notes,
                            'item' => [
                                'id' => $post->id,
                                'title' => $post->title,
                                'body' => $this->truncateText($post->body, 200, '...'),
                                'media_link' => $post->media_link,
                                'media_type' => $post->media_type,
                                'user_id' => $post->user_id
                            ]
                        ];
                    }
                }
            }
            
            // Sort by order
            usort($organizedItems, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            
            return response()->json([
                'readlist' => [
                    'id' => $readlist->id,
                    'title' => $readlist->title,
                    'description' => $readlist->description,
                    'image_url' => $readlist->image_url,
                    'is_public' => $readlist->is_public,
                    'created_at' => $readlist->created_at,
                    'updated_at' => $readlist->updated_at,
                    'user' => $readlist->user,
                    'items_count' => count($organizedItems),
                    'items' => $organizedItems,
                    'share_key' => $readlist->share_key,
                    'share_url' => $readlist->share_url
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving readlist: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a readlist by its share key (public access).
     */
    public function showByShareKey($shareKey)
    {
        try {
            $readlist = Readlist::with([
                'user:id,username,first_name,last_name,avatar',
                'items.item'
            ])->where('share_key', $shareKey)->first();
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Shared readlist not found'
                ], 404);
            }
            
            // Organize items by type
            $items = $readlist->items;
            $organizedItems = [];
            
            foreach ($items as $item) {
                if ($item->item_type === Course::class) {
                    $course = $item->item;
                    if ($course) {
                        $organizedItems[] = [
                            'id' => $item->id,
                            'type' => 'course',
                            'order' => $item->order,
                            'notes' => $item->notes,
                            'item' => [
                                'id' => $course->id,
                                'title' => $course->title,
                                'description' => $course->description,
                                'thumbnail_url' => $course->thumbnail_url,
                                'user_id' => $course->user_id,
                                'difficulty_level' => $course->difficulty_level ?? 'Not specified',
                                'price' => $course->price
                            ]
                        ];
                    }
                } elseif ($item->item_type === Post::class) {
                    $post = $item->item;
                    if ($post) {
                        $organizedItems[] = [
                            'id' => $item->id,
                            'type' => 'post',
                            'order' => $item->order,
                            'notes' => $item->notes,
                            'item' => [
                                'id' => $post->id,
                                'title' => $post->title,
                                'body' => $this->truncateText($post->body, 200, '...'),
                                'media_link' => $post->media_link,
                                'media_type' => $post->media_type,
                                'user_id' => $post->user_id
                            ]
                        ];
                    }
                }
            }
            
            // Sort by order
            usort($organizedItems, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            
            return response()->json([
                'readlist' => [
                    'id' => $readlist->id,
                    'title' => $readlist->title,
                    'description' => $readlist->description,
                    'image_url' => $readlist->image_url,
                    'is_public' => $readlist->is_public,
                    'created_at' => $readlist->created_at,
                    'updated_at' => $readlist->updated_at,
                    'user' => $readlist->user,
                    'items_count' => count($organizedItems),
                    'items' => $organizedItems,
                    'share_url' => $readlist->share_url
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving shared readlist: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve shared readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified readlist.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max, optional for updates
        ]);
        
        try {
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Check if user owns the readlist
            if (auth()->id() !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to update this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            if ($request->has('title')) {
                $readlist->title = $request->title;
            }
            
            if ($request->has('description')) {
                $readlist->description = $request->description;
            }
            
            if ($request->has('is_public')) {
                $readlist->is_public = $request->is_public === 'true' || $request->is_public === '1' ? true : false;
            }
            
            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($readlist->image_url) {
                    $this->fileUploadService->deleteFile($readlist->image_url);
                }
                
                $imageUrl = $this->fileUploadService->uploadFile(
                    $request->file('image'),
                    'readlist_images',
                    [
                        'process_image' => true,
                        'width' => 800,
                        'height' => 450,
                        'fit' => true
                    ]
                );
                
                if (!$imageUrl) {
                    throw new \Exception('Failed to upload image');
                }
                
                $readlist->image_url = $imageUrl;
            }
            
            $readlist->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Readlist updated successfully',
                'readlist' => $readlist,
                'share_url' => $readlist->share_url
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Readlist update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified readlist.
     */
    public function destroy($id)
    {
        try {
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Check if user owns the readlist
            if (auth()->id() !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to delete this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Delete image if it exists
            if ($readlist->image_url) {
                $this->fileUploadService->deleteFile($readlist->image_url);
            }
            
            // Delete will cascade to readlist items due to foreign key constraints
            $readlist->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Readlist deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Readlist deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add an item to a readlist.
     */
    public function addItem(Request $request, $id)
    {
        $request->validate([
            'item_type' => 'required|in:course,post',
            'item_id' => 'required|integer',
            'order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);
        
        try {
            // Log the incoming ID for debugging
            \Log::info('Looking for readlist with ID', [
                'id' => $id,
                'id_type' => gettype($id)
            ]);
            
            // Try both with direct UUID and by querying
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                // If not found, try with where query
                $readlist = Readlist::where('id', (string)$id)->first();
                \Log::info('Tried alternative lookup method', [
                    'found' => $readlist ? 'yes' : 'no'
                ]);
            }
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            $user = auth()->user();
            
            // Debug information
            \Log::info('Add to readlist attempt', [
                'readlist_id' => $id,
                'readlist_user_id' => $readlist->user_id,
                'auth_user_id' => $user->id,
                'is_owner' => ($readlist->user_id === $user->id)
            ]);
            
            // Check if user owns the readlist
            if ($user->id !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to modify this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $itemType = $request->item_type === 'course' ? Course::class : Post::class;
            $itemId = $request->item_id;
            
            // Check if the item exists
            $item = $itemType::find($itemId);
            if (!$item) {
                return response()->json([
                    'message' => ucfirst($request->item_type) . ' not found'
                ], 404);
            }
            
            // If no order specified, add to the end
            $order = $request->order;
            if ($order === null) {
                $maxOrder = $readlist->items()->max('order') ?? -1;
                $order = $maxOrder + 1;
            } else {
                // If order is specified, shift existing items
                $readlist->items()
                    ->where('order', '>=', $order)
                    ->increment('order');
            }
            
            // Create the readlist item
            $readlistItem = $readlist->items()->updateOrCreate(
                [
                    'item_id' => $itemId,
                    'item_type' => $itemType
                ],
                [
                    'order' => $order,
                    'notes' => $request->notes
                ]
            );
            
            DB::commit();
            
            return response()->json([
                'message' => ucfirst($request->item_type) . ' added to readlist',
                'readlist_item' => $readlistItem
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Adding item to readlist failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to add item to readlist: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Remove an item from a readlist.
     */
    public function removeItem(Request $request, $id, $itemId)
    {
        try {
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Check if user owns the readlist
            if (auth()->id() !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to modify this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $readlistItem = ReadlistItem::find($itemId);
            
            if (!$readlistItem) {
                return response()->json([
                    'message' => 'Readlist item not found'
                ], 404);
            }
            
            // Ensure the item belongs to the specified readlist
            if ($readlistItem->readlist_id !== (int)$id) {
                return response()->json([
                    'message' => 'Item does not belong to this readlist'
                ], 400);
            }
            
            // Get the order of the item to be removed
            $removedOrder = $readlistItem->order;
            
            // Delete the item
            $readlistItem->delete();
            
            // Reorder remaining items to prevent gaps
            $readlist->items()
                ->where('order', '>', $removedOrder)
                ->decrement('order');
            
            DB::commit();
            
            return response()->json([
                'message' => 'Item removed from readlist'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Removing item from readlist failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove item from readlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder items in a readlist.
     */
    public function reorderItems(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:readlist_items,id',
            'items.*.order' => 'required|integer|min:0',
        ]);
        
        try {
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Check if user owns the readlist
            if (auth()->id() !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to modify this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            foreach ($request->items as $itemData) {
                $readlistItem = ReadlistItem::find($itemData['id']);
                
                // Ensure the item belongs to the specified readlist
                if ($readlistItem && $readlistItem->readlist_id === (int)$id) {
                    $readlistItem->order = $itemData['order'];
                    $readlistItem->save();
                } else {
                    return response()->json([
                        'message' => 'One or more items do not belong to this readlist'
                    ], 400);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Items reordered successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reordering items in readlist failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to reorder items: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Regenerate the share key for a readlist.
     */
    public function regenerateShareKey($id)
    {
        try {
            $readlist = Readlist::find($id);
            
            if (!$readlist) {
                return response()->json([
                    'message' => 'Readlist not found'
                ], 404);
            }
            
            // Check if user owns the readlist
            if (auth()->id() !== $readlist->user_id) {
                return response()->json([
                    'message' => 'You do not have permission to modify this readlist'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $readlist->share_key = Str::random(10);
            $readlist->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Share key regenerated successfully',
                'share_key' => $readlist->share_key,
                'share_url' => $readlist->share_url
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Share key regeneration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to regenerate share key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug helper to see what readlists exist and their ID types
     */
    public function debugReadlists()
    {
        try {
            // Get all readlists
            $readlists = Readlist::all();
            
            $debugInfo = [
                'total_count' => $readlists->count(),
                'readlists' => $readlists->map(function($readlist) {
                    return [
                        'id' => $readlist->id,
                        'id_type' => gettype($readlist->id),
                        'title' => $readlist->title,
                        'user_id' => $readlist->user_id,
                        'created_at' => $readlist->created_at->toDateTimeString(),
                        'items_count' => $readlist->items()->count()
                    ];
                }),
                'model_config' => [
                    'incrementing' => Readlist::$incrementing,
                    'keyType' => (new Readlist())->getKeyType()
                ]
            ];
            
            return response()->json($debugInfo);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Helper function to truncate text safely without mb_strimwidth
     */
    private function truncateText($text, $length = 200, $ellipsis = '...')
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $ellipsis;
    }
}