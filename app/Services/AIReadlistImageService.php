<?php

namespace App\Services;

use App\Models\Readlist;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AIReadlistImageService
{
    /**
     * Generate a water paint/art style image for a readlist using DALL-E
     * 
     * @param Readlist $readlist The readlist to generate an image for
     * @param string $topic The main topic of the readlist
     * @param array $keywords Additional keywords for the image
     * @return string|null The URL of the generated image, or null on failure
     */
    public function generateReadlistImage(Readlist $readlist, string $topic, array $keywords = [])
    {
        try {
            // Create artistic prompt from readlist content
            $prompt = $this->createArtisticImagePrompt($readlist, $topic, $keywords);
            
            // Call OpenAI API for DALL-E image generation
            $apiKey = config('services.openai.api_key');
            
            if (empty($apiKey)) {
                Log::error('OpenAI API key not configured for readlist image generation');
                return null;
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'hd',
                'style' => 'vivid'
            ]);
            
            if ($response->successful()) {
                $imageData = $response->json();
                $imageUrl = $imageData['data'][0]['url'] ?? null;
                
                if ($imageUrl) {
                    // Download and store the image in Cloudinary
                    $storedUrl = $this->downloadAndStoreImage($imageUrl, $readlist->id);
                    
                    if ($storedUrl) {
                        // Update the readlist with the new image
                        $readlist->image_url = $storedUrl;
                        $readlist->save();
                        
                        Log::info('Successfully generated and stored readlist image', [
                            'readlist_id' => $readlist->id,
                            'topic' => $topic,
                            'image_url' => $storedUrl
                        ]);
                        
                        return $storedUrl;
                    }
                }
            } else {
                Log::error('DALL-E image generation failed', [
                    'response' => $response->json(),
                    'readlist_id' => $readlist->id,
                    'topic' => $topic
                ]);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error generating readlist image', [
                'message' => $e->getMessage(),
                'readlist_id' => $readlist->id,
                'topic' => $topic
            ]);
            
            return null;
        }
    }
    
    /**
     * Create an artistic prompt for water paint/art style image generation
     * 
     * @param Readlist $readlist The readlist to create a prompt for
     * @param string $topic The main topic
     * @param array $keywords Additional keywords
     * @return string The generated prompt
     */
    private function createArtisticImagePrompt(Readlist $readlist, string $topic, array $keywords = [])
    {
        // Start with the artistic style description
        $prompt = "Create a beautiful watercolor painting in artistic style featuring ";
        
        // Add the main topic
        $prompt .= "the concept of '{$topic}'";
        
        // Add readlist title if available
        if (!empty($readlist->title)) {
            $prompt .= " for a collection titled '{$readlist->title}'";
        }
        
        // Add description context if available
        if (!empty($readlist->description)) {
            $prompt .= ". The collection is about: {$readlist->description}";
        }
        
        // Add keywords for more specific imagery
        if (!empty($keywords)) {
            $keywordString = implode(', ', array_slice($keywords, 0, 5));
            $prompt .= ". Include visual elements representing: {$keywordString}";
        }
        
        // Add artistic style instructions
        $prompt .= ". The painting should be:";
        $prompt .= "\n- In a beautiful watercolor style with soft, flowing colors";
        $prompt .= "\n- Artistic and abstract, not photorealistic";
        $prompt .= "\n- Using a harmonious color palette that reflects the theme";
        $prompt .= "\n- With elegant brushstrokes and artistic composition";
        $prompt .= "\n- Suitable for an educational platform cover image";
        $prompt .= "\n- Avoid any text, words, or recognizable logos";
        $prompt .= "\n- Create a sense of wonder and learning through visual metaphor";
        
        // Add specific style variations based on topic
        $topicLower = strtolower($topic);
        if (strpos($topicLower, 'technology') !== false || strpos($topicLower, 'programming') !== false) {
            $prompt .= "\n- Use blues, purples, and digital-inspired geometric elements";
        } elseif (strpos($topicLower, 'nature') !== false || strpos($topicLower, 'environment') !== false) {
            $prompt .= "\n- Use greens, earth tones, and organic flowing shapes";
        } elseif (strpos($topicLower, 'art') !== false || strpos($topicLower, 'creative') !== false) {
            $prompt .= "\n- Use vibrant, expressive colors with dynamic brushwork";
        } elseif (strpos($topicLower, 'science') !== false || strpos($topicLower, 'research') !== false) {
            $prompt .= "\n- Use cool blues and whites with subtle scientific symbols";
        } elseif (strpos($topicLower, 'history') !== false || strpos($topicLower, 'culture') !== false) {
            $prompt .= "\n- Use warm, vintage-inspired colors with classical elements";
        } else {
            $prompt .= "\n- Use a balanced, professional color palette suitable for learning";
        }
        
        return $prompt;
    }
    
    /**
     * Download and store the generated image in Cloudinary
     * 
     * @param string $imageUrl The URL of the generated image
     * @param string $readlistId The readlist ID for naming
     * @return string|null The stored image URL or null on failure
     */
    private function downloadAndStoreImage(string $imageUrl, string $readlistId): ?string
    {
        try {
            // Download the image
            $imageContent = Http::get($imageUrl)->body();
            
            if (empty($imageContent)) {
                Log::error('Failed to download generated image', ['image_url' => $imageUrl]);
                return null;
            }
            
            // Create a temporary file
            $tempPath = storage_path('app/temp/readlist_' . $readlistId . '_' . Str::random(10) . '.png');
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Save the image to temp file
            file_put_contents($tempPath, $imageContent);
            
            // Upload to Cloudinary using the existing service
            $fileUploadService = app(\App\Services\FileUploadService::class);
            
            // Create a mock UploadedFile object
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                'readlist_image.png',
                'image/png',
                null,
                true
            );
            
            // Upload to Cloudinary
            $cloudinaryUrl = $fileUploadService->uploadFile(
                $uploadedFile,
                'readlist_images',
                [
                    'process_image' => true,
                    'width' => 800,
                    'height' => 450,
                    'fit' => true
                ]
            );
            
            // Delete the temp file
            @unlink($tempPath);
            
            return $cloudinaryUrl;
        } catch (\Exception $e) {
            Log::error('Error storing readlist image', [
                'message' => $e->getMessage(),
                'readlist_id' => $readlistId,
                'image_url' => $imageUrl
            ]);
            return null;
        }
    }
    
    /**
     * Extract keywords from readlist content for better image generation
     * 
     * @param Readlist $readlist The readlist to extract keywords from
     * @return array Array of keywords
     */
    public function extractKeywordsFromReadlist(Readlist $readlist): array
    {
        $keywords = [];
        
        // Extract from title
        if (!empty($readlist->title)) {
            $titleKeywords = $this->extractKeywordsFromText($readlist->title);
            $keywords = array_merge($keywords, $titleKeywords);
        }
        
        // Extract from description
        if (!empty($readlist->description)) {
            $descKeywords = $this->extractKeywordsFromText($readlist->description);
            $keywords = array_merge($keywords, $descKeywords);
        }
        
        // Extract from readlist items
        foreach ($readlist->items as $item) {
            if ($item->title) {
                $itemKeywords = $this->extractKeywordsFromText($item->title);
                $keywords = array_merge($keywords, $itemKeywords);
            }
            
            if ($item->description) {
                $itemDescKeywords = $this->extractKeywordsFromText($item->description);
                $keywords = array_merge($keywords, $itemDescKeywords);
            }
        }
        
        // Remove duplicates and common words
        $keywords = array_unique($keywords);
        $keywords = array_filter($keywords, function($keyword) {
            return strlen($keyword) > 3 && !in_array(strtolower($keyword), [
                'the', 'and', 'for', 'with', 'this', 'that', 'from', 'about', 'into', 'through'
            ]);
        });
        
        return array_slice($keywords, 0, 10); // Limit to top 10 keywords
    }
    
    /**
     * Extract keywords from text
     * 
     * @param string $text The text to extract keywords from
     * @return array Array of keywords
     */
    private function extractKeywordsFromText(string $text): array
    {
        // Simple keyword extraction - in a real implementation, you might use NLP libraries
        $words = preg_split('/\s+/', strtolower($text));
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}"\'-');
            if (strlen($word) > 3 && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }
        
        return $keywords;
    }
} 