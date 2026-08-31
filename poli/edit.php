<?php
require_once __DIR__ . '/../includes/functions.php';
$id = (int) query_value('id');
$polyclinic = db_select_one('SELECT * FROM polyclinics WHERE id = ? AND archived_at IS NULL', array($id));
if (!$polyclinic) { flash('danger', 'Data poli tidak ditemukan.'); redirect_to('poli/index.php'); }
$page_title = 'Edit poli';
$page_description = 'Perbarui informasi ruang layanan dan status poli.';
$active_menu = 'poli';
$data = $polyclinic;
$errors = array();
if (is_post()) {
    verify_csrf();
    foreach (array('kode_poli', 'nama_poli', 'lokasi', 'keterangan', 'status') as $field) { $data[$field] = post_value($field); }
    if ($data['kode_poli'] === '' || $data['nama_poli'] === '' || $data['lokasi'] === '') { $errors[] = 'Kode, nama, dan lokasi poli wajib diisi.'; }
    if (strlen($data['kode_poli']) > 12 || strlen($data['nama_poli']) > 80 || strlen($data['lokasi']) > 120 || strlen($data['keterangan']) > 255) { $errors[] = 'Data poli melebihi batas karakter.'; }
    if (!in_array($data['status'], array('Aktif', 'Nonaktif'), true)) { $errors[] = 'Status poli tidak valid.'; }
    if ((int) db_value('SELECT COUNT(*) FROM polyclinics WHERE kode_poli = ? AND id <> ?', array($data['kode_poli'], $id)) > 0) { $errors[] = 'Kode poli tersebut sudah dipakai poli lain.'; }
    if (empty($errors)) {
        try {
            db_execute('UPDATE polyclinics SET kode_poli = ?, nama_poli = ?, lokasi = ?, keterangan = ?, status = ? WHERE id = ?', array($data['kode_poli'], $data['nama_poli'], $data['lokasi'], $data['keterangan'], $data['status'], $id));
            flash('success', 'Data poli berhasil diperbarui.');
            redirect_to('poli/index.php');
        } catch (Throwable $exception) { $errors[] = 'Perubahan belum tersimpan. Pastikan kode poli unik.'; }
    }
}
$form_action = base_url('poli/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Edit informasi poli</h2><p>Poli nonaktif tidak muncul pada pendaftaran kunjungan baru.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
