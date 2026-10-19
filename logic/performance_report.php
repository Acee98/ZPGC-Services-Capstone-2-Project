<?php

if (!function_exists('performance_duration_label')) {
    function performance_duration_label($from, $to)
    {
        if ($from === null || $from === '' || $to === null || $to === '') {
            return '—';
        }
        $start = strtotime((string) $from);
        $end = strtotime((string) $to);
        if ($start === false || $end === false || $end < $start) {
            return '—';
        }
        $seconds = $end - $start;
        if ($seconds < 60) {
            return 'Under 1 minute';
        }
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ($minutes === 1 ? ' Minute' : ' Minutes');
        }
        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;
        $hourLabel = $hours . ($hours === 1 ? ' Hour' : ' Hours');
        if ($remain === 0) {
            return $hourLabel;
        }
        return $hourLabel . ' ' . $remain . ' min';
    }

    function performance_registered_label($createdAt)
    {
        if ($createdAt === null || $createdAt === '') {
            return '—';
        }
        $ts = strtotime((string) $createdAt);
        if ($ts === false) {
            return '—';
        }
        return date('m/d/Y (H:i)', $ts);
    }

    function ticket_clock_label($from, $to = null)
    {
        $start = strtotime((string) $from);
        if ($start === false) {
            return '00:00:00';
        }
        if ($to === null || trim((string) $to) === '') {
            $end = time();
        } else {
            $end = strtotime((string) $to);
            if ($end === false) {
                $end = time();
            }
        }
        $seconds = max(0, $end - $start);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Queue stopwatch: created → now while open, or created → freezeAt when closed.
     */
    function ticket_queue_timer_label($createdAt, $freezeAt = null, $allowLive = true)
    {
        if ($allowLive && ($freezeAt === null || trim((string) $freezeAt) === '')) {
            return ticket_clock_label($createdAt, null);
        }
        if ($freezeAt === null || trim((string) $freezeAt) === '') {
            return '00:00:00';
        }
        return ticket_clock_label($createdAt, $freezeAt);
    }

    function ticket_queue_timer_is_live($status)
    {
        return strtolower(trim((string) $status)) !== 'resolved';
    }

    function ticket_resolution_clock_label($createdAt, $resolvedAt)
    {
        if ($resolvedAt === null || trim((string) $resolvedAt) === '') {
            return '—';
        }
        return ticket_clock_label($createdAt, $resolvedAt);
    }

    function performance_category_rows(mysqli $conn, $assignedTo = 0)
    {
        $order = ['hardware', 'software', 'network', 'account', 'other'];
        $rows = [];
        foreach ($order as $cat) {
            $rows[$cat] = [
                'category' => $cat,
                'label' => ucfirst($cat),
                'total' => 0,
                'critical' => 0,
                'moderate' => 0,
                'low' => 0,
            ];
        }
        $where = "status = 'resolved'";
        if (function_exists('ticket_has_column') && ticket_has_column($conn, 'archived_at')) {
            $where = "(status = 'resolved' OR archived_at IS NOT NULL)";
        }
        $assignedTo = (int) $assignedTo;
        if ($assignedTo > 0) {
            $where .= ' AND assigned_to = ' . $assignedTo;
        }
        $sql = "SELECT LOWER(TRIM(category)) AS cat,
                       LOWER(TRIM(IFNULL(priority, ''))) AS pri,
                       COUNT(*) AS cnt
                FROM tickets
                WHERE {$where}
                GROUP BY cat, pri";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $cat = (string) $row['cat'];
                if (!isset($rows[$cat])) {
                    continue;
                }
                $n = (int) $row['cnt'];
                $rows[$cat]['total'] += $n;
                $pri = (string) $row['pri'];
                if (isset($rows[$cat][$pri])) {
                    $rows[$cat][$pri] = $n;
                }
            }
        }
        return array_values($rows);
    }

    function performance_clip_text($text, $max = 160)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $max) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $max - 1)) . '…';
        }
        if (strlen($text) <= $max) {
            return $text;
        }
        return rtrim(substr($text, 0, $max - 1)) . '…';
    }

    /**
     * Recent resolved tickets for the Performance log.
     */
    function performance_resolved_log(mysqli $conn, $limit = 100, $assignedTo = 0)
    {
        $limit = max(1, min(500, (int) $limit));
        $created = ticket_has_column($conn, 'created_at') ? 't.created_at' : 'NULL AS created_at';
        $responded = ticket_has_column($conn, 'responded_at') ? 't.responded_at' : 'NULL AS responded_at';
        $resolved = ticket_has_column($conn, 'resolved_at') ? 't.resolved_at' : 'NULL AS resolved_at';
        $satCol = ticket_has_column($conn, 'satisfaction') ? 't.satisfaction' : 'NULL AS satisfaction';
        $where = "t.status = 'resolved'";
        if (function_exists('ticket_has_column') && ticket_has_column($conn, 'archived_at')) {
            $where = "(t.status = 'resolved' OR t.archived_at IS NOT NULL)";
        }
        $assignedTo = (int) $assignedTo;
        if ($assignedTo > 0) {
            $where .= ' AND t.assigned_to = ' . $assignedTo;
        }
        $sql = "SELECT t.id, t.subject, t.description, t.category, t.priority, t.assigned_to,
                       {$satCol}, {$created}, {$responded}, {$resolved},
                       u.email AS tech_email, u.first_name AS tech_first, u.last_name AS tech_last
                FROM tickets t
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE {$where}
                ORDER BY t.id DESC
                LIMIT {$limit}";
        $log = [];
        $result = $conn->query($sql);
        if (!$result) {
            return $log;
        }
        $satLabels = function_exists('ticket_satisfaction_labels') ? ticket_satisfaction_labels() : [];
        while ($row = $result->fetch_assoc()) {
            if (function_exists('ticket_reveal_row')) {
                ticket_reveal_row($row);
            }
            $pri = strtolower(trim((string) ($row['priority'] ?? '')));
            if (!in_array($pri, ['critical', 'moderate', 'low'], true)) {
                $pri = '';
            }
            $sat = (int) ($row['satisfaction'] ?? 0);
            $techName = trim((string) ($row['tech_first'] ?? '') . ' ' . (string) ($row['tech_last'] ?? ''));
            if ($techName === '') {
                $techName = (string) ($row['tech_email'] ?? '');
            }
            $log[] = [
                'id' => (int) $row['id'],
                'subject' => performance_clip_text((string) $row['subject'], 120),
                'description' => performance_clip_text((string) $row['description'], 160),
                'category' => strtolower(trim((string) $row['category'])),
                'category_label' => ucfirst(strtolower(trim((string) $row['category']))),
                'priority' => $pri,
                'priority_label' => $pri === '' ? 'None' : ucfirst($pri),
                'registered' => performance_registered_label($row['created_at'] ?? null),
                'response' => performance_duration_label($row['created_at'] ?? null, $row['responded_at'] ?? null),
                'resolution' => ticket_resolution_clock_label($row['created_at'] ?? null, $row['resolved_at'] ?? null),
                'satisfaction' => $sat,
                'satisfaction_label' => $satLabels[$sat] ?? '',
                'technician' => $techName !== '' ? $techName : 'Unassigned',
            ];
        }
        return $log;
    }

    function performance_technician_rows(mysqli $conn)
    {
        $rows = [];
        $resolvedJoin = "t.status = 'resolved'";
        if (function_exists('ticket_has_column') && ticket_has_column($conn, 'archived_at')) {
            $resolvedJoin = "(t.status = 'resolved' OR t.archived_at IS NOT NULL)";
        }
        $sql = "SELECT u.id, u.email, u.first_name, u.last_name,
                       IFNULL(a.specialty, '') AS specialty,
                       COUNT(t.id) AS total
                FROM users u
                LEFT JOIN technician_applications a ON a.user_id = u.id
                LEFT JOIN tickets t ON t.assigned_to = u.id AND {$resolvedJoin}
                WHERE u.role = 'techn'
                GROUP BY u.id, u.email, u.first_name, u.last_name, a.specialty
                ORDER BY total DESC, u.email ASC";
        $result = $conn->query($sql);
        if (!$result) {
            return $rows;
        }
        while ($row = $result->fetch_assoc()) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => $name !== '' ? $name : (string) $row['email'],
                'email' => (string) $row['email'],
                'specialty' => (string) ($row['specialty'] ?? ''),
                'total' => (int) $row['total'],
            ];
        }
        return $rows;
    }

    function performance_service_summary(mysqli $conn)
    {
        $summary = [
            'resolved' => 0,
            'total' => 0,
            'rate' => '—',
            'avg_response' => '—',
        ];
        $counts = $conn->query(
            "SELECT
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_n,
                COUNT(*) AS total_n
             FROM tickets"
        );
        if ($counts && ($row = $counts->fetch_assoc())) {
            $summary['resolved'] = (int) $row['resolved_n'];
            $summary['total'] = (int) $row['total_n'];
            if ($summary['total'] > 0) {
                $summary['rate'] = (string) (int) round(($summary['resolved'] / $summary['total']) * 100) . '%';
            }
        }
        $avg = $conn->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, responded_at)) AS mins
             FROM tickets
             WHERE status = 'resolved'
               AND created_at IS NOT NULL
               AND responded_at IS NOT NULL"
        );
        if ($avg && ($row = $avg->fetch_assoc()) && $row['mins'] !== null) {
            $minutes = (int) round((float) $row['mins']);
            if ($minutes < 60) {
                $summary['avg_response'] = $minutes . ($minutes === 1 ? ' minute' : ' minutes');
            } else {
                $hours = intdiv($minutes, 60);
                $remain = $minutes % 60;
                $summary['avg_response'] = $hours . ($hours === 1 ? ' hour' : ' hours');
                if ($remain > 0) {
                    $summary['avg_response'] .= ' ' . $remain . ' min';
                }
            }
        }
        return $summary;
    }
}
