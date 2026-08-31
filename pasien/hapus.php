<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('Admin');

if (!is_post()) {
    redirect_to('pasien/index.php');
}

try {
    verify_csrf();
    $id = (int) post_value('id');
    if ($id < 1) {
        throw new RuntimeException('Data pasien tidak valid.');
    }
    $user = current_user();
    archive_entity_record('pasien', $id, (int) ($user['id'] ?? 0));
    flash('success', 'Data pasien dipindahkan ke Arsip. Data tetap tersimpan dan dapat dipulihkan.');
} catch (Throwable $exception) {
    flash('danger', $exception->getMessage());
}

redirect_to('pasien/index.php');
