<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogResponseTime
{
    /**
     * Handle an incoming request and log response time.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds
        
        // Only log API requests
        if ($request->is('api/*')) {
            $method = $request->method();
            $path = $request->path();
            $status = $response->getStatusCode();
            
            // Log with context
            Log::info("⏱️ API Request", [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'duration_ms' => $duration,
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Warn on slow requests (> 1000ms)
            if ($duration > 1000) {
                Log::warning("🐌 Slow API Request", [
                    'method' => $method,
                    'path' => $path,
                    'duration_ms' => $duration
                ]);
            }
        }
        
        // Add timing header for debugging
        $response->headers->set('X-Response-Time', $duration . 'ms');
        
        return $response;
    }
}
