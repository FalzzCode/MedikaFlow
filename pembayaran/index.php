<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Pembayaran';
$page_description = 'Hitung biaya layanan dan catat status pelunasan pasien.';
$active_menu = 'pembayaran';
$page_action_html = page_action('Lihat kunjungan', 'kunjungan/index.php', 'calendar-days', 'button button-secondary');

$q = query_value('q');
$status = query_value('status');
$tanggal = query_value('tanggal');
$conditions = array();
$params = array();
if ($q !== '') {
    $conditions[] = '(v.no_kunjungan LIKE ? OR p.nama LIKE ? OR p.no_rm LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if (in_array($status, array('Belum Dibayar', 'Sudah Dibayar'), true)) {
    $conditions[] = 'pay.status = ?';
    $params[] = $status;
}
if ($tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $conditions[] = 'v.tanggal_kunjungan = ?';
    $params[] = $tanggal;
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$payments = db_select_all("SELECT pay.*, v.id AS visit_id, v.no_kunjungan, v.tanggal_kunjungan, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, v.status AS status_kunjungan
    FROM payments pay INNER JOIN visits v ON v.id = pay.visit_id INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id $where ORDER BY pay.status = 'Belum Dibayar' DESC, v.tanggal_kunjungan DESC", $params);
$unpaid_total = (float) db_value("SELECT COALESCE(SUM(total), 0) FROM payments WHERE status = 'Belum Dibayar'");
$paid_today = (float) db_value("SELECT COALESCE(SUM(total), 0) FROM payments WHERE status = 'Sudah Dibayar' AND DATE(dibayar_pada) = CURDATE()");
$unpaid_count = (int) db_value("SELECT COUNT(*) FROM payments WHERE status = 'Belum Dibayar'");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="visit-summary"><div class="summary-tile"><span>Belum dibayar</span><strong><?= e($unpaid_count) ?> transaksi</strong></div><div class="summary-tile"><span>Piutang berjalan</span><strong><?= e(format_currency($unpaid_total)) ?></strong></div><div class="summary-tile"><span>Masuk hari ini</span><strong><?= e(format_currency($paid_today)) ?></strong></div></div>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('pembayaran/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari no. kunjungan, nama, atau no. rekam medis" aria-label="Cari pembayaran"></div><select class="filter-select" name="status" aria-label="Filter status pembayaran"><option value="">Semua pembayaran</option><option value="Belum Dibayar" <?= $status === 'Belum Dibayar' ? 'selected' : '' ?>>Belum Dibayar</option><option value="Sudah Dibayar" <?= $status === 'Sudah Dibayar' ? 'selected' : '' ?>>Sudah Dibayar</option></select><input class="filter-input" type="date" name="tanggal" value="<?= e($tanggal) ?>" aria-label="Filter tanggal"><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($payments)) ?> transaksi</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar pembayaran</h2><p>Komponen total terdiri dari pemeriksaan, tindakan, dan obat.</p></div><?php if ($q !== '' || $status !== '' || $tanggal !== ''): ?><a class="panel-link" href="<?= e(base_url('pembayaran/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($payments)): ?><?= render_empty_state('wallet', 'Belum ada transaksi', 'Pembayaran dari setiap kunjungan akan muncul di sini.') ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Pasien</th><th>Kunjungan</th><th>Poli</th><th>Total</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><div class="patient-cell"><span class="patient-initial"><?= e(initials($payment['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($payment['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($payment['no_rm']) ?></div></div></div></td><td><div class="cell-primary mono"><?= e($payment['no_kunjungan']) ?></div><div class="cell-muted"><?= e(format_date_id($payment['tanggal_kunjungan'])) ?></div></td><td><?= e($payment['nama_poli']) ?></td><td><div class="cell-primary"><?= e(format_currency($payment['total'])) ?></div><div class="cell-muted">Obat <?= e(format_currency($payment['total_obat'])) ?></div></td><td><span class="<?= e(status_class($payment['status'])) ?>"><?= status_icon($payment['status']) ?><?= e($payment['status']) ?></span></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('pembayaran/detail.php?visit_id=' . $payment['visit_id'])) ?>" aria-label="Detail pembayaran"><?= icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
