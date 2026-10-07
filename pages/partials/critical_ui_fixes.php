<?php
/** Inline theme overrides for dark mode and mobile layout. */
?>
<style id="zpgc-theme-overrides">
/* Dark mode: surfaces + readable text */
html[data-theme="dark"] body {
    background: #141418 !important;
    color: #f2f2f7 !important;
}
html[data-theme="dark"] .main-wrap .showcase {
    background: #141418 !important;
}
html[data-theme="dark"] .showcase .head header h1 {
    color: #f2f2f7 !important;
}

/* Ticket History: Satisfaction Level as a fixed trailing column */
.ticket-history-list--satisfaction .tickets-list-header,
.ticket-history-list--satisfaction .ticket-row,
.ticket-history-list:has(.tickets-col-satisfaction) .tickets-list-header,
.ticket-history-list:has(.tickets-col-satisfaction) .ticket-row {
    display: grid !important;
    grid-template-columns:
        72px
        90px
        minmax(120px, 1.2fr)
        minmax(160px, 2fr)
        110px
        100px
        150px !important;
    column-gap: 12px !important;
    align-items: center !important;
    justify-content: stretch !important;
}
.ticket-history-list .tickets-col-satisfaction {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    min-width: 0 !important;
    max-width: 100% !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.ticket-history-list .tickets-list-header .tickets-col-satisfaction {
    justify-content: center !important;
    text-align: center !important;
}

/* Cards / panels / lists */
html[data-theme="dark"] .tickets-list,
html[data-theme="dark"] .tickets-list-body,
html[data-theme="dark"] .ticket-history-list,
html[data-theme="dark"] .mailbox-container,
html[data-theme="dark"] .mailbox-threads,
html[data-theme="dark"] .mailbox-threads-list,
html[data-theme="dark"] .mailbox-chat,
html[data-theme="dark"] .mailbox-compose,
html[data-theme="dark"] .mailbox-readonly-note,
html[data-theme="dark"] .mailbox-threads-header,
html[data-theme="dark"] .mailbox-chat-header,
html[data-theme="dark"] .mailbox-thread-item,
html[data-theme="dark"] .audit-panel,
html[data-theme="dark"] .ticket-retention-card,
html[data-theme="dark"] .ticket-retention-footer,
html[data-theme="dark"] .perf-summary-card,
html[data-theme="dark"] .priority-queue-panel,
html[data-theme="dark"] .priority-queue-band,
html[data-theme="dark"] .status-card,
html[data-theme="dark"] .chart-card,
html[data-theme="dark"] .user-form-card,
html[data-theme="dark"] .settings-card,
html[data-theme="dark"] .profile-card,
html[data-theme="dark"] .profile-hero,
html[data-theme="dark"] #page-profile .profile-hero,
html[data-theme="dark"] #page-profile .profile-card {
    background: #222228 !important;
    background-color: #222228 !important;
    color: #f2f2f7 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    box-shadow: none !important;
}

/* Mailbox thread rows (were stuck on white) */
html[data-theme="dark"] button.mailbox-thread-item,
html[data-theme="dark"] .mailbox-thread-item {
    background: #222228 !important;
    background-color: #222228 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #f2f2f7 !important;
}
html[data-theme="dark"] .mailbox-thread-item:hover {
    background: #2a2a32 !important;
    background-color: #2a2a32 !important;
}
html[data-theme="dark"] .mailbox-thread-item.active {
    background: rgba(139, 74, 82, 0.38) !important;
    background-color: rgba(139, 74, 82, 0.38) !important;
    border-left-color: #f2b8bc !important;
}
html[data-theme="dark"] .mailbox-thread-subject,
html[data-theme="dark"] .mailbox-thread-item .mailbox-thread-subject {
    color: #f2f2f7 !important;
}
html[data-theme="dark"] .mailbox-thread-time,
html[data-theme="dark"] .mailbox-thread-item .mailbox-thread-time {
    color: #c4c4d0 !important;
}

html[data-theme="dark"] .tickets-list-header,
html[data-theme="dark"] .audit-head {
    background: #2a2a32 !important;
    color: #c4c4d0 !important;
}

