<?php

namespace App\Ai;

/**
 * Converts between the Anthropic format (used by StatsAssistant) and the OpenAI chat/completions format,
 * so any compatible model can be used (OpenRouter, Groq, Gemini, Ollama...).
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
                    // Gemini 3 requires the thought_signature to be sent back with the tool call on the next turn
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
            // Official OpenAI (newer models) rejects max_tokens; other providers still use it
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

    /** Returns the same shape as an Anthropic response: stop_reason + content blocks + usage. */
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
