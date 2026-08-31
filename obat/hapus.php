<?php
require_once __DIR__ . '/../includes/functions.php';
if (!is_post()) { redirect_to('obat/index.php'); }
try {
    verify_csrf();
    $id = (int) post_value('id');
    if ($id < 1) { throw new RuntimeException('Data obat tidak valid.'); }
    $user = current_user();
    archive_entity_record('obat', $id, (int) ($user['id'] ?? 0));
    flash('success', 'Data obat dipindahkan ke Arsip. Data tetap tersimpan dan dapat dipulihkan.');
} catch (Throwable $exception) {
    flash('danger', $exception->getMessage());
}
redirect_to('obat/index.php');
