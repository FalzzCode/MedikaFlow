CREATE DATABASE IF NOT EXISTS klinik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE klinik;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS prescription_details;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS examinations;
DROP TABLE IF EXISTS visits;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS medicine_categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS auth_login_attempts;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS specializations;
DROP TABLE IF EXISTS polyclinics;
DROP TABLE IF EXISTS patients;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_rm VARCHAR(20) NOT NULL UNIQUE,
    nik CHAR(16) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    tanggal_lahir DATE NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL DEFAULT NULL,
    archived_by INT UNSIGNED NULL DEFAULT NULL,
    INDEX idx_patients_name (nama),
    INDEX idx_patients_phone (no_hp),
    INDEX idx_patients_archived_at (archived_at)
) ENGINE=InnoDB;

CREATE TABLE specializations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(80) NOT NULL UNIQUE,
    keterangan VARCHAR(255) DEFAULT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE polyclinics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_poli VARCHAR(12) NOT NULL UNIQUE,
    nama_poli VARCHAR(80) NOT NULL,
    lokasi VARCHAR(120) NOT NULL,
    keterangan VARCHAR(255) DEFAULT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL DEFAULT NULL,
    archived_by INT UNSIGNED NULL DEFAULT NULL,
    INDEX idx_polyclinics_name (nama_poli),
    INDEX idx_polyclinics_archived_at (archived_at)
) ENGINE=InnoDB;

CREATE TABLE doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_dokter VARCHAR(16) NOT NULL UNIQUE,
    nama_dokter VARCHAR(120) NOT NULL,
    specialization_id INT UNSIGNED NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL,
    jadwal_hari VARCHAR(120) NOT NULL DEFAULT 'Senin - Jumat',
    jam_mulai TIME NOT NULL DEFAULT '08:00:00',
    jam_selesai TIME NOT NULL DEFAULT '16:00:00',
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL DEFAULT NULL,
    archived_by INT UNSIGNED NULL DEFAULT NULL,
    CONSTRAINT fk_doctors_specialization FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_doctors_name (nama_dokter),
    INDEX idx_doctors_status (status),
    INDEX idx_doctors_archived_at (archived_at)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Dokter', 'Petugas') NOT NULL,
    doctor_id INT UNSIGNED DEFAULT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_users_doctor (doctor_id),
    INDEX idx_users_role_status (role, status),
    INDEX idx_users_name (nama_lengkap)
) ENGINE=InnoDB;

CREATE TABLE auth_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_lookup (identifier_hash, ip_address, attempted_at),
        INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
        INDEX idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE medicine_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(80) NOT NULL UNIQUE,
    keterangan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE medicines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_obat VARCHAR(16) NOT NULL UNIQUE,
    nama_obat VARCHAR(120) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    satuan VARCHAR(30) NOT NULL,
    harga DECIMAL(14, 2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    tanggal_expired DATE NOT NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL DEFAULT NULL,
    archived_by INT UNSIGNED NULL DEFAULT NULL,
    CONSTRAINT fk_medicines_category FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_medicines_name (nama_obat),
    INDEX idx_medicines_stock (stok),
    INDEX idx_medicines_expiry (tanggal_expired),
    INDEX idx_medicines_archived_at (archived_at)
) ENGINE=InnoDB;

CREATE TABLE visits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_kunjungan VARCHAR(24) NOT NULL UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    polyclinic_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    tanggal_kunjungan DATE NOT NULL,
    nomor_antrian INT UNSIGNED NOT NULL,
    keluhan_awal TEXT NOT NULL,
    status ENUM('Menunggu', 'Diperiksa', 'Selesai', 'Batal') NOT NULL DEFAULT 'Menunggu',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_visits_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_visits_polyclinic FOREIGN KEY (polyclinic_id) REFERENCES polyclinics(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_visits_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_visits_queue (tanggal_kunjungan, nomor_antrian),
    INDEX idx_visits_date (tanggal_kunjungan),
    INDEX idx_visits_status (status)
) ENGINE=InnoDB;

CREATE TABLE examinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_id INT UNSIGNED NOT NULL UNIQUE,
    keluhan TEXT NOT NULL,
    hasil_pemeriksaan TEXT NOT NULL,
    tekanan_darah VARCHAR(20) DEFAULT NULL,
    suhu DECIMAL(4, 1) DEFAULT NULL,
    berat_badan DECIMAL(5, 2) DEFAULT NULL,
    diagnosa VARCHAR(255) NOT NULL,
    tindakan TEXT DEFAULT NULL,
    catatan_dokter TEXT DEFAULT NULL,
    diperiksa_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_examinations_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_resep VARCHAR(24) NOT NULL UNIQUE,
    visit_id INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'Diselesaikan', 'Dibatalkan') NOT NULL DEFAULT 'Draft',
    catatan TEXT DEFAULT NULL,
    selesai_pada DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prescriptions_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_prescriptions_status (status),
    INDEX idx_prescriptions_visit (visit_id)
) ENGINE=InnoDB;

CREATE TABLE prescription_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    jumlah INT UNSIGNED NOT NULL,
    dosis VARCHAR(80) NOT NULL,
    aturan_penggunaan VARCHAR(160) NOT NULL,
    harga_satuan DECIMAL(14, 2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_prescription_details_prescription FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prescription_details_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_prescription_medicine (prescription_id, medicine_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_id INT UNSIGNED NOT NULL UNIQUE,
    biaya_pemeriksaan DECIMAL(14, 2) NOT NULL DEFAULT 0,
    biaya_tindakan DECIMAL(14, 2) NOT NULL DEFAULT 0,
    total_obat DECIMAL(14, 2) NOT NULL DEFAULT 0,
    total DECIMAL(14, 2) NOT NULL DEFAULT 0,
    status ENUM('Belum Dibayar', 'Sudah Dibayar') NOT NULL DEFAULT 'Belum Dibayar',
    dibayar_pada DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_payments_status (status)
) ENGINE=InnoDB;

INSERT INTO specializations (nama, keterangan) VALUES
    ('Umum', 'Layanan konsultasi kesehatan dasar'),
    ('Gigi', 'Pemeriksaan dan perawatan gigi'),
    ('Anak', 'Layanan kesehatan anak'),
    ('Penyakit Dalam', 'Konsultasi penyakit internal');

INSERT INTO medicine_categories (nama_kategori, keterangan) VALUES
    ('Analgesik', 'Pereda nyeri dan demam'),
    ('Antibiotik', 'Obat untuk infeksi bakteri'),
    ('Vitamin', 'Suplemen untuk mendukung kesehatan'),
    ('Topikal', 'Obat pemakaian luar'),
    ('Antihistamin', 'Pereda gejala alergi');

-- Tidak ada data operasional atau akun contoh.
-- Admin menambahkan data klinik dari antarmuka aplikasi.
