<?php
require_once __DIR__ . '/../includes/functions.php';
$visit_id = (int) query_value('visit_id');
$visit = db_select_one("SELECT v.*, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id WHERE v.id = ?", array($visit_id));
if (!$visit) { flash('danger', 'Kunjungan tidak ditemukan.'); redirect_to('pemeriksaan/index.php'); }
if ($visit['status'] === 'Batal') { flash('danger', 'Kunjungan yang dibatalkan tidak dapat diperiksa.'); redirect_to('kunjungan/detail.php?id=' . $visit_id); }
if ($visit['status'] === 'Selesai') { flash('danger', 'Kunjungan yang sudah selesai tidak dapat diberi pemeriksaan baru.'); redirect_to('kunjungan/detail.php?id=' . $visit_id); }
$existing = db_select_one('SELECT id FROM examinations WHERE visit_id = ?', array($visit_id));
if ($existing) { redirect_to('pemeriksaan/edit.php?id=' . $existing['id']); }
$page_title = 'Isi pemeriksaan';
$page_description = 'Catat temuan dokter untuk ' . $visit['nama_pasien'] . ' · ' . $visit['no_kunjungan'];
$active_menu = 'pemeriksaan';
$data = array('keluhan' => $visit['keluhan_awal'], 'hasil_pemeriksaan' => '', 'tekanan_darah' => '', 'suhu' => '', 'berat_badan' => '', 'diagnosa' => '', 'tindakan' => '', 'catatan_dokter' => '');
$errors = array();
$transaction_started = false;
if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) { $data[$field] = post_value($field); }
    if ($data['keluhan'] === '' || $data['hasil_pemeriksaan'] === '' || $data['diagnosa'] === '') { $errors[] = 'Keluhan, hasil pemeriksaan, dan diagnosa wajib diisi.'; }
    if (strlen($data['keluhan']) > 4000 || strlen($data['hasil_pemeriksaan']) > 4000 || strlen($data['diagnosa']) > 255 || strlen($data['tindakan']) > 4000 || strlen($data['catatan_dokter']) > 4000) { $errors[] = 'Catatan pemeriksaan melebihi batas karakter.'; }
    if ($data['tekanan_darah'] !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/D', $data['tekanan_darah'])) { $errors[] = 'Tekanan darah harus memakai format sistolik/diastolik.'; }
    if ($data['suhu'] !== '' && (!is_valid_nonnegative_decimal($data['suhu'], 2, 1) || (float) $data['suhu'] < 25 || (float) $data['suhu'] > 45)) { $errors[] = 'Suhu harus berada di antara 25 dan 45 °C.'; }
    if ($data['berat_badan'] !== '' && (!is_valid_nonnegative_decimal($data['berat_badan'], 3, 2) || (float) $data['berat_badan'] <= 0)) { $errors[] = 'Berat badan harus berupa angka positif yang valid.'; }
    if (empty($errors)) {
        try {
            begin_transaction();
            $transaction_started = true;
            $locked_visit = db_select_one('SELECT status FROM visits WHERE id = ? FOR UPDATE', array($visit_id));
            if (!$locked_visit || in_array($locked_visit['status'], array('Batal', 'Selesai'), true)) { throw new RuntimeException('Status kunjungan sudah tidak memungkinkan pemeriksaan baru.'); }
            db_execute('INSERT INTO examinations (visit_id, keluhan, hasil_pemeriksaan, tekanan_darah, suhu, berat_badan, diagnosa, tindakan, catatan_dokter, diperiksa_pada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', array($visit_id, $data['keluhan'], $data['hasil_pemeriksaan'], $data['tekanan_darah'] ?: null, $data['suhu'] === '' ? null : (float) $data['suhu'], $data['berat_badan'] === '' ? null : (float) $data['berat_badan'], $data['diagnosa'], $data['tindakan'], $data['catatan_dokter']));
            db_execute("UPDATE visits SET status = 'Diperiksa' WHERE id = ? AND status = 'Menunggu'", array($visit_id));
            commit_transaction();
            $transaction_started = false;
            flash('success', 'Hasil pemeriksaan berhasil disimpan.');
            redirect_to('kunjungan/detail.php?id=' . $visit_id);
        } catch (Throwable $exception) { if ($transaction_started) { rollback_transaction(); } $errors[] = 'Hasil pemeriksaan belum tersimpan. Pastikan kunjungan belum memiliki pemeriksaan.'; }
    }
}
$form_action = base_url('pemeriksaan/tambah.php?visit_id=' . $visit_id);
$submit_label = 'Simpan pemeriksaan';
require_once __DIR__ . '/../includes/header.php';
?><div class="detail-card" style="max-width: 960px; margin-bottom: 18px;"><div class="detail-card-body"><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?> · <?= e($visit['nama_poli']) ?> · <?= e($visit['nama_dokter']) ?></div></div></div></div></div><div class="form-card"><div class="form-card-header"><h2>Catatan pemeriksaan</h2><p>Keluhan awal sudah dibawa dari formulir pendaftaran dan dapat disesuaikan.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
