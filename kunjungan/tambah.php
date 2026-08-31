<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Daftarkan kunjungan';
$page_description = 'Buat antrean baru dan arahkan pasien ke poli serta dokter yang tepat.';
$active_menu = 'kunjungan';
$data = array('patient_id' => (int) query_value('patient_id'), 'polyclinic_id' => '', 'doctor_id' => '', 'tanggal_kunjungan' => date('Y-m-d'), 'keluhan_awal' => '');
$errors = array();
$transaction_started = false;
$visit_lock = '';

if (is_post()) {
    verify_csrf();
    foreach (array('patient_id', 'polyclinic_id', 'doctor_id', 'tanggal_kunjungan', 'keluhan_awal') as $field) {
        $data[$field] = post_value($field);
    }
    if ((int) $data['patient_id'] < 1 || (int) $data['polyclinic_id'] < 1 || (int) $data['doctor_id'] < 1 || $data['tanggal_kunjungan'] === '' || $data['keluhan_awal'] === '') {
        $errors[] = 'Pasien, poli, dokter, tanggal, dan keluhan awal wajib diisi.';
    }
    if ($data['tanggal_kunjungan'] !== '' && !is_valid_calendar_date($data['tanggal_kunjungan'])) {
        $errors[] = 'Format tanggal kunjungan tidak valid.';
    }
    if (is_valid_calendar_date($data['tanggal_kunjungan']) && $data['tanggal_kunjungan'] < date('Y-m-d')) {
        $errors[] = 'Tanggal kunjungan tidak boleh berada di masa lalu.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM patients WHERE id = ? AND archived_at IS NULL', array((int) $data['patient_id'])) === 0) {
        $errors[] = 'Pasien yang dipilih tidak ditemukan.';
    }
    if ((int) db_value("SELECT COUNT(*) FROM polyclinics WHERE id = ? AND archived_at IS NULL AND status = 'Aktif'", array((int) $data['polyclinic_id'])) === 0) {
        $errors[] = 'Poli yang dipilih tidak aktif atau tidak ditemukan.';
    }
    if ((int) db_value("SELECT COUNT(*) FROM doctors d INNER JOIN specializations s ON s.id = d.specialization_id AND s.status = 'Aktif' WHERE d.id = ? AND d.archived_at IS NULL AND d.status = 'Aktif'", array((int) $data['doctor_id'])) === 0) {
        $errors[] = 'Dokter yang dipilih tidak aktif atau tidak ditemukan.';
    }

    if (empty($errors)) {
        try {
            $visit_lock = acquire_database_lock('visit:' . $data['tanggal_kunjungan'], 5);
            begin_transaction();
            $transaction_started = true;
            $queue = next_queue_number($data['tanggal_kunjungan']);
            $visit_number = generate_visit_number($data['tanggal_kunjungan']);
            $insert = db_execute('INSERT INTO visits (no_kunjungan, patient_id, polyclinic_id, doctor_id, tanggal_kunjungan, nomor_antrian, keluhan_awal, status) VALUES (?, ?, ?, ?, ?, ?, ?, \'Menunggu\')', array($visit_number, (int) $data['patient_id'], (int) $data['polyclinic_id'], (int) $data['doctor_id'], $data['tanggal_kunjungan'], $queue, $data['keluhan_awal']));
            db_execute('INSERT INTO payments (visit_id) VALUES (?)', array((int) $insert['insert_id']));
            commit_transaction();
            $transaction_started = false;
            release_database_lock($visit_lock);
            $visit_lock = '';
            flash('success', 'Kunjungan ' . $visit_number . ' berhasil didaftarkan sebagai antrean #' . $queue . '.');
            redirect_to('kunjungan/detail.php?id=' . $insert['insert_id']);
        } catch (Throwable $exception) {
            if ($transaction_started) {
                rollback_transaction();
            }
            $errors[] = 'Kunjungan belum tersimpan. Coba ulangi beberapa saat lagi.';
        } finally {
            if ($visit_lock !== '') {
                release_database_lock($visit_lock);
                $visit_lock = '';
            }
        }
    }
}

