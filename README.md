# Stats AI Service

A standalone Laravel service (Docker) that answers statistics questions with AI using Tool Use.

```
Main project (Filament) ──HTTP + Bearer token──► Stats AI Service ──SELECT only──► main project database
                                                         │
                                                         └─► AI provider API
```

- The service reads the main project's database with a `SELECT`-only user and stores its logs in a small sqlite database inside its own volume (it never writes to the main database).
- API: `POST /api/v1/ask` with `{"question": "...", "user_ref": "admin-1"}`; the response is `{answer, tools, files, usage}`.

## Installation

### 1. Create a new Laravel app and put the files on top of it

```bash
composer create-project laravel/laravel stats-ai-service
# or without local PHP:
# docker run --rm -v "$PWD":/app composer:2 create-project laravel/laravel stats-ai-service

# Unzip over the project folder (replace the existing files: bootstrap/app.php and bootstrap/providers.php)
cd stats-ai-service
```

### 2. Prepare the main database

Run `sql/readonly_user.sql` on the main project's database (change the password and table names).
If the database is on the same server, it must accept connections from Docker:
- PostgreSQL: `listen_addresses` in `postgresql.conf`, and a line in `pg_hba.conf` for the Docker network (e.g. `host your_db ai_readonly 172.16.0.0/12 scram-sha-256`).
- MySQL: a user as `'ai_readonly'@'%'`, and make sure `bind-address` is not only `127.0.0.1`.

### 3. Configuration

```bash
cp .env.example .env
```
Fill in: `APP_KEY`, `AI_SERVICE_TOKEN`, `AI_API_KEY` (or `ANTHROPIC_API_KEY`) and `AI_DB_*` (set `AI_DB_DRIVER` to `mysql` if needed).
The container will not start if one of them is missing (a clear message is printed in the logs).

### 4. Run

```bash
docker compose up -d --build
docker compose logs -f app            # watch the migration and startup
curl http://localhost:8090/up         # health check
```

### 5. Try it

```bash
curl -s http://localhost:8090/api/v1/ask \
  -H "Authorization: Bearer $AI_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"question":"How many new users this month?","user_ref":"me"}'
```

## Connecting from the main project

See `examples/main-project/`:
- `AiStatsClient.php`: the client + `config/services.php` and `.env` settings.
- `StatsAssistantPage.php` + the blade view: a Filament page that calls the client.
- `ask.mjs`: the same idea from Node.

The URL depends on where the main project runs:
- On the same server without Docker: `http://127.0.0.1:8090`.
- Inside another Docker setup: `http://host.docker.internal:8090` (add `extra_hosts: host.docker.internal:host-gateway` there), or join them to one network (see the comments in `docker-compose.yml`) and use `http://web`.
- On another server: put a reverse proxy with HTTPS in front of the service, and do not expose the port to the internet without it, because the token travels over the network.

## What you must adjust

- Table, column and value names in `app/Ai/Tools/*` (they must match the main project's schema), and the `SELECT` grants in `sql/readonly_user.sql` must match them.
- `AI_DB_TIMEZONE`: if the main project stores `created_at` in a timezone other than UTC.
- `AI_MODEL`: the latest model name from the provider's docs.

## Adding a new tool

1. Copy any file in `app/Ai/Tools` and change `name/description/schema/handle`.
2. Add it to the `ToolRegistry` list.
3. Grant `ai_readonly` `SELECT` on the table.
4. Write a test for it, and try it with a real question.

## Tests

```bash
php artisan test --filter=AskApiTest
```

## Security

- The Bearer token is compared with `hash_equals`, and the rate limit is per minute per `user_ref`.
- The tools only return aggregated numbers, and DB errors never reach the model.
- Every question is recorded in `ai_logs` (question, tools, tokens, duration).
- If you change the token in the service, change it in the main project too.
