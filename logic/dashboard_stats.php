<?php

if (!function_exists('dashboard_chart_data')) {
    function dashboard_has_created_at(mysqli $conn)
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $res = $conn->query("SHOW COLUMNS FROM tickets LIKE 'created_at'");
        $has = ($res && $res->num_rows > 0);
        return $has;
    }

    function dashboard_normalize_range($range)
    {
        $range = strtolower(trim((string) $range));
        return in_array($range, ['week', 'month', 'year', 'custom'], true) ? $range : 'week';
    }

    function dashboard_parse_ymd($raw)
    {
        $raw = trim((string) $raw);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return '';
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return '';
        }
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    function dashboard_custom_bounds($from, $to)
    {
        $from = dashboard_parse_ymd($from);
        $to = dashboard_parse_ymd($to);
        if ($from === '' && $to === '') {
            return null;
        }
        if ($from === '') {
            $from = $to;
        }
        if ($to === '') {
            $to = $from;
        }
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }
        $start = strtotime($from . ' 00:00:00');
        $end = strtotime($to . ' 00:00:00');
        if ($start === false || $end === false) {
            return null;
        }
        if (($end - $start) > 366 * 86400) {
            $to = date('Y-m-d', $start + 366 * 86400);
        }
        return [$from, $to];
    }

    function dashboard_sql_between($column, $from, $to)
    {
        $column = preg_replace('/[^a-zA-Z0-9_\(\), ]/', '', (string) $column);
        $from = dashboard_parse_ymd($from);
        $to = dashboard_parse_ymd($to);
        if ($column === '' || $from === '' || $to === '') {
            return '1=1';
        }
        return "{$column} >= '{$from} 00:00:00' AND {$column} < DATE_ADD('{$to} 00:00:00', INTERVAL 1 DAY)";
    }

    function dashboard_range_label($range, $from = '', $to = '')
    {
        $bounds = dashboard_custom_bounds($from, $to);
        if ($bounds) {
            return $bounds[0] . ' to ' . $bounds[1];
        }
        $range = dashboard_normalize_range($range);
        $labels = [
            'week' => 'This Week',
            'month' => 'This Month',
            'year' => 'This Year',
        ];
        return $labels[$range] ?? 'This Week';
    }

    function dashboard_range_clause($range, $column = 'created_at')
    {
        $range = dashboard_normalize_range($range);
        $column = preg_replace('/[^a-zA-Z0-9_\(\), ]/', '', (string) $column);
        if ($column === '') {
            $column = 'created_at';
        }
        if ($range === 'month') {
            return "{$column} >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        }
        if ($range === 'year') {
            return "{$column} >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
        }
        return "{$column} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
    }

    function dashboard_resolved_date_expr(mysqli $conn)
    {
        if (function_exists('ticket_has_column') && ticket_has_column($conn, 'resolved_at')) {
            return 'COALESCE(resolved_at, created_at)';
        }
        return 'created_at';
    }

    function dashboard_chart_data(mysqli $conn, $range = 'week', $from = '', $to = '')
    {
        $bounds = dashboard_custom_bounds($from, $to);
        if ($bounds) {
            $range = 'custom';
            $from = $bounds[0];
            $to = $bounds[1];
        } else {
            $range = dashboard_normalize_range($range);
            if ($range === 'custom') {
                $range = 'week';
            }
            $from = '';
            $to = '';
        }
        $ttl = 45;
        $cacheKey = '_dash_charts_' . $range . '_' . $from . '_' . $to;
        $cacheAtKey = $cacheKey . '_at';
        $cached = $_SESSION[$cacheKey] ?? null;
        $at = (int) ($_SESSION[$cacheAtKey] ?? 0);
        if (is_array($cached) && $at > 0 && (time() - $at) < $ttl) {
            return $cached;
        }

        $hasCreated = dashboard_has_created_at($conn);
        $resolvedExpr = $hasCreated ? dashboard_resolved_date_expr($conn) : 'created_at';
        if ($bounds && $hasCreated) {
            $where = dashboard_sql_between('created_at', $from, $to);
            $resolvedWhere = dashboard_sql_between($resolvedExpr, $from, $to);
        } else {
            $where = $hasCreated ? dashboard_range_clause($range, 'created_at') : '1=1';
            $resolvedWhere = $hasCreated ? dashboard_range_clause($range, $resolvedExpr) : '1=1';
        }

        if ($range === 'custom' && $hasCreated) {
            $startTs = strtotime($from . ' 00:00:00');
            $endTs = strtotime($to . ' 00:00:00');
            $days = (int) floor(($endTs - $startTs) / 86400) + 1;
            $labels = [];
            $submitted = [];
            $resolved = [];
            $index = [];
            if ($days <= 45) {
                for ($i = 0; $i < $days; $i++) {
                    $day = date('Y-m-d', $startTs + ($i * 86400));
                    $index[$day] = $i;
                    $labels[] = date('M j', $startTs + ($i * 86400));
                    $submitted[] = 0;
                    $resolved[] = 0;
                }
                $sql = "SELECT DATE(created_at) AS bucket, COUNT(*) AS cnt FROM tickets WHERE {$where} GROUP BY DATE(created_at)";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $key = (string) $row['bucket'];
                        if (isset($index[$key])) {
                            $submitted[$index[$key]] = (int) $row['cnt'];
                        }
                    }
                }
                $sqlR = "SELECT DATE({$resolvedExpr}) AS bucket, COUNT(*) AS cnt FROM tickets WHERE status = 'resolved' AND {$resolvedWhere} GROUP BY DATE({$resolvedExpr})";
                if ($res = $conn->query($sqlR)) {
                    while ($row = $res->fetch_assoc()) {
                        $key = (string) $row['bucket'];
                        if (isset($index[$key])) {
                            $resolved[$index[$key]] = (int) $row['cnt'];
                        }
                    }
                }
            } else {
                $cursor = strtotime(date('Y-m-01', $startTs));
                $endMonth = strtotime(date('Y-m-01', $endTs));
                $i = 0;
                while ($cursor <= $endMonth) {
                    $key = date('Y-m', $cursor);
                    $index[$key] = $i;
                    $labels[] = date('M Y', $cursor);
                    $submitted[] = 0;
                    $resolved[] = 0;
                    $cursor = strtotime('+1 month', $cursor);
                    $i++;
                }
                $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket, COUNT(*) AS cnt FROM tickets WHERE {$where} GROUP BY bucket";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $key = (string) $row['bucket'];
                        if (isset($index[$key])) {
                            $submitted[$index[$key]] = (int) $row['cnt'];
                        }
                    }
                }
                $sqlR = "SELECT DATE_FORMAT({$resolvedExpr}, '%Y-%m') AS bucket, COUNT(*) AS cnt FROM tickets WHERE status = 'resolved' AND {$resolvedWhere} GROUP BY bucket";
                if ($res = $conn->query($sqlR)) {
                    while ($row = $res->fetch_assoc()) {
                        $key = (string) $row['bucket'];
                        if (isset($index[$key])) {
                            $resolved[$index[$key]] = (int) $row['cnt'];
                        }
                    }
                }
            }
        } elseif ($range === 'year') {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $submitted = array_fill(0, 12, 0);
            $resolved = array_fill(0, 12, 0);
            if ($hasCreated) {
                $sql = "SELECT MONTH(created_at) AS bucket, COUNT(*) AS cnt
                        FROM tickets
                        WHERE {$where}
                        GROUP BY MONTH(created_at)";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = (int) $row['bucket'] - 1;
                        if ($idx >= 0 && $idx < 12) {
                            $submitted[$idx] = (int) $row['cnt'];
                        }
                    }
                }
                $sqlR = "SELECT MONTH({$resolvedExpr}) AS bucket, COUNT(*) AS cnt
                         FROM tickets
                         WHERE status = 'resolved' AND {$resolvedWhere}
                         GROUP BY MONTH({$resolvedExpr})";
                if ($res = $conn->query($sqlR)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = (int) $row['bucket'] - 1;
                        if ($idx >= 0 && $idx < 12) {
                            $resolved[$idx] = (int) $row['cnt'];
                        }
                    }
                }
            }
        } elseif ($range === 'month') {
            $daysInMonth = (int) date('t');
            $labels = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $labels[] = (string) $d;
            }
            $submitted = array_fill(0, $daysInMonth, 0);
            $resolved = array_fill(0, $daysInMonth, 0);
            if ($hasCreated) {
                $sql = "SELECT DAY(created_at) AS bucket, COUNT(*) AS cnt
                        FROM tickets
                        WHERE {$where}
                        GROUP BY DAY(created_at)";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = (int) $row['bucket'] - 1;
                        if ($idx >= 0 && $idx < $daysInMonth) {
                            $submitted[$idx] = (int) $row['cnt'];
                        }
                    }
                }
                $sqlR = "SELECT DAY({$resolvedExpr}) AS bucket, COUNT(*) AS cnt
                         FROM tickets
                         WHERE status = 'resolved' AND {$resolvedWhere}
                         GROUP BY DAY({$resolvedExpr})";
                if ($res = $conn->query($sqlR)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = (int) $row['bucket'] - 1;
                        if ($idx >= 0 && $idx < $daysInMonth) {
                            $resolved[$idx] = (int) $row['cnt'];
                        }
                    }
                }
            }
        } else {
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $submitted = array_fill(0, 7, 0);
            $resolved = array_fill(0, 7, 0);
            $dowToIndex = [1 => 6, 2 => 0, 3 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 5];
            if ($hasCreated) {
                $sql = "SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
                        FROM tickets
                        WHERE {$where}
                        GROUP BY DAYOFWEEK(created_at)";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = $dowToIndex[(int) $row['dow']] ?? null;
                        if ($idx !== null) {
                            $submitted[$idx] = (int) $row['cnt'];
                        }
                    }
                }
                $sqlR = "SELECT DAYOFWEEK({$resolvedExpr}) AS dow, COUNT(*) AS cnt
                         FROM tickets
                         WHERE status = 'resolved' AND {$resolvedWhere}
                         GROUP BY DAYOFWEEK({$resolvedExpr})";
                if ($res = $conn->query($sqlR)) {
                    while ($row = $res->fetch_assoc()) {
                        $idx = $dowToIndex[(int) $row['dow']] ?? null;
                        if ($idx !== null) {
                            $resolved[$idx] = (int) $row['cnt'];
                        }
                    }
                }
            } else {
                $phpToIndex = [0 => 6, 1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];
                $todayIdx = $phpToIndex[(int) date('w')] ?? 0;
                $total = 0;
                $resT = $conn->query('SELECT COUNT(*) AS c FROM tickets');
                if ($resT && ($r = $resT->fetch_assoc())) {
                    $total = (int) $r['c'];
                }
                $resCount = 0;
                $resR = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'resolved'");
                if ($resR && ($r = $resR->fetch_assoc())) {
                    $resCount = (int) $r['c'];
                }
                $submitted[$todayIdx] = $total;
                $resolved[$todayIdx] = $resCount;
            }
        }

        $catLabels = ['hardware', 'software', 'network', 'account', 'other'];
        $catCounts = array_fill_keys($catLabels, 0);
        $catSql = "SELECT category, COUNT(*) AS cnt FROM tickets WHERE {$where} GROUP BY category";
        if ($res = $conn->query($catSql)) {
            while ($row = $res->fetch_assoc()) {
                $key = strtolower(trim((string) $row['category']));
                if (isset($catCounts[$key])) {
                    $catCounts[$key] = (int) $row['cnt'];
                }
            }
        }

        $sevLabels = ['critical', 'moderate', 'low'];
        $sevCounts = array_fill_keys($sevLabels, 0);
        $unprioritized = 0;
        $sevSql = "SELECT COALESCE(LOWER(TRIM(priority)), '') AS pri, COUNT(*) AS cnt
                   FROM tickets WHERE {$where} GROUP BY pri";
        if ($res = $conn->query($sevSql)) {
            while ($row = $res->fetch_assoc()) {
                $pri = (string) $row['pri'];
                if ($pri === '') {
                    $unprioritized += (int) $row['cnt'];
                } elseif (isset($sevCounts[$pri])) {
                    $sevCounts[$pri] = (int) $row['cnt'];
                }
            }
        }

        $buckets = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $satNote = 'Counts of ratings users saved on resolved tickets.';
        if (function_exists('ticket_ensure_satisfaction_column')) {
            ticket_ensure_satisfaction_column($conn);
        }
        if (function_exists('ticket_has_column') && ticket_has_column($conn, 'satisfaction')) {
            $satSql = "SELECT satisfaction, COUNT(*) AS cnt FROM tickets
                       WHERE satisfaction BETWEEN 1 AND 5 AND {$where}
                       GROUP BY satisfaction";
            if ($res = $conn->query($satSql)) {
                while ($row = $res->fetch_assoc()) {
                    $buckets[(int) $row['satisfaction']] = (int) $row['cnt'];
                }
            }
        } else {
            $satNote = 'Apply database/v1.6_satisfaction.sql so ratings can be stored.';
        }
        $sat = [$buckets[5], $buckets[4], $buckets[3], $buckets[2], $buckets[1]];

        $payload = [
            'live' => true,
            'range' => $range,
            'range_label' => dashboard_range_label($range, $from, $to),
            'from' => $from,
            'to' => $to,
            'report' => [
                'labels' => $labels,
                'submitted' => $submitted,
                'resolved' => $resolved,
            ],
            'categories' => [
                'labels' => ['Hardware', 'Software', 'Network', 'Account', 'Other'],
                'data' => array_values($catCounts),
            ],
            'severity' => [
                'labels' => ['Critical', 'Moderate', 'Low'],
                'data' => array_values($sevCounts),
                'unprioritized' => $unprioritized,
            ],
            'satisfaction' => [
                'labels' => ['Very satisfied', 'Satisfied', 'Not sure', 'Not satisfied', 'Hate it'],
                'data' => $sat,
                'note' => $satNote,
            ],
        ];
        $_SESSION[$cacheKey] = $payload;
        $_SESSION[$cacheAtKey] = time();
        return $payload;
    }
}
