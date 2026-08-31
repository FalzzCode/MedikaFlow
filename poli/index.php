<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Data poli';
$page_description = 'Atur ruang layanan, lokasi, dan ketersediaan poli klinik.';
$active_menu = 'poli';
$page_action_html = page_action('Tambah poli', 'poli/tambah.php', 'plus');

$q = query_value('q');
$status = query_value('status');
$conditions = array('p.archived_at IS NULL');
$params = array();
if ($q !== '') {
    $conditions[] = '(kode_poli LIKE ? OR nama_poli LIKE ? OR lokasi LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if (in_array($status, array('Aktif', 'Nonaktif'), true)) {
    $conditions[] = 'p.status = ?';
    $params[] = $status;
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$polyclinics = db_select_all("SELECT p.* FROM polyclinics p $where ORDER BY p.status = 'Aktif' DESC, p.nama_poli", $params);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('poli/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama poli, atau lokasi" aria-label="Cari poli"></div><select class="filter-select" name="status" aria-label="Filter status poli"><option value="">Semua status</option><option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Nonaktif" <?= $status === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($polyclinics)) ?> poli</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar poli</h2><p>Poli aktif dapat dipilih pada pendaftaran kunjungan. Data yang diarsipkan tersedia di menu Arsip.</p></div><?php if ($q !== '' || $status !== ''): ?><a class="panel-link" href="<?= e(base_url('poli/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($polyclinics)): ?><?= render_empty_state('building-2', 'Poli belum ditemukan', 'Coba ubah filter atau tambahkan poli baru.', page_action('Tambah poli', 'poli/tambah.php', 'plus', 'button button-ghost')) ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Poli</th><th>Lokasi</th><th>Keterangan</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($polyclinics as $polyclinic): ?><tr><td><div class="cell-primary"><?= e($polyclinic['nama_poli']) ?></div><div class="cell-muted mono"><?= e($polyclinic['kode_poli']) ?></div></td><td><div class="cell-primary"><?= e($polyclinic['lokasi']) ?></div></td><td><?= e($polyclinic['keterangan'] ?: '-') ?></td><td><span class="<?= e(status_class($polyclinic['status'])) ?>"><?= status_icon($polyclinic['status']) ?><?= e($polyclinic['status']) ?></span></td><td><div class="action-cell"><a class="table-action" href="<?= e(base_url('poli/edit.php?id=' . $polyclinic['id'])) ?>" aria-label="Edit poli"><?= icon('edit-3') ?></a><form class="inline-form" method="post" action="<?= e(base_url('poli/hapus.php')) ?>" data-confirm="Arsipkan data poli ini? Data tetap tersimpan dan dapat dipulihkan dari menu Arsip."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($polyclinic['id']) ?>"><button class="table-action is-archive" type="submit" aria-label="Arsipkan poli" title="Arsipkan poli"><?= icon('archive') ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
