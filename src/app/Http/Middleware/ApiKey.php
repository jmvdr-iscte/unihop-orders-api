<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKey
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
	public function handle(Request $request, Closure $next): Response
	{
		$auth_header = $request->header('Authorization');
		$api_key = env('API_KEY');

		if (!$auth_header || !preg_match('/Bearer\s(.+)/', $auth_header, $matches)) {
			return response()->json(['message' => 'Unauthorized.'], 401);
		}

		$provided_api_key = $matches[1];

		if ($provided_api_key !== $api_key) {
			return response()->json(['code' => 'authentication_error', 'message' => 'Invalid Authentication'], 401);
		}

		return $next($request);
	}
}
