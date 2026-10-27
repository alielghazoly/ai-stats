-- PostgreSQL: اعمل user بصلاحية SELECT بس على الجداول المطلوبة.
-- نفّذه بـ superuser. غيّر الباسورد واسم الداتابيز.

CREATE ROLE ai_readonly LOGIN PASSWORD 'CHANGE_ME_STRONG_PASSWORD';

GRANT CONNECT ON DATABASE your_database TO ai_readonly;
GRANT USAGE ON SCHEMA public TO ai_readonly;

-- ادّي SELECT على الجداول اللي الـ tools بتقرأ منها بس (مش كل الجداول):
GRANT SELECT ON notifications, payments, users, complaints TO ai_readonly;

-- حماية إضافية على مستوى الـ role
ALTER ROLE ai_readonly SET statement_timeout = '5s';
ALTER ROLE ai_readonly SET default_transaction_read_only = on;

-- ============================================================
-- MySQL بديل:
-- CREATE USER 'ai_readonly'@'%' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
-- GRANT SELECT ON your_database.notifications TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.payments      TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.users         TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.complaints    TO 'ai_readonly'@'%';
-- (وفي MySQL استخدم: SET GLOBAL max_execution_time أو optimizer hint MAX_EXECUTION_TIME)
