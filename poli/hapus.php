<?php
require_once __DIR__ . '/../includes/functions.php';
if (!is_post()) { redirect_to('poli/index.php'); }
try {
    verify_csrf();
    $id = (int) post_value('id');
    if ($id < 1) { throw new RuntimeException('Data poli tidak valid.'); }
    $user = current_user();
    archive_entity_record('poli', $id, (int) ($user['id'] ?? 0));
    flash('success', 'Data poli dipindahkan ke Arsip. Data tetap tersimpan dan dapat dipulihkan.');
} catch (Throwable $exception) {
    flash('danger', $exception->getMessage());
}
redirect_to('poli/index.php');
