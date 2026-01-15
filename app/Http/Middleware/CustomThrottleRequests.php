<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Cache\RateLimiter;
use Closure;

class CustomThrottleRequests extends ThrottleRequests
{
    /**
     * Create a new rate limiter instance.
     *
     * @param  \Illuminate\Cache\RateLimiter  $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        parent::__construct($limiter);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        // Determine the appropriate throttle limits based on the request
        if ($this->isFileUploadRequest($request)) {
            // Use file upload rate limits
            $maxAttempts = config('throttle.file_uploads.max_attempts', 120);
            $decayMinutes = config('throttle.file_uploads.decay_minutes', 1);
        } else if ($request->user()) {
            // Use authenticated user rate limits
            $maxAttempts = config('throttle.authenticated.max_attempts', 120);
            $decayMinutes = config('throttle.authenticated.decay_minutes', 1);
        } else {
            // Use unauthenticated user rate limits
            $maxAttempts = config('throttle.unauthenticated.max_attempts', 40);
            $decayMinutes = config('throttle.unauthenticated.decay_minutes', 1);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /**
     * Determine if the request is for a file upload endpoint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isFileUploadRequest($request)
    {
        // Define patterns for file upload routes
        $fileUploadPatterns = [
            '/api/posts',               // Post creation with files
            '/api/files/upload',        // Direct file uploads
            '/api/profile/avatar',      // Profile picture uploads
            '/api/courses/*/media',     // Course media uploads
            '/api/lessons/*/media',     // Lesson media uploads
            '/api/messages/*/attachment', // Message attachments
        ];

        // Check if the current route matches any file upload pattern
        foreach ($fileUploadPatterns as $pattern) {
            if ($request->is($pattern) && ($request->isMethod('post') || $request->isMethod('put'))) {
                return true;
            }
        }

        return false;
    }
}