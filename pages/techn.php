<?php
require_once '../logic/session_config.php';
require_once '../logic/version.php';
require_once '../logic/i18n.php';
require_once '../logic/config.php';
require_once '../logic/ui_state.php';
require_once '../logic/ticket_status.php';
require_once '../logic/ticket_files.php';
require_once '../logic/ticket_times.php';
require_once '../logic/ticket_retention.php';
require_once '../logic/performance_report.php';
require_once '../logic/techn_queue.php';
require_once '../logic/ticket_crypto.php';
require_once '../logic/iso20000.php';
require_once '../logic/ai_reclassify.php';
require_role('techn');

$current_user_id = current_user_id($conn);
$tab = zpgc_ui_resolve_tab('techn', 'dashboard');
zpgc_ui_persist_redirect($tab);
zpgc_ui_no_store();
$ticket_flash = $_SESSION['ticket_flash'] ?? '';
unset($_SESSION['ticket_flash']);

ticket_ensure_archived_column($conn);
ticket_retention_maybe_auto_purge($conn);
// Skip heavy index DDL + retention backfill on every technician page load.
$tech_tickets = [];
$hasArchived = ticket_has_column($conn, 'archived_at');
$cols = 'id, subject, description, category, priority, status'
    . ($hasArchived ? ', archived_at' : '');
