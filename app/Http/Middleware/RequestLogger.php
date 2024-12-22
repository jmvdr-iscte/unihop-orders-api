<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * TThe received request
     * 
     * @param  \Closure $next
     * The next step
     * 
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        Log::info('Request Method: ' . $request->method());
        Log::info('Request URL: ' . $request->fullUrl());
        Log::info('Request Headers: ', $request->headers->all());
        Log::info('Request Payload: ', $request->all());
        Log::info('Request Time: ' . now());

        return $next($request);
    }
}
