<?php

namespace App\Http\Controllers;

use App\Ai\AiUnavailable;
use App\Ai\StatsAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskController
{
    public function __invoke(Request $request, StatsAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
            // مين الأدمن اللي سأل في المشروع الأساسي (id أو email) - للـ logs والـ rate limit
            'user_ref' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $result = $assistant->ask($data['question'], $data['user_ref'] ?? null);
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'answer' => $result['answer'],
            'tools'  => $result['tools'],
            'usage'  => [
                'input_tokens'  => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ],
        ]);
    }
}
