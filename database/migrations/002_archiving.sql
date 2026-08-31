USE klinik;

-- Arsip memakai soft-delete agar relasi kunjungan dan resep tetap utuh.
-- Instalasi yang sudah berjalan akan menjalankan perubahan ini otomatis
-- melalui ensure_application_schema() saat aplikasi dibuka.
ALTER TABLE patients
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX idx_patients_archived_at (archived_at);

ALTER TABLE doctors
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX idx_doctors_archived_at (archived_at);

ALTER TABLE polyclinics
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX idx_polyclinics_archived_at (archived_at);

ALTER TABLE medicines
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX idx_medicines_archived_at (archived_at);
