<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Tambah obat';
$page_description = 'Masukkan obat baru dan informasi persediaannya.';
$active_menu = 'obat';
$data = array('kode_obat' => '', 'nama_obat' => '', 'category_id' => '', 'satuan' => '', 'harga' => '0', 'stok' => '0', 'tanggal_expired' => '', 'status' => 'Aktif');
$errors = array();
if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) { $data[$field] = post_value($field, $default); }
    if ($data['kode_obat'] === '' || $data['nama_obat'] === '' || $data['category_id'] === '' || $data['satuan'] === '' || $data['tanggal_expired'] === '') { $errors[] = 'Kode, nama, kategori, satuan, dan expired wajib diisi.'; }
    if (strlen($data['kode_obat']) > 16 || strlen($data['nama_obat']) > 120 || strlen($data['satuan']) > 30) { $errors[] = 'Data obat melebihi batas karakter.'; }
    if (!is_valid_nonnegative_decimal($data['harga'], 12, 2)) { $errors[] = 'Harga harus berupa angka maksimal 2 desimal dan tidak boleh negatif.'; }
    if (filter_var($data['stok'], FILTER_VALIDATE_INT) === false || (int) $data['stok'] < 0 || (int) $data['stok'] > 2147483647) { $errors[] = 'Stok harus berupa angka bulat yang valid dan tidak boleh negatif.'; }
    if (!is_valid_calendar_date($data['tanggal_expired'])) { $errors[] = 'Format tanggal expired tidak valid.'; }
    if (!in_array($data['status'], array('Aktif', 'Nonaktif'), true)) { $errors[] = 'Status obat tidak valid.'; }
    if ((int) db_value('SELECT COUNT(*) FROM medicines WHERE kode_obat = ?', array($data['kode_obat'])) > 0) { $errors[] = 'Kode obat tersebut sudah terdaftar.'; }
    if ((int) db_value('SELECT COUNT(*) FROM medicine_categories WHERE id = ?', array((int) $data['category_id'])) === 0) { $errors[] = 'Kategori obat yang dipilih tidak ditemukan.'; }
    if (empty($errors)) {
        try {
            db_execute('INSERT INTO medicines (kode_obat, nama_obat, category_id, satuan, harga, stok, tanggal_expired, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array($data['kode_obat'], $data['nama_obat'], (int) $data['category_id'], $data['satuan'], (float) $data['harga'], (int) $data['stok'], $data['tanggal_expired'], $data['status']));
            flash('success', 'Obat baru berhasil ditambahkan.');
            redirect_to('obat/index.php');
        } catch (Throwable $exception) { $errors[] = 'Data obat belum tersimpan. Pastikan kode obat unik.'; }
    }
}
$categories = db_select_all('SELECT id, nama_kategori FROM medicine_categories ORDER BY nama_kategori');
$form_action = base_url('obat/tambah.php');
$submit_label = 'Simpan obat';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Informasi obat</h2><p>Stok rendah dan expired akan otomatis muncul di dashboard.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
