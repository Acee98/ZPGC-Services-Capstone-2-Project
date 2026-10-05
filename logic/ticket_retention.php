<?php

require_once __DIR__ . '/ticket_times.php';
require_once __DIR__ . '/ticket_files.php';

if (!defined('ZPGC_TICKET_PURGE_BATCH')) {
    // Hard-delete at most this many tickets per purge run.
    define('ZPGC_TICKET_PURGE_BATCH', 50);
}
if (!defined('ZPGC_TICKET_PURGE_RATED_DAYS')) {
    // Rated + archived resolved tickets older than this are eligible.
    define('ZPGC_TICKET_PURGE_RATED_DAYS', 30);
}
if (!defined('ZPGC_TICKET_PURGE_UNRATED_DAYS')) {
    // Unrated archived resolved tickets need a longer grace period.
    define('ZPGC_TICKET_PURGE_UNRATED_DAYS', 60);
}
if (!defined('ZPGC_TICKET_PURGE_AUTO_HOURS')) {
    // Auto purge on admin pages at most once per this many hours.
    define('ZPGC_TICKET_PURGE_AUTO_HOURS', 6);
}

if (!function_exists('ticket_retention_backfill_archives')) {
    /**
     * Every resolved ticket must be archived automatically.
     */
    function ticket_retention_backfill_archives(mysqli $conn)
    {
        return ticket_backfill_resolved_archives($conn);
    }

    /**
     * Run archive backfill at most once per hour for the current session.
     */
    function ticket_retention_maybe_backfill(mysqli $conn, $hours = 1)
    {
        $seconds = max(300, (int) round(((float) $hours) * 3600));
        $last = (int) ($_SESSION['_ticket_backfill_at'] ?? 0);
        if ($last > 0 && (time() - $last) < $seconds) {
            return 0;
        }
        $_SESSION['_ticket_backfill_at'] = time();
        return ticket_retention_backfill_archives($conn);
    }

    function ticket_retention_count_eligible_cached(mysqli $conn, $ttlSeconds = 300)
    {
        $ttlSeconds = max(30, (int) $ttlSeconds);
        $cached = $_SESSION['_purge_eligible'] ?? null;
        $at = (int) ($_SESSION['_purge_eligible_at'] ?? 0);
        if (is_int($cached) && $at > 0 && (time() - $at) < $ttlSeconds) {
            return $cached;
        }
        $n = ticket_retention_count_eligible($conn);
        $_SESSION['_purge_eligible'] = $n;
        $_SESSION['_purge_eligible_at'] = time();
        return $n;
    }

    function ticket_retention_clear_eligible_cache()
    {
        unset($_SESSION['_purge_eligible'], $_SESSION['_purge_eligible_at']);
    }

    function ticket_retention_eligible_ids(mysqli $conn, $limit = null)
    {
        ticket_ensure_archived_column($conn);
        if (!ticket_has_column($conn, 'archived_at')) {
            return [];
        }
        $limit = $limit === null ? ZPGC_TICKET_PURGE_BATCH : max(1, (int) $limit);
        $ratedDays = max(1, (int) ZPGC_TICKET_PURGE_RATED_DAYS);
        $unratedDays = max($ratedDays, (int) ZPGC_TICKET_PURGE_UNRATED_DAYS);

        // Requirements for hard delete:
        // 1) status = resolved
        // 2) archived_at is set
        // 3) rated tickets: archived_at older than RATED_DAYS
        // 4) unrated tickets: archived_at older than UNRATED_DAYS
        $sql = "SELECT id FROM tickets
                WHERE status = 'resolved'
                  AND archived_at IS NOT NULL
                  AND (
                        (
                          satisfaction BETWEEN 1 AND 5
                          AND archived_at <= DATE_SUB(NOW(), INTERVAL {$ratedDays} DAY)
                        )
                     OR (
                          (satisfaction IS NULL OR satisfaction < 1 OR satisfaction > 5)
                          AND archived_at <= DATE_SUB(NOW(), INTERVAL {$unratedDays} DAY)
                        )
                  )
                ORDER BY archived_at ASC, id ASC
                LIMIT {$limit}";
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        return $ids;
    }

    function ticket_retention_count_eligible(mysqli $conn)
    {
        ticket_ensure_archived_column($conn);
        if (!ticket_has_column($conn, 'archived_at')) {
            return 0;
        }
        $ratedDays = max(1, (int) ZPGC_TICKET_PURGE_RATED_DAYS);
        $unratedDays = max($ratedDays, (int) ZPGC_TICKET_PURGE_UNRATED_DAYS);
        $sql = "SELECT COUNT(*) AS c FROM tickets
                WHERE status = 'resolved'
                  AND archived_at IS NOT NULL
                  AND (
                        (
                          satisfaction BETWEEN 1 AND 5
                          AND archived_at <= DATE_SUB(NOW(), INTERVAL {$ratedDays} DAY)
                        )
                     OR (
                          (satisfaction IS NULL OR satisfaction < 1 OR satisfaction > 5)
                          AND archived_at <= DATE_SUB(NOW(), INTERVAL {$unratedDays} DAY)
                        )
                  )";
        $result = $conn->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }

    function ticket_retention_delete_one(mysqli $conn, $ticketId)
    {
        $ticketId = (int) $ticketId;
        if ($ticketId <= 0) {
            return false;
        }

        $conn->query('DELETE FROM messages WHERE ticket_id = ' . $ticketId);

        $tables = $conn->query("SHOW TABLES LIKE 'ticket_attachments'");
        if ($tables && $tables->num_rows > 0) {
            $files = $conn->query(
                'SELECT stored_name FROM ticket_attachments WHERE ticket_id = ' . $ticketId
            );
            if ($files) {
                while ($file = $files->fetch_assoc()) {
                    ticket_unlink_stored((string) $file['stored_name']);
                }
            }
            $conn->query('DELETE FROM ticket_attachments WHERE ticket_id = ' . $ticketId);
        }

        $repl = $conn->query("SHOW TABLES LIKE 'replacement_requests'");
        if ($repl && $repl->num_rows > 0) {
            $conn->query('DELETE FROM replacement_requests WHERE ticket_id = ' . $ticketId);
        }

        $auth = $conn->query("SHOW TABLES LIKE 'auth_tokens'");
        // auth_tokens are user-scoped, not ticket-scoped — leave them.

        $del = $conn->prepare('DELETE FROM tickets WHERE id = ? AND status = \'resolved\'');
        $del->bind_param('i', $ticketId);
        $del->execute();
        $ok = $del->affected_rows > 0;
        $del->close();
        return $ok;
    }

    /**
     * @return array{archived_backfill:int,eligible:int,deleted:int,ids:int[]}
     */
    function ticket_retention_purge_batch(mysqli $conn, $limit = null)
    {
        $backfill = ticket_retention_backfill_archives($conn);
        $ids = ticket_retention_eligible_ids($conn, $limit);
        $deleted = 0;
        $removed = [];
        foreach ($ids as $id) {
            if (ticket_retention_delete_one($conn, $id)) {
                $deleted++;
                $removed[] = $id;
            }
        }
        return [
            'archived_backfill' => $backfill,
            'eligible' => count($ids),
            'deleted' => $deleted,
            'ids' => $removed,
        ];
    }

    function ticket_retention_maybe_auto_purge(mysqli $conn)
    {
        $hours = max(1, (int) ZPGC_TICKET_PURGE_AUTO_HOURS);
        $last = (int) ($_SESSION['_ticket_purge_at'] ?? 0);
        if ($last > 0 && (time() - $last) < ($hours * 3600)) {
            return null;
        }
        $_SESSION['_ticket_purge_at'] = time();
        $result = ticket_retention_purge_batch($conn);
        ticket_retention_clear_eligible_cache();
        if ($result['deleted'] > 0 && function_exists('audit_write')) {
            $detail = 'Auto-disposed ' . $result['deleted'] . ' archived resolved ticket(s)';
            if (!empty($result['ids'])) {
                $detail .= ': #' . implode(', #', array_slice($result['ids'], 0, 12));
            }
            audit_write($conn, 'ticket_disposal_auto', 0, substr($detail, 0, 255));
        }
        return $result;
    }

    function ticket_retention_rules_summary()
    {
        return [
            'batch' => (int) ZPGC_TICKET_PURGE_BATCH,
            'rated_days' => (int) ZPGC_TICKET_PURGE_RATED_DAYS,
            'unrated_days' => (int) ZPGC_TICKET_PURGE_UNRATED_DAYS,
            'auto_hours' => (int) ZPGC_TICKET_PURGE_AUTO_HOURS,
        ];
    }
}
