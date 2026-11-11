<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAY_HEADER = 'Idempotent-Replayed';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || $key === '' || strlen($key) > 255) {
            return $this->error(400, 'The Idempotency-Key header is required (max 255 characters).');
        }

        $fingerprint = $this->fingerprint($request);
        $existing = IdempotencyKey::where('key', $key)->first();

        if ($existing !== null) {
            return $this->answerForExisting($existing, $fingerprint);
        }

        $record = IdempotencyKey::create([
            'key' => $key,
            'request_hash' => $fingerprint,
        ]);

        return $this->runAndRemember($record, $request, $next);
    }

    private function answerForExisting(IdempotencyKey $record, string $fingerprint): Response
    {
        if (! hash_equals($record->request_hash, $fingerprint)) {
            return $this->error(422, 'This Idempotency-Key was already used with a different request.');
        }

        if (! $record->hasResponse()) {
            return $this->error(409, 'A request with this Idempotency-Key is still being processed.');
        }

        return response((string) $record->response_body, (int) $record->response_status)
            ->header('Content-Type', 'application/json')
            ->header(self::REPLAY_HEADER, 'true');
    }

    private function runAndRemember(IdempotencyKey $record, Request $request, Closure $next): Response
    {
        $response = $next($request);

        // A server error says nothing about the outcome, so the client must be free to retry.
        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return $response;
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
        ]);

        return $response;
    }

    private function fingerprint(Request $request): string
    {
        $payload = $request->all();
        $this->sortRecursively($payload);

        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]));
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function sortRecursively(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
    }

    private function error(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
