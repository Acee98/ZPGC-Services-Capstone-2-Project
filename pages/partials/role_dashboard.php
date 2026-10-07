<?php

$cards = $dashboard_card_keys ?? ['ongoing', 'processing', 'resolved'];
$labels = [
    'pending' => 'Pending',
    'ongoing' => 'Ongoing',
    'processing' => 'Processing',
    'resolved' => 'Resolved',
];
$counts = $status_counts ?? [];
$rows = $dash_tickets ?? [];
$showCategory = !empty($dash_show_category);
$showDashQueue = !empty($dash_show_queue);
$showDashTimes = !empty($dash_show_times);
$dashExtraClass = ($showDashQueue ? ' role-dash-list--queue' : '') . ($showDashTimes ? ' role-dash-list--times' : '');
?>
<div class="status-cards role-dash-cards">
    <?php foreach ($cards as $key) {
        $cardClass = preg_replace('/[^a-z_]/', '', strtolower((string) $key));
    ?>
    <div class="status-card tech-accent status-card--<?php echo htmlspecialchars($cardClass); ?>">
        <div class="status-card-info">
            <span class="status-card-count"><?php echo (int) ($counts[$key] ?? 0); ?></span>
            <span class="status-card-label"><?php echo htmlspecialchars($labels[$key] ?? $key); ?></span>
        </div>
    </div>
    <?php } ?>
</div>
<div class="tickets-list role-dash-list<?php echo $showCategory ? ' role-dash-list--category' : ''; ?>">
    <div class="tickets-list-header">
        <span class="tickets-col-id">ID</span>
        <?php if ($showCategory) { ?>
        <span class="tickets-col-category">Category</span>
        <?php } ?>
        <span class="tickets-col-subject">Subject</span>
        <span class="tickets-col-description">Description</span>
        <?php if ($showCategory) { ?>
        <span class="dash-col-severity">Severity</span>
        <?php } ?>
        <span class="tickets-col-status">Status</span>
        <?php if ($showDashTimes) { ?>
        <span class="tickets-col-reg">Registration Date &amp; Time</span>
        <?php } ?>
        <?php if ($showDashQueue) { ?>
        <span class="tickets-col-queue">Queue timer</span>
        <?php } ?>
        <?php if ($showDashTimes || $showDashQueue) { ?>
        <span class="tickets-col-resolution">Resolution Time</span>
        <?php } ?>
    </div>
    <div class="tickets-list-body">
        <?php if (empty($rows)) { ?>
        <div class="tickets-empty-state"><p>No tickets yet.</p></div>
        <?php } else { ?>
        <?php foreach (array_slice($rows, 0, 8) as $row) {
            $st = (string) ($row['status'] ?? '');
            $regLabel = function_exists('performance_registered_label')
                ? performance_registered_label($row['created_at'] ?? null)
                : '—';
            $resLabel = function_exists('performance_duration_label')
                ? performance_duration_label($row['created_at'] ?? null, $row['resolved_at'] ?? null)
                : '—';
            $queueLive = function_exists('ticket_queue_timer_is_live')
                && ticket_queue_timer_is_live($st);
            $queueLabel = function_exists('ticket_queue_timer_label')
                ? ticket_queue_timer_label(
                    $row['created_at'] ?? null,
                    $queueLive ? null : ($row['resolved_at'] ?? $row['responded_at'] ?? null),
                    $queueLive
                )
                : '00:00:00';
            $queueStart = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
        ?>
        <div class="ticket-row">
            <span class="tickets-col-id">#<?php echo (int) $row['id']; ?></span>
            <?php if ($showCategory) { ?>
            <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($row['category'] ?? '')); ?></span>
            <?php } ?>
            <span class="tickets-col-subject"><?php echo htmlspecialchars((string) $row['subject']); ?></span>
            <span class="tickets-col-description"><?php echo htmlspecialchars((string) $row['description']); ?></span>
            <?php if ($showCategory) { ?>
            <span class="dash-col-severity">
                <?php
                    $sev = preg_replace('/[^a-z]/', '', strtolower((string) ($row['priority'] ?? '')));
                    $sevLabel = $sev !== '' ? ucfirst((string) $row['priority']) : '—';
                ?>
                <span class="severity-badge <?php echo $sev !== '' ? htmlspecialchars($sev) : 'undefined'; ?>">
                    <?php echo htmlspecialchars($sevLabel); ?>
                </span>
            </span>
            <?php } ?>
            <span class="tickets-col-status">
                <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                    <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                </span>
            </span>
            <?php if ($showDashTimes) { ?>
            <span class="tickets-col-reg"><?php echo htmlspecialchars($regLabel); ?></span>
            <?php } ?>
            <?php if ($showDashQueue) { ?>
            <span class="tickets-col-queue"<?php echo $queueLive && $queueStart > 0 ? ' data-queue-start="' . (int) $queueStart . '"' : ''; ?>><?php echo htmlspecialchars($queueLabel); ?></span>
            <?php } ?>
            <?php if ($showDashTimes || $showDashQueue) { ?>
            <span class="tickets-col-resolution"><?php echo htmlspecialchars($resLabel); ?></span>
            <?php } ?>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>
