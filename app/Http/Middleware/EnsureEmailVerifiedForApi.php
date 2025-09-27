<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedForApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is not authenticated, let auth:sanctum handle it
        if (!$user) {
            return $next($request);
        }

        // If user is authenticated but not verified, return error
        if (!$user->isVerified()) {
            return response()->json([
                'message' => 'Your email address is not verified. Please verify your email to access this resource.',
                'requires_verification' => true,
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
                'status' => 403
            ], 403);
        }

        return $next($request);
    }
}
