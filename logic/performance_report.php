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
        $hasCreated = ticket_has_column($conn, 'created_at');
        $hasResponded = ticket_has_column($conn, 'responded_at');
        $hasResolved = ticket_has_column($conn, 'resolved_at');
        $created = $hasCreated ? 'created_at' : 'NULL AS created_at';
        $responded = $hasResponded ? 'responded_at' : 'NULL AS responded_at';
        $resolved = $hasResolved ? 'resolved_at' : 'NULL AS resolved_at';
        $where = "status = 'resolved'";
        $assignedTo = (int) $assignedTo;
        if ($assignedTo > 0) {
            $where .= ' AND assigned_to = ' . $assignedTo;
        }
        $sql = "SELECT id, subject, description, category, priority, {$created}, {$responded}, {$resolved}
                FROM tickets
                WHERE {$where}
                ORDER BY id DESC
                LIMIT {$limit}";
        $log = [];
        $result = $conn->query($sql);
        if (!$result) {
            return $log;
        }
        while ($row = $result->fetch_assoc()) {
            $pri = strtolower(trim((string) ($row['priority'] ?? '')));
            if (!in_array($pri, ['critical', 'moderate', 'low'], true)) {
                $pri = '';
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
                'resolution' => performance_duration_label($row['created_at'] ?? null, $row['resolved_at'] ?? null),
            ];
        }
        return $log;
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
