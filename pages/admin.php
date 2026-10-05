<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/priority_queue.php';
require_once '../logic/dashboard_stats.php';
require_once '../logic/ticket_times.php';
require_once '../logic/ticket_retention.php';
require_once '../logic/performance_report.php';
require_once '../logic/audit_log.php';
require_once '../logic/ticket_status.php';
require_once '../logic/ui_state.php';
require_role('admin');

$current_user_id = current_user_id($conn);
$tab = zpgc_ui_resolve_tab('admin', 'dashboard');
zpgc_ui_persist_redirect($tab);

ticket_ensure_archived_column($conn);
ticket_ensure_satisfaction_column($conn);
// Index ensure + archive backfill only on utilities (not every admin click).
if ($tab === 'utilities') {
    ticket_ensure_indexes($conn);
    ticket_retention_maybe_backfill($conn, 6);
    // Repair admin-created accounts that were Active but still unverified (blocked login).
    @$conn->query(
        "UPDATE users SET email_verified = 1
         WHERE status = 'active' AND COALESCE(email_verified, 0) = 0"
    );
}
$purge_rules = ticket_retention_rules_summary();
$purge_eligible = 0;
$purge_flash = '';
$auto_purge = null;
if ($tab === 'utilities') {
    // Count only on GET — never auto-delete during demos (use Run disposal batch).
    $purge_eligible = ticket_retention_count_eligible_cached($conn);
}

$role_labels = [
    'user' => 'User',
    'techn' => 'Technician',
    'admin' => 'Administrator',
];

$all_users = [];
$technicians = [];
$active_tickets = [];
$history_tickets = [];
$mailbox_tickets = [];
$recent_tickets = [];
$attention_tickets = [];
$replacement_requests = [];
$performance_categories = [];
$performance_log = [];
$audit_rows = [];
$queue_snapshot = [
    'bands' => [],
    'active_total' => 0,
    'waiting_total' => 0,
    'max_total' => 9,
    'borrow_m' => 0,
    'borrow_l' => 0,
];
$dashboard_charts = [
    'live' => false,
    'report' => [
        'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'submitted' => [0, 0, 0, 0, 0, 0, 0],
        'resolved' => [0, 0, 0, 0, 0, 0, 0],
    ],
    'categories' => [
        'labels' => ['Hardware', 'Software', 'Network', 'Account', 'Other'],
        'data' => [0, 0, 0, 0, 0],
    ],
    'severity' => [
        'labels' => ['Critical', 'Moderate', 'Low'],
        'data' => [0, 0, 0],
        'unprioritized' => 0,
    ],
    'satisfaction' => [
        'labels' => ['Very satisfied', 'Satisfied', 'Not sure', 'Not satisfied', 'Hate it'],
        'data' => [0, 0, 0, 0, 0],
        'note' => '',
    ],
];
$status_counts = [
    'pending' => 0,
    'ongoing' => 0,
    'processing' => 0,
    'awaiting_confirmation' => 0,
    'resolved' => 0,
];

$needsTickets = in_array($tab, ['tickets', 'messages'], true);
$needsDashboardList = ($tab === 'dashboard');
$needsTechnicians = in_array($tab, ['tickets', 'messages'], true);
$hasArchivedCol = ticket_has_column($conn, 'archived_at');

