<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('Admin');

$page_title = 'Manajemen akun';
$page_description = 'Kelola akses Admin, Dokter, dan Petugas tanpa kredensial bawaan.';
$active_menu = 'akun';
$page_action_html = page_action('Tambah akun', 'akun/tambah.php', 'user-plus');

$q = query_value('q');
$role = query_value('role');
$status = query_value('status');
$conditions = array();
$params = array();

if ($q !== '') {
    $search = '%' . $q . '%';
    $conditions[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR d.nama_dokter LIKE ?)';
    $params = array($search, $search, $search, $search);
}
if (in_array($role, array('Admin', 'Dokter', 'Petugas'), true)) {
    $conditions[] = 'u.role = ?';
    $params[] = $role;
}
if (in_array($status, array('Aktif', 'Nonaktif'), true)) {
    $conditions[] = 'u.status = ?';
    $params[] = $status;
}

$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$accounts = db_select_all("SELECT u.*, d.nama_dokter, d.kode_dokter, s.nama AS spesialisasi
    FROM users u
    LEFT JOIN doctors d ON d.id = u.doctor_id
    LEFT JOIN specializations s ON s.id = d.specialization_id
    $where
    ORDER BY u.status = 'Aktif' DESC, FIELD(u.role, 'Admin', 'Dokter', 'Petugas'), u.nama_lengkap", $params);

$account_summary = db_select_one("SELECT COUNT(*) AS total,
    SUM(role = 'Admin' AND status = 'Aktif') AS admins,
    SUM(role = 'Dokter' AND status = 'Aktif') AS doctors,
    SUM(role = 'Petugas' AND status = 'Aktif') AS staff
    FROM users");

require_once __DIR__ . '/../includes/header.php';
?>
<section class="account-summary-grid" aria-label="Ringkasan akun">
    <div class="account-summary-card"><span>Total akun</span><strong><?= e($account_summary['total'] ?? 0) ?></strong><small>Tersimpan di database</small></div>
    <div class="account-summary-card is-admin"><span>Admin aktif</span><strong><?= e($account_summary['admins'] ?? 0) ?></strong><small>Akses seluruh modul</small></div>
    <div class="account-summary-card is-doctor"><span>Dokter aktif</span><strong><?= e($account_summary['doctors'] ?? 0) ?></strong><small>Layanan klinis</small></div>
    <div class="account-summary-card is-staff"><span>Petugas aktif</span><strong><?= e($account_summary['staff'] ?? 0) ?></strong><small>Administrasi layanan</small></div>
</section>

<div class="page-toolbar">
    <form class="toolbar-filters" method="get" action="<?= e(base_url('akun/index.php')) ?>">
        <div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, username, email, atau dokter" aria-label="Cari akun"></div>
        <select class="filter-select" name="role" aria-label="Filter peran"><option value="">Semua peran</option><option value="Admin" <?= $role === 'Admin' ? 'selected' : '' ?>>Admin</option><option value="Dokter" <?= $role === 'Dokter' ? 'selected' : '' ?>>Dokter</option><option value="Petugas" <?= $role === 'Petugas' ? 'selected' : '' ?>>Petugas</option></select>
        <select class="filter-select" name="status" aria-label="Filter status"><option value="">Semua status</option><option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Nonaktif" <?= $status === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select>
        <button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button>
    </form>
    <span class="toolbar-meta"><?= e(count($accounts)) ?> akun</span>
</div>

<section class="panel">
    <div class="panel-header"><div><h2>Daftar pengguna</h2><p>Password tidak pernah ditampilkan. Akun nonaktif tidak dapat login.</p></div><?php if ($q !== '' || $role !== '' || $status !== ''): ?><a class="panel-link" href="<?= e(base_url('akun/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div>
    <?php if (empty($accounts)): ?>
        <?= render_empty_state('users-round', 'Akun belum ditemukan', 'Ubah filter atau tambahkan pengguna baru.', page_action('Tambah akun', 'akun/tambah.php', 'user-plus', 'button button-ghost')) ?>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Pengguna</th><th>Peran</th><th>Profil terhubung</th><th>Login terakhir</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody>
        <?php foreach ($accounts as $account): ?>
            <tr>
                <td><div class="patient-cell"><span class="patient-initial"><?= user_avatar_content($account) ?></span><div><div class="cell-primary"><?= e($account['nama_lengkap']) ?></div><div class="cell-muted"><?= e($account['username']) ?> · <?= e($account['email']) ?></div></div></div></td>
                <td><span class="role-pill role-<?= e(strtolower($account['role'])) ?>"><?= icon($account['role'] === 'Admin' ? 'shield-check' : ($account['role'] === 'Dokter' ? 'stethoscope' : 'badge-check')) ?><?= e($account['role']) ?></span></td>
                <td><?php if ($account['role'] === 'Dokter' && $account['nama_dokter']): ?><div class="cell-primary"><?= e($account['nama_dokter']) ?></div><div class="cell-muted"><?= e($account['spesialisasi']) ?> · <?= e($account['kode_dokter']) ?></div><?php else: ?><span class="cell-muted">Tidak memerlukan profil dokter</span><?php endif; ?></td>
                <td><div class="cell-primary"><?= $account['last_login_at'] ? e(format_date_id($account['last_login_at'], true)) : 'Belum pernah' ?></div><div class="cell-muted">Dibuat <?= e(format_date_id($account['created_at'])) ?></div></td>
                <td><span class="<?= e(status_class($account['status'])) ?>"><?= status_icon($account['status']) ?><?= e($account['status']) ?></span></td>
                <td class="action-cell"><a class="table-action" href="<?= e(base_url('akun/edit.php?id=' . $account['id'])) ?>" aria-label="Edit akun <?= e($account['nama_lengkap']) ?>"><?= icon('edit-3') ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
