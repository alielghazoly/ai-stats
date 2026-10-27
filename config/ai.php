<?php

$driver = env('AI_DB_DRIVER', 'pgsql'); // pgsql | mysql

return [
    // ---- Anthropic API ----
    'api_key'    => env('ANTHROPIC_API_KEY'),
    'base_url'   => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'version'    => '2023-06-01',
    // راجع الـ docs لأحدث اسم موديل. موديل أصغر كفاية لمهمة زي دي.
    'model'      => env('AI_MODEL', 'claude-sonnet-5-5'),
    'max_tokens' => 1024,
    'timeout'    => 30,
    'max_iterations' => 5,

    // ---- حماية الـ API بين المشروعين ----
    // توكن طويل عشوائي (openssl rand -hex 32). نفس القيمة في المشروع الأساسي.
    'service_token' => env('AI_SERVICE_TOKEN'),
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT', 10),

    // ---- لوج الطلبات ----
    'log_channel' => env('AI_LOG_CHANNEL', 'ai_requests'),

    // ---- التواريخ ----
    // توقيت الأدمن (اللي بيقول "الشهر ده" بتوقيته)
    'timezone'    => env('AI_TIMEZONE', 'Africa/Cairo'),
    // التوقيت اللي المشروع الأساسي بيخزن بيه created_at. Laravel بيخزن UTC افتراضيًا
    // إلا لو غيرت app.timezone هناك. راجع config/app.php في المشروع الأساسي.
    'db_timezone' => env('AI_DB_TIMEZONE', 'UTC'),
    'max_range_days' => 366,

    // ---- داتابيز المشروع الأساسي (read-only) ----
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
