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
                'chunk_size' => 20000000, // 20MB chunks for larger files
                'timeout' => 600000, // 10 minutes timeout for large uploads
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
            'chunk_size' => 50000000, // 50MB chunks for larger files
            'timeout' => 1800000, // 30 minutes timeout for large uploads
            'eager_notification_url' => config('app.url') . '/api/cloudinary/notification',
            'use_filename' => true,
            'unique_filename' => true,
            'overwrite' => true,
        ];
        
        // Add adaptive streaming profiles for better video delivery
        $options['eager'] = [
            [
                'streaming_profile' => 'hd', 
                'format' => 'm3u8'
            ],
            [
                'quality' => 'auto',
                'format' => 'mp4'
            ]
        ];
        
        // Add custom transformations if provided
        if (!empty($transformations)) {
            $options['eager'][] = $transformations;
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
    
    /**
     * Extract public ID from a Cloudinary URL
     * 
     * @param string $url The Cloudinary URL
     * @return string The public ID
     */
    protected function getPublicIdFromUrl(string $url): string
    {
        if (Str::startsWith($url, 'http')) {
            $parts = parse_url($url);
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
            
            return $publicId;
        }
        
        return $url;
    }
    
    /**
     * Create an adaptive streaming version of an existing video
     * 
     * @param string $videoUrl The URL of the uploaded video
     * @param array $options Additional options
     * @return string The URL of the streaming video
     */
    public function createAdaptiveStreamingVideo(string $videoUrl, array $options = []): string
    {
        try {
            // Use Cloudinary's API to create an adaptive streaming profile
            $result = $this->uploadApi->explicit(
                $this->getPublicIdFromUrl($videoUrl),
                [
                    'resource_type' => 'video',
                    'eager' => [
                        [
                            'streaming_profile' => 'hd',
                            'format' => 'm3u8'
                        ],
                        [
                            'quality' => 'auto',
                            'format' => 'mp4'
                        ]
                    ],
                    'eager_async' => true,
                    'eager_notification_url' => config('app.url') . '/api/cloudinary/notification',
                    'type' => 'upload'
                ]
            );
            
            return $result['eager'][0]['secure_url'] ?? $videoUrl;
        } catch (Exception $e) {
            Log::error('Cloudinary streaming profile creation failed: ' . $e->getMessage(), [
                'video_url' => $videoUrl,
                'exception' => $e
            ]);
            
            return $videoUrl;
        }
    }
}