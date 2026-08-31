<?php
require_once __DIR__ . '/../includes/functions.php';

if (!is_post()) {
    redirect_to('dokter/index.php');
}
try {
    verify_csrf();
    $id = (int) post_value('id');
    if ($id < 1) {
        throw new RuntimeException('Data dokter tidak valid.');
    }
    if ((int) db_value('SELECT COUNT(*) FROM users WHERE doctor_id = ?', array($id), 0) > 0) {
        throw new RuntimeException('Dokter tidak dapat diarsipkan karena masih terhubung dengan akun login. Nonaktifkan dokter atau pindahkan hubungan akunnya terlebih dahulu.');
    }
    $user = current_user();
    archive_entity_record('dokter', $id, (int) ($user['id'] ?? 0));
    flash('success', 'Data dokter dipindahkan ke Arsip. Data tetap tersimpan dan dapat dipulihkan.');
} catch (Throwable $exception) {
    flash('danger', $exception->getMessage());
}
redirect_to('dokter/index.php');
