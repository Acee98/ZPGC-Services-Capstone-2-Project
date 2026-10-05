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
?>
<div class="status-cards role-dash-cards">
    <?php foreach ($cards as $key) { ?>
    <div class="status-card tech-accent">
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
        <span class="tickets-col-subject">Subject</span>
        <span class="tickets-col-description">Description</span>
        <?php if ($showCategory) { ?>
        <span class="dash-col-category">Category</span>
        <span class="dash-col-severity">Severity</span>
        <?php } ?>
        <span class="tickets-col-status">Status</span>
    </div>
    <div class="tickets-list-body">
        <?php if (empty($rows)) { ?>
        <div class="tickets-empty-state"><p>No tickets yet.</p></div>
        <?php } else { ?>
        <?php foreach (array_slice($rows, 0, 8) as $row) {
            $st = (string) ($row['status'] ?? '');
        ?>
        <div class="ticket-row">
            <span class="tickets-col-id">#<?php echo (int) $row['id']; ?></span>
            <span class="tickets-col-subject"><?php echo htmlspecialchars((string) $row['subject']); ?></span>
            <span class="tickets-col-description"><?php echo htmlspecialchars((string) $row['description']); ?></span>
            <?php if ($showCategory) { ?>
            <span class="dash-col-category"><?php echo htmlspecialchars(ucfirst((string) ($row['category'] ?? ''))); ?></span>
            <span class="dash-col-severity"><?php echo htmlspecialchars(ucfirst((string) ($row['priority'] ?? '—'))); ?></span>
            <?php } ?>
            <span class="tickets-col-status">
                <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                    <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                </span>
            </span>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>
