<?php
require_once __DIR__ . '/../includes/functions.php';

// Logout mengubah state sesi, jadi link GET juga wajib membawa token CSRF.
// POST tetap didukung untuk integrasi form yang lebih ketat.
if (is_post()) {
    verify_csrf();
} else {
    $token = query_value('csrf_token');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(405);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Permintaan logout tidak valid.';
        exit;
    }
}

logout_user();
flash('success', 'Anda sudah keluar dari sesi ' . app_brand_name() . '.');
redirect_to('auth/login.php');
