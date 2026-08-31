USE klinik;

ALTER TABLE doctors
    ADD COLUMN jadwal_hari VARCHAR(120) NOT NULL DEFAULT 'Senin - Jumat' AFTER alamat,
    ADD COLUMN jam_mulai TIME NOT NULL DEFAULT '08:00:00' AFTER jadwal_hari,
    ADD COLUMN jam_selesai TIME NOT NULL DEFAULT '16:00:00' AFTER jam_mulai;

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

-- Tidak ada akun contoh. Buka /auth/setup.php untuk membuat Admin pertama.
