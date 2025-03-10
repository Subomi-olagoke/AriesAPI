<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Transformation\Resize;
use Cloudinary\Transformation\Gravity;
use Cloudinary\Transformation\Quality;
use Cloudinary\Transformation\FocusOn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Exception;
use Log;

class CloudinaryService
{
    protected $cloudinary;
    protected $uploadApi;

    /**
     * Create a new service instance.
     */
    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
        $this->uploadApi = $cloudinary->uploadApi();
    }

    /**
     * Upload a file to Cloudinary
     *
     * @param UploadedFile $file The file to upload
     * @param string $type The type of upload (avatar, post_image, etc.)
     * @param array $options Additional upload options
     * @return string|null The URL of the uploaded file or null on failure
     */
    public function uploadFile(UploadedFile $file, string $type, array $options = []): ?string
    {
        try {
            // Get the preset folder for this type of upload
            $preset = config('cloudinary.upload_presets.' . $type, $type);
            
            // Generate a unique public ID for the asset
            $publicId = $preset . '/' . Str::uuid()->toString();
            
            // Set up default options
            $uploadOptions = [
                'folder' => $preset,
                'public_id' => $publicId,
                'resource_type' => 'auto', // auto-detect if it's an image or video
                'overwrite' => true,
            ];
            
            // Apply transformations if configured for this type
            $transformations = config('cloudinary.transformations.' . $type);
            if ($transformations) {
                $uploadOptions['transformation'] = $transformations;
            }
            
            // Merge with any custom options
            $uploadOptions = array_merge($uploadOptions, $options);

            // Perform the upload
            $result = $this->uploadApi->upload($file->getRealPath(), $uploadOptions);
            
            // Return the secure URL
            return $result['secure_url'] ?? null;
        } catch (Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'type' => $type,
                'options' => $options,
                'exception' => $e
            ]);
            
            return null;
        }
    }

    /**
     * Upload an image with custom transformations
     *
     * @param UploadedFile $file The image to upload
     * @param string $type The type of upload
     * @param array $transformations Custom transformations
     * @return string|null The URL of the uploaded file
     */
    public function uploadImage(UploadedFile $file, string $type, array $transformations = []): ?string
    {
        $options = [
            'resource_type' => 'image',
        ];
        
        if (!empty($transformations)) {
            $options['transformation'] = $transformations;
        }
        
        return $this->uploadFile($file, $type, $options);
    }

    /**
     * Upload a video with custom transformations
     *
     * @param UploadedFile $file The video to upload
     * @param string $type The type of upload
     * @param array $transformations Custom transformations
     * @return string|null The URL of the uploaded file
     */
    public function uploadVideo(UploadedFile $file, string $type, array $transformations = []): ?string
    {
        $options = [
            'resource_type' => 'video',
            'eager_async' => true,
        ];
        
        if (!empty($transformations)) {
            $options['eager'] = $transformations;
        }
        
        return $this->uploadFile($file, $type, $options);
    }

    /**
     * Delete a file from Cloudinary
     *
     * @param string $publicId The public ID of the file
     * @param string $resourceType The resource type (image, video, raw)
     * @return bool Whether the deletion was successful
     */
    public function deleteFile(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            // Extract public ID from URL if a full URL was provided
            if (Str::startsWith($publicId, 'http')) {
                $parts = parse_url($publicId);
                $path = $parts['path'] ?? '';
                
                // Remove version if present
                $path = preg_replace('/\/v\d+\//', '/', $path);
                
                // Extract filename and folder
                $pathParts = explode('/', trim($path, '/'));
                $filename = end($pathParts);
                
                // Remove file extension
                $publicId = Str::beforeLast($filename, '.');
                
                // Add folder if present
                if (count($pathParts) > 1) {
                    $folder = $pathParts[count($pathParts) - 2];
                    $publicId = $folder . '/' . $publicId;
                }
            }
            
            $result = $this->uploadApi->destroy($publicId, [
                'resource_type' => $resourceType
            ]);
            
            return $result['result'] === 'ok';
        } catch (Exception $e) {
            Log::error('Cloudinary deletion failed: ' . $e->getMessage(), [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'exception' => $e
            ]);
            
            return false;
        }
    }

    /**
     * Generate a signed URL for a transformation
     *
     * @param string $url The original URL
     * @param array $transformations The transformations to apply
     * @return string The transformed URL
     */
    public function generateUrl(string $url, array $transformations): string
    {
        // Implementation depends on your specific needs
        // This is a simplified example
        return $url;
    }
}