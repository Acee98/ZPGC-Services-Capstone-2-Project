<?php

require_once __DIR__ . '/ticket_times.php';

if (!function_exists('assign_least_busy_technician')) {
    function ticket_routing_specialty($category)
    {
        $cat = strtolower(trim((string) $category));
        $allowed = ['hardware', 'software', 'network', 'account', 'other'];
        if (!in_array($cat, $allowed, true)) {
            $cat = 'other';
        }
        return $cat;
    }

    function ticket_has_applications_table(mysqli $conn)
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $res = $conn->query("SHOW TABLES LIKE 'technician_applications'");
        $has = ($res && $res->num_rows > 0);
        return $has;
    }

    /**
     * Workload: tickets a technician is actively working.
     * Pending Low (self-help hold) does not count, so Critical routing is not starved.
     */
    function ticket_technician_open_count_sql()
    {
        return "SUM(CASE
                    WHEN t.id IS NOT NULL
                     AND (
                            t.status IN ('ongoing','processing','awaiting_confirmation')
                         OR (t.status = 'pending' AND LOWER(IFNULL(t.priority,'')) <> 'low')
                     )
                    THEN 1 ELSE 0 END)";
    }

    function ticket_pick_technician_by_specialty(mysqli $conn, $specialty)
    {
        $specialty = ticket_routing_specialty($specialty);
        if ($specialty === '' || !ticket_has_applications_table($conn)) {
            return null;
        }
        $openSql = ticket_technician_open_count_sql();
        $sql = "SELECT u.id, {$openSql} AS open_count
                FROM users u
                INNER JOIN technician_applications a ON a.user_id = u.id
                LEFT JOIN tickets t ON t.assigned_to = u.id
                WHERE u.role = 'techn' AND u.status = 'active'
                  AND LOWER(a.specialty) = ?
                GROUP BY u.id
                ORDER BY open_count ASC, u.id ASC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $specialty);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int) $row['id'] > 0) {
            return (int) $row['id'];
        }
        return null;
    }

    function assign_least_busy_technician(mysqli $conn, $category = '')
    {
        $specialty = ticket_routing_specialty($category);
        $match = ticket_pick_technician_by_specialty($conn, $specialty);
        if ($match) {
            return $match;
        }
        if ($specialty !== 'other') {
            $other = ticket_pick_technician_by_specialty($conn, 'other');
            if ($other) {
                return $other;
            }
        }
        return null;
    }

    /**
     * @param array $opts category?: string, mark_responded?: bool
     */
    function ticket_auto_assign(mysqli $conn, $ticket_id, $status = 'ongoing', array $opts = [])
    {
        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0) {
            return null;
        }

        $category = isset($opts['category']) ? (string) $opts['category'] : '';
        if ($category === '') {
            $q = $conn->prepare('SELECT category FROM tickets WHERE id = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $ticket_id);
                $q->execute();
                $found = $q->get_result()->fetch_assoc();
                $q->close();
                $category = (string) ($found['category'] ?? '');
            }
        }

        $tech_id = assign_least_busy_technician($conn, $category);
        if ($tech_id === null || $tech_id <= 0) {
            return null;
        }

        $allowed = ['pending', 'ongoing', 'processing'];
        if (!in_array($status, $allowed, true)) {
            $status = 'ongoing';
        }

        $stmt = $conn->prepare(
            'UPDATE tickets SET assigned_to = ?, status = ? WHERE id = ? AND (assigned_to IS NULL OR assigned_to = 0)'
        );
        $stmt->bind_param('isi', $tech_id, $status, $ticket_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        $markResponded = !array_key_exists('mark_responded', $opts) || $opts['mark_responded'];
        if ($ok && $markResponded) {
            ticket_mark_responded($conn, $ticket_id);
        }
        return $ok ? $tech_id : null;
    }

    function ticket_release_self_help_hold(mysqli $conn, $ticket_id, $status = 'ongoing')
    {
        $ticket_id = (int) $ticket_id;
        $allowed = ['pending', 'ongoing', 'processing'];
        if (!in_array($status, $allowed, true)) {
            $status = 'ongoing';
        }
        $stmt = $conn->prepare(
            'UPDATE tickets SET status = ? WHERE id = ? AND status = \'pending\''
        );
        $stmt->bind_param('si', $status, $ticket_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        if ($ok) {
            ticket_mark_responded($conn, $ticket_id);
        }
        return $ok;
    }
}
