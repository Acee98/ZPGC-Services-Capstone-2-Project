<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/priority_queue.php';
require_role('admin');

$current_user_id = current_user_id($conn);
$tab = $_GET['tab'] ?? 'dashboard';

$all_users = [];
$result = $conn->query('SELECT id, first_name, last_name, email, role, status FROM users');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_users[] = $row;
    }
}

$technicians = [];
$tech_result = $conn->query(
    "SELECT id, first_name, last_name 
    FROM users WHERE role = 'techn' AND status = 'active' 
    ORDER BY last_name, first_name"
    );

if ($tech_result) {
    while($row = $tech_result->fetch_assoc()) {
        $technicians[] =$row;
    }
}

$role_labels = [
    'user' => 'User',
    'techn' => 'Technician',
    'admin' => 'Administrator',
];

$all_tickets = [];
$ticket_result = $conn->query(
    'SELECT t.id, t.subject, t.description, t.status, t.priority, t.assigned_to,
            u.first_name, u.last_name,
            tech.first_name AS tech_first, tech.last_name AS tech_last
     FROM tickets t
     INNER JOIN users u ON t.user_id = u.id
     LEFT JOIN users tech ON t.assigned_to = tech.id
     ORDER BY t.id DESC'
);

if ($ticket_result) {
    while ($row = $ticket_result->fetch_assoc()) {
        $all_tickets[] = $row;
    }
}

$mailbox_tickets = $all_tickets;
$recent_tickets = $all_tickets;
$queue_snapshot = priority_queue_snapshot($conn);

$status_counts = [
    'pending' => 0,
    'ongoing' => 0,
    'processing' => 0,
    'awaiting_confirmation' => 0,
    'resolved' => 0,
];
$count_result = $conn->query('SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status');
if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $key = $row['status'];
        if (isset($status_counts[$key])) {
            $status_counts[$key] = (int) $row['cnt'];
        }
    }
}

$utilities_action = $_GET['action'] ?? '';
$edit_id = (int) ($_GET['edit_id'] ?? 0);
$editing_user = null;
if ($edit_id > 0) {
    foreach ($all_users as $u) {
        if ((int) $u['id'] === $edit_id) {
            $editing_user = $u;
            break;
        }
    }
}
$utilities_success = $_SESSION['utilities_success'] ?? '';
$utilities_error = $_SESSION['utilities_error'] ?? '';
unset($_SESSION['utilities_success'], $_SESSION['utilities_error']);
$ui_theme = current_ui_theme();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main_interface.css">
    <link rel="stylesheet" href="../css/dashboard_extra.css">
    <link rel="stylesheet" href="../css/theme.css">
    <title>ZPGC Services | Administrator</title>
</head>

