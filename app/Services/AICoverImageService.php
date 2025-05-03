<?php

namespace App\Services;

use App\Models\OpenLibrary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AICoverImageService
{
    /**
     * Generate a cover image for a library using OpenAI's DALL-E
     * 
     * @param OpenLibrary $library The library to generate a cover for
     * @return string|null The URL of the generated image, or null on failure
     */
    public function generateCoverImage(OpenLibrary $library)
    {
        try {
            // Create prompt from library contents
            $prompt = $this->createImagePrompt($library);
            
            // Save the prompt to the library
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
                // Get the image URL
                $imageUrl = $response->json('data.0.url');
                
                // Download and store the image
                $storedUrl = $this->downloadAndStoreImage($imageUrl, $library->id);
                
                // Update the library
                $library->cover_image_url = $storedUrl;
                $library->has_ai_cover = true;
                $library->save();
                
                return $storedUrl;
            } else {
                Log::error('OpenAI DALL-E generation failed', [
                    'response' => $response->json(),
                    'library_id' => $library->id
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error generating library cover image', [
                'message' => $e->getMessage(),
                'library_id' => $library->id
            ]);
            return null;
        }
    }
    
    /**
     * Create a prompt for the DALL-E image generation
     * 
     * @param OpenLibrary $library The library to create a prompt for
     * @return string The generated prompt
     */
    private function createImagePrompt(OpenLibrary $library)
    {
        // Base prompt for all library covers
        $basePrompt = "Create a subtle, abstract, and minimal cover image";
        
        // Add library name and description
        $prompt = "{$basePrompt} for a content library titled '{$library->name}'";
        
        if (!empty($library->description)) {
            $prompt .= " about {$library->description}";
        }
        
        // Extract topics from library content
        $topics = $this->extractTopicsFromLibrary($library);
        
        if (!empty($topics)) {
            $prompt .= ". The library contains content about: " . implode(", ", array_slice($topics, 0, 5));
        }
        
        // Add style guidance
        $prompt .= ". Use a light color palette with subtle gradients. The design should be modern, clean, and professional without being too busy or distracting. Do not include any text or words in the image.";
        
        return $prompt;
    }
    
    /**
     * Extract topic keywords from a library's content
     * 
     * @param OpenLibrary $library The library to extract topics from
     * @return array An array of topic strings
     */
    private function extractTopicsFromLibrary(OpenLibrary $library)
    {
        $topics = [];
        
        // Get content items
        $contents = $library->contents()
            ->with('content')
            ->orderBy('relevance_score', 'desc')
            ->take(10)
            ->get();
            
        foreach ($contents as $item) {
            // Skip if content doesn't exist
            if (!$item->content) {
                continue;
            }
            
            // Add title if available
            if (isset($item->content->title)) {
                $topics[] = $item->content->title;
            }
            
            // Add topic name if it's a course with a topic
            if ($item->content_type === 'App\\Models\\Course' && 
                isset($item->content->topic) && 
                isset($item->content->topic->name)) {
                $topics[] = $item->content->topic->name;
            }
        }
        
        return array_unique($topics);
    }
    
    /**
     * Download an image from a URL and store it in cloud storage
     * 
     * @param string $imageUrl The URL of the image to download
     * @param int $libraryId The ID of the library
     * @return string The URL where the image is stored
     */
    private function downloadAndStoreImage($imageUrl, $libraryId)
    {
        // Download the image
        $imageContents = file_get_contents($imageUrl);
        
        // Generate a unique filename
        $filename = 'library_' . $libraryId . '_' . Str::random(8) . '.png';
        $path = 'libraries/covers/' . $filename;
        
        // Store the image
        if (config('filesystems.default') === 's3') {
            // Store on S3
            Storage::disk('s3')->put($path, $imageContents, 'public');
            return Storage::disk('s3')->url($path);
        } else {
            // Store locally and return the public URL
            Storage::disk('public')->put($path, $imageContents);
            return Storage::disk('public')->url($path);
        }
    }
}