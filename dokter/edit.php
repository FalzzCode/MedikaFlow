<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) query_value('id');
$doctor = db_select_one('SELECT * FROM doctors WHERE id = ? AND archived_at IS NULL', array($id));
if (!$doctor) {
    flash('danger', 'Data dokter tidak ditemukan.');
    redirect_to('dokter/index.php');
}
$page_title = 'Edit dokter';
$page_description = 'Perbarui profil dan status dokter yang terdaftar.';
$active_menu = 'dokter';
$data = $doctor;
$errors = array();

if (is_post()) {
    verify_csrf();
    foreach (array('kode_dokter', 'nama_dokter', 'specialization_id', 'no_hp', 'alamat', 'jadwal_hari', 'jam_mulai', 'jam_selesai', 'status') as $field) {
        $data[$field] = post_value($field);
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
    if (!in_array($data['status'], array('Aktif', 'Nonaktif'), true)) {
        $errors[] = 'Status dokter tidak valid.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM doctors WHERE kode_dokter = ? AND id <> ?', array($data['kode_dokter'], $id)) > 0) {
        $errors[] = 'Kode dokter tersebut sudah dipakai dokter lain.';
    }
    if ((int) db_value("SELECT COUNT(*) FROM specializations WHERE id = ? AND status = 'Aktif'", array((int) $data['specialization_id'])) === 0) {
        $errors[] = 'Spesialisasi yang dipilih tidak ditemukan.';
    }
    if (empty($errors)) {
        try {
            db_execute('UPDATE doctors SET kode_dokter = ?, nama_dokter = ?, specialization_id = ?, no_hp = ?, alamat = ?, jadwal_hari = ?, jam_mulai = ?, jam_selesai = ?, status = ? WHERE id = ?', array($data['kode_dokter'], $data['nama_dokter'], (int) $data['specialization_id'], $data['no_hp'], $data['alamat'], $data['jadwal_hari'], $data['jam_mulai'], $data['jam_selesai'], $data['status'], $id));
            flash('success', 'Data dokter berhasil diperbarui.');
            redirect_to('dokter/index.php');
        } catch (Throwable $exception) {
            $errors[] = 'Perubahan belum tersimpan. Pastikan kode dokter unik.';
        }
    }
}
$specializations = db_select_all("SELECT id, nama FROM specializations WHERE status = 'Aktif' OR id = ? ORDER BY nama", array((int) $data['specialization_id']));
$form_action = base_url('dokter/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Edit profil dokter</h2><p>Status nonaktif tidak muncul di pendaftaran kunjungan baru.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
