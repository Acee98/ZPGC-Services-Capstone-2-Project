<?php
/**
 * Live dashboard chart aggregates from tickets table.
 * Returns array suitable for json_encode into JS.
 */
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

    function dashboard_chart_data(mysqli $conn)
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $submitted = array_fill(0, 7, 0);
        $resolved = array_fill(0, 7, 0);

        // MySQL DAYOFWEEK: 1=Sunday … 7=Saturday → Mon-first index 0..6
        $dowToIndex = [1 => 6, 2 => 0, 3 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 5];

        if (dashboard_has_created_at($conn)) {
            $sql = "SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
                    FROM tickets
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                    GROUP BY DAYOFWEEK(created_at)";
            if ($res = $conn->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $dow = (int) $row['dow'];
                    $idx = $dowToIndex[$dow] ?? null;
                    if ($idx !== null) {
                        $submitted[$idx] = (int) $row['cnt'];
                    }
                }
            }

            $sqlR = "SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
                     FROM tickets
                     WHERE status = 'resolved'
                       AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                     GROUP BY DAYOFWEEK(created_at)";
            if ($res = $conn->query($sqlR)) {
                while ($row = $res->fetch_assoc()) {
                    $dow = (int) $row['dow'];
                    $idx = $dowToIndex[$dow] ?? null;
                    if ($idx !== null) {
                        $resolved[$idx] = (int) $row['cnt'];
                    }
                }
            }
        } else {
            // Fallback without created_at: put totals on "today" slot (PHP date w: 0=Sun)
            $phpDow = (int) date('w');
            $phpToIndex = [0 => 6, 1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];
            $todayIdx = $phpToIndex[$phpDow] ?? 0;
            $total = 0;
            $resT = $conn->query('SELECT COUNT(*) AS c FROM tickets');
            if ($resT && ($r = $resT->fetch_assoc())) {
                $total = (int) $r['c'];
            }
            $resR = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'resolved'");
            $resCount = 0;
            if ($resR && ($r = $resR->fetch_assoc())) {
                $resCount = (int) $r['c'];
            }
            $submitted[$todayIdx] = $total;
            $resolved[$todayIdx] = $resCount;
        }

        $catLabels = ['hardware', 'software', 'network', 'account', 'other'];
        $catCounts = array_fill_keys($catLabels, 0);
        if ($res = $conn->query('SELECT category, COUNT(*) AS cnt FROM tickets GROUP BY category')) {
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
        if ($res = $conn->query(
            "SELECT COALESCE(LOWER(TRIM(priority)), '') AS pri, COUNT(*) AS cnt FROM tickets GROUP BY pri"
        )) {
            while ($row = $res->fetch_assoc()) {
                $pri = (string) $row['pri'];
                if ($pri === '' || $pri === null) {
                    $unprioritized += (int) $row['cnt'];
                } elseif (isset($sevCounts[$pri])) {
                    $sevCounts[$pri] = (int) $row['cnt'];
                }
            }
        }

        // Satisfaction: no rating table yet — derive soft proxy from resolved vs reopen-ish statuses
        $resolvedN = 0;
        $openN = 0;
        if ($res = $conn->query(
            "SELECT
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_n,
                SUM(CASE WHEN status IN ('pending','ongoing','processing','awaiting_confirmation') THEN 1 ELSE 0 END) AS open_n
             FROM tickets"
        )) {
            if ($row = $res->fetch_assoc()) {
                $resolvedN = (int) $row['resolved_n'];
                $openN = (int) $row['open_n'];
            }
        }
        $totalSat = max(1, $resolvedN + $openN);
        // Proxy buckets so the chart is never empty; not a real survey.
        $sat = [
            (int) round(($resolvedN / $totalSat) * 50),
            (int) round(($resolvedN / $totalSat) * 30),
            (int) round(($openN / $totalSat) * 15),
            (int) round(($openN / $totalSat) * 10),
            (int) round(($openN / $totalSat) * 5),
        ];

        return [
            'live' => true,
            'report' => [
                'labels' => $days,
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
                'note' => 'Proxy from resolved vs open tickets (no survey table yet).',
            ],
        ];
    }
}
