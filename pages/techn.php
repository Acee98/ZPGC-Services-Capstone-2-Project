<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/ui_state.php';
require_once '../logic/ticket_status.php';
require_once '../logic/ticket_files.php';
require_once '../logic/ticket_times.php';
require_once '../logic/ticket_retention.php';
require_role('techn');

$current_user_id = current_user_id($conn);
$tab = zpgc_ui_resolve_tab('techn', 'dashboard');
zpgc_ui_persist_redirect($tab);
$ticket_flash = $_SESSION['ticket_flash'] ?? '';
unset($_SESSION['ticket_flash']);

ticket_ensure_archived_column($conn);
ticket_ensure_indexes($conn);
ticket_retention_maybe_backfill($conn, 1);
$tech_tickets = [];
$hasArchived = ticket_has_column($conn, 'archived_at');
$cols = 'id, subject, description, category, priority, status'
    . ($hasArchived ? ', archived_at' : '');
$stmt = $conn->prepare(
    "SELECT {$cols} FROM tickets WHERE assigned_to = ? ORDER BY id DESC LIMIT 200"
);
$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tech_tickets[] = $row;
}
$stmt->close();
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
$ui_theme = current_ui_theme();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main_interface.css?v=1.6.6">
    <link rel="stylesheet" href="../css/theme.css?v=1.6.6">
    <?php include __DIR__ . '/partials/critical_ui_fixes.php'; ?>
    <title>ZPGC Services | Technician</title>
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
                <div class="tickets-list tickets-list-techn-actions">
                <div class="tickets-list-header">
                    <span class="tickets-col-id">ID</span>
                    <span class="tickets-col-subject">Subject</span>
                    <span class="tickets-col-description">Description</span>
                    <span class="tickets-col-status">Status</span>
                    <span class="tickets-col-action">Action</span>
                </div>
                <div class="tickets-list-body" id="techn-tickets-body">
                    <?php if (empty($listed_tickets)) { ?>
                    <div class="tickets-empty-state">
                        <p>No tickets assigned yet.</p>
                    </div>
                    <?php } else { ?>
                    <?php foreach ($listed_tickets as $ticket) {
                        $st = $ticket['status'];
                        $tid = (int) $ticket['id'];
                        $canEditStatus = in_array($st, $techn_statuses, true);
                    ?>
                    <?php if ($canEditStatus) { ?>
                    <form class="ticket-row" action="../logic/ticket_techn_mngmnt.php" method="post"
                        data-status="<?php echo htmlspecialchars($st); ?>">
                        <?php echo zpgc_csrf_field(); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                        <span class="tickets-col-id">#<?php echo $tid; ?></span>
                        <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                        <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                        <span class="tickets-col-status">
                            <select name="status" class="admin-ticket-select" aria-label="Status for ticket <?php echo $tid; ?>">
                                <?php foreach ($techn_statuses as $opt) { ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"
                                    <?php echo ($st === $opt) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ticket_status_label($opt)); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </span>
                        <span class="tickets-col-action">
                            <button type="submit" name="save_tech_ticket" class="btn-save-ticket">Save</button>
                            <button type="submit" class="ticket-icon-btn" formaction="../logic/ticket_replace_mngmnt.php" name="replace_me" aria-label="Replace" title="Ask admin to replace me">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 16H2v2h14v4l6-5-6-5zM8 1 2 6l6 5V7h14V5H8z"></path></svg>
                            </button>
                        </span>
                    </form>
                    <?php } else { ?>
                    <div class="ticket-row" data-status="<?php echo htmlspecialchars($st); ?>">
                        <span class="tickets-col-id">#<?php echo $tid; ?></span>
                        <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                        <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                        <span class="tickets-col-status">
                            <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                                <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                            </span>
                        </span>
                        <span class="tickets-col-action">
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
                include __DIR__ . '/partials/ticket_history_list.php';
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
    <script src="../js/lazy_load.js?v=1.6.6"></script>
    <script src="../js/behavior.js?v=1.6.6" defer></script>
</body>

</html>
