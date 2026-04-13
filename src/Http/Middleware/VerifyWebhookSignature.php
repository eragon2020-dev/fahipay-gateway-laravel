<?php

namespace Fahipay\Gateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Fahipay\Gateway\Facades\FahipayGateway;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Fahipay-Signature');
        
        if (empty($signature)) {
            return response()->json([
                'error' => 'Missing signature header',
            ], 401);
        }

        $payload = $request->getContent();
        $secret = config('fahipay.secret_key');
        
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $payload, $secret, true)
        );
        
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }
}