// Load accounts only on Utilities (filters / Activate) — not every admin tab.
if ($tab === 'utilities') {
    $result = $conn->query(
        'SELECT id, first_name, last_name, email, role, status FROM users ORDER BY id ASC LIMIT 500'
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
}

if ($tab === 'utilities') {
    audit_ready($conn);
    $auditResult = $conn->query(
        "SELECT a.action, a.detail, a.created_at, u.first_name, u.last_name
         FROM admin_audit a
         LEFT JOIN users u ON u.id = a.actor_id
         ORDER BY a.id DESC
         LIMIT 20"
    );
    if ($auditResult) {
        while ($row = $auditResult->fetch_assoc()) {
            $audit_rows[] = $row;
        }
    }
}

if ($needsTechnicians) {
    $tech_result = $conn->query(
        "SELECT id, first_name, last_name
         FROM users WHERE role = 'techn' AND status = 'active'
         ORDER BY last_name, first_name"
    );
    if ($tech_result) {
        while ($row = $tech_result->fetch_assoc()) {
            $technicians[] = $row;
        }
    }
}

if ($needsTickets || $needsDashboardList) {
    $ticketCols = 't.id, t.subject, t.description, t.status, t.priority, t.assigned_to'
        . ($hasArchivedCol ? ', t.archived_at' : '')
        . ',
            u.first_name, u.last_name,
            tech.first_name AS tech_first, tech.last_name AS tech_last';
    $activeWhere = "t.status <> 'resolved'";
    if ($hasArchivedCol) {
        $activeWhere .= ' AND t.archived_at IS NULL';
    }
    // Cap rows — unbounded JOINs were a major source of Azure lag.
    $activeLimit = $needsDashboardList && !$needsTickets ? 80 : 250;
    $activeSql = "SELECT {$ticketCols}
         FROM tickets t
         INNER JOIN users u ON t.user_id = u.id
         LEFT JOIN users tech ON t.assigned_to = tech.id
         WHERE {$activeWhere}
         ORDER BY t.id DESC
         LIMIT {$activeLimit}";
    $ticket_result = $conn->query($activeSql);
    if ($ticket_result) {
        while ($row = $ticket_result->fetch_assoc()) {
            $active_tickets[] = $row;
        }
    }

    if ($tab === 'tickets') {
        $historyWhere = "t.status = 'resolved'";
        if ($hasArchivedCol) {
            $historyWhere = "(t.status = 'resolved' OR t.archived_at IS NOT NULL)";
        }
        $historySql = "SELECT {$ticketCols}
             FROM tickets t
             INNER JOIN users u ON t.user_id = u.id
             LEFT JOIN users tech ON t.assigned_to = tech.id
             WHERE {$historyWhere}
             ORDER BY t.id DESC
             LIMIT 150";
        $history_result = $conn->query($historySql);
        if ($history_result) {
            while ($row = $history_result->fetch_assoc()) {
                $history_tickets[] = $row;
            }
        }
    }

    $mailbox_tickets = $active_tickets;
    $recent_tickets = $history_tickets;

    if ($tab === 'tickets') {
        $replaceTable = $conn->query("SHOW TABLES LIKE 'replacement_requests'");
        if ($replaceTable && $replaceTable->num_rows > 0) {
            $lastReplaceFix = (int) ($_SESSION['_replace_fix_at'] ?? 0);
            if ($lastReplaceFix <= 0 || (time() - $lastReplaceFix) > 300) {
                $_SESSION['_replace_fix_at'] = time();
                $conn->query(
                    "UPDATE replacement_requests r
                     INNER JOIN tickets t ON t.id = r.ticket_id
                     SET r.status = 'resolved'
                     WHERE r.status = 'pending'
                       AND t.assigned_to IS NOT NULL
                       AND CAST(t.assigned_to AS UNSIGNED) <> 0
                       AND CAST(t.assigned_to AS UNSIGNED) <> CAST(r.techn_id AS UNSIGNED)"
                );
            }
            $replaceRows = $conn->query(
                "SELECT r.id, r.ticket_id, r.created_at, t.subject,
                        u.first_name, u.last_name
                 FROM replacement_requests r
                 INNER JOIN tickets t ON t.id = r.ticket_id
                 INNER JOIN users u ON u.id = r.techn_id
                 WHERE r.status = 'pending'
                 ORDER BY r.id DESC
                 LIMIT 100"
            );
            if ($replaceRows) {
                while ($row = $replaceRows->fetch_assoc()) {
                    $replacement_requests[] = $row;
                }
            }
        }
    }
    $attention_ticket_ids = [];
    foreach ($replacement_requests as $req) {
        $attention_ticket_ids[] = (int) $req['ticket_id'];
    }
    $attention_tickets = ticket_sort_for_attention($active_tickets, $attention_ticket_ids);
}

if ($tab === 'dashboard' || $tab === 'tickets') {
    $queue_snapshot = priority_queue_snapshot($conn);
}
if ($tab === 'dashboard') {
    $dashboard_charts = dashboard_chart_data($conn);
    $count_result = $conn->query('SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status');
    if ($count_result) {
        while ($row = $count_result->fetch_assoc()) {
            $key = $row['status'];
            if (isset($status_counts[$key])) {
                $status_counts[$key] = (int) $row['cnt'];
            }
        }
    }
}
if ($tab === 'performance') {
    $performance_categories = performance_category_rows($conn);
    $performance_log = performance_resolved_log($conn, 100);
}

$utilities_action = $_GET['action'] ?? '';
$edit_id = (int) ($_GET['edit_id'] ?? 0);
$editing_user = null;
if ($edit_id > 0 && $tab === 'utilities') {
    foreach ($all_users as $u) {
        if ((int) $u['id'] === $edit_id) {
            $editing_user = $u;
            break;
        }
    }
}
$utilities_success = $_SESSION['utilities_success'] ?? '';
$utilities_error = $_SESSION['utilities_error'] ?? '';
$ticket_flash = $_SESSION['ticket_flash'] ?? '';
unset($_SESSION['utilities_success'], $_SESSION['utilities_error'], $_SESSION['ticket_flash']);
$ui_theme = current_ui_theme();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main_interface.css?v=1.6.18">
    <link rel="stylesheet" href="../css/dashboard_extra.css?v=1.6.18">
    <link rel="stylesheet" href="../css/theme.css?v=1.6.18">
    <?php include __DIR__ . '/partials/critical_ui_fixes.php'; ?>
    <style id="zpgc-tickets-table-mobile">
        @media (max-width: 768px) {
            body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-admin-five {
                overflow: auto !important;
                -webkit-overflow-scrolling: touch;
                max-width: 100% !important;
                max-height: calc(100dvh - 250px - 58px) !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five .tickets-list-header {
                position: sticky !important;
                top: 0 !important;
                z-index: 8 !important;
                background: #f5f5f5 !important;
            }
            html[data-theme="dark"] body[data-page="tickets"] #page-tickets .tickets-list-admin-five .tickets-list-header {
                background: #2a2a32 !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five .tickets-list-header,
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five form.ticket-row:not(.ticket-row-filtered-out) {
                display: grid !important;
                grid-template-columns: 56px minmax(110px, 1.1fr) minmax(130px, 1.3fr) 120px 110px minmax(130px, 1fr) 72px !important;
                min-width: 920px !important;
                width: 920px !important;
                max-width: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            html[data-theme="dark"] body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-admin-five {
                background: #222228 !important;
            }
            html[data-theme="dark"] body[data-page="tickets"] #page-tickets .tickets-list-admin-five form.ticket-row:not(.ticket-row-filtered-out) {
                background: transparent !important;
                color: #f2f2f7 !important;
            }
            html[data-theme="dark"] body[data-page="tickets"] #page-tickets .tickets-list-admin-five .ticket-row span {
                color: #f2f2f7 !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five .tickets-list-header {
                display: grid !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five form.ticket-row > span::before {
                content: none !important;
                display: none !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five form.ticket-row > span {
                grid-column: auto !important;
            }
            body[data-page="tickets"] #page-tickets .tickets-list-admin-five form.ticket-row.ticket-row-filtered-out {
                display: none !important;
            }
        }
    </style>
    <title>ZPGC Services | Administrator</title>
</head>

<body data-page="<?php echo htmlspecialchars($tab); ?>" data-role="admin" data-web-base="<?php echo htmlspecialchars(zpgc_web_base(), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'utilities'); ?>" data-nav="utilities">
                                <a href="?tab=utilities" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20.71 6.04a.99.99 0 0 0-.9.27l-3.18 3.18-2.12-2.12 3.18-3.18a.98.98 0 0 0 .27-.9c-.07-.33-.29-.6-.6-.73A7.47 7.47 0 0 0 9.2 4.19a7.49 7.49 0 0 0-1.86 7.52L2.3 16.75c-.19.19-.29.44-.29.71s.11.52.29.71l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l5.04-5.04c2.64.82 5.53.12 7.52-1.86a7.47 7.47 0 0 0 1.63-8.16c-.13-.31-.4-.53-.73-.6Zm-2.32 7.34a5.51 5.51 0 0 1-5.98 1.2c-.37-.15-.8-.07-1.09.22l-4.78 4.78-2.12-2.12 4.78-4.78c.29-.29.37-.71.22-1.09a5.47 5.47 0 0 1 1.2-5.98 5.5 5.5 0 0 1 4.41-1.59l-2.65 2.65a.996.996 0 0 0 0 1.41l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l2.65-2.65c.16 1.61-.4 3.23-1.59 4.42Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Utilities</span>
                                </a>
                            </li>
                            <li class="nav-list-item<?php echo zpgc_nav_selected_class($tab, 'performance'); ?>" data-nav="performance">
                                <a href="?tab=performance" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5">
                                        </path>
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
            <div class="page-content" id="page-dashboard">
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
                <?php if (!empty($replacement_requests)) { ?>
                <div class="replacement-notice">
                    <?php foreach ($replacement_requests as $req) { ?>
                    <p>Technician <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?> asked to be replaced on ticket #<?php echo (int) $req['ticket_id']; ?> (<?php echo htmlspecialchars($req['subject']); ?>). Assign another technician from Tickets.</p>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php include __DIR__ . '/partials/dashboard_status_cards.php'; ?>
                <?php include __DIR__ . '/partials/dashboard_charts.php'; ?>
                <?php include __DIR__ . '/partials/admin_dashboard_history.php'; ?>
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
                <div class="tickets-toolbar">
                    <div class="tickets-filter-tabs" id="admin-tickets-filter-tabs">
                        <button type="button" class="filter-tab active-tab" data-filter="all">Active</button>
                        <button type="button" class="filter-tab" data-filter="pending">Pending</button>
                        <button type="button" class="filter-tab" data-filter="ongoing">Ongoing</button>
                        <button type="button" class="filter-tab" data-filter="processing">Processing</button>
                        <button type="button" class="filter-tab" data-filter="awaiting_confirmation">Confirming</button>
                        <button type="button" class="filter-tab" data-filter="replacement">Replacement</button>
                    </div>
                </div>
                <?php if ($ticket_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($ticket_flash); ?></div>
                <?php } ?>
                <?php include __DIR__ . '/partials/priority_queue_panel.php'; ?>
                <div class="tickets-list tickets-list-admin-five">
                    <div class="tickets-list-header">
                        <span class="tickets-col-id">ID</span>
                        <span class="tickets-col-subject">Subject</span>
                        <span class="tickets-col-description">Description</span>
                        <span class="tickets-col-status">Status</span>
                        <span class="tickets-col-priority">Priority</span>
                        <span class="tickets-col-assigned">Assigned To</span>
                        <span class="tickets-col-action">Action</span>
                    </div>
                    <div class="tickets-list-body" id="admin-tickets-body">
                        <?php if (empty($attention_tickets)) {?>
                        <div class="tickets-empty-state">
                            <p>No tickets found.</p>
                        </div>
                        <?php } else {?>
                        <?php foreach ($attention_tickets as $ticket) {?>
                        <?php
                            $tid = (int) $ticket['id'];
                            $st = $ticket['status'];
                            $pri = $ticket['priority'] ?? '';
                            $needsReplace = false;
                            foreach ($replacement_requests as $req) {
                                if ((int) $req['ticket_id'] === $tid) {
                                    $needsReplace = true;
                                    break;
                                }
                            }
                        ?>
                        <form class="ticket-row<?php echo $needsReplace ? ' ticket-needs-replace' : ''; ?>" action="../logic/ticket_admin_mngmnt.php" method="post"
                            data-status="<?php echo htmlspecialchars($st); ?>"
                            data-replace="<?php echo $needsReplace ? '1' : '0'; ?>">
                            <?php echo zpgc_csrf_field(); ?>
                            <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                            <span class="tickets-col-id">#<?php echo $tid; ?></span>
                            <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                            <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                            <span class="tickets-col-status">
                                <select name="status" class="admin-ticket-select" aria-label="Status for ticket <?php echo $tid; ?>">
                                    <?php foreach (['pending', 'ongoing', 'processing', 'awaiting_confirmation', 'resolved'] as $opt) { ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($st === $opt) ? 'selected' : ''; ?>>
                                        <?php
                                        if ($opt === 'awaiting_confirmation') {
                                            echo 'Confirming';
                                        } else {
                                            echo ucfirst($opt);
                                        }
                                        ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </span>
                            <span class="tickets-col-priority">
                                <select name="priority" class="admin-ticket-select" aria-label="Priority for ticket <?php echo $tid; ?>">
                                    <option value="" <?php echo ($pri === '' || $pri === null) ? 'selected' : ''; ?>>None</option>
                                    <?php foreach (['critical' => 'Critical', 'moderate' => 'Moderate', 'low' => 'Low'] as $pval => $plabel) { ?>
                                    <option value="<?php echo $pval; ?>" <?php echo ($pri === $pval) ? 'selected' : ''; ?>>
                                        <?php echo $plabel; ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </span>
                            <span class="tickets-col-assigned">
                                <select name="assigned_to" class="admin-ticket-select admin-ticket-select-assign"
                                    aria-label="Assign technician for ticket <?php echo $tid; ?>">
                                    <option value="" <?php echo empty($ticket['assigned_to']) ? 'selected' : ''; ?>>
                                        Unassigned
                                    </option>
                                    <?php foreach ($technicians as $tech) { ?>
                                    <option value="<?php echo (int) $tech['id']; ?>"
                                        <?php echo ((int) ($ticket['assigned_to'] ?? 0) === (int) $tech['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </span>
                            <span class="tickets-col-action">
                                <button type="submit" name="save_ticket" class="btn-save-ticket">Save</button>
                            </span>
                        </form>
                        <?php }?>
                        <?php }?>
                    </div>
                </div>
                <?php
                $history_show_assigned = true;
                $history_title = 'Ticket History';
                $history_subtitle = 'Resolved and archived tickets';
                $history_empty = 'No resolved tickets in history yet.';
                include __DIR__ . '/partials/ticket_history_list.php';
                ?>
            </div>
            <div class="page-content" id="page-utilities">
                <div class="head">
                    <header>
                        <h1>Utilities</h1>
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

                <?php if ($utilities_success !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($utilities_success); ?></div>
                <?php } ?>
                <?php if ($purge_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($purge_flash); ?></div>
                <?php } ?>
                <?php if ($utilities_error !== '') { ?>
                <div class="utilities-notice-error"><?php echo htmlspecialchars($utilities_error); ?></div>
                <?php } ?>

                <div class="ticket-retention-card">
                    <div class="ticket-retention-copy">
                        <h2>Resolved ticket retention</h2>
                        <p class="form-subtitle">
                            Resolved tickets are archived automatically. Eligible archived tickets are permanently disposed
                            in batches (max <?php echo (int) $purge_rules['batch']; ?> per run).
                        </p>
                        <ul class="ticket-retention-rules">
                            <li>Auto-archive when a ticket becomes <strong>resolved</strong>.</li>
                            <li>Dispose rated archived tickets after <strong><?php echo (int) $purge_rules['rated_days']; ?> days</strong>.</li>
                            <li>Dispose unrated archived tickets after <strong><?php echo (int) $purge_rules['unrated_days']; ?> days</strong>.</li>
                            <li>Disposal runs only when an admin clicks <strong>Run disposal batch</strong> (no surprise deletes).</li>
                        </ul>
                    </div>
                    <div class="ticket-retention-footer">
                        <div class="ticket-retention-meta">
                            <span class="ticket-retention-meta-label">Eligible for disposal</span>
                            <span class="ticket-retention-meta-value"><?php echo (int) $purge_eligible; ?></span>
                            <span class="ticket-retention-meta-note">Batch capacity <?php echo (int) $purge_rules['batch']; ?></span>
                        </div>
                        <form class="ticket-retention-form" action="../logic/ticket_purge_mngmnt.php" method="post"
                            onsubmit="return confirm('Permanently dispose up to <?php echo (int) $purge_rules['batch']; ?> archived resolved tickets that meet retention rules?\n\nThis cannot be undone.');">
                            <?php echo zpgc_csrf_field(); ?>
                            <button type="submit" name="dispose_archived_tickets" value="1" class="btn-dispose-batch">
                                Run disposal batch
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($utilities_action === 'add') { ?>
                <div class="user-form-card">
                    <h2>Add User</h2>
                    <p class="form-subtitle">Create a new account directly — it is active immediately.</p>
                    <form class="user-form" action="../logic/user_admin_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                        <div class="user-form-row">
                            <div class="user-form-field">
                                <label for="add_first_name">First Name</label>
                                <input type="text" id="add_first_name" name="first_name" required>
                            </div>
                            <div class="user-form-field">
                                <label for="add_last_name">Last Name</label>
                                <input type="text" id="add_last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="user-form-field">
                            <label for="add_email">Email Address</label>
                            <input type="email" id="add_email" name="email" required>
                        </div>
                        <div class="user-form-row">
                            <div class="user-form-field">
                                <label for="add_role">Role</label>
                                <select id="add_role" name="role" required>
                                    <option value="" disabled selected>Select a role</option>
                                    <option value="user">User</option>
                                    <option value="techn">Technician</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="user-form-field">
                                <label for="add_password">Temporary Password</label>
                                <input type="password" id="add_password" name="password" minlength="8" required>
                                <small>At least 8 characters.</small>
                            </div>
                        </div>
                        <div class="user-form-actions">
                            <a href="?tab=utilities" class="btn-cancel-user">Cancel</a>
                            <button type="submit" name="add_user" class="btn-new-ticket">Create Account</button>
                        </div>
                    </form>
                </div>

                <?php } elseif ($editing_user) { ?>
                <div class="user-form-card">
                    <h2>Edit User</h2>
                    <p class="form-subtitle">Update this account's name, email, or role.</p>
                    <form class="user-form" action="../logic/user_admin_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $editing_user['id']; ?>">
                        <div class="user-form-row">
                            <div class="user-form-field">
                                <label for="edit_first_name">First Name</label>
                                <input type="text" id="edit_first_name" name="first_name"
                                    value="<?php echo htmlspecialchars($editing_user['first_name']); ?>" required>
                            </div>
                            <div class="user-form-field">
                                <label for="edit_last_name">Last Name</label>
                                <input type="text" id="edit_last_name" name="last_name"
                                    value="<?php echo htmlspecialchars($editing_user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="user-form-field">
                            <label for="edit_email">Email Address</label>
                            <input type="email" id="edit_email" name="email"
                                value="<?php echo htmlspecialchars($editing_user['email']); ?>" required>
                        </div>
                        <div class="user-form-field">
                            <label for="edit_role">Role</label>
                            <select id="edit_role" name="role" required>
                                <?php foreach ($role_labels as $val => $label) { ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"
                                    <?php echo $editing_user['role'] === $val ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="user-form-actions">
                            <a href="?tab=utilities" class="btn-cancel-user">Cancel</a>
                            <button type="submit" name="edit_user" class="btn-new-ticket">Save Changes</button>
                        </div>
                    </form>
                </div>

                <?php } else { ?>
                <div class="tickets-toolbar">
                    <div class="tickets-filter-tabs" id="utilities-filter-tabs">
                        <button type="button" class="filter-tab active-tab" data-filter="all">All</button>
                        <button type="button" class="filter-tab" data-filter="user">User</button>
                        <button type="button" class="filter-tab" data-filter="techn">Technician</button>
                        <button type="button" class="filter-tab" data-filter="admin">Administrator</button>
                        <button type="button" class="filter-tab" data-filter="pending">Pending Approval</button>
                    </div>
                    <a href="?tab=utilities&action=add" class="btn-new-ticket">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path>
                        </svg>
                        Add User
                    </a>
                </div>

                <div class="tickets-list tickets-list-utilities">
                    <div class="tickets-list-header">
                        <span class="ucol-id">ID</span>
                        <span class="ucol-name">Name</span>
                        <span class="ucol-email">Email</span>
                        <span class="ucol-role">Role</span>
                        <span class="ucol-status">Status</span>
                        <span class="ucol-action">Actions</span>
                    </div>
                    <div class="tickets-list-body" id="utilities-users-body">
                        <?php if (empty($all_users)) { ?>
                        <div class="tickets-empty-state">
                            <p>No user accounts found.</p>
                        </div>
                        <?php } else { ?>
                        <?php foreach ($all_users as $u) {
                            $isActive = ($u['status'] === 'active');
                            if (isset($role_labels[$u['role']])) {
                                $roleLabel = $role_labels[$u['role']];
                            } else {
                                $roleLabel = ucfirst($u['role']);
                            }
                            $deleteConfirmName = htmlspecialchars(
                                json_encode($u['first_name'] . ' ' . $u['last_name']),
                                ENT_QUOTES
                            );
                        ?>
                        <div class="ticket-row"
                            data-role="<?php echo htmlspecialchars($u['role']); ?>"
                            data-status="<?php echo htmlspecialchars($u['status']); ?>">
                            <span class="ucol-id">#<?php echo (int) $u['id']; ?></span>
                            <span class="ucol-name">
                                <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                            </span>
                            <span class="ucol-email">
                                <?php echo htmlspecialchars($u['email']); ?>
                            </span>
                            <span class="ucol-role">
                                <span class="profile-role-badge role-<?php echo htmlspecialchars($u['role']); ?>">
                                    <?php echo htmlspecialchars($roleLabel); ?>
                                </span>
                            </span>
                            <span class="ucol-status">
                                <span class="status-badge <?php echo $isActive ? 'active-account' : 'inactive-account'; ?>">
                                    <?php echo $isActive ? 'Active' : 'Pending'; ?>
                                </span>
                            </span>
                            <span class="ucol-action">
                                <a href="?tab=utilities&edit_id=<?php echo (int) $u['id']; ?>" class="btn-assign">Edit</a>
                                <form action="../logic/user_admin_mngmnt.php" method="post" class="ucol-action-form">
<?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="set_status" class="btn-update-status">
                                        <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <?php if ($u['role'] !== 'admin') { ?>
                                <form action="../logic/user_admin_mngmnt.php" method="post" class="ucol-action-form"
                                    onsubmit="return confirm('Permanently delete ' + <?php echo $deleteConfirmName; ?> + '\'s account? This can\'t be undone.');">
                                    <?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                                </form>
                                <?php } else { ?>
                                <span class="ucol-action-spacer" aria-hidden="true"></span>
                                <?php } ?>
                            </span>
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="audit-panel">
                    <h2>Audit security check</h2>
                    <div class="audit-row audit-head">
                        <span>When</span>
                        <span>Who</span>
                        <span>Action</span>
                        <span>Detail</span>
                    </div>
                    <?php if (empty($audit_rows)) { ?>
                    <p class="audit-empty">No admin actions recorded yet. Edit, activate, or save a ticket to add a row.</p>
                    <?php } else { foreach ($audit_rows as $log) { ?>
                    <div class="audit-row">
                        <span data-label="When"><?php echo htmlspecialchars((string) $log['created_at']); ?></span>
                        <span data-label="Who"><?php echo htmlspecialchars(trim((string) $log['first_name'] . ' ' . (string) $log['last_name'])); ?></span>
                        <span data-label="Action"><?php echo htmlspecialchars((string) $log['action']); ?></span>
                        <span data-label="Detail"><?php echo htmlspecialchars((string) $log['detail']); ?></span>
                    </div>
                    <?php } } ?>
                </div>
                <?php } ?>
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
                <div class="perf-section-header">
                    <h2>Resolved by Category</h2>
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
                        <?php foreach ($performance_categories as $perfRow) { ?>
                        <div class="ticket-row">
                            <span class="pcol-category"><?php echo htmlspecialchars($perfRow['label']); ?></span>
                            <span class="pcol-resolved"><?php echo (int) $perfRow['total']; ?></span>
                            <span class="pcol-critical"><span class="perf-count critical"><?php echo (int) $perfRow['critical']; ?></span></span>
                            <span class="pcol-moderate"><span class="perf-count moderate"><?php echo (int) $perfRow['moderate']; ?></span></span>
                            <span class="pcol-low"><span class="perf-count low"><?php echo (int) $perfRow['low']; ?></span></span>
                        </div>
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
                <div class="tickets-list perf-detail-list">
                    <div class="tickets-list-header">
                        <span class="dcol-category">Category</span>
                        <span class="dcol-ticket">Ticket ID</span>
                        <span class="dcol-subject">Subject</span>
                        <span class="dcol-description">Description</span>
                        <span class="dcol-reg">Registration Date &amp; Time</span>
                        <span class="dcol-response">Response Time</span>
                        <span class="dcol-resolution">Resolution Time</span>
                        <span class="dcol-severity">Severity Level</span>
                    </div>
                    <div class="tickets-list-body" id="perf-log-body">
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
                            ]));
                        ?>
                        <div class="ticket-row" data-perf-search="<?php echo htmlspecialchars($searchBlob); ?>">
                            <span class="dcol-category"><?php echo htmlspecialchars($logRow['category_label']); ?></span>
                            <span class="dcol-ticket">#<?php echo (int) $logRow['id']; ?></span>
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
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>
                </div>
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
                <div class="settings-container">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>Appearance</h2>
                            <p>Stay in control of how the dashboard looks on this account.</p>
                        </div>
                        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form">
<?php echo zpgc_csrf_field(); ?>
                            <div class="settings-pref-row">
                                <span class="settings-pref-label">Theme</span>
                                <select name="theme" class="settings-pref-select">
                                    <option value="light" <?php echo $ui_theme === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo $ui_theme === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                </select>
                            </div>
                            <button type="submit" name="save_appearance" class="btn-save-ticket settings-save-btn">Save appearance</button>
                        </form>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>Security</h2>
                        </div>
                        <h3 class="settings-subheading">Change password</h3>
                        <p class="settings-subtitle">Use at least 8 characters. You will stay signed in after updating.</p>
                        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form settings-password-form">
<?php echo zpgc_csrf_field(); ?>
                            <div class="settings-form-field">
                                <label for="settings-current-password">Current password</label>
                                <input type="password" id="settings-current-password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="settings-form-field">
                                <label for="settings-new-password">New password</label>
                                <input type="password" id="settings-new-password" name="new_password" minlength="8" required autocomplete="new-password">
                            </div>
                            <div class="settings-form-field">
                                <label for="settings-confirm-password">Confirm new password</label>
                                <input type="password" id="settings-confirm-password" name="confirm_password" minlength="8" required autocomplete="new-password">
                            </div>
                            <button type="submit" name="change_password" class="btn-save-ticket settings-save-btn">Update password</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        window.DASHBOARD_CHART_DATA = <?php echo json_encode($dashboard_charts, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../js/lazy_load.js?v=1.6.18"></script>
    <script src="../js/utilities_filter.js?v=1.6.18"></script>
    <script src="../js/behavior.js?v=1.6.20" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('perf-log-search');
            var body = document.getElementById('perf-log-body');
            if (!input || !body) return;

            var emptyEl = null;
            function ensureEmptyNotice() {
                if (emptyEl) return emptyEl;
                emptyEl = document.createElement('div');
                emptyEl.className = 'tickets-empty-state perf-filter-empty perf-filter-empty--hidden';
                emptyEl.innerHTML = '<p>No matching tickets.</p>';
                body.appendChild(emptyEl);
                return emptyEl;
            }

            function applyPerfFilter() {
                var q = (input.value || '').toLowerCase().trim();
                var visible = 0;
                body.querySelectorAll('.ticket-row').forEach(function (row) {
                    var blob = row.getAttribute('data-perf-search') || '';
                    var match = q === '' || blob.indexOf(q) !== -1;
                    row.classList.toggle('perf-row-hidden', !match);
                    if (match) visible += 1;
                });
                var notice = ensureEmptyNotice();
                var hasRows = body.querySelectorAll('.ticket-row').length > 0;
                var showEmpty = hasRows && q !== '' && visible === 0;
                notice.classList.toggle('perf-filter-empty--hidden', !showEmpty);
            }

            input.addEventListener('input', applyPerfFilter);
            input.addEventListener('search', applyPerfFilter);

            // Performance search only matters on that tab; wire immediately (tiny).
            if (window.ZpgcLazy) {
                window.ZpgcLazy.whenTab('performance', function () {
                    applyPerfFilter();
                });
            }
        });
    </script>
    <!-- chart.umd.js, dashboard charts, tickets/utilities filters: lazy-loaded per tab via lazy_load.js -->
</body>

</html>