if (ticket_has_column($conn, 'created_at')) {
    $cols .= ', created_at';
}
if (ticket_has_column($conn, 'resolved_at')) {
    $cols .= ', resolved_at';
}
if (ticket_has_column($conn, 'responded_at')) {
    $cols .= ', responded_at';
}
if (ticket_has_column($conn, 'satisfaction')) {
    $cols .= ', satisfaction';
}
$techSpecialty = techn_queue_specialty($conn, $current_user_id);
$techCategory = techn_queue_category_key($techSpecialty);
if ($techCategory !== '') {
    $stmt = $conn->prepare(
        "SELECT {$cols} FROM tickets
         WHERE LOWER(TRIM(IFNULL(category, ''))) = ?
           AND (assigned_to = ? OR assigned_to IS NULL)
         ORDER BY id DESC LIMIT 200"
    );
    $stmt->bind_param('si', $techCategory, $current_user_id);
} else {
    $stmt = $conn->prepare(
        "SELECT {$cols} FROM tickets WHERE assigned_to = ? ORDER BY id DESC LIMIT 200"
    );
    $stmt->bind_param('i', $current_user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tech_tickets[] = $row;
}
$stmt->close();
ticket_reveal_rows($tech_tickets);
ai_reclassify_pending($conn, 3);
$replaceIds = [];
$replaceTable = $conn->query("SHOW TABLES LIKE 'replacement_requests'");
if ($replaceTable && $replaceTable->num_rows > 0) {
    $replaceStmt = $conn->prepare(
        "SELECT ticket_id FROM replacement_requests WHERE techn_id = ? AND status = 'pending'"
    );
    $replaceStmt->bind_param('i', $current_user_id);
    $replaceStmt->execute();
    $replaceResult = $replaceStmt->get_result();
    while ($replaceRow = $replaceResult->fetch_assoc()) {
        $replaceIds[] = (int) $replaceRow['ticket_id'];
    }
    $replaceStmt->close();
}
[$active_tech_tickets, $history_tickets] = ticket_partition_active_history($tech_tickets);
$mailbox_tickets = $active_tech_tickets;
$listed_tickets = ticket_sort_for_attention($active_tech_tickets, $replaceIds);
$techn_statuses = ticket_techn_allowed_statuses();
$performance_categories = performance_category_rows($conn, $current_user_id);
$performance_log = performance_resolved_log($conn, 100, $current_user_id);
$office_kpis = performance_office_kpis($conn, $current_user_id);
$iso_clauses = [];
$iso_show_capacity = false;
$ui_theme_pref = current_ui_theme();
$ui_theme = $ui_theme_pref !== '' ? $ui_theme_pref : 'light';
?>

<!DOCTYPE html>
<html <?php echo zpgc_html_root_attrs(); ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <?php echo zpgc_theme_boot_script(); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(zpgc_asset('../css/main_interface.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(zpgc_asset('../css/theme.css')); ?>">
    <?php include __DIR__ . '/partials/critical_ui_fixes.php'; ?>
    <title><?php echo htmlspecialchars(zpgc_page_title('Technician')); ?></title>
</head>

<body data-page="<?php echo htmlspecialchars($tab); ?>" data-role="techn" data-web-base="<?php echo htmlspecialchars(zpgc_web_base(), ENT_QUOTES, 'UTF-8'); ?>">
    <main class="main-wrap">
        <header class="main-head">
            <div class="main-nav">
                <nav class="navbar">
                    <div class="navbar-nav">
                        <div class="logo">
                            <img src="../images/ZPGC.com2.png" alt="ZPGC">
                            <button class="showcase-toggler">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path d="M3 4h18v2H3zm0 7h18v2H3zm0 7h18v2H3z"></path>
                                </svg>
                            </button>
                        </div>
                        <?php include __DIR__ . '/partials/app_version.php'; ?>
                        <ul class="nav-list">
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'dashboard'); ?>" data-nav="dashboard">
                                <a href="?tab=dashboard" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 11h-6c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-8c0-.55-.45-1-1-1m-1 8h-4v-6h4zm-9-4H4c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1m-1 4H5v-2h4zM20 3h-6c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 4h-4V5h4zm-9-4H4c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 8H5V5h4z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'tickets'); ?>" data-nav="tickets">
                                <a href="?tab=tickets" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M21 8h-2V3a1 1 0 0 0-1.37-.93l-15 6c-.09.04-.16.1-.24.16-.03.02-.06.03-.09.06-.04.04-.05.08-.08.12-.05.06-.1.12-.13.19-.01.02 0 .05-.02.08-.03.1-.06.2-.06.31v3.55c0 .48.33.89.8.98a1.499 1.499 0 0 1 0 2.94c-.47.09-.8.5-.8.98v3.55c0 .55.45 1 1 1h18c.55 0 1-.45 1-1v-3.55c0-.48-.33-.89-.8-.98a1.499 1.499 0 0 1 0-2.94c.47-.09.8-.5.8-.98V8.99c0-.55-.45-1-1-1Zm-4 0H8.19L17 4.48zm3 3.84c-1.2.57-2 1.79-2 3.16s.8 2.59 2 3.16V20h-4v-2h-1v2H4v-1.84c1.2-.57 2-1.79 2-3.16s-.8-2.59-2-3.16V10h11v1h1v-1h4z">
                                        </path>
                                        <path d="M15 12h1v2h-1zm0 3h1v2h-1z"></path>
                                    </svg>
                                    <span class="link-text">Tickets</span>
                                </a>
                            </li>
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'performance'); ?>" data-nav="performance">
                                <a href="?tab=performance" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="m6.89 12.45 1.05-2.1 3.14 7.06c.16.36.52.59.91.59h.05c.41-.02.77-.29.9-.68l2.98-8.93 1.11 3.89c.12.43.52.73.96.73h4v-2h-3.25l-1.79-6.27c-.12-.42-.5-.72-.94-.73a.98.98 0 0 0-.97.68l-3.18 9.55L8.91 7.6c-.16-.35-.5-.58-.89-.59-.41-.04-.74.21-.92.55l-1.72 3.45H2v2h4c.38 0 .73-.21.89-.55Z"></path>
                                    </svg>
                                    <span class="link-text">Performance</span>
                                </a>
                            </li>
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'messages'); ?>" data-nav="messages">
                                <a href="?tab=messages" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 2v.51l-8 6.22-8-6.22V6zM4 18V9.04l7.39 5.74c.18.14.4.21.61.21s.43-.07.61-.21L20 9.03v8.96H4Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Mailbox</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>
        <div class="sidebar-spacer"></div>
        <section class="showcase">
            <div class="page-content role-dashboard-page" id="page-dashboard">
                <div class="head">
                    <header>
                        <h1>Dashboard</h1>
                        <div class="search-bar-wrapper">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                                </path>
                            </svg>
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php
                $dashboard_card_keys = ['ongoing', 'processing', 'resolved', 'pending'];
                $dash_tickets = $active_tech_tickets;
                $dash_show_category = true;
                $status_counts = [
                    'pending' => 0,
                    'ongoing' => 0,
                    'processing' => 0,
                    'resolved' => 0,
                ];
                foreach ($tech_tickets as $ticket) {
                    $key = (string) ($ticket['status'] ?? '');
                    if (isset($status_counts[$key])) {
                        $status_counts[$key]++;
                    }
                }
                include __DIR__ . '/partials/role_dashboard.php';
                ?>
            </div>
            <div class="page-content" id="page-tickets">
                <div class="head">
                    <header>
                        <h1>Tickets</h1>
                        <div class="search-bar-wrapper">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                                </path>
                            </svg>
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php if ($ticket_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($ticket_flash); ?></div>
                <?php } ?>
                <div class="tickets-toolbar">
                    <?php echo ticket_priority_filter_markup('techn-tickets-priority', 'techn-tickets-body'); ?>
                </div>
                <div class="tickets-list tickets-list-techn-actions tickets-list-techn-actions--times">
                <div class="tickets-list-header">
                    <span class="tickets-col-id">ID</span>
                    <span class="tickets-col-category">Category</span>
                    <span class="tickets-col-subject">Subject</span>
                    <span class="tickets-col-description">Description</span>
                    <span class="tickets-col-status">Status</span>
                    <span class="tickets-col-queue">Queue timer</span>
                    <span class="tickets-col-resolution">Resolution Time</span>
                    <span class="tickets-col-action">Action</span>
                </div>
                <div class="tickets-list-body js-paged-table" id="techn-tickets-body" data-page-size="15">
                    <?php if (empty($listed_tickets)) { ?>
                    <div class="tickets-empty-state">
                        <p>No tickets assigned yet.</p>
                    </div>
                    <?php } else { ?>
                    <?php foreach ($listed_tickets as $ticket) {
                        $st = $ticket['status'];
                        $tid = (int) $ticket['id'];
                        $canEditStatus = in_array($st, $techn_statuses, true);
                        $queueLive = ticket_queue_timer_is_live($st);
                        $queueLabel = ticket_queue_timer_label(
                            $ticket['created_at'] ?? null,
                            $queueLive ? null : ($ticket['resolved_at'] ?? $ticket['responded_at'] ?? null),
                            $queueLive
                        );
                        $queueStart = strtotime((string) ($ticket['created_at'] ?? '')) ?: 0;
                        $resLabel = ticket_resolution_clock_label($ticket['created_at'] ?? null, $ticket['resolved_at'] ?? null);
                        $queueAttr = ($queueLive && $queueStart > 0) ? ' data-queue-start="' . (int) $queueStart . '"' : '';
                    ?>
                    <?php if ($canEditStatus) { ?>
                    <form class="ticket-row" action="../logic/ticket_techn_mngmnt.php" method="post"
                        data-ticket-id="<?php echo $tid; ?>"
                        data-status="<?php echo htmlspecialchars($st); ?>"
                        data-priority="<?php echo htmlspecialchars(ticket_priority_key($ticket['priority'] ?? '')); ?>">
                        <?php echo zpgc_csrf_field(); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                        <span class="tickets-col-id">#<?php echo $tid; ?></span>
                        <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
                        <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                        <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                        <span class="tickets-col-status">
                            <select name="status" class="admin-ticket-select ticket-select-status" data-select-tone="status" data-value="<?php echo htmlspecialchars($st); ?>" aria-label="Status for ticket <?php echo $tid; ?>">
                                <?php foreach ($techn_statuses as $opt) { ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"
                                    <?php echo ($st === $opt) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ticket_status_label($opt)); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </span>
                        <span class="tickets-col-queue"<?php echo $queueAttr; ?>><?php echo htmlspecialchars($queueLabel); ?></span>
                        <span class="tickets-col-resolution<?php echo (trim((string) $resLabel) === '—' || trim((string) $resLabel) === '') ? ' ticket-col-empty' : ''; ?>"><?php echo htmlspecialchars($resLabel); ?></span>
                        <span class="tickets-col-action">
                            <?php echo ticket_quick_view_button($ticket); ?>
                            <button type="submit" name="save_tech_ticket" class="btn-save-ticket">Save</button>
                            <button type="submit" class="ticket-icon-btn" formaction="../logic/ticket_replace_mngmnt.php" name="replace_me" aria-label="Replace" title="Ask admin to replace me">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 16H2v2h14v4l6-5-6-5zM8 1 2 6l6 5V7h14V5H8z"></path></svg>
                            </button>
                        </span>
                    </form>
                    <?php } else { ?>
                    <div class="ticket-row" data-ticket-id="<?php echo $tid; ?>" data-status="<?php echo htmlspecialchars($st); ?>" data-priority="<?php echo htmlspecialchars(ticket_priority_key($ticket['priority'] ?? '')); ?>">
                        <span class="tickets-col-id">#<?php echo $tid; ?></span>
                        <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
                        <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                        <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                        <span class="tickets-col-status">
                            <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                                <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                            </span>
                        </span>
                        <span class="tickets-col-queue"<?php echo $queueAttr; ?>><?php echo htmlspecialchars($queueLabel); ?></span>
                        <span class="tickets-col-resolution<?php echo (trim((string) $resLabel) === '—' || trim((string) $resLabel) === '') ? ' ticket-col-empty' : ''; ?>"><?php echo htmlspecialchars($resLabel); ?></span>
                        <span class="tickets-col-action">
                            <?php echo ticket_quick_view_button($ticket); ?>
                            <span class="techn-status-readonly">Closed by reporter</span>
                        </span>
                    </div>
                    <?php } ?>
                    <?php } ?>
                    <?php } ?>
                </div>
            </div>
                <?php
                $history_show_assigned = false;
                $history_title = 'Ticket History';
                $history_subtitle = 'Resolved and archived tickets';
                $history_show_times = true;
                $history_show_satisfaction = ticket_has_column($conn, 'satisfaction');
                $history_show_preview = true;
                $history_allow_rate = false;
                include __DIR__ . '/partials/ticket_history_list.php';
                ?>
            </div>
            <div class="page-content" id="page-performance">
                <div class="head">
                    <header>
                        <h1>Performance</h1>
                        <div class="search-bar-wrapper">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                                </path>
                            </svg>
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php
                include __DIR__ . '/partials/kpi_iso_panel.php';
                $performance_show_view = true;
                include __DIR__ . '/partials/performance_panel.php';
                ?>
            </div>
            <div class="page-content" id="page-messages">
                <div class="head">
                    <header>
                        <h1>Mailbox</h1>
                        <div class="search-bar-wrapper">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                                </path>
                            </svg>
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php if ($ticket_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($ticket_flash); ?></div>
                <?php } ?>
                <?php include 'mailbox_panel.php'; ?>
            </div>
                            <?php include __DIR__ . '/partials/profile_panel.php'; ?>
            <div class="page-content" id="page-settings">
                <div class="head">
                    <header>
                        <h1>Settings</h1>
                        <div class="search-bar-wrapper">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                                </path>
                            </svg>
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php include __DIR__ . '/partials/settings_panel.php'; ?>
            </div>
        </section>
    </main>
    <script src="../js/lazy_load.js?v=1.6.55"></script>
    <script src="../js/table_controls.js?v=1.6.88"></script>
    <script src="../js/ajax_forms.js?v=1.6.87"></script>
    <script src="../js/ticket_live.js?v=1.6.40"></script>
    <script src="../js/ticket_quick_view.js?v=1.6.87"></script>
    <script src="../js/behavior.js?v=1.6.87" defer></script>
    <script src="../js/performance_filter.js?v=1.6.22"></script>
</body>

</html>
