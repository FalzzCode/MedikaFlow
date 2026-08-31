<?php

function validate_account_form($data, $password, $password_confirmation, $user_id = 0, $password_required = true)
{
    $errors = array();
    $roles = array('Admin', 'Dokter', 'Petugas');
    $statuses = array('Aktif', 'Nonaktif');

    if ($data['nama_lengkap'] === '' || $data['username'] === '' || $data['email'] === '' || $data['role'] === '' || $data['status'] === '') {
        $errors[] = 'Semua field identitas dan akses wajib diisi.';
    }
    if (strlen($data['nama_lengkap']) > 120 || strlen($data['username']) > 50 || strlen($data['email']) > 120) {
        $errors[] = 'Nama, username, atau email melebihi batas karakter.';
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $data['username'])) {
        $errors[] = 'Username memakai 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email belum valid.';
    }
    if (!in_array($data['role'], $roles, true)) {
        $errors[] = 'Peran akun tidak valid.';
    }
    if (!in_array($data['status'], $statuses, true)) {
        $errors[] = 'Status akun tidak valid.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', array($data['username'], (int) $user_id), 0) > 0) {
        $errors[] = 'Username sudah digunakan akun lain.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', array($data['email'], (int) $user_id), 0) > 0) {
        $errors[] = 'Email sudah digunakan akun lain.';
    }

    if ($data['role'] === 'Dokter') {
        if ((int) $data['doctor_id'] <= 0) {
            $errors[] = 'Akun Dokter wajib dihubungkan ke data dokter.';
        } elseif ((int) db_value("SELECT COUNT(*) FROM doctors WHERE id = ? AND archived_at IS NULL AND status = 'Aktif'", array((int) $data['doctor_id']), 0) === 0) {
            $errors[] = 'Akun login hanya dapat dihubungkan ke dokter berstatus aktif.';
        } elseif ((int) db_value('SELECT COUNT(*) FROM users WHERE doctor_id = ? AND id <> ?', array((int) $data['doctor_id'], (int) $user_id), 0) > 0) {
            $errors[] = 'Dokter tersebut sudah terhubung ke akun lain.';
        }
    }

    if ($password_required || $password !== '') {
        $password_error = validate_password_strength($password);
        if ($password_error !== '') {
            $errors[] = $password_error;
        }
        if ($password !== $password_confirmation) {
            $errors[] = 'Konfirmasi password belum sama.';
        }
    }

    return array_values(array_unique($errors));
}

function account_form_doctors($user_id = 0)
{
    return db_select_all("SELECT d.id, d.kode_dokter, d.nama_dokter, d.status, s.nama AS spesialisasi,
            u.id AS linked_user_id
        FROM doctors d
        INNER JOIN specializations s ON s.id = d.specialization_id
        LEFT JOIN users u ON u.doctor_id = d.id
        WHERE d.archived_at IS NULL AND (u.id IS NULL OR u.id = ?)
        ORDER BY d.status = 'Aktif' DESC, d.nama_dokter", array((int) $user_id));
}
