<?php

namespace Tests\Unit;

use App\Ai\OpenAiAdapter;
use Tests\TestCase;

class OpenAiAdapterTest extends TestCase
{
    public function test_tool_call_round_trip_keeps_gemini_thought_signature(): void
    {
        $adapter = new OpenAiAdapter();

        $normalized = $adapter->normalize([
            'choices' => [['message' => ['content' => null, 'tool_calls' => [[
                'id'            => 'c1',
                'function'      => ['name' => 'get_new_users_count', 'arguments' => '{"from_date":"2026-09-01"}'],
                'extra_content' => ['google' => ['thought_signature' => 'sig']],
            ]]]]],
            'usage'   => ['prompt_tokens' => 3, 'completion_tokens' => 2],
        ]);

        $this->assertSame('tool_use', $normalized['stop_reason']);
        $this->assertSame(['from_date' => '2026-09-01'], $normalized['content'][0]['input']);

        $payload = $adapter->payload('m', 10, 'sys', [], [
            ['role' => 'user', 'content' => 'q'],
            ['role' => 'assistant', 'content' => $normalized['content']],
            ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'c1', 'content' => '{}']]],
        ]);

        $this->assertSame('sig', $payload['messages'][2]['tool_calls'][0]['extra_content']['google']['thought_signature']);
        $this->assertSame('tool', $payload['messages'][3]['role']);
    }
}
