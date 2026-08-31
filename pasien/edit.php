<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) query_value('id');
$patient = db_select_one('SELECT * FROM patients WHERE id = ? AND archived_at IS NULL', array($id));
if (!$patient) {
    flash('danger', 'Data pasien tidak ditemukan.');
    redirect_to('pasien/index.php');
}

$page_title = 'Edit pasien';
$page_description = 'Perbarui identitas pasien tanpa mengubah riwayat layanannya.';
$active_menu = 'pasien';
$data = $patient;
$errors = array();

if (is_post()) {
    verify_csrf();
    foreach (array('no_rm', 'nik', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'no_hp', 'alamat') as $field) {
        $data[$field] = post_value($field);
    }

    if ($data['no_rm'] === '' || $data['nik'] === '' || $data['nama'] === '' || $data['jenis_kelamin'] === '' || $data['tanggal_lahir'] === '' || $data['no_hp'] === '' || $data['alamat'] === '') {
        $errors[] = 'Semua field wajib diisi.';
    }
    if ($data['no_rm'] !== '' && !preg_match('/^RM-\d{8}$/D', $data['no_rm'])) {
        $errors[] = 'Nomor rekam medis harus mengikuti format RM-YYMMNNNN.';
    }
    if (strlen($data['nama']) > 120 || strlen($data['no_hp']) > 20 || strlen($data['alamat']) > 4000) {
        $errors[] = 'Nama, nomor HP, atau alamat melebihi batas karakter.';
    }
    if ($data['nik'] !== '' && !preg_match('/^\d{16}$/', $data['nik'])) {
        $errors[] = 'NIK harus terdiri dari 16 digit angka.';
    }
    if ($data['no_hp'] !== '' && !preg_match('/^[0-9+().\-\s]{7,20}$/D', $data['no_hp'])) {
        $errors[] = 'Format nomor HP belum valid.';
    }
    if ($data['tanggal_lahir'] !== '' && !is_valid_calendar_date($data['tanggal_lahir'])) {
        $errors[] = 'Format tanggal lahir tidak valid.';
    } elseif ($data['tanggal_lahir'] !== '' && $data['tanggal_lahir'] > date('Y-m-d')) {
        $errors[] = 'Tanggal lahir tidak boleh melebihi hari ini.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM patients WHERE nik = ? AND id <> ?', array($data['nik'], $id)) > 0) {
        $errors[] = 'NIK tersebut sudah dipakai pasien lain.';
    }
    if ((int) db_value('SELECT COUNT(*) FROM patients WHERE no_rm = ? AND id <> ?', array($data['no_rm'], $id)) > 0) {
        $errors[] = 'Nomor rekam medis tersebut sudah dipakai pasien lain.';
    }

    if (empty($errors)) {
        try {
            db_execute('UPDATE patients SET no_rm = ?, nik = ?, nama = ?, jenis_kelamin = ?, tanggal_lahir = ?, no_hp = ?, alamat = ? WHERE id = ?', array(
                $data['no_rm'], $data['nik'], $data['nama'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['no_hp'], $data['alamat'], $id,
            ));
            flash('success', 'Data pasien berhasil diperbarui.');
            redirect_to('pasien/detail.php?id=' . $id);
        } catch (Throwable $exception) {
            $errors[] = 'Perubahan belum tersimpan. Pastikan data unik dan valid.';
        }
    }
}

$form_action = base_url('pasien/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <div class="form-card-header"><h2>Edit identitas pasien</h2><p>Perubahan ini akan dipakai pada kunjungan berikutnya.</p></div>
    <div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
