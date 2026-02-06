<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\LibraryContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminApiLibraryController extends Controller
{
    /**
     * Get a list of libraries with filtering options
     */
    public function getLibraries(Request $request)
    {
        $query = OpenLibrary::with(['contents']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('approval_status', $request->status);
        }
        
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }
        
        // Sort options
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);
        
        // Pagination
        $libraries = $query->withCount('contents')->paginate($request->input('per_page', 10));
        
        // Format the response
        return response()->json([
            'libraries' => $libraries->items(),
            'pagination' => [
                'total' => $libraries->total(),
                'per_page' => $libraries->perPage(),
                'current_page' => $libraries->currentPage(),
                'last_page' => $libraries->lastPage()
            ]
        ]);
    }
    
    /**
     * Get a specific library with its contents
     */
    public function getLibrary($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        // Get detailed content items
        $contents = LibraryContent::where('library_id', $id)
            ->with(['content'])
            ->orderBy('relevance_score', 'desc')
            ->get()
            ->map(function($item) {
                $content = $item->content;
                if (!$content) {
                    return null;
                }
                
                return [
                    'id' => $item->id,
                    'content_id' => $content->id,
                    'content_type' => class_basename($item->content_type),
                    'title' => $content->title ?? 'Untitled',
                    'description' => $content->description ?? 
                                     $content->body ?? 
                                     substr($content->content ?? '', 0, 100) . '...',
                    'relevance_score' => $item->relevance_score,
                    'user' => $content->user ? [
                        'id' => $content->user->id,
                        'name' => $content->user->first_name . ' ' . $content->user->last_name,
                        'username' => $content->user->username
                    ] : null,
                    'created_at' => $content->created_at
                ];
            })
            ->filter(); // Remove null entries
        
        return response()->json([
            'library' => $library,
            'contents' => $contents,
            'approval_info' => [
                'can_approve' => $library->approval_status !== 'approved' && $contents->count() >= 5,
                'can_reject' => $library->approval_status !== 'rejected',
                'approver' => $library->approved_by ? [
                    'id' => $library->approver->id,
                    'name' => $library->approver->first_name . ' ' . $library->approver->last_name
                ] : null
            ]
        ]);
    }
    
    /**
     * Approve a library
     */
    public function approveLibrary(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        // Check if already approved
        if ($library->approval_status === 'approved') {
            return response()->json([
                'message' => 'Library is already approved'
            ], 400);
        }
        
        // Validate minimum content requirement
        $contentCount = LibraryContent::where('library_id', $id)->count();
        if ($contentCount < 5) {
            return response()->json([
                'message' => 'Library must have at least 5 content items to be approved'
            ], 400);
        }
        
        // Update library status
        $library->is_approved = true;
        $library->approval_status = 'approved';
        $library->approval_date = now();
        $library->approved_by = Auth::id();
        
        // Generate cover image if requested
        if ($request->has('generate_cover') && $request->generate_cover === true) {
            $this->generateCoverImage($library);
        }
        
        $library->save();
        
        return response()->json([
            'message' => 'Library approved successfully',
            'library' => $library
        ]);
    }
    
    /**
     * Reject a library
     */
    public function rejectLibrary(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        $library = OpenLibrary::findOrFail($id);
        
        // Check if already rejected
        if ($library->approval_status === 'rejected') {
            return response()->json([
                'message' => 'Library is already rejected'
            ], 400);
        }
        
        // Update library status
        $library->is_approved = false;
        $library->approval_status = 'rejected';
        $library->rejection_reason = $request->reason;
        $library->save();
        
        return response()->json([
            'message' => 'Library rejected successfully',
            'library' => $library
        ]);
    }
    
    /**
     * Generate a cover image for the library
     */
    public function generateCoverImage(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        try {
            // Create a prompt based on library content
            $contents = LibraryContent::where('library_id', $id)
                ->with('content')
                ->orderBy('relevance_score', 'desc')
                ->take(10)
                ->get();
                
            $topics = [];
            foreach ($contents as $item) {
                if ($item->content && isset($item->content->title)) {
                    $topics[] = $item->content->title;
                }
            }
            
            // Base prompt template
            $prompt = "Create a subtle, abstract, and minimal cover image for a content library titled '{$library->name}'";
            
            if (!empty($library->description)) {
                $prompt .= " about {$library->description}";
            }
            
            if (!empty($topics)) {
                $prompt .= ". The library contains content about: " . implode(", ", $topics);
            }
            
            $prompt .= ". Use a light color palette with subtle gradients. The design should be modern, clean, and professional without being too busy or distracting. Do not include any text or words in the image.";
            
            // Save the prompt
            $library->cover_prompt = $prompt;
            $library->save();
            
            // Call OpenAI API
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
                $imageData = $response->json();
                $imageUrl = $imageData['data'][0]['url'];
                
                // Save the image URL
                $library->cover_image_url = $imageUrl;
                $library->has_ai_cover = true;
                $library->save();
                
                return response()->json([
                    'message' => 'Cover image generated successfully',
                    'cover_image_url' => $imageUrl,
                    'prompt' => $prompt
                ]);
            } else {
                Log::error('OpenAI API error: ' . $response->body());
                return response()->json([
                    'message' => 'Failed to generate cover image: ' . $response->json('error.message', 'API error')
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error generating cover image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error generating cover image: ' . $e->getMessage()
            ], 500);
        }
    }
}