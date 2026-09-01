-- MedikaFlow: foto profil akun yang dikelola Admin.
-- Jalankan satu kali pada database project lama. Instalasi baru sudah
-- mendapatkan kolom ini dari database/klinik.sql.
USE klinik;

SET @profile_photo_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'profile_photo'
);
SET @profile_photo_sql := IF(
    @profile_photo_column_exists = 0,
    'ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER email',
    'SELECT 1'
);
PREPARE profile_photo_statement FROM @profile_photo_sql;
EXECUTE profile_photo_statement;
DEALLOCATE PREPARE profile_photo_statement;
