<?php
/**
 * Read-only resolved / archived ticket history.
 * Expects $history_tickets (array). Optional: $history_title, $history_empty, $history_show_assigned,
 * $history_show_satisfaction, $history_rating_labels.
 */
$history_tickets = $history_tickets ?? [];
$history_title = $history_title ?? 'Ticket History';
$history_subtitle = $history_subtitle ?? 'Resolved and archived tickets';
$history_empty = $history_empty ?? 'No resolved tickets in history yet.';
$history_show_assigned = !empty($history_show_assigned);
$history_show_satisfaction = !empty($history_show_satisfaction);
$history_rating_labels = $history_rating_labels ?? [
    5 => 'Very satisfied',
    4 => 'Satisfied',
    3 => 'Not sure',
    2 => 'Not satisfied',
    1 => 'Hate it',
];
?>
<section class="ticket-history-section">
    <div class="ticket-history-head">
        <header>
            <h2><?php echo htmlspecialchars($history_title); ?></h2>
            <p class="ticket-history-subtitle"><?php echo htmlspecialchars($history_subtitle); ?></p>
        </header>
    </div>
    <div class="tickets-list ticket-history-list<?php echo $history_show_assigned ? ' ticket-history-list--assigned' : ''; ?><?php echo $history_show_satisfaction ? ' ticket-history-list--satisfaction' : ''; ?>">
        <div class="tickets-list-header">
            <span class="tickets-col-id">ID</span>
            <span class="tickets-col-subject">Subject</span>
            <span class="tickets-col-description">Description</span>
            <span class="tickets-col-status">Status</span>
            <span class="tickets-col-priority">Priority</span>
            <?php if ($history_show_assigned) { ?>
            <span class="tickets-col-assigned">Assigned To</span>
            <?php } ?>
            <?php if ($history_show_satisfaction) { ?>
            <span class="tickets-col-satisfaction">Satisfaction</span>
            <?php } ?>
        </div>
        <div class="tickets-list-body">
            <?php if (empty($history_tickets)) { ?>
            <div class="tickets-empty-state">
                <p><?php echo htmlspecialchars($history_empty); ?></p>
            </div>
            <?php } else { ?>
            <?php foreach ($history_tickets as $ticket) {
                $st = (string) ($ticket['status'] ?? 'resolved');
                $pri = trim((string) ($ticket['priority'] ?? ''));
                $assignedLabel = 'Unassigned';
                if ($history_show_assigned) {
                    $techId = (int) ($ticket['assigned_to'] ?? 0);
                    if ($techId > 0 && !empty($ticket['tech_first'])) {
                        $assignedLabel = trim($ticket['tech_first'] . ' ' . ($ticket['tech_last'] ?? ''));
                    } elseif ($techId > 0) {
                        $assignedLabel = 'Tech #' . $techId;
                    }
                }
                $satScore = (int) ($ticket['satisfaction'] ?? 0);
                $satLabel = $history_rating_labels[$satScore] ?? '';
            ?>
            <div class="ticket-row ticket-history-row" data-status="<?php echo htmlspecialchars($st); ?>">
                <span class="tickets-col-id">#<?php echo (int) $ticket['id']; ?></span>
                <span class="tickets-col-subject"><?php echo htmlspecialchars((string) ($ticket['subject'] ?? '')); ?></span>
                <span class="tickets-col-description"><?php echo htmlspecialchars((string) ($ticket['description'] ?? '')); ?></span>
                <span class="tickets-col-status">
                    <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                        <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                    </span>
                </span>
                <span class="tickets-col-priority">
                    <?php if ($pri !== '') { ?>
                    <span class="priority-badge <?php echo htmlspecialchars(preg_replace('/[^a-z]/', '', strtolower($pri))); ?>">
                        <?php echo htmlspecialchars(ucfirst($pri)); ?>
                    </span>
                    <?php } else { ?>
                    <span class="priority-badge undefined">None</span>
                    <?php } ?>
                </span>
                <?php if ($history_show_assigned) { ?>
                <span class="tickets-col-assigned"><?php echo htmlspecialchars($assignedLabel); ?></span>
                <?php } ?>
                <?php if ($history_show_satisfaction) { ?>
                <span class="tickets-col-satisfaction">
                    <?php if ($satLabel !== '') { ?>
                    <span class="confirm-placeholder"><?php echo htmlspecialchars($satLabel); ?></span>
                    <?php } elseif ($st === 'resolved') { ?>
                    <a class="btn-rate-visit" href="user.php?tab=tickets&amp;rate=<?php echo (int) $ticket['id']; ?>">Rate visit</a>
                    <?php } else { ?>
                    <span class="confirm-placeholder">—</span>
                    <?php } ?>
                </span>
                <?php } ?>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>
