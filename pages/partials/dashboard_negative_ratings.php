<?php
$hate_tickets = $hate_tickets ?? [];
?>
<section class="hate-feedback-panel">
    <div class="ticket-history-head">
        <header>
            <h2>Negative feedback</h2>
            <p class="ticket-history-subtitle">Tickets rated “Hate it” so you can follow up on the exact visits, not just the chart total.</p>
        </header>
    </div>
    <div class="tickets-list hate-feedback-list">
        <div class="tickets-list-header">
            <span class="tickets-col-id">ID</span>
            <span class="tickets-col-subject">Subject</span>
            <span class="tickets-col-description">Description</span>
            <span class="tickets-col-category">Category</span>
            <span class="tickets-col-priority">Priority</span>
            <span class="tickets-col-preview">View</span>
        </div>
        <div class="tickets-list-body js-paged-table" id="hate-feedback-body" data-page-size="10">
            <?php if (empty($hate_tickets)) { ?>
            <div class="tickets-empty-state">
                <p>No “Hate it” ratings in this list yet.</p>
            </div>
            <?php } else { ?>
            <?php foreach ($hate_tickets as $ticket) {
                $pri = trim((string) ($ticket['priority'] ?? ''));
            ?>
            <div class="ticket-row" data-ticket-id="<?php echo (int) $ticket['id']; ?>" data-status="<?php echo htmlspecialchars((string) ($ticket['status'] ?? '')); ?>" data-priority="<?php echo htmlspecialchars(ticket_priority_key($pri)); ?>">
                <span class="tickets-col-id">#<?php echo (int) $ticket['id']; ?></span>
                <span class="tickets-col-subject"><?php echo htmlspecialchars((string) ($ticket['subject'] ?? '')); ?></span>
                <span class="tickets-col-description"><?php echo htmlspecialchars((string) ($ticket['description'] ?? '')); ?></span>
                <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
                <span class="tickets-col-priority"><?php echo $pri !== '' ? htmlspecialchars(ucfirst($pri)) : '—'; ?></span>
                <span class="tickets-col-preview">
                    <?php echo ticket_quick_view_button($ticket, ['data-qv-satisfaction' => 'Hate it']); ?>
                </span>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>
