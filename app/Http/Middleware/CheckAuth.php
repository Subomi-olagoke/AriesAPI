<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // User is authenticated, proceed with the request
            return $next($request);
        }

        // User is not authenticated, handle the unauthorized access as needed
        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
