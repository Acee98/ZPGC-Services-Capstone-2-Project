<?php
/**
 * Priority queue status banner for admin Tickets tab.
 * Expects: $queue_snapshot from priority_queue_snapshot()
 */
if (empty($queue_snapshot) || !isset($queue_snapshot['bands'])) {
    return;
}
$bands = $queue_snapshot['bands'];
$tierKeys = ['critical', 'moderate', 'low'];
?>
<div class="priority-queue-panel" aria-label="Priority queue status">
    <div class="priority-queue-head">
        <strong>Priority queue</strong>
        <span class="priority-queue-total">
            <?php echo (int) $queue_snapshot['active_total']; ?> / <?php echo (int) $queue_snapshot['max_total']; ?>
        </span>
    </div>
    <div class="priority-queue-bands">
        <?php foreach ($tierKeys as $tierKey) { ?>
            <?php
                $band = $bands[$tierKey] ?? [];
                $batchName = $band['batch_name'] ?? priority_queue_tier_meta($tierKey)['batch_name'];
                $priorityLabel = $band['priority_label'] ?? priority_queue_tier_meta($tierKey)['priority_label'];
                $used = (int) ($band['used'] ?? 0);
                $limit = (int) ($band['limit'] ?? 3);
                $borrowed = (int) ($band['borrowed'] ?? max(0, $used - $limit));
                $pct = $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0;
                $fullClass = $used >= $limit ? ' is-full' : '';
            ?>
            <div class="priority-queue-band priority-<?php echo htmlspecialchars($tierKey); ?><?php echo $fullClass; ?>">
                <span class="priority-queue-band-label"><?php echo htmlspecialchars($batchName); ?></span>
                <span class="priority-queue-band-tier"><?php echo htmlspecialchars($priorityLabel); ?></span>
                <span class="priority-queue-band-count">
                    <?php echo $used; ?> / <?php echo $limit; ?>
                    <?php if ($borrowed > 0) { ?>
                        <span class="priority-queue-borrowed-tag">(+<?php echo $borrowed; ?> borrowed)</span>
                    <?php } ?>
                </span>
                <div class="priority-queue-bar" role="presentation">
                    <span class="priority-queue-bar-fill" style="width: <?php echo $pct; ?>%"></span>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
