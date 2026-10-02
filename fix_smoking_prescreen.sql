-- ============================================================
-- إضافة: سؤال التدخين + فحص العينة قبل التبرع
-- السبب: دعم قاعدة التدخين (منع التدخين ساعة قبل التبرع)
--        وتسجيل نتائج فحص العينة المأخوذة قبل التبرع
-- التشغيل: من phpMyAdmin ← اختر قاعدة bloodbank2_db ← تبويب SQL والصق الملف كاملاً
-- ملاحظة: الكود يعمل حتى بدون تشغيل هذا الملف، لكن التشغيل موصى به
-- ============================================================

ALTER TABLE Donors
    ADD COLUMN smoker ENUM('Yes','No') DEFAULT 'No' AFTER health_status,
    ADD COLUMN last_smoke_time DATETIME NULL AFTER smoker,
    ADD COLUMN prescreen_sample ENUM('Taken','NotTaken') DEFAULT 'NotTaken' AFTER last_smoke_time;

ALTER TABLE DonorScreeningLog
    ADD COLUMN smoker ENUM('Yes','No') DEFAULT 'No' AFTER donor_id;
