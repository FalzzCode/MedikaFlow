<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Data obat';
$page_description = 'Pantau stok, harga, kategori, dan masa berlaku obat.';
$active_menu = 'obat';
$page_action_html = page_action('Tambah obat', 'obat/tambah.php', 'plus');

$q = query_value('q');
$stock_filter = query_value('stok');
$conditions = array('m.archived_at IS NULL');
$params = array();
if ($q !== '') {
    $conditions[] = '(m.kode_obat LIKE ? OR m.nama_obat LIKE ? OR mc.nama_kategori LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if ($stock_filter === 'low') {
    $conditions[] = 'm.stok <= 10 AND m.tanggal_expired >= CURDATE()';
} elseif ($stock_filter === 'expired') {
    $conditions[] = 'm.tanggal_expired < CURDATE()';
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$medicines = db_select_all("SELECT m.*, mc.nama_kategori FROM medicines m INNER JOIN medicine_categories mc ON mc.id = m.category_id $where ORDER BY (m.tanggal_expired < CURDATE()) DESC, m.stok ASC, m.nama_obat", $params);
$low_stock_count = (int) db_value("SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL AND status = 'Aktif' AND stok <= 10 AND tanggal_expired >= CURDATE()");
$expired_count = (int) db_value("SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL AND status = 'Aktif' AND tanggal_expired < CURDATE()");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('obat/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama obat, atau kategori" aria-label="Cari obat"></div><select class="filter-select" name="stok" aria-label="Filter kondisi obat"><option value="">Semua kondisi</option><option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Stok menipis</option><option value="expired" <?= $stock_filter === 'expired' ? 'selected' : '' ?>>Sudah expired</option></select><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($medicines)) ?> obat · <?= e($low_stock_count) ?> stok menipis</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar obat</h2><p>Batas stok menipis ditandai saat jumlah tersisa 10 atau kurang. Data yang diarsipkan tersedia di menu Arsip.</p></div><?php if ($q !== '' || $stock_filter !== ''): ?><a class="panel-link" href="<?= e(base_url('obat/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if ($expired_count > 0): ?><div style="padding: 0 20px 16px;"><div class="alert alert-danger" style="margin: 0;"><?= icon('alert-triangle') ?><span>Ada <strong><?= e($expired_count) ?> obat expired</strong>. Pisahkan dari stok yang dapat digunakan.</span></div></div><?php endif; ?><?php if (empty($medicines)): ?><?= render_empty_state('pill', 'Obat belum ditemukan', 'Coba ubah filter atau tambahkan obat baru.', page_action('Tambah obat', 'obat/tambah.php', 'plus', 'button button-ghost')) ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Obat</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Expired</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($medicines as $medicine): ?><?php $is_expired = strtotime($medicine['tanggal_expired']) < strtotime(date('Y-m-d')); $is_low = (int) $medicine['stok'] <= 10 && !$is_expired; ?><tr><td><div class="cell-primary"><?= e($medicine['nama_obat']) ?></div><div class="cell-muted mono"><?= e($medicine['kode_obat']) ?> · <?= e($medicine['satuan']) ?></div></td><td><?= e($medicine['nama_kategori']) ?></td><td><?= e(format_currency($medicine['harga'])) ?></td><td><div class="cell-primary"><?= e($medicine['stok']) ?></div><?php if ($is_low): ?><span class="stock-warning"><?= icon('alert-triangle') ?> Menipis</span><?php endif; ?></td><td><div class="cell-primary <?= $is_expired ? 'stock-warning is-expired' : '' ?>"><?= e(format_date_id($medicine['tanggal_expired'])) ?></div><?php if ($is_expired): ?><span class="stock-warning is-expired"><?= icon('x') ?> Expired</span><?php endif; ?></td><td><span class="<?= e(status_class($medicine['status'])) ?>"><?= status_icon($medicine['status']) ?><?= e($medicine['status']) ?></span></td><td><div class="action-cell"><a class="table-action" href="<?= e(base_url('obat/edit.php?id=' . $medicine['id'])) ?>" aria-label="Edit obat"><?= icon('edit-3') ?></a><form class="inline-form" method="post" action="<?= e(base_url('obat/hapus.php')) ?>" data-confirm="Arsipkan data obat ini? Data tetap tersimpan dan dapat dipulihkan dari menu Arsip."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($medicine['id']) ?>"><button class="table-action is-archive" type="submit" aria-label="Arsipkan obat" title="Arsipkan obat"><?= icon('archive') ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
