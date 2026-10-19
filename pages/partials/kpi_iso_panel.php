<?php
$office_kpis = $office_kpis ?? [];
$iso_clauses = $iso_clauses ?? [];
$iso_show_capacity = !empty($iso_show_capacity);
$capacity_flash = $_SESSION['capacity_flash'] ?? '';
$capacity_error = $_SESSION['capacity_error'] ?? '';
unset($_SESSION['capacity_flash'], $_SESSION['capacity_error']);
$iso_last_capacity = $iso_last_capacity ?? null;
$lastRunLabel = '';
if (is_array($iso_last_capacity) && !empty($iso_last_capacity['ran_at'])) {
    $lastRunLabel = 'Last run '
        . (string) $iso_last_capacity['ran_at']
        . ': '
        . (int) ($iso_last_capacity['ok_n'] ?? 0)
        . '/'
        . (int) ($iso_last_capacity['total_n'] ?? 0)
        . ' · median '
        . (int) ($iso_last_capacity['p50_ms'] ?? 0)
        . ' ms · p95 '
        . (int) ($iso_last_capacity['p95_ms'] ?? 0)
        . ' ms.';
}
?>
<div class="perf-service-wrap">
<div class="kpi-grid" aria-label="Office-wide service measures">
    <div class="kpi-card">
        <span class="kpi-label">Resolution rate</span>
        <span class="kpi-value"><?php echo htmlspecialchars((string) ($office_kpis['resolution_rate'] ?? '—')); ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Average response time</span>
        <span class="kpi-value"><?php echo htmlspecialchars((string) ($office_kpis['avg_response'] ?? '—')); ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Average resolution time</span>
        <span class="kpi-value"><?php echo htmlspecialchars((string) ($office_kpis['avg_resolution'] ?? '—')); ?></span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Open tickets</span>
        <span class="kpi-value"><?php echo (int) ($office_kpis['open'] ?? 0); ?></span>
    </div>
</div>
<?php if ($iso_show_capacity && $iso_clauses !== []) { ?>
<div class="iso-panel" data-capacity-panel>
    <div class="perf-section-header">
        <h2>ISO/IEC 25010:2023</h2>
    </div>
    <div data-capacity-banner class="<?php echo $capacity_error !== '' ? 'utilities-notice-error' : 'utilities-notice'; ?>" <?php echo ($capacity_flash === '' && $capacity_error === '') ? 'hidden' : ''; ?>>
        <?php echo htmlspecialchars($capacity_error !== '' ? $capacity_error : $capacity_flash); ?>
    </div>
    <div class="iso-table iso-table--two">
        <div class="iso-row iso-head">
            <span>Characteristic</span>
            <span>Evidence</span>
        </div>
        <?php foreach ($iso_clauses as $clause) { ?>
        <div class="iso-row">
            <span><?php echo htmlspecialchars((string) $clause['clause']); ?></span>
            <span<?php echo (($clause['key'] ?? '') === 'capacity') ? ' data-iso-capacity-evidence' : ''; ?>><?php echo htmlspecialchars((string) $clause['evidence']); ?></span>
        </div>
        <?php } ?>
    </div>
    <div class="iso-capacity-form">
        <?php echo zpgc_csrf_field(); ?>
        <button type="button" class="btn-new-ticket" data-capacity-run>Run concurrent user check</button>
    </div>
    <p class="iso-last-run" data-capacity-last<?php echo $lastRunLabel === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($lastRunLabel); ?></p>
</div>
<?php } ?>
</div>
