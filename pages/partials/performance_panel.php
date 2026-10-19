<?php
$performance_categories = $performance_categories ?? [];
$performance_log = $performance_log ?? [];
$performance_technicians = $performance_technicians ?? [];
$performance_show_technician = !empty($performance_show_technician);
$performance_show_view = !empty($performance_show_view);
?>
<?php if (!empty($performance_technicians)) { ?>
<div class="perf-section-header">
    <h2>Resolved by Technician</h2>
</div>
<div class="tickets-list perf-tech-list">
    <div class="tickets-list-header">
        <span class="pcol-category">Technician</span>
        <span class="pcol-resolved">Role</span>
        <span class="pcol-critical">Tickets Resolved</span>
    </div>
    <div class="tickets-list-body">
        <?php foreach ($performance_technicians as $techRow) { ?>
        <div class="ticket-row">
            <span class="pcol-category"><?php echo htmlspecialchars($techRow['name']); ?></span>
            <span class="pcol-resolved"><?php echo htmlspecialchars($techRow['specialty'] !== '' ? $techRow['specialty'] : 'Unspecified'); ?></span>
            <span class="pcol-critical"><?php echo (int) $techRow['total']; ?></span>
        </div>
        <?php } ?>
    </div>
</div>
<div class="perf-table-spacer"></div>
<?php } ?>
<div class="perf-section-header">
    <h2><?php echo htmlspecialchars(function_exists('zpgc_t') ? zpgc_t('Resolved by Category') : 'Resolved by Category'); ?></h2>
</div>
<div class="tickets-list perf-category-list">
    <div class="tickets-list-header">
        <span class="pcol-category">Category</span>
        <span class="pcol-resolved">Tickets Resolved</span>
        <span class="pcol-critical">Critical</span>
        <span class="pcol-moderate">Moderate</span>
        <span class="pcol-low">Low</span>
    </div>
    <div class="tickets-list-body">
        <?php if (empty($performance_categories)) { ?>
        <div class="tickets-empty-state">
            <p>No resolved tickets yet.</p>
        </div>
        <?php } else { ?>
        <?php foreach ($performance_categories as $perfRow) { ?>
        <div class="ticket-row">
            <span class="pcol-category"><?php echo htmlspecialchars($perfRow['label']); ?></span>
            <span class="pcol-resolved"><?php echo (int) $perfRow['total']; ?></span>
            <span class="pcol-critical"><span class="perf-count critical"><?php echo (int) $perfRow['critical']; ?></span></span>
            <span class="pcol-moderate"><span class="perf-count moderate"><?php echo (int) $perfRow['moderate']; ?></span></span>
            <span class="pcol-low"><span class="perf-count low"><?php echo (int) $perfRow['low']; ?></span></span>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>
<div class="perf-table-spacer"></div>
<div class="perf-filters-row">
    <div class="search-bar-wrapper">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
            fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
            </path>
        </svg>
        <input type="search" class="search-bar" id="perf-log-search" placeholder="Search ticket ID, subject, times..." aria-label="Search resolved tickets">
    </div>
</div>
<div class="tickets-list perf-detail-list<?php echo $performance_show_technician ? ' perf-detail-list--tech' : ''; ?><?php echo $performance_show_view ? ' perf-detail-list--view' : ''; ?>">
    <div class="tickets-list-header">
        <span class="dcol-ticket">Ticket ID</span>
        <span class="dcol-category">Category</span>
        <span class="dcol-subject">Subject</span>
        <span class="dcol-description">Description</span>
        <span class="dcol-reg">Registration Date &amp; Time</span>
        <span class="dcol-response">Response Time</span>
        <span class="dcol-resolution">Resolution Time</span>
        <span class="dcol-severity">Severity Level</span>
        <?php if ($performance_show_technician) { ?>
        <span class="dcol-tech">Technician</span>
        <?php } ?>
        <span class="dcol-satisfaction">Satisfaction</span>
        <?php if ($performance_show_view) { ?>
        <span class="dcol-preview">View</span>
        <?php } ?>
    </div>
    <div class="tickets-list-body js-paged-table" id="perf-log-body" data-page-size="15">
        <?php if (empty($performance_log)) { ?>
        <div class="tickets-empty-state">
            <p>No resolved tickets yet.</p>
        </div>
        <?php } else { ?>
        <?php foreach ($performance_log as $logRow) {
            $sev = $logRow['priority'];
            $searchBlob = strtolower(implode(' ', [
                (string) $logRow['category_label'],
                (string) $logRow['id'],
                '#' . (string) $logRow['id'],
                (string) $logRow['subject'],
                (string) $logRow['description'],
                (string) $logRow['registered'],
                (string) $logRow['response'],
                (string) $logRow['resolution'],
                (string) $logRow['priority_label'],
                (string) ($logRow['technician'] ?? ''),
                (string) ($logRow['satisfaction_label'] ?? ''),
            ]));
        ?>
        <div class="ticket-row" data-perf-search="<?php echo htmlspecialchars($searchBlob); ?>">
            <span class="dcol-ticket">#<?php echo (int) $logRow['id']; ?></span>
            <span class="dcol-category"><?php echo htmlspecialchars($logRow['category_label']); ?></span>
            <span class="dcol-subject"><?php echo htmlspecialchars($logRow['subject']); ?></span>
            <span class="dcol-description"><?php echo htmlspecialchars($logRow['description']); ?></span>
            <span class="dcol-reg"><?php echo htmlspecialchars($logRow['registered']); ?></span>
            <span class="dcol-response"><?php echo htmlspecialchars($logRow['response']); ?></span>
            <span class="dcol-resolution"><?php echo htmlspecialchars($logRow['resolution']); ?></span>
            <span class="dcol-severity">
                <?php if ($sev !== '') { ?>
                <span class="severity-badge <?php echo htmlspecialchars($sev); ?>"><?php echo htmlspecialchars($logRow['priority_label']); ?></span>
                <?php } else { ?>
                <span class="severity-badge undefined">None</span>
                <?php } ?>
            </span>
            <?php if ($performance_show_technician) { ?>
            <span class="dcol-tech"><?php echo htmlspecialchars((string) ($logRow['technician'] ?? '—')); ?></span>
            <?php } ?>
            <span class="dcol-satisfaction"><?php echo htmlspecialchars(($logRow['satisfaction_label'] ?? '') !== '' ? $logRow['satisfaction_label'] : 'Unrated'); ?></span>
            <?php if ($performance_show_view) { ?>
            <span class="dcol-preview">
                <?php
                echo ticket_quick_view_button([
                    'id' => $logRow['id'],
                    'subject' => $logRow['subject'],
                    'description' => $logRow['description'],
                    'category' => $logRow['category'],
                    'priority' => $logRow['priority'],
                    'status' => 'resolved',
                    'satisfaction' => $logRow['satisfaction'] ?? 0,
                ], [
                    'data-qv-assigned' => (string) ($logRow['technician'] ?? ''),
                    'data-qv-registered' => (string) ($logRow['registered'] ?? ''),
                    'data-qv-satisfaction' => (string) (($logRow['satisfaction_label'] ?? '') !== '' ? $logRow['satisfaction_label'] : 'Unrated'),
                ]);
                ?>
            </span>
            <?php } ?>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>
