-- PostgreSQL: create a user with SELECT-only permission on the required tables.
-- Run as superuser. Change the password and database name.

CREATE ROLE ai_readonly LOGIN PASSWORD 'CHANGE_ME_STRONG_PASSWORD';

GRANT CONNECT ON DATABASE your_database TO ai_readonly;
GRANT USAGE ON SCHEMA public TO ai_readonly;

-- Grant SELECT only on the tables the tools read from (not all tables):
GRANT SELECT ON notifications, recharge_session, users, contact_forms TO ai_readonly;

-- Extra protection at the role level
ALTER ROLE ai_readonly SET statement_timeout = '5s';
ALTER ROLE ai_readonly SET default_transaction_read_only = on;

-- ============================================================
-- MySQL alternative:
-- CREATE USER 'ai_readonly'@'%' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
-- GRANT SELECT ON your_database.notifications TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.recharge_session TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.users         TO 'ai_readonly'@'%';
-- GRANT SELECT ON your_database.contact_forms TO 'ai_readonly'@'%';
-- (in MySQL use: SET GLOBAL max_execution_time or the MAX_EXECUTION_TIME optimizer hint)
