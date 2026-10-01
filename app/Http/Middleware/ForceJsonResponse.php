<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // If the client expects JSON, set the header
        if ($request->is('api/*')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
