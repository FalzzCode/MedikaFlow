<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Tambah dokter';
$page_description = 'Tambahkan dokter dan pasangkan dengan spesialisasi layanan.';
$active_menu = 'dokter';
$data = array('kode_dokter' => '', 'nama_dokter' => '', 'specialization_id' => '', 'no_hp' => '', 'alamat' => '', 'jadwal_hari' => 'Senin - Jumat', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'status' => 'Aktif');
$errors = array();

if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) {
        $data[$field] = post_value($field, $default);
    }
    if ($data['kode_dokter'] === '' || $data['nama_dokter'] === '' || $data['specialization_id'] === '' || $data['no_hp'] === '' || $data['alamat'] === '' || $data['jadwal_hari'] === '' || $data['jam_mulai'] === '' || $data['jam_selesai'] === '') {
        $errors[] = 'Semua field wajib diisi.';
    }
    if (strlen($data['kode_dokter']) > 16 || strlen($data['nama_dokter']) > 120 || strlen($data['no_hp']) > 20 || strlen($data['alamat']) > 4000 || strlen($data['jadwal_hari']) > 120) {
        $errors[] = 'Data dokter melebihi batas karakter.';
    }
    if ($data['no_hp'] !== '' && !preg_match('/^[0-9+().\-\s]{7,20}$/D', $data['no_hp'])) {
        $errors[] = 'Format nomor HP dokter belum valid.';
    }
    if ($data['jam_mulai'] !== '' && !is_valid_clock_time($data['jam_mulai'])) {
        $errors[] = 'Format jam mulai tidak valid.';
    }
    if ($data['jam_selesai'] !== '' && !is_valid_clock_time($data['jam_selesai'])) {
        $errors[] = 'Format jam selesai tidak valid.';
    }
    if (is_valid_clock_time($data['jam_mulai']) && is_valid_clock_time($data['jam_selesai']) && $data['jam_selesai'] <= $data['jam_mulai']) {
        $errors[] = 'Jam selesai harus lebih akhir dari jam mulai.';
    }
    if ($data['status'] !== 'Aktif' && $data['status'] !== 'Nonaktif') {
        $errors[] = 'Status dokter tidak valid.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM doctors WHERE kode_dokter = ?', array($data['kode_dokter'])) > 0) {
        $errors[] = 'Kode dokter tersebut sudah terdaftar.';
    }
    if ((int) db_value("SELECT COUNT(*) FROM specializations WHERE id = ? AND status = 'Aktif'", array((int) $data['specialization_id'])) === 0) {
        $errors[] = 'Spesialisasi yang dipilih tidak ditemukan.';
    }
    if (empty($errors)) {
        try {
            db_execute('INSERT INTO doctors (kode_dokter, nama_dokter, specialization_id, no_hp, alamat, jadwal_hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array($data['kode_dokter'], $data['nama_dokter'], (int) $data['specialization_id'], $data['no_hp'], $data['alamat'], $data['jadwal_hari'], $data['jam_mulai'], $data['jam_selesai'], $data['status']));
            flash('success', 'Dokter baru berhasil ditambahkan.');
            redirect_to('dokter/index.php');
        } catch (Throwable $exception) {
            $errors[] = 'Data dokter belum tersimpan. Pastikan kode dokter unik.';
        }
    }
}
$specializations = db_select_all("SELECT id, nama FROM specializations WHERE status = 'Aktif' ORDER BY nama");
$form_action = base_url('dokter/tambah.php');
$submit_label = 'Simpan dokter';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Profil dokter</h2><p>Data ini dipakai saat petugas memilih dokter di kunjungan.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
