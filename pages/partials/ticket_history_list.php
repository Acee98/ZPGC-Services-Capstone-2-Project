<?php
/**
 * Read-only resolved / archived ticket history.
 * Expects $history_tickets (array). Optional: $history_title, $history_empty, $history_show_assigned,
 * $history_show_satisfaction, $history_show_times, $history_rating_labels, $history_allow_rate.
 */
$history_tickets = $history_tickets ?? [];
$history_title = $history_title ?? 'Ticket History';
$history_subtitle = $history_subtitle ?? 'Resolved and archived tickets';
$history_empty = $history_empty ?? 'No resolved tickets in history yet.';
$history_show_assigned = !empty($history_show_assigned);
$history_show_satisfaction = !empty($history_show_satisfaction);
$history_show_times = !empty($history_show_times);
$history_allow_rate = !empty($history_allow_rate);
$history_show_preview = array_key_exists('history_show_preview', get_defined_vars())
    ? !empty($history_show_preview)
    : true;
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
        <?php echo ticket_priority_filter_markup('history-priority-filter', 'ticket-history-body'); ?>
    </div>
    <div class="tickets-list ticket-history-list<?php echo $history_show_assigned ? ' ticket-history-list--assigned' : ''; ?><?php echo $history_show_satisfaction ? ' ticket-history-list--satisfaction' : ''; ?><?php echo $history_show_times ? ' ticket-history-list--times' : ''; ?><?php echo $history_show_preview ? ' ticket-history-list--preview' : ''; ?>">
        <div class="tickets-list-header">
            <span class="tickets-col-id">ID</span>
            <span class="tickets-col-category">Category</span>
            <span class="tickets-col-subject">Subject</span>
            <span class="tickets-col-description">Description</span>
            <span class="tickets-col-status">Status</span>
            <span class="tickets-col-priority">Priority</span>
            <?php if ($history_show_times) { ?>
            <span class="tickets-col-reg">Registration Date &amp; Time</span>
            <span class="tickets-col-resolution">Resolution Time</span>
            <?php } ?>
            <?php if ($history_show_assigned) { ?>
            <span class="tickets-col-assigned">Assigned To</span>
            <?php } ?>
            <?php if ($history_show_satisfaction) { ?>
            <span class="tickets-col-satisfaction">Satisfaction</span>
            <?php } ?>
            <?php if ($history_show_preview) { ?>
            <span class="tickets-col-preview">View</span>
            <?php } ?>
        </div>
        <div class="tickets-list-body js-paged-table" id="ticket-history-body" data-page-size="15">
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
                $regLabel = function_exists('performance_registered_label')
                    ? performance_registered_label($ticket['created_at'] ?? null)
                    : '—';
                $resLabel = function_exists('ticket_resolution_clock_label')
                    ? ticket_resolution_clock_label($ticket['created_at'] ?? null, $ticket['resolved_at'] ?? null)
                    : '—';
            ?>
            <div class="ticket-row ticket-history-row" data-status="<?php echo htmlspecialchars($st); ?>">
                <span class="tickets-col-id">#<?php echo (int) $ticket['id']; ?></span>
                <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
                <span class="tickets-col-subject"><?php echo htmlspecialchars((string) ($ticket['subject'] ?? '')); ?></span>
                <span class="tickets-col-description"><?php echo htmlspecialchars((string) ($ticket['description'] ?? '')); ?></span>
                <span class="ticket-row-badges">
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
                </span>
                <?php if ($history_show_times) { ?>
                <span class="tickets-col-reg"><?php echo htmlspecialchars($regLabel); ?></span>
                <span class="tickets-col-resolution<?php echo (trim((string) $resLabel) === '—' || trim((string) $resLabel) === '') ? ' ticket-col-empty' : ''; ?>"><?php echo htmlspecialchars($resLabel); ?></span>
                <?php } ?>
                <?php if ($history_show_assigned) { ?>
                <span class="tickets-col-assigned"><?php echo htmlspecialchars($assignedLabel); ?></span>
                <?php } ?>
                <?php if ($history_show_satisfaction) { ?>
                <span class="tickets-col-satisfaction">
                    <?php if ($satLabel !== '') { ?>
                    <span class="confirm-placeholder"><?php echo htmlspecialchars($satLabel); ?></span>
                    <?php } elseif ($history_allow_rate && $st === 'resolved') { ?>
                    <a class="btn-rate-visit" href="user.php?tab=tickets&amp;rate=<?php echo (int) $ticket['id']; ?>">Rate visit</a>
                    <?php } else { ?>
                    <span class="confirm-placeholder">—</span>
                    <?php } ?>
                </span>
                <?php } ?>
                <?php if ($history_show_preview) { ?>
                <span class="tickets-col-preview">
                    <?php
                    $qvExtra = [];
                    if ($history_show_assigned) {
                        $qvExtra['data-qv-assigned'] = $assignedLabel;
                    }
                    $qvExtra['data-qv-registered'] = $regLabel;
                    if ($satLabel !== '') {
                        $qvExtra['data-qv-satisfaction'] = $satLabel;
                    } elseif ($st === 'resolved') {
                        $qvExtra['data-qv-satisfaction'] = 'Unrated';
                    }
                    echo ticket_quick_view_button($ticket, $qvExtra);
                    ?>
                </span>
                <?php } ?>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>
