<?php
require_once '../logic/session_config.php';
require_once '../logic/version.php';
require_once '../logic/i18n.php';
require_once '../logic/config.php';
require_once '../logic/priority_queue.php';
require_once '../logic/dashboard_stats.php';
require_once '../logic/ticket_times.php';
require_once '../logic/ticket_retention.php';
require_once '../logic/audit_log.php';
require_once '../logic/ticket_status.php';
require_once '../logic/performance_report.php';
require_once '../logic/iso20000.php';
require_once '../logic/ticket_crypto.php';
require_once '../logic/ai_reclassify.php';
require_once '../logic/account_kind.php';
require_once '../logic/auth_mail.php';
require_once '../logic/capacity.php';
require_once '../logic/ui_state.php';
require_once '../logic/techn_apply.php';
require_role('admin');

$current_user_id = current_user_id($conn);
$tab = zpgc_ui_resolve_tab('admin', 'dashboard');
zpgc_ui_persist_redirect($tab);
zpgc_ui_no_store();

ticket_ensure_archived_column($conn);
ticket_ensure_satisfaction_column($conn);
ticket_retention_maybe_auto_purge($conn);
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

$role_labels = [
    'user' => 'Student / Staff',
    'techn' => 'Technician',
    'admin' => 'Administrator',
];
account_kind_ensure($conn);
ticket_crypto_ensure_columns($conn);
if ($tab === 'performance' || $tab === 'tickets') {
    ticket_crypto_migrate_batch($conn, 15);
    ai_reclassify_pending($conn, 4);
}

$all_users = [];
$techn_applications = [];
$techn_specialties = [];
$techn_spec_by_user = [];
$technicians = [];
$active_tickets = [];
$history_tickets = [];
$mailbox_tickets = [];
$recent_tickets = [];
$attention_tickets = [];
$replacement_requests = [];
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
$performance_categories = performance_category_rows($conn, 0);
$performance_log = performance_resolved_log($conn, 150, 0);
$performance_technicians = performance_technician_rows($conn);
$office_kpis = performance_office_kpis($conn, 0);
$iso_last_capacity = iso20000_last_capacity($conn);
$iso_clauses = iso20000_clauses($conn, $office_kpis, $iso_last_capacity);
capacity_ensure_table($conn);
$hasArchivedCol = ticket_has_column($conn, 'archived_at');

