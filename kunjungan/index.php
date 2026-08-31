<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Kunjungan';
$page_description = 'Atur antrean, status layanan, dan detail kunjungan pasien.';
$active_menu = 'kunjungan';
$page_action_html = page_action('Daftarkan kunjungan', 'kunjungan/tambah.php', 'calendar-days');

$q = query_value('q');
$status = query_value('status');
$tanggal = query_value('tanggal');
$conditions = array();
$params = array();
if ($q !== '') {
    $conditions[] = '(v.no_kunjungan LIKE ? OR p.nama LIKE ? OR d.nama_dokter LIKE ? OR pc.nama_poli LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search, $search);
}
if (in_array($status, array('Menunggu', 'Diperiksa', 'Selesai', 'Batal'), true)) {
    $conditions[] = 'v.status = ?';
    $params[] = $status;
}
if ($tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $conditions[] = 'v.tanggal_kunjungan = ?';
    $params[] = $tanggal;
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$visits = db_select_all("SELECT v.*, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter, e.diagnosa, pay.total, pay.status AS status_pembayaran
    FROM visits v
    INNER JOIN patients p ON p.id = v.patient_id
    INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id
    INNER JOIN doctors d ON d.id = v.doctor_id
    LEFT JOIN examinations e ON e.visit_id = v.id
    LEFT JOIN payments pay ON pay.visit_id = v.id
    $where ORDER BY v.tanggal_kunjungan DESC, v.nomor_antrian ASC", $params);

$today_count = (int) db_value('SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE()');
$waiting_count = (int) db_value("SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE() AND status = 'Menunggu'");
$exam_count = (int) db_value("SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE() AND status = 'Diperiksa'");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="visit-summary">
    <div class="summary-tile"><span>Hari ini</span><strong><?= e($today_count) ?> kunjungan</strong></div>
    <div class="summary-tile"><span>Menunggu</span><strong><?= e($waiting_count) ?> pasien</strong></div>
    <div class="summary-tile"><span>Sedang diperiksa</span><strong><?= e($exam_count) ?> pasien</strong></div>
</div>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('kunjungan/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari no. kunjungan, pasien, dokter, atau poli" aria-label="Cari kunjungan"></div><select class="filter-select" name="status" aria-label="Filter status kunjungan"><option value="">Semua status</option><option value="Menunggu" <?= $status === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option><option value="Diperiksa" <?= $status === 'Diperiksa' ? 'selected' : '' ?>>Diperiksa</option><option value="Selesai" <?= $status === 'Selesai' ? 'selected' : '' ?>>Selesai</option><option value="Batal" <?= $status === 'Batal' ? 'selected' : '' ?>>Batal</option></select><input class="filter-input" type="date" name="tanggal" value="<?= e($tanggal) ?>" aria-label="Filter tanggal"><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($visits)) ?> kunjungan</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar kunjungan</h2><p>Status membantu tim mengetahui langkah layanan berikutnya.</p></div><?php if ($q !== '' || $status !== '' || $tanggal !== ''): ?><a class="panel-link" href="<?= e(base_url('kunjungan/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($visits)): ?><?= render_empty_state('calendar-days', 'Belum ada kunjungan', 'Pendaftaran baru akan muncul di daftar ini.', page_action('Daftarkan kunjungan', 'kunjungan/tambah.php', 'calendar-days', 'button button-ghost')) ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Antrean</th><th>Pasien</th><th>Poli / dokter</th><th>Tanggal</th><th>Status layanan</th><th>Pembayaran</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($visits as $visit): ?><tr><td><div class="cell-primary">#<?= e($visit['nomor_antrian']) ?></div><div class="cell-muted mono"><?= e($visit['no_kunjungan']) ?></div></td><td><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?></div></div></div></td><td><div class="cell-primary"><?= e($visit['nama_poli']) ?></div><div class="cell-muted"><?= e($visit['nama_dokter']) ?></div></td><td><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></td><td><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></td><td><div class="cell-primary"><?= e(format_currency($visit['total'] ?: 0)) ?></div><div class="cell-muted"><?= e($visit['status_pembayaran'] ?: 'Belum dibuat') ?></div></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit['id'])) ?>" aria-label="Lihat detail kunjungan"><?= icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
