<?php
require_once __DIR__ . '/functions.php';

$page_title = $page_title ?? 'Dashboard';
$page_description = $page_description ?? 'Pantau operasional klinik dari satu tempat.';
$active_menu = $active_menu ?? 'dashboard';
$page_action_html = $page_action_html ?? '';
$app_brand_name = app_brand_name();
$flash_messages = pull_flash_messages();

$page_presets = array(
    'dashboard' => array('theme' => 'teal', 'eyebrow' => 'Ruang kendali klinik', 'hero_icon' => 'layout-dashboard', 'hero_tag' => 'Tinjauan harian', 'hero_note' => 'Data yang paling penting, lebih dekat.'),
    'kunjungan' => array('theme' => 'blue', 'eyebrow' => 'Alur layanan', 'hero_icon' => 'calendar-days', 'hero_tag' => 'Antrean terarah', 'hero_note' => 'Pendaftaran sampai pasien selesai.'),
    'pemeriksaan' => array('theme' => 'violet', 'eyebrow' => 'Catatan klinis', 'hero_icon' => 'stethoscope', 'hero_tag' => 'Konteks tetap utuh', 'hero_note' => 'Temuan dokter tercatat rapi.'),
    'resep' => array('theme' => 'orange', 'eyebrow' => 'Layanan farmasi', 'hero_icon' => 'clipboard-list', 'hero_tag' => 'Stok lebih aman', 'hero_note' => 'Pengeluaran obat terkontrol.'),
    'pembayaran' => array('theme' => 'green', 'eyebrow' => 'Administrasi klinik', 'hero_icon' => 'wallet', 'hero_tag' => 'Tagihan transparan', 'hero_note' => 'Komponen biaya mudah ditinjau.'),
    'riwayat' => array('theme' => 'indigo', 'eyebrow' => 'Jejak layanan', 'hero_icon' => 'file-clock', 'hero_tag' => 'Riwayat terhubung', 'hero_note' => 'Perjalanan pasien dalam satu tempat.'),
    'pasien' => array('theme' => 'cyan', 'eyebrow' => 'Data pasien', 'hero_icon' => 'users-round', 'hero_tag' => 'Rekam medis tertata', 'hero_note' => 'Identitas dan kunjungan tetap sinkron.'),
    'dokter' => array('theme' => 'purple', 'eyebrow' => 'Tim klinis', 'hero_icon' => 'user-round', 'hero_tag' => 'Tim siap melayani', 'hero_note' => 'Spesialisasi terlihat sejak awal.'),
    'poli' => array('theme' => 'rose', 'eyebrow' => 'Ruang layanan', 'hero_icon' => 'building-2', 'hero_tag' => 'Ruang lebih jelas', 'hero_note' => 'Ketersediaan poli tidak terlewat.'),
    'obat' => array('theme' => 'amber', 'eyebrow' => 'Persediaan farmasi', 'hero_icon' => 'pill', 'hero_tag' => 'Stok terkendali', 'hero_note' => 'Perhatian diberikan sebelum terlambat.'),
    'profil' => array('theme' => 'blue', 'eyebrow' => 'Identitas & keamanan', 'hero_icon' => 'user-round', 'hero_tag' => 'Akun pribadi', 'hero_note' => 'Profil dan kredensial selalu terkendali.'),
    'akun' => array('theme' => 'indigo', 'eyebrow' => 'Kontrol akses', 'hero_icon' => 'shield-check', 'hero_tag' => 'Akses sesuai peran', 'hero_note' => 'Setiap pengguna mendapat ruang kerja yang tepat.'),
    'arsip' => array('theme' => 'indigo', 'eyebrow' => 'Pusat pemulihan data', 'hero_icon' => 'archive', 'hero_tag' => 'Jejak tetap terlindungi', 'hero_note' => 'Data dapat dipulihkan tanpa memutus riwayat.'),
);
$page_preset = $page_presets[$active_menu] ?? $page_presets['dashboard'];
$active_user = current_user();
$active_profile_name = $active_user['nama_lengkap'] ?? 'Pengguna';
$active_profile_role = user_role_label($active_user);
$active_profile_initials = initials($active_profile_name);
$active_profile_schedule = user_schedule($active_user);
$visit_attention_count = (int) db_value("SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE() AND status IN ('Menunggu', 'Diperiksa')", array(), 0);

