<?php

// Main project: app/Services/AiStatsClient.php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiStatsClient
{
    /**
     * @return array{answer: string, tools: array, usage: array}
     */
    public function ask(string $question, ?string $userRef = null): array
    {
        try {
            return Http::baseUrl(config('services.ai_stats.url'))
                ->withToken(config('services.ai_stats.token'))
                ->acceptJson()
                ->timeout(120) // the service may run up to 5 rounds with the AI
                ->post('/api/v1/ask', [
                    'question' => $question,
                    'user_ref' => $userRef,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = match ($e->response->status()) {
                422     => 'Invalid question (too short or longer than 500 characters).',
                429     => 'Too many requests, wait a moment and try again.',
                default => 'The statistics assistant is not available right now.',
            };

            throw new RuntimeException($message, previous: $e);
        }
    }
}

/*
 * ----- config/services.php (add this key) -----
 *
 * 'ai_stats' => [
 *     'url'   => env('AI_STATS_URL'),    // e.g. http://host.docker.internal:8090
 *     'token' => env('AI_STATS_TOKEN'),  // same as AI_SERVICE_TOKEN
 * ],
 *
 * ----- .env in the main project -----
 *
 * AI_STATS_URL=http://host.docker.internal:8090
 * AI_STATS_TOKEN=...
 */
