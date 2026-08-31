<?php
require_once __DIR__ . '/functions.php';

$auth_title = $auth_title ?? 'Masuk ke MedikaFlow';
$auth_description = $auth_description ?? 'Gunakan akun yang terdaftar untuk melanjutkan.';
$auth_kicker = $auth_kicker ?? 'Akses sistem';
$auth_mode = $auth_mode ?? 'login';
$app_brand_name = app_brand_name();
$auth_flash_messages = pull_flash_messages();
$auth_rail_steps = $auth_mode === 'setup'
    ? array('Identitas', 'Keamanan', 'Siap dipakai')
    : array('Identitas', 'Verifikasi', 'Workspace');
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b172a">
    <title><?= e($auth_title) ?> · <?= e($app_brand_name) ?></title>
    <script>document.documentElement.classList.add('has-page-loader');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/auth.css?v=20260831-1')) ?>">
</head>
<body class="auth-page auth-mode-<?= e($auth_mode) ?> page-is-loading" data-page-key="auth" data-page-route="<?= e(current_route_key()) ?>">
<div class="auth-shell">
    <section class="auth-story" aria-label="Tentang <?= e($app_brand_name) ?>">
        <a class="auth-brand" href="<?= e(base_url()) ?>" aria-label="<?= e($app_brand_name) ?>">
            <img class="auth-brand-mark auth-brand-logo" src="<?= e(base_url('assets/images/medikaflow-logo-mark-v2.webp?v=20260831-2')) ?>" alt="">
            <span><strong><?= e($app_brand_name) ?></strong><small>Manajemen Klinik</small></span>
        </a>

        <div class="auth-story-copy">
            <span class="auth-story-kicker">SISTEM KLINIK TERHUBUNG</span>
            <h1>Setiap shift dimulai dari akses yang jelas.</h1>
            <p>Identitas, peran, dan aktivitas pengguna dibaca langsung dari database agar alur pasien tetap aman dan mudah ditelusuri.</p>
        </div>

        <div class="auth-feature-list">
            <div class="auth-feature"><span><?= icon('shield-check') ?></span><div><strong>Akses sesuai peran</strong><small>Admin, Dokter, dan Petugas melihat menu kerja yang relevan.</small></div></div>
            <div class="auth-feature"><span><?= icon('activity') ?></span><div><strong>Data langsung dari MySQL</strong><small>Tidak ada kredensial contoh atau profil yang ditulis di tampilan.</small></div></div>
            <div class="auth-feature"><span><?= icon('check-circle-2') ?></span><div><strong>Sesi kerja terlindungi</strong><small>Password di-hash, formulir memakai CSRF, dan sesi diperbarui saat login.</small></div></div>
        </div>

        <div class="auth-data-visual" aria-hidden="true">
            <div class="auth-visual-head"><span></span><span></span><span></span><i>ALUR LAYANAN</i></div>
            <div class="auth-care-flow">
                <span class="auth-care-step is-done"><?= icon('users-round') ?><small>Pasien</small></span>
                <span class="auth-care-connector"></span>
                <span class="auth-care-step is-current"><?= icon('calendar-days') ?><small>Kunjungan</small></span>
                <span class="auth-care-connector"></span>
                <span class="auth-care-step"><?= icon('check-circle-2') ?><small>Selesai</small></span>
            </div>
            <div class="auth-visual-caption"><span>Data tersambung</span><strong>Siap untuk shift berikutnya</strong></div>
        </div>

        <div class="auth-story-foot"><span class="auth-live-dot is-active" aria-hidden="true"></span> PHP Procedural · MySQL · Sesi database</div>
    </section>

    <main class="auth-main" data-page-content aria-busy="true">
        <div class="auth-mobile-brand"><img class="auth-brand-mark auth-brand-logo" src="<?= e(base_url('assets/images/medikaflow-logo-mark-v2.webp?v=20260831-2')) ?>" alt=""><span><strong><?= e($app_brand_name) ?></strong><small>Manajemen Klinik</small></span></div>
        <div class="auth-main-toolbar" aria-label="Status sistem">
            <span class="auth-main-breadcrumb"><span class="auth-main-dot is-active" aria-hidden="true"></span><?= e(strtoupper($app_brand_name)) ?> <i>/</i> AKSES TERKENDALI</span>
            <span class="auth-main-status"><i class="is-active" aria-hidden="true"></i> Database siap</span>
        </div>
        <div class="auth-main-ambient" aria-hidden="true">
            <span class="auth-ambient-ring auth-ambient-ring-one"></span>
            <span class="auth-ambient-ring auth-ambient-ring-two"></span>
            <span class="auth-ambient-node auth-ambient-node-one"></span>
            <span class="auth-ambient-node auth-ambient-node-two"></span>
            <span class="auth-ambient-node auth-ambient-node-three"></span>
            <span class="auth-ambient-line auth-ambient-line-one"></span>
            <span class="auth-ambient-line auth-ambient-line-two"></span>
        </div>
        <div class="auth-main-stage">
            <div class="auth-loading-skeleton auth-loading-skeleton-<?= e($auth_mode) ?>" data-page-skeleton="auth" aria-hidden="true">
                <div class="auth-skeleton-rail">
                    <div class="auth-skeleton-rail-top"><?= icon('shield-check') ?><?= icon('activity') ?></div>
                    <div class="auth-skeleton-rail-mark"></div>
                    <div class="auth-skeleton-rail-copy"><?= clinic_skeleton_block('auth-skeleton-rail-title') ?><?= clinic_skeleton_block('auth-skeleton-rail-copy-line') ?></div>
                    <div class="auth-skeleton-rail-steps"><i></i><i></i><i></i></div>
                </div>
                <section class="auth-skeleton-card">
                    <div class="auth-skeleton-context"><?= clinic_skeleton_block('auth-skeleton-context-label') ?><?= clinic_skeleton_block('auth-skeleton-context-index') ?></div>
                    <div class="auth-skeleton-card-head">
                        <?= clinic_skeleton_block('auth-skeleton-kicker') ?>
                        <?= clinic_skeleton_block('auth-skeleton-title') ?>
                        <?= clinic_skeleton_block('auth-skeleton-description') ?>
                        <?= clinic_skeleton_block('auth-skeleton-description auth-skeleton-description-short') ?>
                    </div>
                    <div class="auth-skeleton-progress"><i></i><i></i><i></i></div>
                    <div class="auth-skeleton-fields">
                        <div class="auth-skeleton-field"><?= clinic_skeleton_block('auth-skeleton-label') ?><?= clinic_skeleton_block('auth-skeleton-input') ?></div>
                        <div class="auth-skeleton-field"><?= clinic_skeleton_block('auth-skeleton-label') ?><?= clinic_skeleton_block('auth-skeleton-input') ?></div>
                        <div class="auth-skeleton-field auth-skeleton-field-wide"><?= clinic_skeleton_block('auth-skeleton-label') ?><?= clinic_skeleton_block('auth-skeleton-input') ?></div>
                        <div class="auth-skeleton-field"><?= clinic_skeleton_block('auth-skeleton-label') ?><?= clinic_skeleton_block('auth-skeleton-input') ?></div>
                        <div class="auth-skeleton-field"><?= clinic_skeleton_block('auth-skeleton-label') ?><?= clinic_skeleton_block('auth-skeleton-input') ?></div>
                    </div>
                    <?= clinic_skeleton_block('auth-skeleton-submit') ?>
                    <div class="auth-skeleton-note"><?= clinic_skeleton_block('auth-skeleton-note-icon') ?><?= clinic_skeleton_block('auth-skeleton-note-copy') ?></div>
                </section>
            </div>
            <div class="auth-access-dock">
                <aside class="auth-dock-rail" aria-hidden="true">
                    <div class="auth-dock-rail-top"><span class="auth-dock-rail-kicker">SECURE ACCESS</span><span class="auth-dock-rail-index">01</span></div>
                    <div class="auth-dock-rail-mark"><?= icon('shield-check') ?></div>
                    <div class="auth-dock-rail-copy"><strong>Ruang<br>kerja klinik</strong><span>Alur layanan<br>yang terhubung</span></div>
                    <ol class="auth-dock-rail-steps">
                        <?php foreach ($auth_rail_steps as $step_index => $step_label): ?>
                            <li class="<?= $step_index === 0 ? 'is-active' : '' ?>"><i><?= e(str_pad((string) ($step_index + 1), 2, '0', STR_PAD_LEFT)) ?></i><span><?= e($step_label) ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="auth-dock-rail-footer"><span class="auth-dock-rail-live is-active" aria-hidden="true"></span><span>DATABASE<br><strong>ONLINE</strong></span></div>
                </aside>

                <section class="auth-card" aria-labelledby="auth-title">
                    <div class="auth-card-context"><span><?= icon('activity') ?> <span>Ruang kerja klinik</span></span><span>01</span></div>
                    <div class="auth-card-head">
                        <span class="auth-card-kicker"><?= e($auth_kicker) ?></span>
                        <h2 id="auth-title"><?= e($auth_title) ?></h2>
                        <p><?= e($auth_description) ?></p>
                    </div>

                    <?php foreach ($auth_flash_messages as $flash): ?>
                        <div class="auth-alert auth-alert-<?= e($flash['type']) ?>" role="status">
                            <?= icon($flash['type'] === 'success' ? 'check-circle-2' : 'alert-triangle') ?>
                            <span><?= e($flash['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