$navigation = array(
    'Ringkasan' => array(
        array('key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => 'dashboard/index.php'),
    ),
    'Operasional' => array(
        array('key' => 'kunjungan', 'label' => 'Kunjungan', 'icon' => 'calendar-days', 'url' => 'kunjungan/index.php'),
        array('key' => 'pemeriksaan', 'label' => 'Pemeriksaan', 'icon' => 'stethoscope', 'url' => 'pemeriksaan/index.php'),
        array('key' => 'resep', 'label' => 'Resep obat', 'icon' => 'clipboard-list', 'url' => 'resep/index.php'),
        array('key' => 'pembayaran', 'label' => 'Pembayaran', 'icon' => 'wallet', 'url' => 'pembayaran/index.php'),
        array('key' => 'riwayat', 'label' => 'Riwayat pasien', 'icon' => 'file-clock', 'url' => 'riwayat/index.php'),
    ),
    'Data master' => array(
        array('key' => 'pasien', 'label' => 'Pasien', 'icon' => 'users-round', 'url' => 'pasien/index.php'),
        array('key' => 'dokter', 'label' => 'Dokter', 'icon' => 'user-round', 'url' => 'dokter/index.php'),
        array('key' => 'poli', 'label' => 'Poli', 'icon' => 'building-2', 'url' => 'poli/index.php'),
        array('key' => 'obat', 'label' => 'Obat', 'icon' => 'pill', 'url' => 'obat/index.php'),
    ),
    'Sistem' => array(
        array('key' => 'akun', 'label' => 'Manajemen akun', 'icon' => 'shield-check', 'url' => 'akun/index.php'),
        array('key' => 'arsip', 'label' => 'Arsip data', 'icon' => 'archive', 'url' => 'arsip/index.php'),
    ),
);

foreach ($navigation as $section_label => $items) {
    $navigation[$section_label] = array_values(array_filter($items, function ($item) use ($active_user) {
        return can_access_module($item['key'], $active_user['role'] ?? '');
    }));
    if (empty($navigation[$section_label])) {
        unset($navigation[$section_label]);
    }
}
$page_skeleton_context = get_defined_vars();
$page_skeleton_html = render_page_skeleton($active_menu, current_route_key(), $page_skeleton_context);
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b172a">
    <title><?= e($page_title) ?> · <?= e($app_brand_name) ?></title>
    <script>document.documentElement.classList.add('has-page-loader');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css?v=20260828-7')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/clinic-theme.css?v=20260901-8')) ?>">
</head>
<body class="app-theme-<?= e($page_preset['theme']) ?> page-is-loading" data-page-key="<?= e($active_menu) ?>" data-page-route="<?= e(current_route_key()) ?>">
<script>
    (function () {
        try {
            if (window.sessionStorage.getItem('ruang-sehat-archive-filter-transition') === '1') {
                window.sessionStorage.removeItem('ruang-sehat-archive-filter-transition');
                document.body.setAttribute('data-skip-page-loader', '1');
                document.body.classList.remove('page-is-loading');
            }
        } catch (error) {
            // Session storage is optional; the normal page loader remains the fallback.
        }
    }());
