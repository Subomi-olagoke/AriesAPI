<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\CloudinaryService;

class FileUploadService
{
    protected $cloudinaryService;

    /**
     * Create a new service instance.
     */
    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload a file using Cloudinary
     *
     * @param UploadedFile $file
     * @param string $path
     * @param array $options Optional processing options
     * @return string The file URL
     */
    public function uploadFile(UploadedFile $file, string $path, array $options = [])
    {
        // Map the path to a Cloudinary upload type
        $type = $this->mapPathToType($path);
        
        // Determine if this is a video file
        $mimeType = $file->getMimeType();
        $isVideo = strpos($mimeType, 'video/') === 0;
        
        if ($isVideo) {
            // Use video upload for video files
            return $this->cloudinaryService->uploadVideo($file, $type);
        } else {
            // Use image upload for images and other files
            return $this->cloudinaryService->uploadFile($file, $type, $options);
        }
    }
    
    /**
     * Upload an image file
     *
     * @param UploadedFile $file
     * @param string $path
     * @param array $options Optional processing options
     * @return string The image URL
     */
    public function uploadImage(UploadedFile $file, string $path, array $options = [])
    {
        // Map the path to a Cloudinary upload type
        $type = $this->mapPathToType($path);
        
        // Process image if options provided
        if (!empty($options)) {
            return $this->processAndUploadImage($file, $type, $options);
        }
        
        // Use the CloudinaryService's uploadImage method
        return $this->cloudinaryService->uploadImage($file, $type);
    }
    
    /**
     * Process and upload an image with optional resizing
     */
    private function processAndUploadImage(UploadedFile $file, string $type, array $options)
    {
        $transformations = [];
        
        // Resize if dimensions provided
        if (isset($options['width']) && isset($options['height'])) {
            $transformations = [
                'width' => $options['width'],
                'height' => $options['height'],
                'crop' => isset($options['fit']) && $options['fit'] ? 'fill' : 'scale',
                'quality' => 'auto'
            ];
            
            // Add gravity option for face-aware cropping
            if ($type === 'avatar') {
                $transformations['gravity'] = 'face';
            }
        }
        
        return $this->cloudinaryService->uploadImage($file, $type, $transformations);
    }
    
    /**
     * Map the path to a Cloudinary upload type
     */
    private function mapPathToType(string $path): string
    {
        $mapping = [
            'avatars' => 'avatar',
            'profile_images' => 'avatar',
            'channel_pictures' => 'channel_picture',
            'course_thumbnails' => 'course_thumbnail',
            'course_videos' => 'course_video',
            'course_files' => 'course_image',
            'lesson_thumbnails' => 'lesson_thumbnail',
            'lesson_videos' => 'lesson_video',
            'lesson_files' => 'lesson_image',
            'media/singles' => 'post_image',
            'media/images' => 'post_image',
            'media/videos' => 'post_video',
            'media/thumbnails' => 'post_thumbnail',
            'message_attachments' => 'message_attachment',
            'readlist_images' => 'readlist_images'
        ];
        
        // Find the best match - look for direct matches first
        if (isset($mapping[$path])) {
            return $mapping[$path];
        }
        
        // Otherwise, look for partial matches
        foreach ($mapping as $key => $type) {
            if (strpos($path, $key) !== false) {
                return $type;
            }
        }
        
        // Default fallback
        return 'image';
    }
    
    /**
     * Delete a file
     * 
     * @param string $url The file URL to delete
     * @return bool Whether the deletion was successful
     */
    public function deleteFile(string $url): bool
    {
        // Extract file extension to determine resource type
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $resourceType = $this->getResourceTypeFromExtension($extension);
        
        return $this->cloudinaryService->deleteFile($url, $resourceType);
    }
    
    /**
     * Determine the Cloudinary resource type based on file extension
     */
    private function getResourceTypeFromExtension(string $extension): string
    {
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'ogg'];
        
        if (in_array(strtolower($extension), $videoExtensions)) {
            return 'video';
        }
        
        return 'image';
    }
}