<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserBanned
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_banned) {
            // Revoke tokens for banned users
            Auth::user()->tokens()->delete();
            
            return response()->json([
                'message' => 'Your account has been suspended',
                'reason' => Auth::user()->ban_reason,
                'banned_at' => Auth::user()->banned_at,
            ], 403);
        }
        
        return $next($request);
    }
}