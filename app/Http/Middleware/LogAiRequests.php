<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** يسجل كل طلب على الـ API (حتى المرفوض 401/429) في قناة الـ log المخصصة. مفيش tokens بتتسجل. */
class LogAiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response  = $next($request);
        $status    = $response->getStatusCode();

        Log::channel(config('ai.log_channel'))->log(
            $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
            'ai.request',
            [
                'method'      => $request->method(),
                'path'        => $request->path(),
                'status'      => $status,
                'ip'          => $request->ip(),
                'user_ref'    => $request->input('user_ref'),
                'question'    => $request->input('question'),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]
        );

        return $response;
    }
}
