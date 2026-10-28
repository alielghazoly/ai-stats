-- يحذف بيانات الاختبار اللي اتضافت في الداتابيز (كلها متعلّمة بـ FAKE). نفّذه بيوزر ليه صلاحية DELETE.
BEGIN;
DELETE FROM contact_forms    WHERE description LIKE '[FAKE]%';
DELETE FROM notifications    WHERE title_en LIKE '[FAKE]%';
DELETE FROM recharge_session WHERE card_serial LIKE 'FAKE-%';
DELETE FROM users            WHERE name LIKE '[FAKE]%';
COMMIT;
