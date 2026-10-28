# Stats AI Service

Standalone Laravel 13 (PHP 8.3+) API that answers admin statistics questions in natural language
(Arabic, Egyptian dialect, by default) using Anthropic tool use. The main project (Filament) calls it over HTTP
with a Bearer token; the service reads the main project's DB with a **SELECT-only** user and stores its own logs in a local sqlite DB.

## Layout
- `routes/api.php` — `POST /api/v1/ask` (`LogAiRequests` → `ServiceTokenAuth` → `throttle:ai`). Health: `/up`.
- `app/Http/Controllers/AskController.php` — validates `question` (2–500) and optional `user_ref`.
- `app/Ai/StatsAssistant.php` — tool-use loop against `POST {base_url}/v1/messages`, writes `ai_logs`.
- `app/Ai/ToolRegistry.php` + `app/Ai/Tools/*` — the tools the model can call (aggregate counts only).
- `app/Http/Middleware/LogAiRequests.php` — per-request log to the `ai_requests` channel.
- `config/ai.php` — all AI/service/main-DB settings. `AiServiceProvider` registers the `main_readonly` connection.
  `AI_PROVIDER=anthropic|openai` (openai = any OpenAI-compatible API, e.g. free OpenRouter/Groq/Ollama models) — `app/Ai/OpenAiAdapter.php` converts formats.
- `sql/readonly_user.sql` — grants for the read-only DB user. `examples/main-project/` — client code for the caller.
- `docker/` + `docker-compose.yml` — php-fpm + nginx (port 8090). `entrypoint.sh` fails fast if required env is missing.

## Environment
Required: `APP_KEY`, `AI_SERVICE_TOKEN` (`openssl rand -hex 32`, same value in the main project),
`ANTHROPIC_API_KEY`, `AI_DB_DATABASE`, `AI_DB_USERNAME`, `AI_DB_PASSWORD`.
Optional: `AI_MODEL`, `AI_DB_DRIVER` (pgsql|mysql), `AI_DB_HOST/PORT/SCHEMA/SSLMODE`, `AI_TIMEZONE`, `AI_DB_TIMEZONE`, `AI_RATE_LIMIT`,
`AI_LOG_CHANNEL`, `AI_LOG_DAYS`.
**Never point `DB_*` at the main project's DB** — `DB_*` is the service's own sqlite (sessions, cache, jobs, `ai_logs`); the main DB is only `AI_DB_*`.

## Logging
Every API request (including 401/422/429) is logged to `storage/logs/ai-requests-YYYY-MM-DD.log`
(method, path, status, ip, user_ref, question, duration_ms; never the token). Level: info <400, warning 4xx, error 5xx.
Each answered question is also stored in the `ai_logs` table (tools, tokens, duration).

## Commands
- `php artisan test` (needs `pdo_sqlite`), `vendor/bin/pint` for style.
- `docker compose up -d --build`, `docker compose logs -f app`.

## Conventions
- New tool: copy a file in `app/Ai/Tools`, implement `name/description/schema/handle`, register it in `ToolRegistry`, grant `SELECT` in `sql/readonly_user.sql`, add a test.
- Tools return aggregated numbers only; never expose DB errors to the model. Table/column names in tools are assumptions about the main project — verify them.
- Code comments and user-facing strings are Arabic; keep that style.
