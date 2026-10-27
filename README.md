# Stats AI Service

خدمة Laravel منفصلة (Docker) بتجاوب أسئلة الإحصائيات بالـ AI عن طريق Tool Use.

```
المشروع الأساسي (Filament) ──HTTP + Bearer token──► Stats AI Service ──SELECT فقط──► داتابيز المشروع الأساسي
                                                         │
                                                         └─► Anthropic API
```

- الخدمة بتقرأ من داتابيز المشروع الأساسي بـ user بصلاحية `SELECT` بس، وبتخزن الـ logs في sqlite صغيرة جوه volume خاص بيها (مفيش أي كتابة على الداتابيز الأساسية).
- الـ API: `POST /api/v1/ask` بـ `{"question": "...", "user_ref": "admin-1"}`، والرد `{answer, tools, usage}`.

## التركيب

### 1. اعمل Laravel جديد وحط الملفات فوقه

```bash
composer create-project laravel/laravel stats-ai-service
# أو من غير PHP محلي:
# docker run --rm -v "$PWD":/app composer:2 create-project laravel/laravel stats-ai-service

# فك ضغط الـ zip فوق مجلد المشروع (استبدل الملفات الموجودة: bootstrap/app.php وbootstrap/providers.php)
cd stats-ai-service
```

### 2. جهّز الداتابيز الأساسية

نفّذ `sql/readonly_user.sql` على داتابيز المشروع الأساسي (عدّل الباسورد وأسماء الجداول).
لو الداتابيز على نفس السيرفر، لازم تسمح باتصال من الـ Docker:
- PostgreSQL: `listen_addresses` في `postgresql.conf`، وسطر في `pg_hba.conf` لشبكة Docker (مثلًا `host your_db ai_readonly 172.16.0.0/12 scram-sha-256`).
- MySQL: user بـ `'ai_readonly'@'%'` وتأكد إن `bind-address` مش `127.0.0.1` بس.

### 3. الإعدادات

```bash
cp .env.example .env
```
املأ: `APP_KEY` و`AI_SERVICE_TOKEN` و`ANTHROPIC_API_KEY` و`AI_DB_*` (وغيّر `AI_DB_DRIVER` لـ `mysql` لو لزم).
الـ container مش هيشتغل لو واحد منهم ناقص (رسالة واضحة في الـ logs).

### 4. شغّل

```bash
docker compose up -d --build
docker compose logs -f app            # راقب الـ migrate والبدء
curl http://localhost:8090/up         # health check
```

### 5. جرّب

```bash
curl -s http://localhost:8090/api/v1/ask \
  -H "Authorization: Bearer $AI_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"question":"كام مستخدم جديد الشهر ده؟","user_ref":"me"}'
```

## الربط من المشروع الأساسي

شوف `examples/main-project/`:
- `AiStatsClient.php`: الـ client + إعدادات `config/services.php` و`.env`.
- `StatsAssistantPage.php` + الـ blade: صفحة Filament بتنادي الـ client.
- `ask.mjs`: نفس الفكرة من Node.

الـ URL حسب مكان المشروع الأساسي:
- على نفس السيرفر بدون Docker: `http://127.0.0.1:8090`.
- جوه Docker تاني: `http://host.docker.internal:8090` (ضيف `extra_hosts: host.docker.internal:host-gateway` عنده)، أو ضمّهم لـ network واحدة (الكومنتات في `docker-compose.yml`) واستخدم `http://web`.
- على سيرفر تاني: حط reverse proxy بـ HTTPS قدام الخدمة، ولا تفتح البورت للإنترنت من غير ده، لأن الـ token بيعدي على الشبكة.

## لازم تعدّل

- أسماء الجداول والأعمدة والقيم في `app/Ai/Tools/*` (`payments.method = 'nfc'`, `status = 'success'`, `notifications.channel`...)، وصلاحيات `SELECT` في `sql/readonly_user.sql` تتطابق معاها.
- `AI_DB_TIMEZONE`: لو المشروع الأساسي بيخزن `created_at` بتوقيت غير UTC.
- `AI_MODEL`: آخر اسم موديل من الـ docs.

## إضافة tool جديد

1. انسخ أي ملف في `app/Ai/Tools`، وغيّر `name/description/schema/handle`.
2. ضيفه في قائمة `ToolRegistry`.
3. ادّي `ai_readonly` صلاحية `SELECT` على الجدول.
4. اكتب له اختبار، وجرّبه بالسؤال الحقيقي.

## اختبارات

```bash
php artisan test --filter=AskApiTest
```

## الأمان

- Bearer token بيتقارن بـ `hash_equals`، والـ rate limit بالدقيقة لكل `user_ref`.
- الـ tools بترجّع أرقام مجمّعة بس، وأخطاء الـ DB ما بتوصلش للنموذج.
- كل سؤال بيتسجل في `ai_logs` (السؤال، الـ tools، التوكنز، الزمن).
- لو غيّرت التوكن في الخدمة غيّره في المشروع الأساسي.
