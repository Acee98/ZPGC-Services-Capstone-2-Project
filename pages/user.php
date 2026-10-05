<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/ui_state.php';
require_once '../logic/ticket_status.php';
require_once '../logic/ticket_files.php';
require_once '../logic/ticket_times.php';
require_once '../logic/ticket_retention.php';
require_role('user');

$current_user_id = current_user_id($conn);
$tab = zpgc_ui_resolve_tab('user', 'dashboard');
zpgc_ui_persist_redirect($tab);

$confirm_success = $_SESSION['confirm_success'] ?? '';
$confirm_error = $_SESSION['confirm_error'] ?? '';
$ticket_flash = $_SESSION['ticket_flash'] ?? '';
unset($_SESSION['confirm_success'], $_SESSION['confirm_error'], $_SESSION['ticket_flash']);

$has_ai_guidance = false;
$colCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'ai_guidance'");
if ($colCheck && $colCheck->num_rows > 0) {
    $has_ai_guidance = true;
}
if (empty($_SESSION['_zpgc_sat_col'])) {
    $satCol = $conn->query("SHOW COLUMNS FROM tickets LIKE 'satisfaction'");
    if (!$satCol || $satCol->num_rows === 0) {
        $conn->query('ALTER TABLE tickets ADD COLUMN satisfaction TINYINT NULL DEFAULT NULL');
    }
    $_SESSION['_zpgc_sat_col'] = 1;
}
ticket_ensure_archived_column($conn);
ticket_ensure_indexes($conn);
ticket_retention_maybe_backfill($conn, 1);
$rating_labels = [
    5 => 'Very satisfied',
    4 => 'Satisfied',
    3 => 'Not sure',
    2 => 'Not satisfied',
    1 => 'Hate it',
];

$user_tickets = [];
$selectCols = $has_ai_guidance
    ? 'id, subject, description, status, priority, assigned_to, ai_guidance, satisfaction, archived_at'
    : 'id, subject, description, status, priority, assigned_to, satisfaction, archived_at';
if (!ticket_has_column($conn, 'archived_at')) {
    $selectCols = str_replace(', archived_at', '', $selectCols);
}
$stmt = $conn->prepare(
    "SELECT {$selectCols} FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 200"
);
$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_tickets[] = $row;
}
$stmt->close();
[$active_user_tickets, $history_tickets] = ticket_partition_active_history($user_tickets);
$mailbox_tickets = $active_user_tickets;
$listed_tickets = ticket_sort_for_attention($active_user_tickets, []);
$ui_theme = current_ui_theme();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main_interface.css?v=1.6.2">
    <link rel="stylesheet" href="../css/theme.css?v=1.6.2">
    <?php include __DIR__ . '/partials/critical_ui_fixes.php'; ?>
    <title>ZPGC Services | User</title>
</head>

