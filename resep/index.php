<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Resep obat';
$page_description = 'Kelola resep, aturan minum, dan ketersediaan obat pasien.';
$active_menu = 'resep';
$page_action_html = page_action('Buat resep', 'resep/tambah.php', 'plus');

$q = query_value('q');
$status = query_value('status');
$conditions = array();
$params = array();
if ($q !== '') {
    $conditions[] = '(pr.no_resep LIKE ? OR p.nama LIKE ? OR v.no_kunjungan LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if (in_array($status, array('Draft', 'Diselesaikan', 'Dibatalkan'), true)) {
    $conditions[] = 'pr.status = ?';
    $params[] = $status;
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$prescriptions = db_select_all("SELECT pr.id, pr.no_resep, pr.status, pr.created_at, pr.selesai_pada, v.no_kunjungan, v.tanggal_kunjungan, p.nama AS nama_pasien, p.no_rm, COUNT(pd.id) AS item_count, COALESCE(SUM(pd.jumlah * pd.harga_satuan), 0) AS total_obat
    FROM prescriptions pr INNER JOIN visits v ON v.id = pr.visit_id INNER JOIN patients p ON p.id = v.patient_id LEFT JOIN prescription_details pd ON pd.prescription_id = pr.id
    $where GROUP BY pr.id ORDER BY pr.created_at DESC", $params);
$draft_count = (int) db_value("SELECT COUNT(*) FROM prescriptions WHERE status = 'Draft'");
$completed_count = (int) db_value("SELECT COUNT(*) FROM prescriptions WHERE status = 'Diselesaikan' AND DATE(selesai_pada) = CURDATE()");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="visit-summary"><div class="summary-tile"><span>Menunggu konfirmasi</span><strong><?= e($draft_count) ?> draft</strong></div><div class="summary-tile"><span>Selesai hari ini</span><strong><?= e($completed_count) ?> resep</strong></div><div class="summary-tile"><span>Total ditampilkan</span><strong><?= e(count($prescriptions)) ?> resep</strong></div></div>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('resep/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari no. resep, pasien, atau no. kunjungan" aria-label="Cari resep"></div><select class="filter-select" name="status" aria-label="Filter status resep"><option value="">Semua status</option><option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option><option value="Diselesaikan" <?= $status === 'Diselesaikan' ? 'selected' : '' ?>>Diselesaikan</option><option value="Dibatalkan" <?= $status === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option></select><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($prescriptions)) ?> resep</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar resep</h2><p>Stok dipotong satu kali saat resep berstatus diselesaikan.</p></div><?php if ($q !== '' || $status !== ''): ?><a class="panel-link" href="<?= e(base_url('resep/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($prescriptions)): ?><?= render_empty_state('clipboard-list', 'Belum ada resep', 'Resep yang dibuat dari detail kunjungan akan muncul di sini.', page_action('Buat resep', 'resep/tambah.php', 'plus', 'button button-ghost')) ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>No. resep</th><th>Pasien</th><th>Kunjungan</th><th>Item</th><th>Total obat</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($prescriptions as $prescription): ?><tr><td class="mono"><?= e($prescription['no_resep']) ?><div class="cell-muted"><?= e(format_date_id($prescription['created_at'])) ?></div></td><td><div class="cell-primary"><?= e($prescription['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($prescription['no_rm']) ?></div></td><td class="mono"><?= e($prescription['no_kunjungan']) ?></td><td><?= e($prescription['item_count']) ?> obat</td><td><?= e(format_currency($prescription['total_obat'])) ?></td><td><span class="<?= e(status_class($prescription['status'])) ?>"><?= status_icon($prescription['status']) ?><?= e($prescription['status']) ?></span></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('resep/detail.php?id=' . $prescription['id'])) ?>" aria-label="Detail resep"><?= icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
