<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Tambah poli';
$page_description = 'Tambahkan ruang layanan baru ke daftar poli klinik.';
$active_menu = 'poli';
$data = array('kode_poli' => '', 'nama_poli' => '', 'lokasi' => '', 'keterangan' => '', 'status' => 'Aktif');
$errors = array();
if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) {
        $data[$field] = post_value($field, $default);
    }
    if ($data['kode_poli'] === '' || $data['nama_poli'] === '' || $data['lokasi'] === '') {
        $errors[] = 'Kode, nama, dan lokasi poli wajib diisi.';
    }
    if (strlen($data['kode_poli']) > 12 || strlen($data['nama_poli']) > 80 || strlen($data['lokasi']) > 120 || strlen($data['keterangan']) > 255) {
        $errors[] = 'Data poli melebihi batas karakter.';
    }
    if (!in_array($data['status'], array('Aktif', 'Nonaktif'), true)) {
        $errors[] = 'Status poli tidak valid.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM polyclinics WHERE kode_poli = ?', array($data['kode_poli'])) > 0) {
        $errors[] = 'Kode poli tersebut sudah terdaftar.';
    }
    if (empty($errors)) {
        try {
            db_execute('INSERT INTO polyclinics (kode_poli, nama_poli, lokasi, keterangan, status) VALUES (?, ?, ?, ?, ?)', array($data['kode_poli'], $data['nama_poli'], $data['lokasi'], $data['keterangan'], $data['status']));
            flash('success', 'Poli baru berhasil ditambahkan.');
            redirect_to('poli/index.php');
        } catch (Throwable $exception) {
            $errors[] = 'Data poli belum tersimpan. Pastikan kode poli unik.';
        }
    }
}
$form_action = base_url('poli/tambah.php');
$submit_label = 'Simpan poli';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Informasi poli</h2><p>Nama dan lokasi akan terlihat saat pendaftaran kunjungan.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
