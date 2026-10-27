<?php

// المشروع الأساسي: app/Services/AiStatsClient.php

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
                ->timeout(120) // الخدمة ممكن تعمل لحد 5 لفات مع الـ AI
                ->post('/api/v1/ask', [
                    'question' => $question,
                    'user_ref' => $userRef,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = match ($e->response->status()) {
                422     => 'السؤال غير صالح (قصير أو طويل أكتر من 500 حرف).',
                429     => 'كتير أوي، استنى شوية وجرّب تاني.',
                default => 'مساعد الإحصائيات مش متاح دلوقتي.',
            };

            throw new RuntimeException($message, previous: $e);
        }
    }
}

/*
 * ----- config/services.php (ضيف المفتاح ده) -----
 *
 * 'ai_stats' => [
 *     'url'   => env('AI_STATS_URL'),    // مثال: http://host.docker.internal:8090
 *     'token' => env('AI_STATS_TOKEN'),  // نفس AI_SERVICE_TOKEN
 * ],
 *
 * ----- .env في المشروع الأساسي -----
 *
 * AI_STATS_URL=http://host.docker.internal:8090
 * AI_STATS_TOKEN=...
 */
