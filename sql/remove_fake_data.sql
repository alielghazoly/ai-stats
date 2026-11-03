-- Deletes the test data added to the database (all marked FAKE). Run as a user with DELETE permission.
BEGIN;
DELETE FROM contact_forms    WHERE description LIKE '[FAKE]%';
DELETE FROM notifications    WHERE title_en LIKE '[FAKE]%';
DELETE FROM recharge_session WHERE card_serial LIKE 'FAKE-%';
DELETE FROM users            WHERE name LIKE '[FAKE]%';
COMMIT;