html[data-theme="dark"] .ticket-row span,
html[data-theme="dark"] .ticket-row > span,
html[data-theme="dark"] .tickets-list-header span,
html[data-theme="dark"] .audit-row,
html[data-theme="dark"] .audit-panel h2,
html[data-theme="dark"] .ticket-history-head h2,
html[data-theme="dark"] .mailbox-threads-title,
html[data-theme="dark"] .mailbox-chat-header-subject,
html[data-theme="dark"] .mailbox-msg-time,
html[data-theme="dark"] .mailbox-thread-party,
html[data-theme="dark"] .mailbox-thread-preview,
html[data-theme="dark"] .ticket-retention-card h2,
html[data-theme="dark"] .ticket-retention-meta-value,
html[data-theme="dark"] .perf-summary-value,
html[data-theme="dark"] .perf-section-header h2,
html[data-theme="dark"] #page-performance .perf-section-header h2,
html[data-theme="dark"] #page-performance .ticket-row span,
html[data-theme="dark"] #page-performance .tickets-list-header span,
html[data-theme="dark"] #page-profile .profile-hero-name,
html[data-theme="dark"] #page-profile .profile-info-value,
html[data-theme="dark"] #page-profile .profile-card h2,
html[data-theme="dark"] #page-profile .profile-pref-label,
html[data-theme="dark"] .profile-pref-label,
html[data-theme="dark"] .priority-queue-band-label,
html[data-theme="dark"] .priority-queue-band-count,
html[data-theme="dark"] .priority-queue-head h2 {
    color: #f2f2f7 !important;
}

