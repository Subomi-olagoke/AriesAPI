<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    /**
     * Handle an incoming request and compress the response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only compress if client accepts gzip
        $acceptEncoding = $request->header('Accept-Encoding', '');
        
        if (!str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }
        
        // Don't compress if already compressed or streaming
        if ($response->headers->has('Content-Encoding') || 
            $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }
        
        // Get the content
        $content = $response->getContent();
        
        // Only compress if content is substantial (> 1KB) and compressible
        if (strlen($content) < 1024) {
            return $response;
        }
        
        // Compress the content
        $compressed = gzencode($content, 6); // Level 6 is good balance between speed and compression
        
        if ($compressed === false) {
            return $response;
        }
        
        // Update response with compressed content
        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', strlen($compressed));
        
        return $response;
    }
}
