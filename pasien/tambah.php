<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Tambah pasien';
$page_description = 'Buat identitas rekam medis baru untuk pasien klinik.';
$active_menu = 'pasien';
$data = array(
    'no_rm' => generate_medical_record_number(),
    'nik' => '',
    'nama' => '',
    'jenis_kelamin' => '',
    'tanggal_lahir' => '',
    'no_hp' => '',
    'alamat' => '',
);
$errors = array();

if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) {
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
    if ($data['jenis_kelamin'] !== '' && !in_array($data['jenis_kelamin'], array('L', 'P'), true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }
    if ($data['no_hp'] !== '' && !preg_match('/^[0-9+().\-\s]{7,20}$/D', $data['no_hp'])) {
        $errors[] = 'Format nomor HP belum valid.';
    }
    if ($data['tanggal_lahir'] !== '' && !is_valid_calendar_date($data['tanggal_lahir'])) {
        $errors[] = 'Format tanggal lahir tidak valid.';
    } elseif ($data['tanggal_lahir'] !== '' && $data['tanggal_lahir'] > date('Y-m-d')) {
        $errors[] = 'Tanggal lahir tidak boleh melebihi hari ini.';
    }
    if ($data['nik'] !== '' && (int) db_value('SELECT COUNT(*) FROM patients WHERE nik = ?', array($data['nik'])) > 0) {
        $errors[] = 'NIK tersebut sudah terdaftar.';
    }
    if ($data['no_rm'] !== '' && (int) db_value('SELECT COUNT(*) FROM patients WHERE no_rm = ?', array($data['no_rm'])) > 0) {
        $errors[] = 'Nomor rekam medis tersebut sudah terdaftar.';
    }

    if (empty($errors)) {
        try {
            db_execute('INSERT INTO patients (no_rm, nik, nama, jenis_kelamin, tanggal_lahir, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?, ?)', array(
                $data['no_rm'], $data['nik'], $data['nama'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['no_hp'], $data['alamat'],
            ));
            flash('success', 'Pasien baru berhasil ditambahkan.');
            redirect_to('pasien/index.php');
        } catch (Throwable $exception) {
            $errors[] = 'Data pasien belum tersimpan. Pastikan nomor identitas tidak sama.';
        }
    }
}

$form_action = base_url('pasien/tambah.php');
$submit_label = 'Simpan pasien';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <div class="form-card-header"><h2>Identitas pasien</h2><p>Lengkapi data sesuai kartu identitas pasien.</p></div>
    <div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