$patients = db_select_all('SELECT id, no_rm, nama FROM patients WHERE archived_at IS NULL ORDER BY nama');
$polyclinics = db_select_all("SELECT id, kode_poli, nama_poli, lokasi FROM polyclinics WHERE archived_at IS NULL AND status = 'Aktif' ORDER BY nama_poli");
$doctors = db_select_all("SELECT d.id, d.kode_dokter, d.nama_dokter, s.nama AS spesialisasi FROM doctors d INNER JOIN specializations s ON s.id = d.specialization_id AND s.status = 'Aktif' WHERE d.archived_at IS NULL AND d.status = 'Aktif' ORDER BY d.nama_dokter");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <div class="form-card-header"><h2>Data pendaftaran</h2><p>Nomor kunjungan dan antrean akan dibuat otomatis setelah disimpan.</p></div>
    <div class="form-card-body">
        <?= render_errors($errors) ?>
        <form method="post" action="<?= e(base_url('kunjungan/tambah.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-section"><div class="form-section-title">Siapa yang datang?</div><div class="form-grid"><div class="form-field full"><label class="form-label" for="patient_id">Pasien <span class="required">*</span></label><select class="form-control" id="patient_id" name="patient_id" required><option value="">Pilih pasien dari rekam medis</option><?php foreach ($patients as $patient): ?><option value="<?= e($patient['id']) ?>" <?= (string) $data['patient_id'] === (string) $patient['id'] ? 'selected' : '' ?>><?= e($patient['nama']) ?> · <?= e($patient['no_rm']) ?></option><?php endforeach; ?></select><p class="form-hint">Pasien belum terdaftar? <a class="link-text" href="<?= e(base_url('pasien/tambah.php')) ?>">Tambah pasien baru</a>.</p></div></div></div>
            <div class="form-section"><div class="form-section-title">Tujuan layanan</div><div class="form-grid"><div class="form-field"><label class="form-label" for="polyclinic_id">Poli <span class="required">*</span></label><select class="form-control" id="polyclinic_id" name="polyclinic_id" required><option value="">Pilih poli</option><?php foreach ($polyclinics as $polyclinic): ?><option value="<?= e($polyclinic['id']) ?>" <?= (string) $data['polyclinic_id'] === (string) $polyclinic['id'] ? 'selected' : '' ?>><?= e($polyclinic['nama_poli']) ?> · <?= e($polyclinic['lokasi']) ?></option><?php endforeach; ?></select></div><div class="form-field"><label class="form-label" for="doctor_id">Dokter <span class="required">*</span></label><select class="form-control" id="doctor_id" name="doctor_id" required><option value="">Pilih dokter</option><?php foreach ($doctors as $doctor): ?><option value="<?= e($doctor['id']) ?>" <?= (string) $data['doctor_id'] === (string) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['nama_dokter']) ?> · <?= e($doctor['spesialisasi']) ?></option><?php endforeach; ?></select></div><div class="form-field"><label class="form-label" for="tanggal_kunjungan">Tanggal kunjungan <span class="required">*</span></label><input class="form-control" type="date" id="tanggal_kunjungan" name="tanggal_kunjungan" value="<?= e($data['tanggal_kunjungan']) ?>" min="<?= e(date('Y-m-d')) ?>" required></div></div></div>
            <div class="form-section"><div class="form-section-title">Keluhan awal</div><div class="form-grid"><div class="form-field full"><label class="form-label" for="keluhan_awal">Keluhan yang disampaikan pasien <span class="required">*</span></label><textarea class="form-control" id="keluhan_awal" name="keluhan_awal" placeholder="Contoh: Demam sejak tadi malam disertai badan pegal" required><?= e($data['keluhan_awal']) ?></textarea></div></div></div>
            <div class="form-note"><?= icon('clipboard-plus') ?><span>Status awal kunjungan adalah <strong>Menunggu</strong>. Setelah disimpan, pasien mendapat nomor antrean sesuai tanggal kunjungan.</span></div>
            <div class="form-actions"><a class="button button-secondary" href="<?= e(base_url('kunjungan/index.php')) ?>">Batal</a><button class="button button-primary" type="submit"><?= icon('calendar-days') ?><span>Simpan pendaftaran</span></button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
