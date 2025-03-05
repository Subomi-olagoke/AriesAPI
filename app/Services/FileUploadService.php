<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class FileUploadService
{
    /**
     * Upload a file to S3
     *
     * @param UploadedFile $file
     * @param string $path
     * @param array $options Optional processing options
     * @return string The file URL
     */
    public function uploadFile(UploadedFile $file, string $path, array $options = [])
    {
        $filename = $this->generateFilename($file);
        $fullPath = $path . '/' . $filename;
        
        // Check if image processing is needed
        if (isset($options['process_image']) && $options['process_image']) {
            return $this->processAndUploadImage($file, $fullPath, $options);
        }
        
        // Regular file upload
        $contents = file_get_contents($file->getRealPath());
        Storage::disk('s3')->put($fullPath, $contents, 'public');
        
        return Storage::disk('s3')->url($fullPath);
    }
    
    /**
     * Process and upload an image with optional resizing
     */
    private function processAndUploadImage(UploadedFile $file, string $fullPath, array $options)
    {
        $image = Image::read($file);
        
        // Resize if dimensions provided
        if (isset($options['width']) && isset($options['height'])) {
            if (isset($options['fit']) && $options['fit']) {
                $image->fit($options['width'], $options['height']);
            } else {
                $image->resize($options['width'], $options['height']);
            }
        }
        
        // Get image data as string
        $encodedImage = $image->encode();
        $imageData = $encodedImage->toString();
        
        // Upload to S3
        Storage::disk('s3')->put($fullPath, $imageData, 'public');
        
        return Storage::disk('s3')->url($fullPath);
    }
    
    /**
     * Generate a unique filename
     */
    private function generateFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        return uniqid() . '_' . time() . '.' . $extension;
    }
}