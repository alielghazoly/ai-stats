<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Logs every API request (even rejected 401/429) with its response to the dedicated log channel. The Authorization header is never logged. */
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
                'response'    => Str::limit((string) $response->getContent(), (int) config('ai.log_body_limit'), '…[truncated]'),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]
        );

        return $response;
    }
}
