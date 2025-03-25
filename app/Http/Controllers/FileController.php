<?php

namespace App\Http\Controllers;

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
}