html[data-theme="dark"] .priority-badge,
html[data-theme="dark"] .ticket-row .priority-badge {
    background: none !important;
    background-color: transparent !important;
}
html[data-theme="dark"] .priority-badge.critical { color: #F87171 !important; }
html[data-theme="dark"] .priority-badge.high { color: #FB923C !important; }
html[data-theme="dark"] .priority-badge.moderate,
html[data-theme="dark"] .priority-badge.medium { color: #FBBF24 !important; }
html[data-theme="dark"] .priority-badge.low { color: #4ADE80 !important; }
html[data-theme="dark"] .priority-badge.undefined,
html[data-theme="dark"] .priority-badge.unassigned { color: #9CA3AF !important; }

html[data-theme="dark"] .severity-badge.critical { background-color: #DC2626 !important; color: #fff !important; }
html[data-theme="dark"] .severity-badge.high { background-color: #EA580C !important; color: #fff !important; }
html[data-theme="dark"] .severity-badge.moderate,
html[data-theme="dark"] .severity-badge.medium { background-color: #D97706 !important; color: #fff !important; }
html[data-theme="dark"] .severity-badge.low { background-color: #15803D !important; color: #fff !important; }
html[data-theme="dark"] .severity-badge.undefined,
html[data-theme="dark"] .severity-badge.unassigned { background-color: #6B7280 !important; color: #fff !important; }

html[data-theme="dark"] .ticket-history-subtitle,
html[data-theme="dark"] .ticket-retention-card .form-subtitle,
html[data-theme="dark"] .ticket-retention-rules,
html[data-theme="dark"] .ticket-retention-rules li,
html[data-theme="dark"] .ticket-retention-meta-label,
html[data-theme="dark"] .ticket-retention-meta-note,
html[data-theme="dark"] .audit-empty,
html[data-theme="dark"] .mailbox-chat-header-sub,
html[data-theme="dark"] .mailbox-readonly-note,
html[data-theme="dark"] .perf-summary,
html[data-theme="dark"] .perf-summary-label,
html[data-theme="dark"] .perf-summary-hint,
html[data-theme="dark"] .priority-queue-band-tier,
html[data-theme="dark"] .priority-queue-note,
html[data-theme="dark"] #page-profile .profile-info-label,
html[data-theme="dark"] #page-profile .profile-info-value.profile-info-empty {
    color: #c4c4d0 !important;
}

/* Priority queue accents readable on dark cards */
html[data-theme="dark"] .priority-queue-borrow-hint,
html[data-theme="dark"] .priority-queue-borrowed-tag {
    color: #f5a8ae !important;
}
html[data-theme="dark"] .priority-queue-bar {
    background: #3a3a44 !important;
}

/* Inputs / search */
html[data-theme="dark"] .mailbox-search,
html[data-theme="dark"] .mailbox-chat-input,
html[data-theme="dark"] .showcase .head header .search-bar,
html[data-theme="dark"] .search-bar-wrapper .search-bar,
html[data-theme="dark"] input.search-bar,
html[data-theme="dark"] .admin-ticket-select:not(.ticket-select-status):not(.ticket-select-priority),
html[data-theme="dark"] .filter-tab:not(.active-tab),
html[data-theme="dark"] .profile-pref-select {
    background: #2a2a32 !important;
    background-color: #2a2a32 !important;
    color: #f2f2f7 !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
}
html[data-theme="dark"] .admin-ticket-select.ticket-select-status,
html[data-theme="dark"] .admin-ticket-select.ticket-select-priority,
html[data-theme="light"] .admin-ticket-select.ticket-select-status,
html[data-theme="light"] .admin-ticket-select.ticket-select-priority {
    color: #fff !important;
    border-color: transparent !important;
}
html[data-theme="dark"] .ticket-select-status[data-value="pending"],
html[data-theme="light"] .ticket-select-status[data-value="pending"] { background: #6B7280 !important; background-color: #6B7280 !important; }
html[data-theme="dark"] .ticket-select-status[data-value="ongoing"],
html[data-theme="light"] .ticket-select-status[data-value="ongoing"] { background: #2563EB !important; background-color: #2563EB !important; }
html[data-theme="dark"] .ticket-select-status[data-value="processing"],
html[data-theme="light"] .ticket-select-status[data-value="processing"] { background: #0D9488 !important; background-color: #0D9488 !important; }
html[data-theme="dark"] .ticket-select-status[data-value="awaiting_confirmation"],
html[data-theme="light"] .ticket-select-status[data-value="awaiting_confirmation"] { background: #7C3AED !important; background-color: #7C3AED !important; }
html[data-theme="dark"] .ticket-select-status[data-value="resolved"],
html[data-theme="light"] .ticket-select-status[data-value="resolved"] { background: #15803D !important; background-color: #15803D !important; }
html[data-theme="dark"] .ticket-select-priority[data-value="critical"],
html[data-theme="light"] .ticket-select-priority[data-value="critical"] { background: #DC2626 !important; background-color: #DC2626 !important; }
html[data-theme="dark"] .ticket-select-priority[data-value="high"],
html[data-theme="light"] .ticket-select-priority[data-value="high"] { background: #EA580C !important; background-color: #EA580C !important; }
html[data-theme="dark"] .ticket-select-priority[data-value="moderate"],
html[data-theme="light"] .ticket-select-priority[data-value="moderate"],
html[data-theme="dark"] .ticket-select-priority[data-value="medium"],
html[data-theme="light"] .ticket-select-priority[data-value="medium"] { background: #D97706 !important; background-color: #D97706 !important; }
html[data-theme="dark"] .ticket-select-priority[data-value="low"],
html[data-theme="light"] .ticket-select-priority[data-value="low"] { background: #15803D !important; background-color: #15803D !important; }
html[data-theme="dark"] .ticket-select-priority[data-value=""],
html[data-theme="light"] .ticket-select-priority[data-value=""] { background: #6B7280 !important; background-color: #6B7280 !important; }
html[data-theme="dark"] .mailbox-search::placeholder,
html[data-theme="dark"] .mailbox-chat-input::placeholder,
html[data-theme="dark"] .showcase .head header .search-bar::placeholder,
html[data-theme="dark"] .search-bar-wrapper .search-bar::placeholder,
html[data-theme="dark"] input.search-bar::placeholder {
    color: #9a9aaa !important;
    opacity: 1 !important;
}
html[data-theme="dark"] .search-bar-wrapper .search-icon {
    color: #9a9aaa !important;
}

/* Performance search fields stay light for contrast */
html[data-theme="dark"] #page-performance .perf-filters-row .search-bar,
html[data-theme="dark"] #page-performance #perf-log-search,
html[data-theme="dark"] #page-performance .search-bar-wrapper .search-bar {
    background: #ffffff !important;
    background-color: #ffffff !important;
    color: #1a1a1a !important;
    border: 1px solid #d9d9d9 !important;
}
html[data-theme="dark"] #page-performance #perf-log-search::placeholder,
html[data-theme="dark"] #page-performance .search-bar-wrapper .search-bar::placeholder {
    color: #787777 !important;
    opacity: 1 !important;
}
html[data-theme="dark"] #page-performance .search-bar-wrapper .search-icon {
    color: #787777 !important;
}
html[data-theme="dark"] .filter-tab.active-tab {
    background: #610107 !important;
    color: #fff !important;
}
html[data-theme="dark"] .mailbox-msg.received .mailbox-msg-bubble {
    background: #2a2a32 !important;
    color: #f2f2f7 !important;
}
html[data-theme="dark"] .mailbox-back-btn {
    background: #2a2a32 !important;
    color: #f5d0d4 !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
}
html[data-theme="dark"] .mobile-tabbar {
    background: #1c1c22 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
}
html[data-theme="dark"] .mobile-tab-btn { color: #e0e0e8 !important; }
html[data-theme="dark"] .mobile-tab-btn.selected {
    color: #f5d0d4 !important;
    background: rgba(139, 74, 82, 0.28) !important;
}
html[data-theme="dark"] #page-profile .profile-hero-avatar {
    background: #32323c !important;
    color: #f2f2f7 !important;
}
html[data-theme="dark"] .ticket-row {
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

.tickets-col-category {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 72px !important;
    text-align: center !important;
    visibility: visible !important;
}

/* Keep open tickets from painting over Ticket History */
body[data-page="tickets"] .showcase {
    overflow-y: auto !important;
}
body[data-page="tickets"] #page-tickets {
    overflow-y: auto !important;
}
body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-user,
body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-user--actions,
body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-techn-actions,
body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-admin-five {
    flex: 0 1 auto !important;
    min-height: 200px !important;
    max-height: min(48vh, 480px) !important;
    overflow: auto !important;
}
body[data-page="tickets"] #page-tickets .tickets-list-user--actions .tickets-list-header,
body[data-page="tickets"] #page-tickets .tickets-list-techn-actions .tickets-list-header,
body[data-page="tickets"] #page-tickets .tickets-list-admin-five .tickets-list-header {
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
}
body[data-page="tickets"] #page-tickets .ticket-history-section {
    position: relative !important;
    z-index: 6 !important;
    isolation: isolate !important;
    margin-top: 12px !important;
    background: #fff !important;
}
html[data-theme="dark"] body[data-page="tickets"] #page-tickets .ticket-history-section {
    background: #141418 !important;
}

/* ========== Mobile: keep content above bottom tab bar ========== */
@media (max-width: 768px) {
    body {
        --zpgc-tabbar-h: calc(58px + env(safe-area-inset-bottom, 0px));
        padding-bottom: calc(var(--zpgc-tabbar-h) + 16px) !important;
    }
    body[data-page="tickets"] #page-tickets,
    body[data-page="utilities"] #page-utilities,
    body[data-page="performance"] #page-performance,
    body[data-page="profile"] #page-profile,
    body[data-page="settings"] #page-settings,
    body[data-page="dashboard"] #page-dashboard {
        padding-bottom: calc(var(--zpgc-tabbar-h) + 80px) !important;
        box-sizing: border-box !important;
    }
    body[data-page="tickets"] .tickets-list-body,
    body[data-page="performance"] .tickets-list-body,
    body[data-page="utilities"] .audit-panel {
        padding-bottom: 28px !important;
    }

    /* User/tech tickets + dashboard: page must scroll above the tab bar */
    body[data-page="tickets"] .showcase,
    body[data-page="dashboard"] .showcase {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        max-height: 100dvh !important;
    }
    body[data-page="tickets"] #page-tickets {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        min-height: 0 !important;
        max-height: none !important;
    }
    body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-user,
    body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-user--actions,
    body[data-page="tickets"] #page-tickets .tickets-list.tickets-list-techn-actions {
        flex: 0 1 auto !important;
        min-height: 180px !important;
        max-height: min(46vh, 340px) !important;
        margin-bottom: 16px !important;
    }
    body[data-page="tickets"] #page-tickets .ticket-history-section {
        flex: 0 0 auto !important;
        flex-shrink: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-bottom: calc(var(--zpgc-tabbar-h) + 48px) !important;
        padding-bottom: 20px !important;
        box-sizing: border-box !important;
    }
    body[data-page="tickets"] #page-tickets .ticket-history-list.tickets-list {
        flex: 0 0 auto !important;
        min-height: 140px !important;
        max-height: min(36vh, 260px) !important;
        height: auto !important;
        margin-bottom: 12px !important;
    }
    body[data-page="tickets"] #page-tickets .ticket-history-list .tickets-list-body {
        flex: 0 1 auto !important;
        min-height: 0 !important;
    }
    /* Extra end spacer so last history pixels clear the fixed ribbon */
    body[data-page="tickets"] #page-tickets::after {
        content: "" !important;
        display: block !important;
        flex: 0 0 auto !important;
        height: calc(var(--zpgc-tabbar-h) + 24px) !important;
        width: 100% !important;
        pointer-events: none !important;
    }
    body[data-page="dashboard"] #page-dashboard.role-dashboard-page {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        min-height: 0 !important;
        padding-bottom: calc(var(--zpgc-tabbar-h) + 80px) !important;
    }
    body[data-page="dashboard"] #page-dashboard.role-dashboard-page .role-dash-list {
        flex: 0 1 auto !important;
        min-height: 160px !important;
        max-height: min(50vh, 360px) !important;
        margin-bottom: calc(var(--zpgc-tabbar-h) + 28px) !important;
    }

    /* Tickets table: sticky header inside its own scroll box */
    body[data-page="tickets"] .tickets-list.tickets-list-admin-five {
        overflow: auto !important;
        max-height: calc(100dvh - 250px - var(--zpgc-tabbar-h)) !important;
        margin-bottom: 16px !important;
        -webkit-overflow-scrolling: touch;
    }
    body[data-page="tickets"] .tickets-list-admin-five .tickets-list-header {
        position: sticky !important;
        top: 0 !important;
        z-index: 8 !important;
        background: #f5f5f5 !important;
        box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
    }
    html[data-theme="dark"] body[data-page="tickets"] .tickets-list.tickets-list-admin-five {
        background: #222228 !important;
    }
    html[data-theme="dark"] body[data-page="tickets"] .tickets-list-admin-five .tickets-list-header {
        background: #2a2a32 !important;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.1);
        color: #f2f2f7 !important;
    }
    html[data-theme="dark"] body[data-page="tickets"] .tickets-list-admin-five .tickets-list-header span {
        color: #f2f2f7 !important;
    }
    body[data-page="tickets"] .tickets-list-admin-five .ticket-row,
    body[data-page="tickets"] .tickets-list-admin-five form.ticket-row {
        background: #ffffff !important;
    }
    html[data-theme="dark"] body[data-page="tickets"] .tickets-list-admin-five .ticket-row,
    html[data-theme="dark"] body[data-page="tickets"] .tickets-list-admin-five form.ticket-row {
        background: #222228 !important;
        border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    }

    /* Mailbox: shell fits above tab bar; compose always visible */
    body[data-page="messages"] {
        padding-bottom: 0 !important;
        overflow: hidden !important;
        height: 100dvh !important;
    }
    body[data-page="messages"] .main-wrap,
    body[data-page="messages"] .showcase {
        height: 100dvh !important;
        max-height: 100dvh !important;
        overflow: hidden !important;
    }
    body[data-page="messages"] #page-messages {
        display: flex !important;
        flex-direction: column !important;
        height: 100% !important;
        max-height: 100% !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    body[data-page="messages"] #page-messages > .head {
        flex-shrink: 0 !important;
    }
    body[data-page="messages"] .mailbox-container {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        margin: 0 0 var(--zpgc-tabbar-h) 0 !important;
        max-height: none !important;
        border-radius: 0 !important;
        overflow: hidden !important;
    }
    body[data-page="messages"] .mailbox-threads {
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
        flex: 1 1 auto !important;
        width: 100% !important;
        background: #222228 !important;
    }
    body[data-page="messages"] .mailbox-threads-list {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 16px !important;
        background: #222228 !important;
    }
    body[data-page="messages"] .mailbox-chat {
        display: none !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        width: 100% !important;
        overflow: hidden !important;
    }
    /* Critical: hide list when a ticket chat is open (was blocked by display:flex !important) */
    body[data-page="messages"] .mailbox-container.is-chat-open .mailbox-threads {
        display: none !important;
    }
    body[data-page="messages"] .mailbox-container.is-chat-open .mailbox-chat {
        display: flex !important;
    }
    body[data-page="messages"] .mailbox-chat-messages {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    body[data-page="messages"] .mailbox-compose,
    body[data-page="messages"] .mailbox-readonly-note {
        flex-shrink: 0 !important;
        position: relative !important;
        z-index: 6 !important;
        margin: 0 !important;
        padding: 10px 12px !important;
    }

    /* Admin mobile: stop page-level horizontal clip / column desync */
    body[data-page="dashboard"] #page-dashboard,
    body[data-page="utilities"] #page-utilities,
    body[data-page="tickets"] #page-tickets {
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }

    .dashboard-charts-grid {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
        overflow: hidden !important;
    }
    .chart-card {
        max-width: 100% !important;
        min-width: 0 !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }
    .chart-card-header h2 {
        min-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    .chart-card-body {
        max-width: 100% !important;
        height: 220px !important;
    }
    .chart-card-body canvas {
        max-width: 100% !important;
    }

    body[data-page="utilities"] .showcase,
    body[data-page="utilities"] .main-wrap {
        overflow-x: hidden !important;
        max-width: 100vw !important;
    }

    body[data-page="tickets"] #page-tickets,
    body[data-page="dashboard"] #page-dashboard.role-dashboard-page {
        padding-left: 12px !important;
        padding-right: 12px !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }

    .ticket-history-list,
    .tickets-list-user,
    .tickets-list-user--actions,
    .tickets-list-techn-actions,
    .role-dash-list,
    body[data-page="utilities"] #page-utilities .tickets-list.tickets-list-utilities,
    .tickets-list-utilities,
    .admin-dashboard-history-list {
        overflow-x: auto !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        box-sizing: border-box !important;
    }
    body[data-page="utilities"] #page-utilities {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .tickets-list-user .tickets-list-body,
    .tickets-list-techn-actions .tickets-list-body,
    .role-dash-list .tickets-list-body {
        overflow: auto !important;
        flex: 1 1 auto !important;
    }

    .tickets-list-user--actions .tickets-list-header,
    .tickets-list-user--actions .ticket-row {
        display: grid !important;
        grid-template-columns: 56px 80px minmax(140px, 1.2fr) minmax(160px, 1.4fr) 100px minmax(120px, 1fr) 48px !important;
        min-width: 940px !important;
        width: 940px !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }
    .tickets-list-user--actions .tickets-list-header > span,
    .tickets-list-user--actions .ticket-row > span {
        flex: unset !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .tickets-list-user--actions .tickets-col-subject,
    .tickets-list-user--actions .tickets-col-description {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    .tickets-list-user--actions .tickets-col-subject .ai-selfhelp {
        white-space: normal !important;
    }

    #page-dashboard.role-dashboard-page .role-dash-list .tickets-list-header,
    #page-dashboard.role-dashboard-page .role-dash-list .ticket-row,
    .role-dash-list .tickets-list-header,
    .role-dash-list .ticket-row {
        display: grid !important;
        grid-template-columns: 56px minmax(140px, 1.2fr) minmax(160px, 1.4fr) 110px !important;
        min-width: 560px !important;
        width: 560px !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }
    #page-dashboard.role-dashboard-page .role-dash-list.role-dash-list--category .tickets-list-header,
    #page-dashboard.role-dashboard-page .role-dash-list.role-dash-list--category .ticket-row {
        grid-template-columns: 56px 80px minmax(120px, 1.1fr) minmax(140px, 1.3fr) 90px 100px !important;
        min-width: 800px !important;
        width: 800px !important;
    }
    .role-dash-list .tickets-col-subject,
    .role-dash-list .tickets-col-description {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .tickets-list-techn-actions .tickets-list-header,
    .tickets-list-techn-actions .ticket-row {
        display: grid !important;
        grid-template-columns: 56px 80px minmax(140px, 1.2fr) minmax(160px, 1.4fr) 120px 110px !important;
        min-width: 800px !important;
        width: 800px !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }
    .ticket-history-list .tickets-list-body,
    .tickets-list-utilities .tickets-list-body,
    .admin-dashboard-history-list .tickets-list-body {
        overflow: auto !important;
        flex: 1 1 auto !important;
    }
    .ticket-history-section {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
        box-sizing: border-box !important;
    }

    .ticket-history-list .tickets-list-header,
    .ticket-history-list .ticket-row {
        display: grid !important;
        grid-template-columns: 56px 80px minmax(120px, 1.2fr) minmax(140px, 1.4fr) 100px 100px !important;
        min-width: 800px !important;
        width: 800px !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }
    .ticket-history-list--satisfaction .tickets-list-header,
    .ticket-history-list--satisfaction .ticket-row,
    .ticket-history-list:has(.tickets-col-satisfaction) .tickets-list-header,
    .ticket-history-list:has(.tickets-col-satisfaction) .ticket-row {
        grid-template-columns: 56px 80px minmax(120px, 1.15fr) minmax(150px, 1.8fr) 100px 96px 140px !important;
        min-width: 940px !important;
        width: 940px !important;
    }
    .ticket-history-list--assigned .tickets-list-header,
    .ticket-history-list--assigned .ticket-row {
        grid-template-columns: 56px 80px minmax(110px, 1.1fr) minmax(130px, 1.25fr) 100px 100px minmax(120px, 1fr) !important;
        min-width: 980px !important;
        width: 980px !important;
    }
    .ticket-history-list--assigned.ticket-history-list--satisfaction .tickets-list-header,
    .ticket-history-list--assigned.ticket-history-list--satisfaction .ticket-row {
        grid-template-columns: 56px 80px minmax(100px, 1fr) minmax(120px, 1.15fr) 96px 96px minmax(110px, 0.9fr) minmax(120px, 0.95fr) !important;
        min-width: 1120px !important;
        width: 1120px !important;
    }

    .tickets-list-utilities .tickets-list-header,
    .tickets-list-utilities .ticket-row {
        display: grid !important;
        grid-template-columns: 48px minmax(100px, 1fr) minmax(150px, 1.3fr) 118px 88px 260px !important;
        min-width: 900px !important;
        width: 900px !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }

    .tickets-list-utilities .ticket-row.ticket-row-filtered-out,
    .tickets-list-utilities .ticket-row[hidden] {
        display: none !important;
    }

    .admin-dashboard-history-list .tickets-list-header,
    .admin-dashboard-history-list .ticket-row {
        display: grid !important;
        grid-template-columns: 56px 80px minmax(130px, 1.4fr) 96px 96px minmax(110px, 1fr) !important;
        min-width: 640px !important;
        width: 640px !important;
        max-width: none !important;
        column-gap: 10px !important;
        align-items: center !important;
        box-sizing: border-box !important;
    }
    .admin-dashboard-history-list .tcol-id,
    .admin-dashboard-history-list .tcol-category,
    .admin-dashboard-history-list .tcol-subject,
    .admin-dashboard-history-list .tcol-priority,
    .admin-dashboard-history-list .tcol-status,
    .admin-dashboard-history-list .tcol-assigned {
        flex: unset !important;
        min-width: 0 !important;
        width: 100% !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .user-form-card,
    .audit-panel,
    #page-utilities .ticket-retention-card {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        box-sizing: border-box !important;
    }

    /* Audit log: stack on phone so nothing clips sideways */
    .audit-panel {
        overflow-x: hidden !important;
        overflow-y: visible !important;
    }
    .audit-panel h2 {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }
    .audit-row.audit-head {
        display: none !important;
    }
    .audit-row:not(.audit-head) {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 6px !important;
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 14px 16px !important;
        box-sizing: border-box !important;
    }
    .audit-row:not(.audit-head) > span {
        display: block !important;
        min-width: 0 !important;
        max-width: 100% !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
        line-height: 1.4 !important;
    }
    .audit-row:not(.audit-head) > span::before {
        content: attr(data-label) ": ";
        font-weight: 700;
        color: #c4c4d0;
    }
    html:not([data-theme="dark"]) .audit-row:not(.audit-head) > span::before {
        color: #6b6b6b;
    }

    body[data-page="utilities"] .tickets-toolbar {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    body[data-page="utilities"] .tickets-toolbar .btn-new-ticket {
        flex-shrink: 0 !important;
    }
    .mobile-tab-btn {
        font-size: 11px !important;
    }
    .tickets-empty-state {
        padding: 24px 16px !important;
        align-items: center !important;
        justify-content: center !important;
    }
}
</style>
