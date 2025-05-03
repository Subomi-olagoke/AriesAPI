<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\LibraryContent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminLibraryController extends Controller
{
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
        
        // Validate minimum content requirement
        $contentCount = $library->contents()->count();
        if ($contentCount < 5) {
            return redirect()->back()->with('error', 'Library must have at least 5 content items to be approved.');
        }
        
        // Generate AI cover image if requested and not already present
        if ($request->has('generate_cover') && !$library->has_ai_cover) {
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
        
        return redirect()->route('admin.libraries.index')->with('success', 'Library has been approved successfully.');
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
        
        // Update library status
        $library->is_approved = false;
        $library->approval_status = 'rejected';
        $library->rejection_reason = $request->rejection_reason;
        $library->save();
        
        return redirect()->route('admin.libraries.index')->with('success', 'Library has been rejected.');
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
}