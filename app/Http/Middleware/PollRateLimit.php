<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PollRateLimit
 * 
 * @package App\Http\Middleware
 */
class PollRateLimit
{
    /**
     * Handler for the poll rate limit middleware
     * 
     * @param Request $request
     * @param Closure $next
     * 
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'poll-req-' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            return response()->json([
                'message' => 'Too many attempts, try again later'
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
