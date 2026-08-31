<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Data pasien';
$page_description = 'Kelola identitas pasien dan temukan rekam medis dengan cepat.';
$active_menu = 'pasien';
$page_action_html = page_action('Tambah pasien', 'pasien/tambah.php', 'user-plus');

$q = query_value('q');
$gender = query_value('jk');
$conditions = array('p.archived_at IS NULL');
$params = array();

if ($q !== '') {
    $conditions[] = '(p.no_rm LIKE ? OR p.nik LIKE ? OR p.nama LIKE ? OR p.no_hp LIKE ?)';
    $search = '%' . $q . '%';
    $params = array($search, $search, $search, $search);
}

if (in_array($gender, array('L', 'P'), true)) {
    $conditions[] = 'p.jenis_kelamin = ?';
    $params[] = $gender;
}

$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
$patients = db_select_all("SELECT p.*, (SELECT MAX(v.tanggal_kunjungan) FROM visits v WHERE v.patient_id = p.id) AS kunjungan_terakhir
    FROM patients p $where ORDER BY p.created_at DESC", $params);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-toolbar">
    <form class="toolbar-filters" method="get" action="<?= e(base_url('pasien/index.php')) ?>">
        <div class="search-box">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, NIK, no. rekam medis, atau HP" aria-label="Cari pasien">
        </div>
        <select class="filter-select" name="jk" aria-label="Filter jenis kelamin">
            <option value="">Semua gender</option>
            <option value="L" <?= $gender === 'L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= $gender === 'P' ? 'selected' : '' ?>>Perempuan</option>
        </select>
        <button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button>
    </form>
    <span class="toolbar-meta"><?= e(count($patients)) ?> pasien ditemukan</span>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Daftar pasien</h2>
            <p>Informasi identitas aktif yang tersimpan di klinik. Data yang diarsipkan tersedia di menu Arsip.</p>
        </div>
        <?php if ($q !== '' || $gender !== ''): ?><a class="panel-link" href="<?= e(base_url('pasien/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?>
    </div>
    <?php if (empty($patients)): ?>
        <?= render_empty_state('users-round', 'Pasien belum ditemukan', 'Coba ubah kata kunci pencarian atau tambahkan pasien baru.', page_action('Tambah pasien', 'pasien/tambah.php', 'user-plus', 'button button-ghost')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Pasien</th>
                    <th>NIK</th>
                    <th>Jenis kelamin</th>
                    <th>Tanggal lahir</th>
                    <th>Kunjungan terakhir</th>
                    <th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td>
                            <div class="patient-cell">
                                <span class="patient-initial"><?= e(initials($patient['nama'])) ?></span>
                                <div><div class="cell-primary"><?= e($patient['nama']) ?></div><div class="cell-muted mono"><?= e($patient['no_rm']) ?></div></div>
                            </div>
                        </td>
                        <td class="mono"><?= e($patient['nik']) ?></td>
                        <td><?= e(format_gender($patient['jenis_kelamin'])) ?></td>
                        <td><div class="cell-primary"><?= e(format_date_id($patient['tanggal_lahir'])) ?></div><div class="cell-muted"><?= e(calculate_age($patient['tanggal_lahir'])) ?></div></td>
                        <td><?= e(format_date_id($patient['kunjungan_terakhir'])) ?></td>
                        <td>
                            <div class="action-cell">
                                <a class="table-action" href="<?= e(base_url('pasien/detail.php?id=' . $patient['id'])) ?>" aria-label="Detail pasien"><?= icon('eye') ?></a>
                                <a class="table-action" href="<?= e(base_url('pasien/edit.php?id=' . $patient['id'])) ?>" aria-label="Edit pasien"><?= icon('edit-3') ?></a>
                                <?php if (($active_user['role'] ?? '') === 'Admin'): ?>
                                    <form class="inline-form" method="post" action="<?= e(base_url('pasien/hapus.php')) ?>" data-confirm="Arsipkan data pasien ini? Data tetap tersimpan dan dapat dipulihkan dari menu Arsip.">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($patient['id']) ?>">
                                    <button class="table-action is-archive" type="submit" aria-label="Arsipkan pasien" title="Arsipkan pasien"><?= icon('archive') ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
