<?php
require_once __DIR__ . '/../includes/functions.php';
$id = (int) query_value('id');
$medicine = db_select_one('SELECT * FROM medicines WHERE id = ? AND archived_at IS NULL', array($id));
if (!$medicine) { flash('danger', 'Data obat tidak ditemukan.'); redirect_to('obat/index.php'); }
$page_title = 'Edit obat';
$page_description = 'Perbarui harga, stok, masa berlaku, atau status obat.';
$active_menu = 'obat';
$data = $medicine;
$errors = array();
if (is_post()) {
    verify_csrf();
    foreach (array('kode_obat', 'nama_obat', 'category_id', 'satuan', 'harga', 'stok', 'tanggal_expired', 'status') as $field) { $data[$field] = post_value($field); }
    if ($data['kode_obat'] === '' || $data['nama_obat'] === '' || $data['category_id'] === '' || $data['satuan'] === '' || $data['tanggal_expired'] === '') { $errors[] = 'Kode, nama, kategori, satuan, dan expired wajib diisi.'; }
    if (strlen($data['kode_obat']) > 16 || strlen($data['nama_obat']) > 120 || strlen($data['satuan']) > 30) { $errors[] = 'Data obat melebihi batas karakter.'; }
    if (!is_valid_nonnegative_decimal($data['harga'], 12, 2)) { $errors[] = 'Harga harus berupa angka maksimal 2 desimal dan tidak boleh negatif.'; }
    if (filter_var($data['stok'], FILTER_VALIDATE_INT) === false || (int) $data['stok'] < 0 || (int) $data['stok'] > 2147483647) { $errors[] = 'Stok harus berupa angka bulat yang valid dan tidak boleh negatif.'; }
    if (!is_valid_calendar_date($data['tanggal_expired'])) { $errors[] = 'Format tanggal expired tidak valid.'; }
    if (!in_array($data['status'], array('Aktif', 'Nonaktif'), true)) { $errors[] = 'Status obat tidak valid.'; }
    if ((int) db_value('SELECT COUNT(*) FROM medicines WHERE kode_obat = ? AND id <> ?', array($data['kode_obat'], $id)) > 0) { $errors[] = 'Kode obat tersebut sudah dipakai obat lain.'; }
    if ((int) db_value('SELECT COUNT(*) FROM medicine_categories WHERE id = ?', array((int) $data['category_id'])) === 0) { $errors[] = 'Kategori obat yang dipilih tidak ditemukan.'; }
    if (empty($errors)) {
        try {
            db_execute('UPDATE medicines SET kode_obat = ?, nama_obat = ?, category_id = ?, satuan = ?, harga = ?, stok = ?, tanggal_expired = ?, status = ? WHERE id = ?', array($data['kode_obat'], $data['nama_obat'], (int) $data['category_id'], $data['satuan'], (float) $data['harga'], (int) $data['stok'], $data['tanggal_expired'], $data['status'], $id));
            flash('success', 'Data obat berhasil diperbarui.');
            redirect_to('obat/index.php');
        } catch (Throwable $exception) { $errors[] = 'Perubahan belum tersimpan. Pastikan kode obat unik.'; }
    }
}
$categories = db_select_all('SELECT id, nama_kategori FROM medicine_categories ORDER BY nama_kategori');
$form_action = base_url('obat/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
require_once __DIR__ . '/../includes/header.php';
?><div class="form-card"><div class="form-card-header"><h2>Edit informasi obat</h2><p>Perubahan stok di sini menjadi stok yang tersedia untuk resep berikutnya.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
