<?php

$driver = env('AI_DB_DRIVER', 'pgsql'); // pgsql | mysql

return [
    // ---- AI provider ----
    // anthropic (default) | openai = any OpenAI-compatible API (OpenRouter, Groq, Gemini, Ollama...)
    // Free example: AI_PROVIDER=openai AI_BASE_URL=https://openrouter.ai/api/v1 AI_API_KEY=... AI_MODEL=<a :free model that supports tools>
    'provider'   => env('AI_PROVIDER', 'anthropic'),
    'api_key'    => env('AI_API_KEY', env('ANTHROPIC_API_KEY')),
    'base_url'   => env('AI_BASE_URL', env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com')),
    'version'    => '2023-06-01',
    // See the docs for the latest model name. A smaller model is enough for a task like this.
    'model'      => env('AI_MODEL', 'claude-sonnet-5-5'),
    'max_tokens' => 1024,
    'timeout'    => 30,
    'max_iterations' => 5,

    // ---- API protection between the two projects ----
    // Long random token (openssl rand -hex 32). Same value in the main project.
    'service_token' => env('AI_SERVICE_TOKEN'),
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT', 10),

    // ---- Request logging ----
    'log_channel' => env('AI_LOG_CHANNEL', 'ai_requests'),
    'provider_log_channel' => env('AI_PROVIDER_LOG_CHANNEL', 'ai_provider'),
    'log_body_limit' => (int) env('AI_LOG_BODY_LIMIT', 4000),

    // ---- File export (Excel/CSV) ----
    // Address the admin opens the link from (not host.docker.internal). In production use the public domain with https.
    'public_url'         => env('AI_PUBLIC_URL', env('APP_URL', 'http://localhost')),
    'export_ttl_minutes' => (int) env('AI_EXPORT_TTL_MINUTES', 60),

    // ---- Dates ----
    // The admin's timezone ("this month" is interpreted in it)
    'timezone'    => env('AI_TIMEZONE', 'Africa/Cairo'),
    // Timezone the main project stores created_at in. Laravel stores UTC by default
    // unless app.timezone was changed there. Check config/app.php in the main project.
    'db_timezone' => env('AI_DB_TIMEZONE', 'UTC'),
    'max_range_days' => 366,

    // ---- Main project database (read-only) ----
    'db_connection' => 'main_readonly',
    'db' => match ($driver) {
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('AI_DB_HOST', 'host.docker.internal'),
            'port'      => env('AI_DB_PORT', '3306'),
            'database'  => env('AI_DB_DATABASE'),
            'username'  => env('AI_DB_USERNAME'),
            'password'  => env('AI_DB_PASSWORD'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
        ],
        default => [
            'driver'   => 'pgsql',
            'host'     => env('AI_DB_HOST', 'host.docker.internal'),
            'port'     => env('AI_DB_PORT', '5432'),
            'database' => env('AI_DB_DATABASE'),
            'username' => env('AI_DB_USERNAME'),
            'password' => env('AI_DB_PASSWORD'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => env('AI_DB_SCHEMA', 'public'),
            'sslmode'  => env('AI_DB_SSLMODE', 'prefer'),
        ],
    },
];
