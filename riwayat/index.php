<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Riwayat pasien';
$page_description = 'Telusuri perjalanan layanan pasien dari kunjungan yang sudah tercatat.';
$active_menu = 'riwayat';
$page_action_html = page_action('Daftarkan kunjungan', 'kunjungan/tambah.php', 'calendar-days');

$q = query_value('q');
$from_date = query_value('dari');
$to_date = query_value('sampai');
$conditions = array();
$params = array();
if ($q !== '') {
    $conditions[] = '(p.nama LIKE ? OR p.no_rm LIKE ? OR v.no_kunjungan LIKE ? OR d.nama_dokter LIKE ? OR pc.nama_poli LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search, $search, $search);
}
if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $conditions[] = 'v.tanggal_kunjungan >= ?';
    $params[] = $from_date;
}
if ($to_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $conditions[] = 'v.tanggal_kunjungan <= ?';
    $params[] = $to_date;
}
$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$visits = db_select_all("SELECT v.id, v.no_kunjungan, v.tanggal_kunjungan, v.status, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter, e.diagnosa, e.tindakan, pay.total, pay.status AS status_pembayaran
    FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id LEFT JOIN examinations e ON e.visit_id = v.id LEFT JOIN payments pay ON pay.visit_id = v.id
    $where ORDER BY v.tanggal_kunjungan DESC, v.nomor_antrian DESC", $params);
$completed_count = (int) db_value("SELECT COUNT(*) FROM visits WHERE status = 'Selesai'");
$all_history_count = (int) db_value('SELECT COUNT(*) FROM visits');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="visit-summary"><div class="summary-tile"><span>Total riwayat</span><strong><?= e($all_history_count) ?> kunjungan</strong></div><div class="summary-tile"><span>Layanan selesai</span><strong><?= e($completed_count) ?> kunjungan</strong></div><div class="summary-tile"><span>Hasil ditampilkan</span><strong><?= e(count($visits)) ?> kunjungan</strong></div></div>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('riwayat/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, no. RM, no. kunjungan, dokter, atau poli" aria-label="Cari riwayat pasien"></div><input class="filter-input" type="date" name="dari" value="<?= e($from_date) ?>" aria-label="Tanggal mulai"><input class="filter-input" type="date" name="sampai" value="<?= e($to_date) ?>" aria-label="Tanggal sampai"><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($visits)) ?> riwayat</span></div>
<section class="panel"><div class="panel-header"><div><h2>Jejak kunjungan</h2><p>Diagnosa, tindakan, dan total pembayaran tersimpan dalam satu alur.</p></div><?php if ($q !== '' || $from_date !== '' || $to_date !== ''): ?><a class="panel-link" href="<?= e(base_url('riwayat/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($visits)): ?><?= render_empty_state('file-clock', 'Riwayat belum ditemukan', 'Coba ubah kata kunci atau rentang tanggal yang dipilih.') ?><?php else: ?><div class="table-wrap"><table class="data-table history-table"><thead><tr><th>Tanggal</th><th>Pasien</th><th>Poli / dokter</th><th>Diagnosa</th><th>Tindakan</th><th>Total pembayaran</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($visits as $visit): ?><tr><td><div class="cell-primary"><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></div><div class="cell-muted mono"><?= e($visit['no_kunjungan']) ?></div></td><td><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?></div></div></div></td><td><div class="cell-primary"><?= e($visit['nama_poli']) ?></div><div class="cell-muted"><?= e($visit['nama_dokter']) ?></div></td><td><?= e($visit['diagnosa'] ?: 'Belum diperiksa') ?></td><td><?= e($visit['tindakan'] ?: '-') ?></td><td><div class="cell-primary"><?= e(format_currency($visit['total'] ?: 0)) ?></div><div class="cell-muted"><?= e($visit['status_pembayaran'] ?: 'Belum dibuat') ?></div></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit['id'])) ?>" aria-label="Buka detail kunjungan"><?= icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
