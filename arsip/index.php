<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('Admin');

$page_title = 'Arsip data';
$page_description = 'Data yang dipindahkan dari daftar aktif tetap tersimpan dan dapat dipulihkan dengan aman.';
$active_menu = 'arsip';
$page_action_html = page_action('Kembali ke dashboard', 'dashboard/index.php', 'layout-dashboard', 'button button-secondary');

$query = query_value('q');
$entity_filter = query_value('jenis');
$definitions = archive_entity_definitions();
$archive_entries = array();
$archive_counts = array();
$total_archive_count = 0;

foreach ($definitions as $entity => $definition) {
    $alias = $definition['alias'];
    $archive_counts[$entity] = (int) db_value(
        'SELECT COUNT(*) FROM ' . $definition['table'] . ' ' . $alias . ' WHERE ' . $alias . '.archived_at IS NOT NULL',
        array(),
        0
    );
    $total_archive_count += $archive_counts[$entity];

    if ($entity_filter !== '' && $entity_filter !== $entity) {
        continue;
    }

    $conditions = array($alias . '.archived_at IS NOT NULL');
    $params = array();
    if ($query !== '') {
        $search_conditions = array();
        foreach ($definition['search_columns'] as $search_column) {
            $search_conditions[] = $search_column . ' LIKE ?';
            $params[] = '%' . $query . '%';
        }
        $conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
    }

    $archive_rows = db_select_all(
        "SELECT '{$entity}' AS entity_key, '{$definition['label']}' AS entity_label, '{$definition['icon']}' AS entity_icon,
            {$alias}.id AS record_id,
            {$alias}.{$definition['name_column']} AS record_name,
            {$alias}.{$definition['code_column']} AS record_code,
            {$definition['meta_sql']} AS record_meta,
            {$alias}.archived_at,
            COALESCE(u.nama_lengkap, 'Sistem') AS archived_by_name
        FROM {$definition['table']} {$alias}
        {$definition['join_sql']}
        LEFT JOIN users u ON u.id = {$alias}.archived_by
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY {$alias}.archived_at DESC",
        $params
    );

    foreach ($archive_rows as $entry) {
        $archive_entries[] = $entry;
    }
}

usort($archive_entries, function ($left, $right) {
    return strcmp((string) $right['archived_at'], (string) $left['archived_at']);
});

$visible_archive_count = count($archive_entries);
$active_entity_label = $entity_filter !== '' && isset($definitions[$entity_filter]) ? $definitions[$entity_filter]['label'] : 'Semua data';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="archive-summary-grid" data-archive-summary aria-label="Ringkasan arsip">
    <a class="archive-summary-card <?= $entity_filter === '' ? 'is-active' : '' ?>" data-archive-filter href="<?= e(base_url('arsip/index.php')) ?>"<?= $entity_filter === '' ? ' aria-current="page"' : '' ?>>
        <span class="archive-summary-icon"><?= icon('archive') ?></span>
        <span class="archive-summary-copy"><strong><?= e($total_archive_count) ?></strong><small>Total diarsipkan</small></span>
    </a>
    <?php foreach ($definitions as $entity => $definition): ?>
        <a class="archive-summary-card archive-summary-<?= e($definition['theme']) ?> <?= $entity_filter === $entity ? 'is-active' : '' ?>" data-archive-filter href="<?= e(base_url('arsip/index.php?jenis=' . urlencode($entity))) ?>"<?= $entity_filter === $entity ? ' aria-current="page"' : '' ?>>
            <span class="archive-summary-icon"><?= icon($definition['icon']) ?></span>
            <span class="archive-summary-copy"><strong><?= e($archive_counts[$entity]) ?></strong><small><?= e($definition['label']) ?></small></span>
        </a>
    <?php endforeach; ?>
</section>

<div class="archive-guidance">
    <span class="archive-guidance-icon"><?= icon('shield-check') ?></span>
    <div><strong>Arsip menjaga riwayat klinik tetap utuh.</strong><span>Data tidak muncul di alur aktif, tetapi relasi kunjungan dan resep tetap aman. Pulihkan data bila ingin menggunakannya kembali.</span></div>
</div>

