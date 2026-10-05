<?php

if (!function_exists('ticket_has_column')) {
    function ticket_has_column(mysqli $conn, $column, $refresh = false)
    {
        static $cache = [];
        $column = (string) $column;
        if ($refresh) {
            unset($cache[$column]);
        }
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        $col = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM tickets LIKE '{$col}'");
        $cache[$column] = ($res && $res->num_rows > 0);
        return $cache[$column];
    }

    function ticket_ensure_archived_column(mysqli $conn)
    {
        if (ticket_has_column($conn, 'archived_at')) {
            return;
        }
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            return;
        }
        $conn->query(
            'ALTER TABLE tickets ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER resolved_at'
        );
        ticket_has_column($conn, 'archived_at', true);
    }

    /**
     * Ensure common ticket/message indexes exist (skips if already present).
     */
    function ticket_ensure_indexes(mysqli $conn)
    {
        static $done = false;
        if ($done || !empty($_SESSION['_zpgc_ticket_idx'])) {
            $done = true;
            return;
        }
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            $done = true;
            return;
        }
        $done = true;

        $ensure = static function (mysqli $conn, $table, $name, $sql) {
            $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
            $name = preg_replace('/[^a-z0-9_]/i', '', (string) $name);
            if ($table === '' || $name === '') {
                return;
            }
            $chk = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            if ($chk && $chk->num_rows > 0) {
                return;
            }
            @$conn->query($sql);
        };

        $ensure($conn, 'tickets', 'idx_tickets_status', 'CREATE INDEX idx_tickets_status ON tickets (status)');
        $ensure($conn, 'tickets', 'idx_tickets_status_category', 'CREATE INDEX idx_tickets_status_category ON tickets (status, category)');
        $ensure($conn, 'tickets', 'idx_tickets_user_id', 'CREATE INDEX idx_tickets_user_id ON tickets (user_id)');
        $ensure($conn, 'tickets', 'idx_tickets_assigned_to', 'CREATE INDEX idx_tickets_assigned_to ON tickets (assigned_to)');
        if (ticket_has_column($conn, 'archived_at')) {
            $ensure($conn, 'tickets', 'idx_tickets_archived_at', 'CREATE INDEX idx_tickets_archived_at ON tickets (archived_at)');
        }
        $msgTable = $conn->query("SHOW TABLES LIKE 'messages'");
        if ($msgTable && $msgTable->num_rows > 0) {
            $ensure($conn, 'messages', 'idx_messages_ticket_id', 'CREATE INDEX idx_messages_ticket_id ON messages (ticket_id)');
        }
        $_SESSION['_zpgc_ticket_idx'] = 1;
    }

    function ticket_backfill_resolved_archives(mysqli $conn)
    {
        ticket_ensure_archived_column($conn);
        if (!ticket_has_column($conn, 'archived_at')) {
            return 0;
        }
        $ok = $conn->query(
            "UPDATE tickets
             SET archived_at = IFNULL(resolved_at, NOW())
             WHERE status = 'resolved'
               AND archived_at IS NULL"
        );
        return $ok ? (int) $conn->affected_rows : 0;
    }

    function ticket_mark_responded(mysqli $conn, $ticket_id)
    {
        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0 || !ticket_has_column($conn, 'responded_at')) {
            return;
        }
        $stmt = $conn->prepare(
            'UPDATE tickets SET responded_at = IFNULL(responded_at, NOW()) WHERE id = ?'
        );
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $stmt->close();
    }

    function ticket_mark_archived(mysqli $conn, $ticket_id)
    {
        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0) {
            return;
        }
        ticket_ensure_archived_column($conn);
        if (!ticket_has_column($conn, 'archived_at')) {
            return;
        }
        $stmt = $conn->prepare(
            'UPDATE tickets SET archived_at = IFNULL(archived_at, NOW()) WHERE id = ?'
        );
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $stmt->close();
    }

    function ticket_mark_resolved(mysqli $conn, $ticket_id)
    {
        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0) {
            return;
        }
        if (ticket_has_column($conn, 'resolved_at')) {
            $stmt = $conn->prepare(
                'UPDATE tickets SET resolved_at = IFNULL(resolved_at, NOW()) WHERE id = ?'
            );
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $stmt->close();
        }
        // Soft-archive immediately when resolved so active queues stay clean.
        ticket_mark_archived($conn, $ticket_id);
    }

    /**
     * Active Tickets tab: hide resolved and archived rows.
     * Those belong in Ticket History only.
     */
    function ticket_is_active_list_row(array $ticket)
    {
        $status = (string) ($ticket['status'] ?? '');
        if ($status === 'resolved') {
            return false;
        }
        if (!empty($ticket['archived_at'])) {
            return false;
        }
        return true;
    }

    function ticket_is_history_row(array $ticket)
    {
        $status = (string) ($ticket['status'] ?? '');
        return $status === 'resolved' || !empty($ticket['archived_at']);
    }

    function ticket_partition_active_history(array $tickets)
    {
        $active = [];
        $history = [];
        foreach ($tickets as $ticket) {
            if (ticket_is_history_row($ticket)) {
                $history[] = $ticket;
            } else {
                $active[] = $ticket;
            }
        }
        return [$active, $history];
    }
}
