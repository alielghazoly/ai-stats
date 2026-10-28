<?php

namespace App\Ai;

/**
 * يحوّل بين صيغة Anthropic (اللي الـ StatsAssistant شغال بيها) وصيغة OpenAI chat/completions،
 * عشان نقدر نستخدم أي موديل مجاني متوافق (OpenRouter, Groq, Gemini, Ollama...).
 */
class OpenAiAdapter
{
    public function payload(string $model, int $maxTokens, string $system, array $tools, array $messages): array
    {
        $out = [['role' => 'system', 'content' => $system]];

        foreach ($messages as $m) {
            if (is_string($m['content'])) {
                $out[] = ['role' => $m['role'], 'content' => $m['content']];
                continue;
            }

            if ($m['role'] === 'assistant') {
                $text  = collect($m['content'])->where('type', 'text')->pluck('text')->implode("\n");
                $calls = collect($m['content'])->where('type', 'tool_use')->map(fn ($b) => array_filter([
                    'id'       => $b['id'],
                    'type'     => 'function',
                    'function' => ['name' => $b['name'], 'arguments' => json_encode((object) ($b['input'] ?? []))],
                    // Gemini 3 بيطلب thought_signature يرجع مع الـ tool call في الدور التالي
                    'extra_content' => $b['extra_content'] ?? null,
                ]))->values()->all();

                $msg = ['role' => 'assistant', 'content' => $text !== '' ? $text : null];
                if ($calls) {
                    $msg['tool_calls'] = $calls;
                }
                $out[] = $msg;
                continue;
            }

            foreach ($m['content'] as $block) {
                if (($block['type'] ?? null) === 'tool_result') {
                    $out[] = ['role' => 'tool', 'tool_call_id' => $block['tool_use_id'], 'content' => $block['content']];
                }
            }
        }

        return [
            'model'      => $model,
            // OpenAI الرسمي (موديلات جديدة) بيرفض max_tokens؛ باقي المزوّدين لسه بيستخدموه
            (str_contains((string) config('ai.base_url'), 'api.openai.com') ? 'max_completion_tokens' : 'max_tokens') => $maxTokens,
            'messages'   => $out,
            'tools'      => array_map(fn ($t) => [
                'type'     => 'function',
                'function' => [
                    'name'        => $t['name'],
                    'description' => $t['description'],
                    'parameters'  => $t['input_schema'],
                ],
            ], $tools),
        ];
    }

    /** يرجّع نفس شكل رد Anthropic: stop_reason + content blocks + usage. */
    public function normalize(array $response): array
    {
        $msg     = $response['choices'][0]['message'] ?? [];
        $content = [];

        if (! empty($msg['content'])) {
            $content[] = ['type' => 'text', 'text' => $msg['content']];
        }

        foreach ($msg['tool_calls'] ?? [] as $call) {
            $content[] = array_filter([
                'type'  => 'tool_use',
                'id'    => $call['id'],
                'name'  => $call['function']['name'],
                'input' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
                'extra_content' => $call['extra_content'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return [
            'stop_reason' => ! empty($msg['tool_calls']) ? 'tool_use' : 'end_turn',
            'content'     => $content,
            'usage'       => [
                'input_tokens'  => $response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
            ],
        ];
    }
}
