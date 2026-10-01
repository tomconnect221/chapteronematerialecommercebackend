<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // If the user is not authenticated on any provided guard, fail.
        if ($this->authenticate($request, $guards) === false) {
            // For API routes, return JSON instead of redirect
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Redirect only for web routes
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }

    /**
     * Attempt to authenticate the user with the given guards.
     */
    protected function authenticate(Request $request, array $guards): bool
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);
                return true;
            }
        }

        return false;
    }
}
