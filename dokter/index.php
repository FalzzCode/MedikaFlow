<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Data dokter';
$page_description = 'Atur dokter yang bertugas dan spesialisasi layanan klinik.';
$active_menu = 'dokter';
$page_action_html = page_action('Tambah dokter', 'dokter/tambah.php', 'user-plus');

$q = query_value('q');
$status = query_value('status');
$conditions = array('d.archived_at IS NULL');
$params = array();

if ($q !== '') {
    $conditions[] = '(d.kode_dokter LIKE ? OR d.nama_dokter LIKE ? OR s.nama LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search);
}
if (in_array($status, array('Aktif', 'Nonaktif'), true)) {
    $conditions[] = 'd.status = ?';
    $params[] = $status;
}

$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$doctors = db_select_all("SELECT d.*, s.nama AS spesialisasi FROM doctors d INNER JOIN specializations s ON s.id = d.specialization_id $where ORDER BY d.status = 'Aktif' DESC, d.nama_dokter", $params);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-toolbar">
    <form class="toolbar-filters" method="get" action="<?= e(base_url('dokter/index.php')) ?>">
        <div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama dokter, atau spesialisasi" aria-label="Cari dokter"></div>
        <select class="filter-select" name="status" aria-label="Filter status dokter"><option value="">Semua status</option><option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Nonaktif" <?= $status === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select>
        <button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button>
    </form>
    <span class="toolbar-meta"><?= e(count($doctors)) ?> dokter</span>
</div>
<section class="panel">
    <div class="panel-header"><div><h2>Daftar dokter</h2><p>Dokter aktif akan muncul saat membuat kunjungan. Data yang diarsipkan tersedia di menu Arsip.</p></div><?php if ($q !== '' || $status !== ''): ?><a class="panel-link" href="<?= e(base_url('dokter/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?></div>
    <?php if (empty($doctors)): ?>
        <?= render_empty_state('stethoscope', 'Dokter belum ditemukan', 'Coba ubah filter atau tambahkan dokter baru.', page_action('Tambah dokter', 'dokter/tambah.php', 'user-plus', 'button button-ghost')) ?>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Dokter</th><th>Spesialisasi</th><th>Jadwal praktik</th><th>No. HP</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody>
        <?php foreach ($doctors as $doctor): ?>
            <?php $schedule = doctor_schedule($doctor); ?>
            <tr><td><div class="patient-cell"><span class="patient-initial"><?= e(initials($doctor['nama_dokter'])) ?></span><div><div class="cell-primary"><?= e($doctor['nama_dokter']) ?></div><div class="cell-muted mono"><?= e($doctor['kode_dokter']) ?></div></div></div></td><td><?= e($doctor['spesialisasi']) ?></td><td><div class="cell-primary"><?= e($schedule['days']) ?></div><div class="cell-muted"><?= e($schedule['hours']) ?></div></td><td><?= e($doctor['no_hp']) ?></td><td><span class="<?= e(status_class($doctor['status'])) ?>"><?= status_icon($doctor['status']) ?><?= e($doctor['status']) ?></span></td><td><div class="action-cell"><a class="table-action" href="<?= e(base_url('dokter/edit.php?id=' . $doctor['id'])) ?>" aria-label="Edit dokter"><?= icon('edit-3') ?></a><form class="inline-form" method="post" action="<?= e(base_url('dokter/hapus.php')) ?>" data-confirm="Arsipkan data dokter ini? Data tetap tersimpan dan dapat dipulihkan dari menu Arsip."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($doctor['id']) ?>"><button class="table-action is-archive" type="submit" aria-label="Arsipkan dokter" title="Arsipkan dokter"><?= icon('archive') ?></button></form></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