<body data-page="<?php echo htmlspecialchars($tab); ?>">
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
                            <li class="nav-list-item selected" data-nav="dashboard">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 11h-6c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-8c0-.55-.45-1-1-1m-1 8h-4v-6h4zm-9-4H4c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1m-1 4H5v-2h4zM20 3h-6c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 4h-4V5h4zm-9-4H4c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 8H5V5h4z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="tickets">
                                <a href="#" class="nav-link">
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
                            <li class="nav-list-item" data-nav="utilities">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20.71 6.04a.99.99 0 0 0-.9.27l-3.18 3.18-2.12-2.12 3.18-3.18a.98.98 0 0 0 .27-.9c-.07-.33-.29-.6-.6-.73A7.47 7.47 0 0 0 9.2 4.19a7.49 7.49 0 0 0-1.86 7.52L2.3 16.75c-.19.19-.29.44-.29.71s.11.52.29.71l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l5.04-5.04c2.64.82 5.53.12 7.52-1.86a7.47 7.47 0 0 0 1.63-8.16c-.13-.31-.4-.53-.73-.6Zm-2.32 7.34a5.51 5.51 0 0 1-5.98 1.2c-.37-.15-.8-.07-1.09.22l-4.78 4.78-2.12-2.12 4.78-4.78c.29-.29.37-.71.22-1.09a5.47 5.47 0 0 1 1.2-5.98 5.5 5.5 0 0 1 4.41-1.59l-2.65 2.65a.996.996 0 0 0 0 1.41l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l2.65-2.65c.16 1.61-.4 3.23-1.59 4.42Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Utilities</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="performance">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5">
                                        </path>
                                    </svg>
                                    <span class="link-text">Performance</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="messages">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 2v.51l-8 6.22-8-6.22V6zM4 18V9.04l7.39 5.74c.18.14.4.21.61.21s.43-.07.61-.21L20 9.03v8.96H4Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Mailbox</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="settings">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4m0 6c-1.08 0-2-.92-2-2s.92-2 2-2 2 .92 2 2-.92 2-2 2">
                                        </path>
                                        <path
                                            d="m20.42 13.4-.51-.29c.05-.37.08-.74.08-1.11s-.03-.74-.08-1.11l.51-.29c.96-.55 1.28-1.78.73-2.73l-1-1.73a2.006 2.006 0 0 0-2.73-.73l-.53.31c-.58-.46-1.22-.83-1.9-1.11v-.6c0-1.1-.9-2-2-2h-2c-1.1 0-2 .9-2 2v.6c-.67.28-1.31.66-1.9 1.11l-.53-.31c-.96-.55-2.18-.22-2.73.73l-1 1.73c-.55.96-.22 2.18.73 2.73l.51.29c-.05.37-.08.74-.08 1.11s.03.74.08 1.11l-.51.29c-.96.55-1.28 1.78-.73 2.73l1 1.73c.55.95 1.77 1.28 2.73.73l.53-.31c.58.46 1.22.83 1.9 1.11v.6c0 1.1.9 2 2 2h2c1.1 0 2-.9 2-2v-.6a8.7 8.7 0 0 0 1.9-1.11l.53.31c.95.55 2.18.22 2.73-.73l1-1.73c.55-.96.22-2.18-.73-2.73m-2.59-2.78c.11.45.17.92.17 1.38s-.06.92-.17 1.38a1 1 0 0 0 .47 1.11l1.12.65-1 1.73-1.14-.66c-.38-.22-.87-.16-1.19.14-.68.65-1.51 1.13-2.38 1.4-.42.13-.71.52-.71.96v1.3h-2v-1.3c0-.44-.29-.83-.71-.96-.88-.27-1.7-.75-2.38-1.4a1.01 1.01 0 0 0-1.19-.15l-1.14.66-1-1.73 1.12-.65c.39-.22.58-.68.47-1.11-.11-.45-.17-.92-.17-1.38s.06-.93.17-1.38A1 1 0 0 0 5.7 9.5l-1.12-.65 1-1.73 1.14.66c.38.22.87.16 1.19-.14.68-.65 1.51-1.13 2.38-1.4.42-.13.71-.52.71-.96v-1.3h2v1.3c0 .44.29.83.71.96.88.27 1.7.75 2.38 1.4.32.31.81.36 1.19.14l1.14-.66 1 1.73-1.12.65c-.39.22-.58.68-.47 1.11Z">
                                        </path>
                                    </svg>
                                    <span class="link-text" id="settings">Settings</span>
                                </a>
                            </li>
                            <li class="nav-list-item">
                                <a href="../logic/logout.php" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 13h7v-2H9V7l-6 5 6 5z"></path>
                                        <path d="M19 3h-7v2h7v14h-7v2h7c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2"></path>
                                    </svg>
                                    <span class="link-text" id="logout">Logout</span>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>
                <div class="tickets-toolbar">
                    <div class="tickets-filter-tabs" id="admin-tickets-filter-tabs">
                        <button type="button" class="filter-tab active-tab" data-filter="all">All</button>
                        <button type="button" class="filter-tab" data-filter="pending">Pending</button>
                        <button type="button" class="filter-tab" data-filter="ongoing">Ongoing</button>
                        <button type="button" class="filter-tab" data-filter="processing">Processing</button>
                        <button type="button" class="filter-tab" data-filter="resolved">Resolved</button>
                    </div>
                </div>
                <?php include __DIR__ . '/partials/priority_queue_panel.php'; ?>
                <div class="tickets-list tickets-list-admin-five">
                    <div class="tickets-list-header">
                        <span class="tickets-col-id">ID</span>
                        <span class="tickets-col-subject">Subject</span>
                        <span class="tickets-col-description">Description</span>
                        <span class="tickets-col-status">Status</span>
                        <span class="tickets-col-priority">Priority</span>
                        <span class="tickets-col-assigned">Assigned To</span>
                    </div>
                    <div class="tickets-list-body" id="admin-tickets-body">
                        <?php if (empty($all_tickets)) {?>
                        <div class="tickets-empty-state">
                            <p>No tickets found.</p>
                        </div>
                        <?php } else {?>
                        <?php foreach ($all_tickets as $ticket) {?>
                        <?php
                            $tid = (int) $ticket['id'];
                            $st = $ticket['status'];
                            $pri = $ticket['priority'] ?? '';
                        ?>
                        <form class="ticket-row" action="../logic/ticket_admin_mngmnt.php" method="post"
                            data-status="<?php echo htmlspecialchars($st); ?>">
                            <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                            <span class="tickets-col-id">#<?php echo $tid; ?></span>
                            <span class="tickets-col-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                            <span class="tickets-col-description"><?php echo htmlspecialchars($ticket['description']); ?></span>
                            <span class="tickets-col-status">
                                <select name="status" class="admin-ticket-select" aria-label="Status for ticket <?php echo $tid; ?>">
                                    <?php foreach (['pending', 'ongoing', 'processing', 'resolved'] as $opt) { ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($st === $opt) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($opt); ?>
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
                                <span class="admin-ticket-form">
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
                                    <button type="submit" name="save_ticket" class="btn-save-ticket">Save</button>
                                </span>
                            </span>
                        </form>
                        <?php }?>
                        <?php }?>
                    </div>
                </div>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>

                <?php if ($utilities_success !== '') { ?>
                <div class="utilities-notice"><?php echo htmlspecialchars($utilities_success); ?></div>
                <?php } ?>
                <?php if ($utilities_error !== '') { ?>
                <div class="utilities-notice-error"><?php echo htmlspecialchars($utilities_error); ?></div>
                <?php } ?>

                <?php if ($utilities_action === 'add') { ?>
                <div class="user-form-card">
                    <h2>Add User</h2>
                    <p class="form-subtitle">Create a new account directly — it is active immediately.</p>
                    <form class="user-form" action="../logic/user_admin_mngmnt.php" method="post">
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

                <div class="tickets-list">
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
                                <form action="../logic/user_admin_mngmnt.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="set_status" class="btn-update-status">
                                        <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form action="../logic/user_admin_mngmnt.php" method="post"
                                    onsubmit="return confirm('Permanently delete ' + <?php echo $deleteConfirmName; ?> + '\'s account? This can\'t be undone.');">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                                </form>
                            </span>
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>
                <div class="perf-section-header">
                    <h2>Technician Record List</h2>
                    <button class="perf-filter-btn" type="button" disabled title="Sample only">Date-Range</button>
                </div>
                <div class="tickets-list">
                    <div class="tickets-list-header">
                        <span class="pcol-teamid">Team ID</span>
                        <span class="pcol-resolved">Ticket Resolved</span>
                        <span class="pcol-critical">Critical</span>
                        <span class="pcol-moderate">Moderate</span>
                        <span class="pcol-low">Low</span>
                    </div>
                    <div class="tickets-list-body">
                        <?php for ($i = 0; $i < 5; $i++) { ?>
                        <div class="ticket-row">
                            <span class="pcol-teamid">#0000</span>
                            <span class="pcol-resolved">0</span>
                            <span class="pcol-critical"><span class="perf-count critical">0</span></span>
                            <span class="pcol-moderate"><span class="perf-count moderate">0</span></span>
                            <span class="pcol-low"><span class="perf-count low">0</span></span>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="perf-table-spacer"></div>
                <div class="perf-filters-row">
                    <div class="search-bar-wrapper">
                        <input type="search" class="search-bar" id="perf-log-search" placeholder="Search..." aria-label="Search ticket log">
                    </div>
                    <button class="perf-filter-btn" type="button" disabled>Severity Level</button>
                    <button class="perf-filter-btn" type="button" disabled>Categories</button>
                </div>
                <div class="tickets-list">
                    <div class="tickets-list-header">
                        <span class="dcol-team">Team ID</span>
                        <span class="dcol-ticket">Ticket ID</span>
                        <span class="dcol-subject">Subject</span>
                        <span class="dcol-description">Description</span>
                        <span class="dcol-category">Category</span>
                        <span class="dcol-reg">Registration Date &amp; Time</span>
                        <span class="dcol-response">Response Time</span>
                        <span class="dcol-resolution">Resolution Time</span>
                        <span class="dcol-severity">Severity Level</span>
                    </div>
                    <div class="tickets-list-body">
                        <?php
                        $placeholder_severities = ['moderate', 'moderate', 'critical', 'critical', 'moderate', 'low'];
                        foreach ($placeholder_severities as $sev) {
                        ?>
                        <div class="ticket-row">
                            <span class="dcol-team">0000</span>
                            <span class="dcol-ticket">0000</span>
                            <span class="dcol-subject">The title of the issue</span>
                            <span class="dcol-description">A brief summary of the request</span>
                            <span class="dcol-category">Hardware</span>
                            <span class="dcol-reg">08/15/2027 (11:11)</span>
                            <span class="dcol-response">10 Minutes</span>
                            <span class="dcol-resolution">1 Hour</span>
                            <span class="dcol-severity"><span class="severity-badge <?php echo $sev; ?>"><?php echo ucfirst($sev); ?></span></span>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <a href="#" class="perf-view-all">View all</a>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>
                <?php include 'mailbox_panel.php'; ?>
            </div>
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
                        <div class="profile-circle"></div>
                    </header>
                </div>
                <div class="settings-container">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>Appearance</h2>
                            <p>Stay in control of how the dashboard looks on this account.</p>
                        </div>
                        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form">
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
    <script src="../js/behavior.js"></script>
    <script src="../js/tickets_filter.js"></script>
    <script src="../js/utilities_filter.js"></script>
    <script src="../js/chart.umd.js"></script>
    <script src="../js/dashboard_static_charts.js"></script>
</body>

</html>