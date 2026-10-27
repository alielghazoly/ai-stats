<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** يتحقق من Bearer token المشترك بين المشروع الأساسي وخدمة الـ AI. */
class ServiceTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ai.service_token');
        $given    = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
