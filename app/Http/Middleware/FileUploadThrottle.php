<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Cache\RateLimiter;

class FileUploadThrottle extends ThrottleRequests
{
    /**
     * Create a new middleware instance.
     *
     * @param \Illuminate\Cache\RateLimiter $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        parent::__construct($limiter);
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next, $maxAttempts = 20, $decayMinutes = 1)
    {
        // Set higher PHP limits for file upload requests
        ini_set('upload_max_filesize', '500M');
        ini_set('post_max_size', '500M');
        ini_set('max_execution_time', '900');  // 15 minutes
        ini_set('max_input_time', '900');      // 15 minutes
        ini_set('memory_limit', '1024M');      // 1GB
        
        // Use a separate limiter key for file uploads
        $key = 'file_uploads:' . $request->ip();
        
        // Use dynamic rate limiting based on available server resources
        $maxAttempts = config('throttle.file_uploads.max_attempts', 20);
        $decayMinutes = config('throttle.file_uploads.decay_minutes', 1);
        
        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $key);
    }
}