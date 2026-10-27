<?php

namespace Tests\Feature;

use App\Ai\ToolRegistry;
use App\Ai\Tools\NewUsersCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // في الاختبارات الـ "main DB" هي نفس الداتابيز الافتراضية (sqlite in-memory)
        config([
            'ai.db_connection' => config('database.default'),
            'ai.service_token' => 'secret',
            'ai.api_key'       => 'test',
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer secret'];
    }

    public function test_requires_token(): void
    {
        $this->postJson('/api/v1/ask', ['question' => 'hi'])->assertUnauthorized();
        $this->postJson('/api/v1/ask', ['question' => 'hi'], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_validates_question(): void
    {
        $this->postJson('/api/v1/ask', [], $this->headers())->assertStatus(422);
        $this->postJson('/api/v1/ask', ['question' => str_repeat('x', 501)], $this->headers())->assertStatus(422);
    }

    public function test_full_flow_with_tool_call(): void
    {
        Http::fakeSequence()
            ->push([
                'stop_reason' => 'tool_use',
                'usage'       => ['input_tokens' => 10, 'output_tokens' => 5],
                'content'     => [[
                    'type'  => 'tool_use',
                    'id'    => 'toolu_1',
                    'name'  => 'get_new_users_count',
                    'input' => ['from_date' => '2026-10-01', 'to_date' => '2026-10-31'],
                ]],
            ])
            ->push([
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 20, 'output_tokens' => 8],
                'content'     => [['type' => 'text', 'text' => 'عدد المستخدمين الجدد: 0']],
            ]);

        $this->app->bind(ToolRegistry::class, fn () => new ToolRegistry([NewUsersCount::class]));

        $this->postJson('/api/v1/ask', ['question' => 'كام مستخدم جديد؟', 'user_ref' => 'admin-1'], $this->headers())
            ->assertOk()
            ->assertJsonPath('answer', 'عدد المستخدمين الجدد: 0')
            ->assertJsonPath('tools.0.name', 'get_new_users_count')
            ->assertJsonPath('usage.input_tokens', 30);

        $this->assertDatabaseHas('ai_logs', ['user_ref' => 'admin-1']);
    }

    public function test_returns_502_when_ai_api_fails(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->postJson('/api/v1/ask', ['question' => 'كام مستخدم؟'], $this->headers())
            ->assertStatus(502);
    }

    public function test_invalid_dates_and_unknown_tools_become_tool_errors(): void
    {
        $registry = new ToolRegistry([NewUsersCount::class]);

        $bad = $registry->run('get_new_users_count', ['from_date' => 'yesterday', 'to_date' => '2026-10-31']);
        $this->assertTrue($bad['is_error']);

        $big = $registry->run('get_new_users_count', ['from_date' => '2020-01-01', 'to_date' => '2026-10-31']);
        $this->assertTrue($big['is_error']);

        $this->assertTrue($registry->run('drop_tables', [])['is_error']);
    }
}