// Load accounts only on Utilities (filters / Activate) — not every admin tab.
if ($tab === 'utilities') {
    $result = $conn->query(
        'SELECT id, first_name, last_name, email, role, status, account_kind FROM users ORDER BY id ASC LIMIT 500'
    );
    if (!$result) {
        $result = $conn->query(
            'SELECT id, first_name, last_name, email, role, status FROM users ORDER BY id ASC LIMIT 500'
        );
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
    techn_apply_expire_stale($conn, false);
    $techn_applications = techn_apply_list_open($conn);
    $techn_specialties = techn_apply_specialties();
    $specMap = $conn->query(
        'SELECT user_id, specialty, proposed_specialty, status FROM technician_applications'
    );
    $techn_spec_by_user = [];
    if ($specMap) {
        while ($specRow = $specMap->fetch_assoc()) {
            $techn_spec_by_user[(int) $specRow['user_id']] = $specRow;
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
        "SELECT u.id, u.email, a.specialty
         FROM users u
         LEFT JOIN technician_applications a ON a.user_id = u.id
         WHERE u.role = 'techn' AND u.status = 'active'
         ORDER BY u.email"
    );
    if ($tech_result) {
        while ($row = $tech_result->fetch_assoc()) {
            $technicians[] = $row;
        }
    }
}

if ($needsTickets || $needsDashboardList) {
    $ticketCols = 't.id, t.subject, t.description, t.category, t.status, t.priority, t.assigned_to'
        . ($hasArchivedCol ? ', t.archived_at' : '');
    if (ticket_has_column($conn, 'created_at')) {
        $ticketCols .= ', t.created_at';
    }
    if (ticket_has_column($conn, 'resolved_at')) {
        $ticketCols .= ', t.resolved_at';
    }
    if (ticket_has_column($conn, 'responded_at')) {
        $ticketCols .= ', t.responded_at';
    }
    if (ticket_has_column($conn, 'satisfaction')) {
        $ticketCols .= ', t.satisfaction';
    }
    $ticketCols .= ',
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

    if ($tab === 'tickets' || $tab === 'dashboard') {
        $historyWhere = "t.status = 'resolved'";
        if ($hasArchivedCol) {
            $historyWhere = "(t.status = 'resolved' OR t.archived_at IS NOT NULL)";
        }
        $historyLimit = $tab === 'dashboard' ? 80 : 150;
        $historySql = "SELECT {$ticketCols}
             FROM tickets t
             INNER JOIN users u ON t.user_id = u.id
             LEFT JOIN users tech ON t.assigned_to = tech.id
             WHERE {$historyWhere}
             ORDER BY t.id DESC
             LIMIT {$historyLimit}";
        $history_result = $conn->query($historySql);
        if ($history_result) {
            while ($row = $history_result->fetch_assoc()) {
                $history_tickets[] = $row;
            }
        }
    }

    ticket_reveal_rows($active_tickets);
    ticket_reveal_rows($history_tickets);
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
                    if (function_exists('ticket_reveal_row')) {
                        ticket_reveal_row($row);
                    }
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
    $hate_tickets = [];
    if (ticket_has_column($conn, 'satisfaction')) {
        $hateRes = $conn->query(
            "SELECT id, subject, description, category, status, priority, created_at
             FROM tickets
             WHERE satisfaction = 1
             ORDER BY id DESC
             LIMIT 40"
        );
        if ($hateRes) {
            while ($row = $hateRes->fetch_assoc()) {
                $hate_tickets[] = $row;
            }
        }
    }
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
$utilities_action = $_GET['action'] ?? '';
$edit_id = (int) ($_GET['edit_id'] ?? 0);
$review_app_id = (int) ($_GET['review_app'] ?? 0);
$editing_user = null;
$reviewing_app = null;
$apps_by_user = [];
foreach ($techn_applications as $appRow) {
    $apps_by_user[(int) $appRow['user_id']] = $appRow;
}
if ($edit_id > 0 && $tab === 'utilities') {
    foreach ($all_users as $u) {
        if ((int) $u['id'] === $edit_id) {
            $editing_user = $u;
            break;
        }
    }
}
if ($review_app_id > 0 && $tab === 'utilities') {
    $reviewing_app = techn_apply_get($conn, $review_app_id);
    if ($reviewing_app && !in_array($reviewing_app['status'] ?? '', ['pending', 'awaiting_role_change'], true)) {
        $reviewing_app = null;
    }
}
$utilities_success = $_SESSION['utilities_success'] ?? '';
$utilities_error = $_SESSION['utilities_error'] ?? '';
$ticket_flash = $_SESSION['ticket_flash'] ?? '';
unset($_SESSION['utilities_success'], $_SESSION['utilities_error'], $_SESSION['ticket_flash']);
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
    <link rel="stylesheet" href="../css/apply.css?v=1.6.3">
    <link rel="stylesheet" href="../css/dashboard_extra.css?v=1.6.55">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(zpgc_asset('../css/theme.css')); ?>">
    <?php include __DIR__ . '/partials/critical_ui_fixes.php'; ?>
    <title><?php echo htmlspecialchars(zpgc_page_title('Administrator')); ?></title>
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
            <div class="page-content admin-dashboard-page" id="page-dashboard">
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
                <?php include __DIR__ . '/partials/dashboard_negative_ratings.php'; ?>
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
                    <div class="tickets-filter-tabs" id="admin-tickets-filter-tabs" data-status-tabs="admin-tickets-body">
                        <button type="button" class="filter-tab active-tab" data-filter="all">Active</button>
                        <button type="button" class="filter-tab" data-filter="pending">Pending</button>
                        <button type="button" class="filter-tab" data-filter="ongoing">Ongoing</button>
                        <button type="button" class="filter-tab" data-filter="processing">Processing</button>
                        <button type="button" class="filter-tab" data-filter="awaiting_confirmation">Confirming</button>
                        <button type="button" class="filter-tab" data-filter="replacement">Replacement</button>
                    </div>
                    <?php echo ticket_priority_filter_markup('admin-tickets-priority', 'admin-tickets-body'); ?>
                </div>
                <?php if ($ticket_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($ticket_flash); ?></div>
                <?php } ?>
                <?php include __DIR__ . '/partials/priority_queue_panel.php'; ?>
                <div class="tickets-list tickets-list-admin-five tickets-list-admin-five--times">
                    <div class="tickets-list-header">
                        <span class="tickets-col-id">ID</span>
                        <span class="tickets-col-category">Category</span>
                        <span class="tickets-col-subject">Subject</span>
                        <span class="tickets-col-description">Description</span>
                        <span class="tickets-col-status">Status</span>
                        <span class="tickets-col-priority">Priority</span>
                        <span class="tickets-col-assigned">Assigned To</span>
                        <span class="tickets-col-reg">Registration Date &amp; Time</span>
                        <span class="tickets-col-queue">Queue timer</span>
                        <span class="tickets-col-resolution">Resolution Time</span>
                        <span class="tickets-col-action">Action</span>
                    </div>
                    <div class="tickets-list-body js-paged-table" id="admin-tickets-body" data-page-size="15">
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
                            $regLabel = performance_registered_label($ticket['created_at'] ?? null);
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
                        <form class="ticket-row<?php echo $needsReplace ? ' ticket-needs-replace' : ''; ?>" action="../logic/ticket_admin_mngmnt.php" method="post"
                            data-ticket-id="<?php echo $tid; ?>"
                            data-status="<?php echo htmlspecialchars($st); ?>"
                            data-priority="<?php echo htmlspecialchars(ticket_priority_key($pri)); ?>"
                            data-replace="<?php echo $needsReplace ? '1' : '0'; ?>">
                            <?php echo zpgc_csrf_field(); ?>
                            <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                            <span class="tickets-col-id">#<?php echo $tid; ?></span>
                            <span class="tickets-col-category"><?php echo htmlspecialchars(ticket_category_label($ticket['category'] ?? '')); ?></span>
                            <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                            <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                            <span class="tickets-col-status">
                                <select name="status" class="admin-ticket-select ticket-select-status" data-select-tone="status" data-value="<?php echo htmlspecialchars($st); ?>" aria-label="Status for ticket <?php echo $tid; ?>">
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
                                <select name="priority" class="admin-ticket-select ticket-select-priority" data-select-tone="priority" data-value="<?php echo htmlspecialchars((string) $pri); ?>" aria-label="Priority for ticket <?php echo $tid; ?>">
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
                                    <?php
                                    $assignGroups = [];
                                    foreach ($technicians as $tech) {
                                        $group = trim((string) ($tech['specialty'] ?? ''));
                                        if ($group === '') {
                                            $group = 'Unspecified';
                                        }
                                        $assignGroups[$group][] = $tech;
                                    }
                                    foreach ($assignGroups as $groupLabel => $groupTechs) { ?>
                                    <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                        <?php foreach ($groupTechs as $tech) { ?>
                                        <option value="<?php echo (int) $tech['id']; ?>"
                                            data-specialty="<?php echo htmlspecialchars(strtolower((string) ($tech['specialty'] ?? ''))); ?>"
                                            <?php echo ((int) ($ticket['assigned_to'] ?? 0) === (int) $tech['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(trim((string) ($tech['email'] ?? ''))); ?>
                                        </option>
                                        <?php } ?>
                                    </optgroup>
                                    <?php } ?>
                                </select>
                            </span>
                            <span class="tickets-col-reg"><?php echo htmlspecialchars($regLabel); ?></span>
                            <span class="tickets-col-queue"<?php echo $queueAttr; ?>><?php echo htmlspecialchars($queueLabel); ?></span>
                            <span class="tickets-col-resolution"><?php echo htmlspecialchars($resLabel); ?></span>
                            <span class="tickets-col-action">
                                <?php echo ticket_quick_view_button($ticket, ['data-qv-registered' => $regLabel]); ?>
                                <button type="submit" name="save_ticket" class="btn-save-ticket">Save</button>
                            </span>
                        </form>
                        <?php }?>
                        <?php }?>
                    </div>
                </div>
                <?php
                $history_show_assigned = false;
                $history_title = 'Ticket History';
                $history_subtitle = 'Resolved and archived tickets';
                $history_empty = 'No resolved tickets in history yet.';
                $history_show_times = true;
                $history_show_satisfaction = ticket_has_column($conn, 'satisfaction');
                $history_allow_rate = false;
                include __DIR__ . '/partials/ticket_history_list.php';
                ?>
            </div>
            <div class="page-content" id="page-performance">
                <div class="head">
                    <header>
                        <h1>Performance</h1>
                        <div class="search-bar-wrapper">
                            <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                        </div>
                        <?php include __DIR__ . '/partials/profile_menu.php'; ?>
                    </header>
                </div>
                <?php
                $iso_show_capacity = true;
                include __DIR__ . '/partials/kpi_iso_panel.php';
                $performance_show_technician = true;
                $performance_show_view = true;
                include __DIR__ . '/partials/performance_panel.php';
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
                <?php if ($utilities_error !== '') { ?>
                <div class="utilities-notice-error"><?php echo htmlspecialchars($utilities_error); ?></div>
                <?php } ?>

                <?php if ($reviewing_app) {
                    $revName = trim((string) ($reviewing_app['first_name'] ?? '') . ' ' . (string) ($reviewing_app['last_name'] ?? ''));
                    $revAwaiting = ($reviewing_app['status'] ?? '') === 'awaiting_role_change';
                    $revInitials = strtoupper(substr((string) ($reviewing_app['first_name'] ?? 'T'), 0, 1) . substr((string) ($reviewing_app['last_name'] ?? ''), 0, 1));
                    ?>
                <article class="apply-shell apply-shell-admin">
                    <header class="apply-shell-head">
                        <p class="apply-kicker">Technician hiring</p>
                        <h2>Review application</h2>
                    </header>
                    <div class="apply-review-person">
                        <span class="apply-avatar" aria-hidden="true"><?php echo htmlspecialchars($revInitials !== '' ? $revInitials : 'T'); ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($revName !== '' ? $revName : 'Technician'); ?></strong>
                            <span><?php echo htmlspecialchars((string) ($reviewing_app['email'] ?? '')); ?></span>
                        </div>
                        <span class="apply-status-chip"><?php echo htmlspecialchars(techn_apply_status_label($reviewing_app['status'] ?? '')); ?></span>
                    </div>
                    <div class="apply-review-facts">
                        <div class="apply-fact">
                            <span class="apply-fact-label">Requested role</span>
                            <span class="apply-role-pill"><?php echo htmlspecialchars((string) $reviewing_app['specialty']); ?></span>
                            <?php if ($revAwaiting) { ?>
                            <span class="apply-fact-note">Proposed: <?php echo htmlspecialchars((string) $reviewing_app['proposed_specialty']); ?></span>
                            <?php } ?>
                        </div>
                        <div class="apply-fact">
                            <span class="apply-fact-label">Resume</span>
                            <a class="apply-resume-link" href="../logic/resume_file.php?id=<?php echo (int) $reviewing_app['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 2.5L19.5 10H14z"/></svg>
                                <?php echo htmlspecialchars((string) ($reviewing_app['resume_original_name'] ?? 'Download resume')); ?>
                            </a>
                        </div>
                    </div>
                    <div class="apply-review-actions">
                        <form action="../logic/techn_apply_admin_mngmnt.php" method="post" class="apply-modify-form" id="apply-review-form">
                            <?php echo zpgc_csrf_field(); ?>
                            <input type="hidden" name="app_id" value="<?php echo (int) $reviewing_app['id']; ?>">
                            <label class="apply-modify-label">Assigned role
                                <select name="assigned_specialty" id="apply-review-role" required>
                                    <?php
                                    $selectedRole = $revAwaiting && ($reviewing_app['proposed_specialty'] ?? '') !== ''
                                        ? (string) $reviewing_app['proposed_specialty']
                                        : (string) $reviewing_app['specialty'];
                                    foreach ($techn_specialties as $spec) { ?>
                                    <option value="<?php echo htmlspecialchars($spec); ?>"
                                        <?php echo strcasecmp($spec, $selectedRole) === 0 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($spec); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <button type="submit" name="apply_decision" class="apply-btn apply-btn-primary" id="apply-review-submit">Approve</button>
                        </form>
                        <form action="../logic/techn_apply_admin_mngmnt.php" method="post"
                            onsubmit="return confirm('Reject this application and permanently delete the technician account?');">
                            <?php echo zpgc_csrf_field(); ?>
                            <input type="hidden" name="app_id" value="<?php echo (int) $reviewing_app['id']; ?>">
                            <button type="submit" name="apply_reject" class="apply-btn apply-btn-danger">Reject / delete</button>
                        </form>
                        <a href="?tab=utilities" class="apply-btn apply-btn-ghost">Back</a>
                    </div>
                    <script>
                    (function () {
                        var sel = document.getElementById('apply-review-role');
                        var btn = document.getElementById('apply-review-submit');
                        var requested = <?php echo json_encode((string) $reviewing_app['specialty']); ?>;
                        if (!sel || !btn) {
                            return;
                        }
                        function sync() {
                            var chosen = sel.value;
                            if (chosen !== requested) {
                                btn.textContent = 'Send ' + chosen + ' offer';
                                btn.classList.remove('apply-btn-primary');
                                btn.classList.add('apply-btn-secondary');
                            } else {
                                btn.textContent = 'Approve ' + requested;
                                btn.classList.add('apply-btn-primary');
                                btn.classList.remove('apply-btn-secondary');
                            }
                        }
                        sel.addEventListener('change', sync);
                        sync();
                    })();
                    </script>
                </article>
                <?php } ?>

                <?php if (!$reviewing_app) { ?>
                <?php if ($utilities_action === 'add') { ?>
                <article class="apply-shell apply-shell-admin user-form-card">
                    <header class="apply-shell-head">
                        <p class="apply-kicker">Account utilities</p>
                        <h2>New user</h2>
                    </header>
                    <form class="user-form apply-user-form" action="../logic/user_admin_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                        <div class="user-form-row">
                            <div class="user-form-field">
                                <label for="add_first_name">First name</label>
                                <input type="text" id="add_first_name" name="first_name" required>
                            </div>
                            <div class="user-form-field">
                                <label for="add_last_name">Last name</label>
                                <input type="text" id="add_last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="user-form-field">
                            <label for="add_email">TSU Outlook email</label>
                            <input type="email" id="add_email" name="email" required
                                placeholder="you@student.tsu.edu.ph"
                                title="Use @student.tsu.edu.ph or @tsu.edu.ph">
                        </div>
                        <div class="user-form-row">
                            <div class="user-form-field">
                                <label for="add_role">Account role</label>
                                <select id="add_role" name="role" required data-specialty-toggle="add-specialty-wrap" data-kind-toggle="add-kind-wrap">
                                    <option value="" disabled selected>Select a role</option>
                                    <option value="user">Student / Staff</option>
                                    <option value="techn">Technician</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="user-form-field">
                                <label for="add_password">Temporary password</label>
                                <input type="password" id="add_password" name="password" minlength="8" required>
                            </div>
                        </div>
                        <div class="user-form-field is-user-kind" id="add-kind-wrap" hidden>
                            <label for="add_account_kind">Student or staff</label>
                            <select id="add_account_kind" name="account_kind">
                                <option value="student">Student</option>
                                <option value="staff">Staff / faculty</option>
                            </select>
                        </div>
                        <div class="user-form-field is-techn-only" id="add-specialty-wrap">
                            <label for="add_specialty">Technical role</label>
                            <select id="add_specialty" name="specialty">
                                <option value="" disabled selected>Select Hardware, Software, Network, Account, or Other</option>
                                <?php foreach ($techn_specialties as $spec) { ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>"><?php echo htmlspecialchars($spec); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="user-form-actions">
                            <a href="?tab=utilities" class="apply-btn apply-btn-ghost">Cancel</a>
                            <button type="submit" name="add_user" class="apply-btn apply-btn-primary">Create account</button>
                        </div>
                    </form>
                </article>

                <?php } elseif ($editing_user) { ?>
                <article class="apply-shell apply-shell-admin user-form-card">
                    <header class="apply-shell-head">
                        <p class="apply-kicker">Account utilities</p>
                        <h2>Edit user</h2>
                    </header>
                    <form class="user-form apply-user-form" action="../logic/user_admin_mngmnt.php" method="post">
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
                            <select id="edit_role" name="role" required data-specialty-toggle="edit-specialty-wrap" data-kind-toggle="edit-kind-wrap">
                                <?php foreach ($role_labels as $val => $label) { ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"
                                    <?php echo $editing_user['role'] === $val ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php
                        $editSpec = '';
                        if (($editing_user['role'] ?? '') === 'techn') {
                            $editSpec = (string) (($techn_spec_by_user[(int) $editing_user['id']]['specialty'] ?? ''));
                        }
                        ?>
                        <div class="user-form-field is-user-kind<?php echo ($editing_user['role'] ?? '') === 'user' ? ' is-open' : ''; ?>" id="edit-kind-wrap" <?php echo ($editing_user['role'] ?? '') === 'user' ? '' : 'hidden'; ?>>
                            <label for="edit_account_kind">Student or staff</label>
                            <?php $editKind = (($editing_user['account_kind'] ?? '') === 'staff') ? 'staff' : 'student'; ?>
                            <select id="edit_account_kind" name="account_kind">
                                <option value="student" <?php echo $editKind === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="staff" <?php echo $editKind === 'staff' ? 'selected' : ''; ?>>Staff / faculty</option>
                            </select>
                        </div>
                        <div class="user-form-field is-techn-only<?php echo ($editing_user['role'] ?? '') === 'techn' ? ' is-open' : ''; ?>" id="edit-specialty-wrap">
                            <label for="edit_specialty">Technical role</label>
                            <select id="edit_specialty" name="specialty" <?php echo ($editing_user['role'] ?? '') === 'techn' ? 'required' : ''; ?>>
                                <option value="" disabled <?php echo $editSpec === '' ? 'selected' : ''; ?>>Select Hardware, Software, Network, Account, or Other</option>
                                <?php foreach ($techn_specialties as $spec) { ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>"
                                    <?php echo strcasecmp($editSpec, $spec) === 0 ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="user-form-actions">
                            <a href="?tab=utilities" class="apply-btn apply-btn-ghost">Cancel</a>
                            <button type="submit" name="edit_user" class="apply-btn apply-btn-primary">Save changes</button>
                        </div>
                    </form>
                </article>

                <?php } else { ?>
                <div class="ticket-retention-card techn-apply-card">
                    <div class="ticket-retention-copy">
                        <h2>Technician applications</h2>
                        <p class="form-subtitle">
                            After Outlook verification, technicians submit a specialty and resume.
                            Open <strong>Review</strong> first. Approve the requested role, or pick a
                            different role to send an Outlook offer. The technician can accept (activates)
                            or decline (removes the account).
                        </p>
                    </div>
                    <?php if (empty($techn_applications)) { ?>
                    <p class="audit-empty">No pending technician applications.</p>
                    <?php } else { ?>
                    <div class="techn-apply-list">
                        <?php foreach ($techn_applications as $appRow) {
                            $appName = trim((string) $appRow['first_name'] . ' ' . (string) $appRow['last_name']);
                            ?>
                        <div class="techn-apply-row">
                            <div class="techn-apply-meta">
                                <strong><?php echo htmlspecialchars($appName); ?></strong>
                                <span><?php echo htmlspecialchars((string) $appRow['email']); ?></span>
                                <span>Applied: <?php echo htmlspecialchars((string) $appRow['specialty']); ?>
                                    · <?php echo htmlspecialchars(techn_apply_status_label($appRow['status'] ?? '')); ?>
                                </span>
                            </div>
                            <div class="techn-apply-actions">
                                <a href="?tab=utilities&review_app=<?php echo (int) $appRow['id']; ?>" class="ticket-icon-btn" data-icon="review" aria-label="Review application" title="Review">
                                    <?php echo ticket_action_icon('review'); ?>
                                    <span class="ticket-icon-fallback">Review</span>
                                </a>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <div class="tickets-toolbar">
                    <div class="tickets-filter-tabs" id="utilities-filter-tabs">
                        <button type="button" class="filter-tab active-tab" data-filter="all">All</button>
                        <button type="button" class="filter-tab" data-filter="student">Student</button>
                        <button type="button" class="filter-tab" data-filter="staff">Staff</button>
                        <button type="button" class="filter-tab" data-filter="techn">Technician</button>
                        <button type="button" class="filter-tab" data-filter="admin">Administrator</button>
                        <button type="button" class="filter-tab" data-filter="pending">Pending Approval</button>
                        <button type="button" class="filter-tab" data-filter="not-tsu">Not TSU</button>
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
                        <span class="ucol-role">Role / specialty</span>
                        <span class="ucol-status">Status</span>
                        <span class="ucol-action">Actions</span>
                    </div>
                    <div class="tickets-list-body js-paged-table" id="utilities-users-body" data-page-size="15">
                        <?php if (empty($all_users)) { ?>
                        <div class="tickets-empty-state">
                            <p>No user accounts found.</p>
                        </div>
                        <?php } else { ?>
                        <?php foreach ($all_users as $u) {
                            $isActive = ($u['status'] === 'active');
                            if (($u['role'] ?? '') === 'user') {
                                $roleLabel = function_exists('account_kind_label')
                                    ? account_kind_label('user', $u['account_kind'] ?? 'student')
                                    : 'Student';
                            } elseif (isset($role_labels[$u['role']])) {
                                $roleLabel = $role_labels[$u['role']];
                            } else {
                                $roleLabel = ucfirst($u['role']);
                            }
                            $kindAttr = function_exists('account_kind_normalize')
                                ? account_kind_normalize($u['account_kind'] ?? '', $u['role'] ?? 'user')
                                : ((($u['role'] ?? '') === 'user') ? 'student' : '');
                            $isCampusMail = auth_mail_is_tsu_email((string) ($u['email'] ?? ''));
                            $specInfo = $techn_spec_by_user[(int) $u['id']] ?? null;
                            $specLabel = '';
                            if (($u['role'] ?? '') === 'techn') {
                                if ($specInfo) {
                                    $specLabel = (string) ($specInfo['specialty'] ?? '');
                                    if (($specInfo['status'] ?? '') === 'awaiting_role_change'
                                        && ($specInfo['proposed_specialty'] ?? '') !== '') {
                                        $specLabel .= ' → ' . (string) $specInfo['proposed_specialty'];
                                    }
                                } else {
                                    $specLabel = 'Unspecified';
                                }
                            }
                            $deleteConfirmName = htmlspecialchars(
                                json_encode($u['first_name'] . ' ' . $u['last_name']),
                                ENT_QUOTES
                            );
                        ?>
                        <div class="ticket-row"
                            data-user-id="<?php echo (int) $u['id']; ?>"
                            data-role="<?php echo htmlspecialchars($u['role']); ?>"
                            data-kind="<?php echo htmlspecialchars($kindAttr); ?>"
                            data-status="<?php echo htmlspecialchars($u['status']); ?>"
                            data-campus="<?php echo $isCampusMail ? '1' : '0'; ?>">
                            <span class="ucol-id">#<?php echo (int) $u['id']; ?></span>
                            <span class="ucol-name">
                                <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                            </span>
                            <span class="ucol-email">
                                <?php echo htmlspecialchars($u['email']); ?>
                                <?php if (!$isCampusMail) { ?>
                                <span class="status-badge inactive-account">Not TSU</span>
                                <?php } ?>
                            </span>
                            <span class="ucol-role">
                                <span class="ucol-role-stack">
                                    <span class="profile-role-badge role-<?php echo htmlspecialchars($u['role']); ?>">
                                        <?php echo htmlspecialchars($roleLabel); ?>
                                    </span>
                                    <?php if ($specLabel !== '') { ?>
                                    <span class="ucol-spec"><?php echo htmlspecialchars($specLabel); ?></span>
                                    <?php } ?>
                                </span>
                            </span>
                            <span class="ucol-status">
                                <span class="status-badge <?php echo $isActive ? 'active-account' : 'inactive-account'; ?>">
                                    <?php echo $isActive ? 'Active' : 'Pending'; ?>
                                </span>
                            </span>
                            <span class="ucol-action">
                                <?php
                                $pendingApp = (!$isActive && ($u['role'] ?? '') === 'techn')
                                    ? ($apps_by_user[(int) $u['id']] ?? null)
                                    : null;
                                if ($pendingApp) { ?>
                                <a href="?tab=utilities&review_app=<?php echo (int) $pendingApp['id']; ?>" class="ticket-icon-btn" data-icon="review" aria-label="Review application" title="Review">
                                    <?php echo ticket_action_icon('review'); ?>
                                    <span class="ticket-icon-fallback">Review</span>
                                </a>
                                <span class="ucol-action-spacer" aria-hidden="true"></span>
                                <span class="ucol-action-spacer" aria-hidden="true"></span>
                                <?php } else { ?>
                                <a href="?tab=utilities&edit_id=<?php echo (int) $u['id']; ?>" class="ticket-icon-btn" data-icon="edit" aria-label="Edit account" title="Edit">
                                    <?php echo ticket_action_icon('edit'); ?>
                                    <span class="ticket-icon-fallback">Edit</span>
                                </a>
                                <form action="../logic/user_admin_mngmnt.php" method="post" class="ucol-action-form">
<?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="set_status" class="ticket-icon-btn" data-icon="<?php echo $isActive ? 'deactivate' : 'activate'; ?>" aria-label="<?php echo $isActive ? 'Deactivate account' : 'Activate account'; ?>" title="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?>">
                                        <?php echo ticket_action_icon($isActive ? 'deactivate' : 'activate'); ?>
                                        <span class="ticket-icon-fallback"><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></span>
                                    </button>
                                </form>
                                <?php if ($u['role'] !== 'admin') { ?>
                                <form action="../logic/user_admin_mngmnt.php" method="post" class="ucol-action-form"
                                    onsubmit="return confirm('Permanently delete ' + <?php echo $deleteConfirmName; ?> + '\'s account? This can\'t be undone.');">
                                    <?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit" name="delete_user" class="ticket-icon-btn" data-icon="delete" aria-label="Delete account" title="Delete">
                                        <?php echo ticket_action_icon('delete'); ?>
                                        <span class="ticket-icon-fallback">Delete</span>
                                    </button>
                                </form>
                                <?php } else { ?>
                                <span class="ucol-action-spacer" aria-hidden="true"></span>
                                <?php } ?>
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
                    <div class="audit-panel-body">
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
                </div>
                <?php } ?>
                <?php } ?>
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
                <?php include __DIR__ . '/partials/settings_panel.php'; ?>
            </div>
        </section>
    </main>
    <script>
        window.DASHBOARD_CHART_DATA = <?php echo json_encode($dashboard_charts, JSON_UNESCAPED_UNICODE); ?>;
        window.DASHBOARD_CHART_RANGE = <?php echo json_encode($dashboard_charts['range'] ?? 'week'); ?>;
        window.DASHBOARD_CHART_FROM = <?php echo json_encode($dashboard_charts['from'] ?? ''); ?>;
        window.DASHBOARD_CHART_TO = <?php echo json_encode($dashboard_charts['to'] ?? ''); ?>;
        window.ZPGC_CSRF = <?php echo json_encode(zpgc_csrf_token()); ?>;
        (function () {
            function bindSpecialty(roleId) {
                var role = document.getElementById(roleId);
                if (!role) {
                    return;
                }
                var wrapId = role.getAttribute('data-specialty-toggle');
                var wrap = wrapId ? document.getElementById(wrapId) : null;
                if (!wrap) {
                    return;
                }
                var spec = wrap.querySelector('select');
                function sync() {
                    var on = role.value === 'techn';
                    wrap.classList.toggle('is-open', on);
                    wrap.hidden = !on;
                    if (spec) {
                        spec.required = on;
                        spec.disabled = !on;
                        if (!on) {
                            spec.selectedIndex = 0;
                        }
                    }
                }
                role.addEventListener('change', sync);
                sync();
            }
            function bindKind(roleId) {
                var role = document.getElementById(roleId);
                if (!role) {
                    return;
                }
                var wrapId = role.getAttribute('data-kind-toggle');
                var wrap = wrapId ? document.getElementById(wrapId) : null;
                if (!wrap) {
                    return;
                }
                var spec = wrap.querySelector('select');
                function syncKind() {
                    var on = role.value === 'user';
                    wrap.classList.toggle('is-open', on);
                    wrap.hidden = !on;
                    if (spec) {
                        spec.disabled = !on;
                    }
                }
                role.addEventListener('change', syncKind);
                syncKind();
            }
            window.zpgcBindUtilitiesSpecialty = function () {
                bindSpecialty('add_role');
                bindSpecialty('edit_role');
                bindKind('add_role');
                bindKind('edit_role');
            };
            window.zpgcBindUtilitiesSpecialty();
        })();
    </script>
    <script src="../js/lazy_load.js?v=1.6.55"></script>
    <script src="<?php echo htmlspecialchars(zpgc_asset('../js/utilities_filter.js')); ?>"></script>
    <script src="../js/table_controls.js?v=1.6.88"></script>
    <script src="../js/ajax_forms.js?v=1.6.87"></script>
    <script src="../js/ticket_live.js?v=1.6.40"></script>
    <script src="../js/ticket_quick_view.js?v=1.6.87"></script>
    <script src="../js/performance_filter.js?v=1.6.22"></script>
    <script src="../js/capacity_check.js?v=1.6.83"></script>
    <script src="../js/behavior.js?v=1.6.87" defer></script>
    <!-- chart.umd.js, dashboard charts, tickets/utilities filters: lazy-loaded per tab via lazy_load.js -->
</body>

</html>
