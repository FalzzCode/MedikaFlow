<?php
require_once __DIR__ . '/../includes/functions.php';
$id = (int) query_value('id');
$examination = db_select_one("SELECT e.*, v.id AS visit_id, v.no_kunjungan, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter FROM examinations e INNER JOIN visits v ON v.id = e.visit_id INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id WHERE e.id = ?", array($id));
if (!$examination) { flash('danger', 'Data pemeriksaan tidak ditemukan.'); redirect_to('pemeriksaan/index.php'); }
$visit = array('id' => $examination['visit_id'], 'no_kunjungan' => $examination['no_kunjungan'], 'nama_pasien' => $examination['nama_pasien'], 'no_rm' => $examination['no_rm'], 'nama_poli' => $examination['nama_poli'], 'nama_dokter' => $examination['nama_dokter']);
$page_title = 'Edit pemeriksaan';
$page_description = 'Perbarui catatan pemeriksaan ' . $visit['nama_pasien'] . ' · ' . $visit['no_kunjungan'];
$active_menu = 'pemeriksaan';
$data = $examination;
$errors = array();
if (is_post()) {
    verify_csrf();
    foreach (array('keluhan', 'hasil_pemeriksaan', 'tekanan_darah', 'suhu', 'berat_badan', 'diagnosa', 'tindakan', 'catatan_dokter') as $field) { $data[$field] = post_value($field); }
    if ($data['keluhan'] === '' || $data['hasil_pemeriksaan'] === '' || $data['diagnosa'] === '') { $errors[] = 'Keluhan, hasil pemeriksaan, dan diagnosa wajib diisi.'; }
    if (strlen($data['keluhan']) > 4000 || strlen($data['hasil_pemeriksaan']) > 4000 || strlen($data['diagnosa']) > 255 || strlen($data['tindakan']) > 4000 || strlen($data['catatan_dokter']) > 4000) { $errors[] = 'Catatan pemeriksaan melebihi batas karakter.'; }
    if ($data['tekanan_darah'] !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/D', $data['tekanan_darah'])) { $errors[] = 'Tekanan darah harus memakai format sistolik/diastolik.'; }
    if ($data['suhu'] !== '' && (!is_valid_nonnegative_decimal($data['suhu'], 2, 1) || (float) $data['suhu'] < 25 || (float) $data['suhu'] > 45)) { $errors[] = 'Suhu harus berada di antara 25 dan 45 °C.'; }
    if ($data['berat_badan'] !== '' && (!is_valid_nonnegative_decimal($data['berat_badan'], 3, 2) || (float) $data['berat_badan'] <= 0)) { $errors[] = 'Berat badan harus berupa angka positif yang valid.'; }
    if (empty($errors)) {
        try {
            db_execute('UPDATE examinations SET keluhan = ?, hasil_pemeriksaan = ?, tekanan_darah = ?, suhu = ?, berat_badan = ?, diagnosa = ?, tindakan = ?, catatan_dokter = ? WHERE id = ?', array($data['keluhan'], $data['hasil_pemeriksaan'], $data['tekanan_darah'] ?: null, $data['suhu'] === '' ? null : (float) $data['suhu'], $data['berat_badan'] === '' ? null : (float) $data['berat_badan'], $data['diagnosa'], $data['tindakan'], $data['catatan_dokter'], $id));
            flash('success', 'Hasil pemeriksaan berhasil diperbarui.');
            redirect_to('kunjungan/detail.php?id=' . $visit['id']);
        } catch (Throwable $exception) { $errors[] = 'Perubahan hasil pemeriksaan belum tersimpan.'; }
    }
}
$form_action = base_url('pemeriksaan/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
require_once __DIR__ . '/../includes/header.php';
?><div class="detail-card" style="max-width: 960px; margin-bottom: 18px;"><div class="detail-card-body"><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?> · <?= e($visit['nama_poli']) ?> · <?= e($visit['nama_dokter']) ?></div></div></div></div></div><div class="form-card"><div class="form-card-header"><h2>Perbarui catatan pemeriksaan</h2><p>Perubahan akan langsung terlihat pada detail kunjungan pasien.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
