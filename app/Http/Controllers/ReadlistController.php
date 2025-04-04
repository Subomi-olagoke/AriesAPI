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
        'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
    ]);
    
    $user = $request->user();
    
    // Log the user ID for debugging
    \Log::info('Creating readlist', [
        'user_id' => $user->id,
        'user_class' => get_class($user)
    ]);
    
    try {
        DB::beginTransaction();
        
        // Get the next ID for readlists (starting from 1)
        $nextId = SimpleIdGenerator::getNextId('readlists');
        
        // Create readlist with direct property assignment
        $readlist = new Readlist();
        $readlist->id = $nextId; // Set the ID explicitly
        $readlist->title = $request->title;
        $readlist->description = $request->description;
        $readlist->is_public = $request->is_public === 'true' || $request->is_public === '1' ? true : false;
        $readlist->user_id = $user->id;
        $readlist->share_key = Str::random(10);
        
        // Handle image upload if provided
        if ($request->hasFile('image')) {
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
        
        // Save the readlist with the custom ID
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
     * Display the specified readlist with its items.
     */
    public function show($id)
    {
        $readlist = Readlist::with([
            'user:id,username,first_name,last_name,avatar',
            'items.item'
        ])->findOrFail($id);
        
        // Permission check removed - allow viewing any readlist
        
        // Organize items by type
        $items = $readlist->items;
        $organizedItems = [];
        
        foreach ($items as $item) {
            if ($item->item_type === Course::class) {
                $course = $item->item;
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
            } elseif ($item->item_type === Post::class) {
                $post = $item->item;
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
    }

    /**
     * Display a readlist by its share key (public access).
     */
    public function showByShareKey($shareKey)
    {
        $readlist = Readlist::with([
            'user:id,username,first_name,last_name,avatar',
            'items.item'
        ])->where('share_key', $shareKey)->firstOrFail();
        
        // Permission check removed - allow viewing any shared readlist
        
        // Organize items by type
        $items = $readlist->items;
        $organizedItems = [];
        
        foreach ($items as $item) {
            if ($item->item_type === Course::class) {
                $course = $item->item;
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
            } elseif ($item->item_type === Post::class) {
                $post = $item->item;
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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
        ]);
        
        $readlist = Readlist::findOrFail($id);
        
        // Permission check removed - allow updating any readlist
        
        try {
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
            DB::rollback();
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
        $readlist = Readlist::findOrFail($id);
        
        // Permission check removed - allow deleting any readlist
        
        try {
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
            DB::rollback();
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
        
        $readlist = Readlist::findOrFail($id);
        $user = auth()->user();
        
        // Debug information
        \Log::info('Add to readlist attempt', [
            'readlist_id' => $id,
            'readlist_user_id' => $readlist->user_id,
            'auth_user_id' => $user->id,
            'is_owner' => ($readlist->user_id === $user->id)
        ]);
        
        // Permission check removed to allow anyone to add items to readlists
        // This allows the app to add items to any readlist regardless of ownership
        
        try {
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
        $readlist = Readlist::findOrFail($id);
        
        // Permission check removed - allow modifying any readlist
        
        try {
            DB::beginTransaction();
            
            $readlistItem = ReadlistItem::findOrFail($itemId);
            
            // Ensure the item belongs to the specified readlist
            if ($readlistItem->readlist_id !== $readlist->id) {
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
            DB::rollback();
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
        
        $readlist = Readlist::findOrFail($id);
        
        // Permission check removed - allow modifying any readlist
        
        try {
            DB::beginTransaction();
            
            foreach ($request->items as $itemData) {
                $readlistItem = ReadlistItem::find($itemData['id']);
                
                // Ensure the item belongs to the specified readlist
                if ($readlistItem && $readlistItem->readlist_id === $readlist->id) {
                    $readlistItem->order = $itemData['order'];
                    $readlistItem->save();
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Items reordered successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
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
        $readlist = Readlist::findOrFail($id);
        
        // Permission check removed - allow modifying any readlist
        
        try {
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
            DB::rollback();
            Log::error('Share key regeneration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to regenerate share key: ' . $e->getMessage()
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
        
        $readlists = Readlist::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'readlists' => $readlists
        ]);
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