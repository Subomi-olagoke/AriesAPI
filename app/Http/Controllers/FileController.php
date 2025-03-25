<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Display a listing of uploaded files.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Get all files from the storage disk (default is 'local')
        // You can change this to 's3' or any other configured disk
        $files = Storage::allFiles();
        
        // Format file information
        $formattedFiles = [];
        foreach ($files as $file) {
            // Skip system files
            if (Str::startsWith(basename($file), '.')) {
                continue;
            }
            
            $formattedFiles[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => Storage::size($file),
                'last_modified' => date('Y-m-d H:i:s', Storage::lastModified($file)),
                'download_url' => route('files.download', ['path' => base64_encode($file)])
            ];
        }
        
        return response()->json([
            'success' => true,
            'files' => $formattedFiles
        ]);
    }

    /**
     * Download a specific file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function download(Request $request)
    {
        // Decode the base64-encoded path
        $path = base64_decode($request->path);
        
        // Check if file exists
        if (!Storage::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }
        
        // Get file content and mime type
        $fileContent = Storage::get($path);
        $mimeType = Storage::mimeType($path);
        
        // Create file download response
        $filename = basename($path);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        return Response::make($fileContent, 200, $headers);
    }
    
    /**
     * View file from a post by ID.
     * 
     * @param int $postId The ID of the post containing the file
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function viewPostFile($postId)
    {
        // Find the post by ID
        $post = Post::findOrFail($postId);
        
        // Check if the post has a file
        if (empty($post->media_link)) {
            return response()->json([
                'success' => false,
                'message' => 'No file associated with this post'
            ], 404);
        }
        
        // Extract the file path from the media_link
        $fileUrl = $post->media_link;
        
        // Check if it's a Cloudinary URL
        if (Str::contains($fileUrl, 'cloudinary.com')) {
            // For Cloudinary URLs, redirect directly to the file
            return redirect($fileUrl);
        }
        
        // For local storage, extract the path
        $path = str_replace(config('filesystems.disks.s3.url') . '/', '', $fileUrl);
        
        // Check if file exists
        if (!Storage::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on storage'
            ], 404);
        }
        
        // Get file content and mime type
        $fileContent = Storage::get($path);
        $mimeType = $post->mime_type ?? Storage::mimeType($path);
        
        // Create file view response (inline display)
        $filename = $post->original_filename ?? basename($path);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ];
        
        return Response::make($fileContent, 200, $headers);
    }
    
    /**
     * Download file from a post by ID.
     * 
     * @param int $postId The ID of the post containing the file
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function downloadPostFile($postId)
    {
        // Find the post by ID
        $post = Post::findOrFail($postId);
        
        // Check if the post has a file
        if (empty($post->media_link)) {
            return response()->json([
                'success' => false,
                'message' => 'No file associated with this post'
            ], 404);
        }
        
        // Extract the file path from the media_link
        $fileUrl = $post->media_link;
        
        // Get the original filename or generate one
        $filename = $post->original_filename ?? 'download-' . $postId . '.' . $this->getExtensionFromUrl($fileUrl);
        
        // Check if it's a Cloudinary URL
        if (Str::contains($fileUrl, 'cloudinary.com')) {
            // For Cloudinary URLs, redirect to the file instead of proxying it
            // This avoids the 401 Unauthorized error
            return redirect($fileUrl);
        }
        
        // For local storage, extract the path
        $path = str_replace(config('filesystems.disks.s3.url') . '/', '', $fileUrl);
        
        // Check if file exists
        if (!Storage::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on storage'
            ], 404);
        }
        
        // Get file content and mime type
        $fileContent = Storage::get($path);
        $mimeType = $post->mime_type ?? Storage::mimeType($path);
        
        // Create file download response
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        return Response::make($fileContent, 200, $headers);
    }
    
    /**
     * Extract extension from a URL.
     * 
     * @param string $url The URL to extract extension from
     * @return string The file extension
     */
    private function getExtensionFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return !empty($extension) ? $extension : 'file';
    }
    
    /**
     * Get MIME type from URL extension.
     * 
     * @param string $url The URL to get MIME type for
     * @return string The MIME type
     */
    private function getMimeTypeFromUrl($url)
    {
        $extension = $this->getExtensionFromUrl($url);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}