<div class="page-toolbar archive-toolbar">
    <form class="toolbar-filters" method="get" action="<?= e(base_url('arsip/index.php')) ?>">
        <div class="search-box"><?= icon('search') ?><input type="search" name="q" value="<?= e($query) ?>" placeholder="Cari nama, kode, NIK, kategori, atau spesialisasi" aria-label="Cari data arsip"></div>
        <select class="filter-select" name="jenis" aria-label="Filter jenis data arsip">
            <option value="">Semua jenis data</option>
            <?php foreach ($definitions as $entity => $definition): ?><option value="<?= e($entity) ?>" <?= $entity_filter === $entity ? 'selected' : '' ?>><?= e($definition['label']) ?></option><?php endforeach; ?>
        </select>
        <button class="button button-secondary button-small" type="submit"><?= icon('filter') ?><span>Filter</span></button>
    </form>
    <?php if ($query !== '' || $entity_filter !== ''): ?><a class="panel-link" href="<?= e(base_url('arsip/index.php')) ?>"><?= icon('refresh-cw') ?> Reset filter</a><?php endif; ?>
    <span class="toolbar-meta"><?= e($visible_archive_count) ?> item · <?= e($active_entity_label) ?></span>
</div>

<section class="panel archive-panel">
    <div class="panel-header">
        <div><h2>Data yang diarsipkan</h2><p>Kelola pemulihan dan penghapusan permanen dari satu tempat.</p></div>
        <span class="archive-policy-badge"><?= icon('shield-check') ?> Hanya Admin</span>
    </div>
    <?php if (empty($archive_entries)): ?>
        <?= render_empty_state('archive', 'Arsip masih kosong', 'Data yang dipindahkan dari daftar aktif akan muncul di sini.') ?>
    <?php else: ?>
        <div class="table-wrap archive-table-wrap">
            <table class="data-table archive-table">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Jenis</th>
                    <th>Diarsipkan pada</th>
                    <th>Oleh</th>
                    <th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($archive_entries as $entry): ?>
                    <?php
                    $entity = $entry['entity_key'];
                    $entity_label = strtolower($entry['entity_label']);
                    $restore_message = 'Pulihkan data ' . $entity_label . ' ini? Data akan kembali ke daftar aktif.';
                    $permanent_message = 'Hapus permanen data ' . $entity_label . ' ini? Tindakan ini tidak dapat dibatalkan.';
                    ?>
                    <tr>
                        <td>
                            <div class="patient-cell">
                                <span class="patient-initial archive-initial archive-initial-<?= e($definitions[$entity]['theme']) ?>"><?= icon($entry['entity_icon']) ?></span>
                                <div><div class="cell-primary"><?= e($entry['record_name']) ?></div><div class="cell-muted mono"><?= e($entry['record_code']) ?></div></div>
                            </div>
                            <div class="archive-record-meta"><?= e($entry['record_meta']) ?></div>
                        </td>
                        <td><span class="archive-type-pill archive-type-<?= e($definitions[$entity]['theme']) ?>"><?= e($entry['entity_label']) ?></span></td>
                        <td><div class="cell-primary"><?= e(format_date_id($entry['archived_at'], true)) ?></div><div class="cell-muted">Data dipindahkan dari daftar aktif</div></td>
                        <td><?= e($entry['archived_by_name']) ?></td>
                        <td>
                            <div class="action-cell archive-actions">
                                <form class="inline-form" method="post" action="<?= e(base_url('arsip/aksi.php')) ?>" data-confirm="<?= e($restore_message) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="entity" value="<?= e($entity) ?>">
                                    <input type="hidden" name="id" value="<?= e($entry['record_id']) ?>">
                                    <button class="table-action is-restore" type="submit" aria-label="Pulihkan <?= e($entity_label) ?>" title="Pulihkan data"><?= icon('undo-2') ?></button>
                                </form>
                                <form class="inline-form" method="post" action="<?= e(base_url('arsip/aksi.php')) ?>" data-confirm="<?= e($permanent_message) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_permanent">
                                    <input type="hidden" name="entity" value="<?= e($entity) ?>">
                                    <input type="hidden" name="id" value="<?= e($entry['record_id']) ?>">
                                    <button class="table-action is-danger" type="submit" aria-label="Hapus permanen <?= e($entity_label) ?>" title="Hapus permanen"><?= icon('trash-2') ?></button>
                                </form>
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