<body data-page="<?php echo htmlspecialchars($tab); ?>" data-role="user" data-web-base="<?php echo htmlspecialchars(zpgc_web_base(), ENT_QUOTES, 'UTF-8'); ?>">
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
                $dashboard_card_keys = ['ongoing', 'processing', 'resolved'];
                $dash_tickets = $active_user_tickets;
                $status_counts = [
                    'ongoing' => 0,
                    'processing' => 0,
                    'resolved' => 0,
                ];
                foreach ($user_tickets as $ticket) {
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
                <div class="tickets-toolbar">
                    <a href="ticket.php" class="btn-new-ticket">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path d="M3 13h8v8h2v-8h8v-2h-8V3h-2v8H3z"></path>
                        </svg>
                        New Ticket
                    </a>
                </div>
                <?php if ($ticket_flash !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($ticket_flash); ?></div>
                <?php } ?>
                <?php if ($confirm_success !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($confirm_success); ?></div>
                <?php } ?>
                <?php if ($confirm_error !== '') { ?>
                <div class="utilities-notice-error"><?php echo htmlspecialchars($confirm_error); ?></div>
                <?php } ?>
                <div class="tickets-list tickets-list-user tickets-list-user--actions">
                <div class="tickets-list-header">
                    <span class="tickets-col-id">ID</span>
                    <span class="tickets-col-subject">Subject</span>
                    <span class="tickets-col-description">Description</span>
                    <span class="tickets-col-status">Status</span>
                    <span class="tickets-col-confirm">Confirm</span>
                    <span class="tickets-col-delete"> </span>
                </div>
                <div class="tickets-list-body" id="user-tickets-body">
                    <?php if (empty($listed_tickets)) { ?>
                    <div class="tickets-empty-state">
                        <p>No tickets submitted yet.</p>
                    </div>
                    <?php } else { ?>
                    <?php foreach ($listed_tickets as $ticket) {
                        $st = $ticket['status'];
                        $needsConfirm = ticket_awaiting_confirmation($st);
                        $guidance = trim((string) ($ticket['ai_guidance'] ?? ''));
                        $unassigned = empty($ticket['assigned_to']);
                        $showSelfHelp = ($guidance !== '' && $unassigned && $st !== 'resolved');
                    ?>
                    <div class="ticket-row" data-status="<?php echo htmlspecialchars($st); ?>">
                        <span class="tickets-col-id">#
                            <?php echo (int) $ticket['id']; ?>
                        </span>
                        <span class="tickets-col-subject">
                            <?php echo htmlspecialchars($ticket['subject']); ?>
                            <?php if ($showSelfHelp) { ?>
                            <details class="ai-selfhelp">
                                <summary>AI troubleshooting tips</summary>
                                <pre class="ai-selfhelp-body"><?php echo htmlspecialchars($guidance); ?></pre>
                                <form action="../logic/ticket_escalate_mngmnt.php" method="post" class="ai-escalate-form">
<?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                    <button type="submit" name="self_help_solved" value="1" class="btn-confirm-solved">
                                        These steps worked
                                    </button>
                                </form>
                                <form action="../logic/ticket_escalate_mngmnt.php" method="post" class="ai-escalate-form">
<?php echo zpgc_csrf_field(); ?>
                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                    <button type="submit" name="request_technician" value="1" class="btn-request-tech">
                                        Still not fixed — Request Technician
                                    </button>
                                </form>
                            </details>
                            <?php } elseif ($unassigned && ($ticket['priority'] ?? '') === 'low' && $st !== 'resolved') { ?>
                            <form action="../logic/ticket_escalate_mngmnt.php" method="post" class="ai-escalate-form">
<?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                <button type="submit" name="request_technician" value="1" class="btn-request-tech">
                                    Request Technician
                                </button>
                            </form>
                            <?php } ?>
                        </span>
                        <span class="tickets-col-description">
                            <?php echo htmlspecialchars($ticket['description']); ?>
                        </span>
                        <span class="tickets-col-status">
                            <span class="status-badge <?php echo htmlspecialchars(ticket_status_class($st)); ?>">
                                <?php echo htmlspecialchars(ticket_status_label($st)); ?>
                            </span>
                        </span>
                        <span class="tickets-col-confirm">
                            <?php if ($needsConfirm) { ?>
                            <form class="confirm-form" action="../logic/ticket_confirm_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                <input type="hidden" name="decision" value="solved">
                                <button type="submit" name="confirm_ticket" value="1" class="btn-confirm-solved">Solved</button>
                            </form>
                            <form class="confirm-form" action="../logic/ticket_confirm_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                <input type="hidden" name="decision" value="not_solved">
                                <button type="submit" name="confirm_ticket" value="1" class="btn-confirm-reopen">Not Solved Yet</button>
                            </form>
                            <?php } elseif ($st === 'resolved' && isset($rating_labels[(int) ($ticket['satisfaction'] ?? 0)])) { ?>
                            <span class="confirm-placeholder"><?php echo htmlspecialchars($rating_labels[(int) $ticket['satisfaction']]); ?></span>
                            <?php } else { ?>
                            <span class="confirm-placeholder">—</span>
                            <?php } ?>
                        </span>
                        <span class="tickets-col-delete">
                            <form action="../logic/ticket_delete_mngmnt.php" method="post" class="ticket-delete-form"
                                onsubmit="return confirm('Delete this ticket?');">
<?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                <button type="submit" class="ticket-icon-btn" aria-label="Delete ticket">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 6V4c0-1.1-.9-2-2-2H9c-1.1 0-2 .9-2 2v2H2v2h2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8h2V6zM9 4h6v2H9zM6 20V8h12v12z"></path><path d="M9 10h2v8H9zm4 0h2v8h-2z"></path></svg>
                                </button>
                            </form>
                        </span>
                    </div>
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
    <?php
    $rateId = (int) ($_SESSION['rate_ticket_id'] ?? 0);
    $rateTicket = null;
    if ($rateId > 0) {
        foreach ($user_tickets as $rateRow) {
            $already = (int) ($rateRow['satisfaction'] ?? 0);
            if ((int) $rateRow['id'] === $rateId && ($rateRow['status'] ?? '') === 'resolved' && !isset($rating_labels[$already])) {
                $rateTicket = $rateRow;
                break;
            }
        }
    }
    ?>
    <?php if ($rateTicket) { ?>
    <div class="survey-overlay" role="dialog" aria-modal="true" aria-labelledby="survey-title">
        <form class="survey-card" action="../logic/ticket_rating_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
            <input type="hidden" name="ticket_id" value="<?php echo (int) $rateTicket['id']; ?>">
            <h2 id="survey-title">How was this visit?</h2>
            <p>Ticket #<?php echo (int) $rateTicket['id']; ?> is resolved. <?php echo htmlspecialchars((string) $rateTicket['subject']); ?></p>
            <div class="survey-options">
                <?php foreach ($rating_labels as $score => $label) { ?>
                <label class="survey-option">
                    <input type="radio" name="satisfaction" value="<?php echo (int) $score; ?>" required>
                    <span><?php echo htmlspecialchars($label); ?></span>
                </label>
                <?php } ?>
            </div>
            <button type="submit" class="btn-save-ticket">Submit rating</button>
        </form>
    </div>
    <?php } ?>
    <script src="../js/lazy_load.js?v=1.6.2"></script>
    <script src="../js/behavior.js?v=1.6.2" defer></script>
</body>

</html>
