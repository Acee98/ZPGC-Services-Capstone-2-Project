<?php
/**
 * Admin dashboard — Recent Ticket History (read-only preview).
 * Expects: $recent_tickets
 */
$tickets = $recent_tickets ?? [];
?>
<div class="tickets-toolbar admin-dashboard-toolbar">
    <div class="tickets-filter-tabs">
        <span class="admin-dashboard-history-title">Recent Ticket History</span>
    </div>
</div>

<div class="tickets-list admin-dashboard-history-list">
    <div class="tickets-list-header">
        <span class="tcol-id">ID</span>
        <span class="tcol-subject">Subject</span>
        <span class="tcol-priority">Priority</span>
        <span class="tcol-status">Status</span>
        <span class="tcol-assigned">Assigned To</span>
    </div>
    <div class="tickets-list-body" id="admin-dashboard-history-body">
        <?php if (empty($tickets)) { ?>
            <div class="tickets-empty-state">
                <p>No tickets submitted yet.</p>
            </div>
        <?php } else { ?>
            <?php foreach ($tickets as $ticket) { ?>
                <?php
                    $tid = (int) $ticket['id'];
                    $statusKey = preg_replace('/[^a-z_]/', '', strtolower($ticket['status']));
                    $pri = trim((string) ($ticket['priority'] ?? ''));
                    $priorityKey = $pri !== '' ? preg_replace('/[^a-z]/', '', strtolower($pri)) : '';
                    $techId = (int) ($ticket['assigned_to'] ?? 0);
                    $assignedLabel = 'Unassigned';
                    if ($techId > 0 && !empty($ticket['tech_first'])) {
                        $assignedLabel = trim($ticket['tech_first'] . ' ' . ($ticket['tech_last'] ?? ''));
                    } elseif ($techId > 0) {
                        $assignedLabel = 'Tech #' . $techId;
                    }
                ?>
                <div class="ticket-row admin-history-row" data-status="<?php echo htmlspecialchars($ticket['status']); ?>">
                    <span class="tcol-id">#<?php echo $tid; ?></span>
                    <span class="tcol-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                    <span class="tcol-priority">
                        <?php if ($pri !== '') { ?>
                            <span class="priority-badge <?php echo htmlspecialchars($priorityKey); ?>">
                                <?php echo htmlspecialchars(ucfirst($pri)); ?>
                            </span>
                        <?php } else { ?>
                            <span class="priority-badge undefined">None</span>
                        <?php } ?>
                    </span>
                    <span class="tcol-status">
                        <span class="status-badge <?php echo htmlspecialchars($statusKey); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['status']))); ?>
                        </span>
                    </span>
                    <span class="tcol-assigned"><?php echo htmlspecialchars($assignedLabel); ?></span>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
