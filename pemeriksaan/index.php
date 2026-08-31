<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Pemeriksaan';
$page_description = 'Catat hasil pemeriksaan dokter tanpa kehilangan konteks kunjungan.';
$active_menu = 'pemeriksaan';
$page_action_html = page_action('Lihat kunjungan', 'kunjungan/index.php', 'calendar-days', 'button button-secondary');

$q = query_value('q');
$status = query_value('status');
$conditions = array("v.status <> 'Batal'");
$params = array();
if ($q !== '') {
    $conditions[] = '(v.no_kunjungan LIKE ? OR p.nama LIKE ? OR d.nama_dokter LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if (in_array($status, array('Menunggu', 'Diperiksa', 'Selesai'), true)) {
    $conditions[] = 'v.status = ?';
    $params[] = $status;
}
$where = 'WHERE ' . implode(' AND ', $conditions);
$visits = db_select_all("SELECT v.id, v.no_kunjungan, v.tanggal_kunjungan, v.nomor_antrian, v.status, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter, e.id AS examination_id, e.diagnosa
    FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id LEFT JOIN examinations e ON e.visit_id = v.id
    $where ORDER BY FIELD(v.status, 'Diperiksa', 'Menunggu', 'Selesai'), v.tanggal_kunjungan DESC, v.nomor_antrian ASC", $params);
$needs_exam = (int) db_value("SELECT COUNT(*) FROM visits WHERE status IN ('Menunggu', 'Diperiksa') AND id NOT IN (SELECT visit_id FROM examinations)");
$completed_exam = (int) db_value('SELECT COUNT(*) FROM examinations WHERE DATE(diperiksa_pada) = CURDATE()');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="visit-summary"><div class="summary-tile"><span>Perlu diisi</span><strong><?= e($needs_exam) ?> kunjungan</strong></div><div class="summary-tile"><span>Selesai hari ini</span><strong><?= e($completed_exam) ?> pemeriksaan</strong></div><div class="summary-tile"><span>Total antrean aktif</span><strong><?= e(count($visits)) ?> kunjungan</strong></div></div>
<div class="page-toolbar"><form class="toolbar-filters" method="get" action="<?= e(base_url('pemeriksaan/index.php')) ?>"><div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari no. kunjungan, pasien, atau dokter" aria-label="Cari pemeriksaan"></div><select class="filter-select" name="status" aria-label="Filter status layanan"><option value="">Semua layanan</option><option value="Menunggu" <?= $status === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option><option value="Diperiksa" <?= $status === 'Diperiksa' ? 'selected' : '' ?>>Diperiksa</option><option value="Selesai" <?= $status === 'Selesai' ? 'selected' : '' ?>>Selesai</option></select><button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button></form><span class="toolbar-meta"><?= e(count($visits)) ?> kunjungan</span></div>
<section class="panel"><div class="panel-header"><div><h2>Daftar pemeriksaan</h2><p>Klik satu kunjungan untuk mengisi atau meninjau hasil dokter.</p></div><?php if ($q !== '' || $status !== ''): ?><a class="panel-link" href="<?= e(base_url('pemeriksaan/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div><?php if (empty($visits)): ?><?= render_empty_state('stethoscope', 'Tidak ada pemeriksaan', 'Kunjungan yang aktif akan muncul di daftar ini.') ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Pasien</th><th>Kunjungan</th><th>Poli / dokter</th><th>Status</th><th>Hasil</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($visits as $visit): ?><tr><td><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?></div></div></div></td><td><div class="cell-primary mono"><?= e($visit['no_kunjungan']) ?></div><div class="cell-muted"><?= e(format_date_id($visit['tanggal_kunjungan'])) ?> · #<?= e($visit['nomor_antrian']) ?></div></td><td><div class="cell-primary"><?= e($visit['nama_poli']) ?></div><div class="cell-muted"><?= e($visit['nama_dokter']) ?></div></td><td><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></td><td><?= e($visit['diagnosa'] ?: 'Belum dicatat') ?></td><td class="action-cell"><?php if (!$visit['examination_id']): ?><a class="button button-ghost button-small" href="<?= e(base_url('pemeriksaan/tambah.php?visit_id=' . $visit['id'])) ?>">Isi hasil</a><?php else: ?><a class="table-action" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit['id'])) ?>" aria-label="Lihat hasil pemeriksaan"><?= icon('eye') ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