</script>
<div class="app-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <aside class="sidebar sidebar-loading-skeleton" aria-hidden="true">
        <div class="sidebar-skeleton-brand">
            <span class="loading-skeleton sidebar-skeleton-mark"></span>
            <div class="sidebar-skeleton-brand-copy">
                <span class="loading-skeleton sidebar-skeleton-brand-name"></span>
                <span class="loading-skeleton sidebar-skeleton-brand-caption"></span>
            </div>
        </div>

        <span class="loading-skeleton sidebar-skeleton-menu-label"></span>
        <div class="sidebar-skeleton-nav">
            <?php foreach ($navigation as $section_label => $items): ?>
                <div class="sidebar-skeleton-section">
                    <span class="loading-skeleton sidebar-skeleton-section-label"></span>
                    <?php foreach ($items as $item): ?>
                        <div class="sidebar-skeleton-nav-item <?= $active_menu === $item['key'] ? 'is-active' : '' ?>">
                            <span class="loading-skeleton sidebar-skeleton-nav-icon"></span>
                            <span class="loading-skeleton sidebar-skeleton-nav-copy"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-skeleton-bottom">
            <div class="sidebar-skeleton-help">
                <span class="loading-skeleton sidebar-skeleton-help-icon"></span>
                <span class="loading-skeleton sidebar-skeleton-help-title"></span>
                <span class="loading-skeleton sidebar-skeleton-help-copy sidebar-skeleton-help-copy-wide"></span>
                <span class="loading-skeleton sidebar-skeleton-help-copy sidebar-skeleton-help-copy-short"></span>
            </div>
            <div class="sidebar-skeleton-user">
                <span class="loading-skeleton sidebar-skeleton-user-avatar"></span>
                <div class="sidebar-skeleton-user-copy">
                    <span class="loading-skeleton sidebar-skeleton-user-name"></span>
                    <span class="loading-skeleton sidebar-skeleton-user-role"></span>
                </div>
            </div>
            <div class="sidebar-skeleton-logout">
                <span class="loading-skeleton sidebar-skeleton-logout-icon"></span>
                <span class="loading-skeleton sidebar-skeleton-logout-copy"></span>
            </div>
        </div>
    </aside>
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <a class="brand-link" href="<?= e(base_url('dashboard/index.php')) ?>" aria-label="Buka dashboard <?= e($app_brand_name) ?>">
                <img class="brand-mark brand-logo" src="<?= e(base_url('assets/images/medikaflow-logo-mark-v2.webp?v=20260831-2')) ?>" alt="">
                <div>
                    <div class="brand-name"><?= e($app_brand_name) ?></div>
                    <div class="brand-caption">Manajemen Klinik</div>
                </div>
            </a>
            <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Tutup navigasi">
                <?= icon('x') ?>
            </button>
        </div>

        <div class="sidebar-label">Menu utama</div>
        <nav class="sidebar-nav" aria-label="Navigasi utama">
            <?php foreach ($navigation as $section_label => $items): ?>
                <div class="nav-section">
                    <div class="nav-section-label"><?= e($section_label) ?></div>
                    <?php foreach ($items as $item): ?>
                        <?php $has_visit_attention = $item['key'] === 'kunjungan' && $visit_attention_count > 0; ?>
                        <a class="nav-item nav-item-<?= e($item['key']) ?> <?= $active_menu === $item['key'] ? 'is-active' : '' ?>" href="<?= e(base_url($item['url'])) ?>"<?= $active_menu === $item['key'] ? ' aria-current="page"' : '' ?> aria-label="<?= e($has_visit_attention ? $item['label'] . ', ada antrean aktif hari ini' : $item['label']) ?>" title="<?= e($item['label']) ?>" data-tooltip="<?= e($item['label']) ?>">
                            <?= icon($item['icon']) ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if ($has_visit_attention): ?><span class="nav-dot is-active" aria-hidden="true"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <div class="help-card-icon"><?= icon('shield-check') ?></div>
                <strong>Data tetap rapi</strong>
                <p>Setiap transaksi tercatat dan saling terhubung.</p>
            </div>
            <div class="sidebar-user">
                <div class="avatar avatar-small"><?= user_avatar_content($active_user) ?></div>
                <div class="sidebar-user-copy">
                    <strong><?= e($active_profile_name) ?></strong>
                    <span><?= e($active_profile_role) ?></span>
                </div>
            </div>
            <a class="sidebar-logout" href="<?= e(base_url('auth/logout.php?csrf_token=' . rawurlencode(csrf_token()))) ?>" data-logout data-confirm-message="Keluar dari sesi kerja sekarang?" aria-label="Logout" title="Logout">
                <?= icon('log-out') ?><span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-button menu-toggle" type="button" data-sidebar-open aria-label="Buka navigasi">
                    <?= icon('menu') ?>
                </button>
                <button class="icon-button sidebar-expand-toggle" type="button" data-sidebar-expand aria-label="Perluas navigasi">
                    <?= icon('menu') ?>
                </button>
                <div class="breadcrumb">
                    <span><?= e($app_brand_name) ?></span>
                    <?= icon('chevron-right') ?>
                    <strong><?= e($page_title) ?></strong>
                </div>
            </div>
            <div class="topbar-right">
                <div class="today-chip">
                    <?= icon('calendar-days') ?>
                    <span><?= e(format_date_long_id(date('Y-m-d'))) ?></span>
                </div>
                <div class="account-menu-shell">
                    <button class="avatar avatar-top account-toggle" type="button" data-account-toggle="topbar-account-menu" aria-expanded="false" aria-controls="topbar-account-menu" aria-label="Buka menu akun"><?= user_avatar_content($active_user) ?></button>
                    <div class="account-menu account-menu-topbar" id="topbar-account-menu" data-account-menu hidden>
                        <div class="account-menu-head">
                            <div class="avatar avatar-small"><?= user_avatar_content($active_user) ?></div>
                            <div><strong><?= e($active_profile_name) ?></strong><span><?= e($active_profile_role) ?></span></div>
                        </div>
                        <div class="account-menu-shift">
                            <span><?= icon('clock-3') ?><span><?= e($active_profile_schedule['label']) ?></span></span>
                            <strong><?= e($active_profile_schedule['hours']) ?></strong>
                            <small><?= e($active_profile_schedule['days']) ?></small>
                        </div>
                        <a href="<?= e(base_url('profil/index.php')) ?>"><?= icon('user-round') ?><span>Profil &amp; keamanan</span><?= icon('chevron-right') ?></a>
                        <?php if (($active_user['role'] ?? '') === 'Admin'): ?><a href="<?= e(base_url('akun/index.php')) ?>"><?= icon('shield-check') ?><span>Manajemen akun</span><?= icon('chevron-right') ?></a><?php endif; ?>
                        <a class="account-logout" href="<?= e(base_url('auth/logout.php?csrf_token=' . rawurlencode(csrf_token()))) ?>" data-logout data-confirm-message="Keluar dari sesi kerja sekarang?"><?= icon('log-out') ?><span>Logout</span></a>
                        <button type="button" data-account-close><?= icon('x') ?><span>Tutup menu</span></button>
                    </div>
                </div>
            </div>
        </header>

        <main class="page-content" data-page-content aria-busy="true">
            <?= $page_skeleton_html ?>
            <div class="page-heading <?= $active_menu === 'dashboard' ? 'page-heading-dashboard' : '' ?>">
                <div class="page-hero">
                    <div class="page-hero-copy">
                        <div class="eyebrow"><?= e($page_preset['eyebrow']) ?></div>
                        <h1><?= e($page_title) ?></h1>
                        <p><?= e($page_description) ?></p>
                    </div>
                    <div class="page-hero-art <?= $active_menu === 'dashboard' ? 'dashboard-hero-art' : '' ?>" aria-hidden="true">
                        <?php if ($active_menu === 'dashboard'): ?>
                            <div class="dashboard-hero-media">
                                <img src="<?= e(base_url('assets/images/medikaflow-dashboard-hero-v4.png?v=20260901-1')) ?>" alt="" width="2172" height="724" loading="eager" fetchpriority="high" decoding="async">
                                <span class="dashboard-hero-blur dashboard-hero-blur-left"></span>
                                <span class="dashboard-hero-blur dashboard-hero-blur-mid"></span>
                                <span class="dashboard-hero-blur dashboard-hero-blur-near"></span>
                                <span class="dashboard-hero-scrim"></span>
                                <span class="dashboard-hero-live"><i></i><span>Workspace terhubung</span></span>
                            </div>
                        <?php else: ?>
                        <span class="hero-visual">
                            <span class="hero-visual-back"></span>
                            <span class="hero-visual-card">
                                <span class="hero-visual-toolbar"><i></i><i></i><i></i></span>
                                <span class="hero-visual-title"></span>
                                <span class="hero-visual-chart"><i class="hero-bar-one"></i><i class="hero-bar-two"></i><i class="hero-bar-three"></i><i class="hero-bar-four"></i><i class="hero-bar-five"></i></span>
                                <span class="hero-visual-foot"><i></i><i></i></span>
                            </span>
                            <span class="hero-visual-badge"><?= icon($page_preset['hero_icon']) ?></span>
                            <span class="hero-visual-status"><i></i><span></span></span>
                        </span>
                        <span class="hero-art-copy"><strong><?= e($page_preset['hero_tag']) ?></strong><small><?= e($page_preset['hero_note']) ?></small></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($page_action_html !== ''): ?>
                    <div class="page-heading-action"><?= $page_action_html ?></div>
                <?php endif; ?>
            </div>

            <?php foreach ($flash_messages as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="status">
                    <?= icon($flash['type'] === 'success' ? 'check-circle-2' : 'alert-triangle') ?>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert-close" data-dismiss-alert aria-label="Tutup notifikasi"><?= icon('x') ?></button>
                </div>
            <?php endforeach; ?>
