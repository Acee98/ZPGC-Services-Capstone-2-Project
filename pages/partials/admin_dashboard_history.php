<?php

$tickets = $recent_tickets ?? [];
?>
<div class="tickets-toolbar admin-dashboard-toolbar">
    <div class="tickets-filter-tabs">
        <span class="admin-dashboard-history-title">Ticket History (resolved / archived)</span>
    </div>
</div>

<div class="tickets-list admin-dashboard-history-list">
    <div class="tickets-list-header">
        <span class="tcol-id">ID</span>
        <span class="tcol-category">Category</span>
        <span class="tcol-subject">Subject</span>
        <span class="tcol-priority">Priority</span>
        <span class="tcol-status">Status</span>
        <span class="tcol-assigned">Assigned To</span>
    </div>
    <div class="tickets-list-body" id="admin-dashboard-history-body">
        <?php if (empty($tickets)) { ?>
            <div class="tickets-empty-state">
                <p>No resolved tickets in history yet.</p>
            </div>
        <?php } else { ?>
            <?php foreach ($tickets as $ticket) { ?>
                <?php
                    $tid = (int) $ticket['id'];
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
                    <span class="tcol-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
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
                        <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($ticket['status'] ?? '')); ?>">
                            <?php echo htmlspecialchars(ticket_status_label($ticket['status'] ?? '')); ?>
                        </span>
                    </span>
                    <span class="tcol-assigned"><?php echo htmlspecialchars($assignedLabel); ?></span>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
