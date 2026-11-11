<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProviderSignature
{
    public const SIGNATURE_HEADER = 'X-Provider-Signature';

    public const TIMESTAMP_HEADER = 'X-Provider-Timestamp';

    public const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.payout_provider.webhook_secret');
        $signature = (string) $request->header(self::SIGNATURE_HEADER);
        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER);

        // Fail closed: without a secret nobody can be verified.
        if ($secret === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return $this->reject();
        }

        // Signing the timestamp with the body stops an old captured request from being replayed.
        if (abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return $this->reject();
        }

        $expected = self::sign($timestamp, $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject();
        }

        return $next($request);
    }

    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    private function reject(): Response
    {
        return response()->json(['message' => 'Invalid webhook signature.'], 401);
    }
}
