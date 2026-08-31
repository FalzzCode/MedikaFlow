<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('Admin');

if (!is_post()) {
    redirect_to('arsip/index.php');
}

$entity = post_value('entity');
$return_query = isset($_POST['entity']) && archive_entity_definition($entity) ? '?jenis=' . urlencode($entity) : '';

try {
    verify_csrf();
    $id = (int) post_value('id');
    $action = post_value('action');
    $definition = archive_entity_definition($entity);
    if (!$definition) {
        throw new RuntimeException('Jenis data arsip tidak valid.');
    }

    if ($action === 'restore') {
        restore_archive_entity($entity, $id);
        flash('success', 'Data ' . strtolower($definition['label']) . ' berhasil dipulihkan ke daftar aktif.');
    } elseif ($action === 'delete_permanent') {
        if ($entity === 'dokter' && (int) db_value('SELECT COUNT(*) FROM users WHERE doctor_id = ?', array($id), 0) > 0) {
            throw new RuntimeException('Dokter tidak dapat dihapus permanen karena masih terhubung dengan akun login. Pindahkan hubungan akun terlebih dahulu.');
        }
        permanently_delete_archive_entity($entity, $id);
        flash('success', 'Data ' . strtolower($definition['label']) . ' dihapus permanen.');
    } else {
        throw new RuntimeException('Aksi arsip tidak valid.');
    }
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    if (strpos($message, '1451') !== false) {
        $message = 'Data ' . strtolower($definition['label'] ?? 'ini') . ' tidak dapat dihapus permanen karena masih terhubung dengan riwayat atau transaksi klinik.';
    }
    flash('danger', $message);
}

redirect_to('arsip/index.php' . $return_query);
