<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Upload an image file
     *
     * @param UploadedFile $file
     * @param string $type The upload type/folder
     * @param array $transformations Optional transformations
     * @return string The uploaded file URL
     */
    public function uploadImage(UploadedFile $file, string $type = 'image', array $transformations = [])
    {
        try {
            $uploadOptions = [
                'folder' => $type,
                'resource_type' => 'image',
            ];

            if (!empty($transformations)) {
                $uploadOptions['transformation'] = $transformations;
            }

            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage());
            throw new \Exception('Failed to upload image: ' . $e->getMessage());
        }
    }

    /**
     * Upload a video file
     *
     * @param UploadedFile $file
     * @param string $type The upload type/folder
     * @return string The uploaded file URL
     */
    public function uploadVideo(UploadedFile $file, string $type = 'video')
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => $type,
                    'resource_type' => 'video',
                ]
            );

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary video upload failed: ' . $e->getMessage());
            throw new \Exception('Failed to upload video: ' . $e->getMessage());
        }
    }

    /**
     * Upload a general file
     *
     * @param UploadedFile $file
     * @param string $type The upload type/folder
     * @param array $options Optional upload options
     * @return string The uploaded file URL
     */
    public function uploadFile(UploadedFile $file, string $type = 'file', array $options = [])
    {
        try {
            $uploadOptions = array_merge([
                'folder' => $type,
            ], $options);

            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary file upload failed: ' . $e->getMessage());
            throw new \Exception('Failed to upload file: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file from Cloudinary
     *
     * @param string $url The file URL to delete
     * @param string $resourceType The resource type (image, video, raw)
     * @return bool Whether the deletion was successful
     */
    public function deleteFile(string $url, string $resourceType = 'image'): bool
    {
        try {
            // Extract public_id from URL
            $publicId = $this->extractPublicId($url);
            
            if (!$publicId) {
                return false;
            }

            $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType
            ]);

            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract public_id from Cloudinary URL
     */
    private function extractPublicId(string $url): ?string
    {
        // Cloudinary URLs typically look like:
        // https://res.cloudinary.com/{cloud_name}/{resource_type}/upload/{transformations}/{public_id}.{format}
        
        $pattern = '/\/v\d+\/(.+?)(?:\.[^.]+)?$/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Fallback: try to extract from path
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        
        // Look for the public_id (usually after 'upload' or version number)
        $uploadIndex = array_search('upload', $parts);
        if ($uploadIndex !== false && isset($parts[$uploadIndex + 2])) {
            return $parts[$uploadIndex + 2];
        }

        return null;
    }
}

