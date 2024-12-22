<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Webhook as SvixWebhook;
use Svix\Exception\WebhookVerificationException;
use Symfony\Component\HttpFoundation\Response;

class Webhook
{
  /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request):
     * 
     * (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $headers = collect($request->headers->all())->transform(function ($item) {
            return $item[0];
        });
        
        try {
            $wh = new SvixWebhook(env("WEBHOOK_SECRET"));
            $wh->verify($payload, $headers);

            return $next($request);
        } catch (WebhookVerificationException $e) {
            Log::error('Invalid Webhook Authentication', ['message' => $e->getMessage(), 
                'data' => $e->getTraceAsString()]);
            return response()->json(['code' => 'authentication_error', 'message' => 'Invalid Authentication'], 401);
        }
    }
}
