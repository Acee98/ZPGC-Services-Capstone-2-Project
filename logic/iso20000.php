<?php
// ISO/IEC 25010:2023 judgment table (filename kept so admin/techn includes stay stable).

require_once __DIR__ . '/performance_report.php';
if (is_file(__DIR__ . '/account_kind.php')) {
    require_once __DIR__ . '/account_kind.php';
}

if (!function_exists('iso20000_minutes_label')) {
    function iso20000_minutes_label($minutes)
    {
        if ($minutes === null) {
            return '—';
        }
        $minutes = (int) round((float) $minutes);
        if ($minutes < 60) {
            return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
        }
        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;
        $out = $hours . ($hours === 1 ? ' hour' : ' hours');
        if ($remain > 0) {
            $out .= ' ' . $remain . ' min';
        }
        return $out;
    }

    function performance_office_kpis(mysqli $conn, $assignedTo = 0)
    {
        $assignedTo = (int) $assignedTo;
        $scope = $assignedTo > 0 ? ' AND assigned_to = ' . $assignedTo : '';
        $kpi = [
            'total' => 0,
            'resolved' => 0,
            'open' => 0,
            'resolution_rate' => '—',
            'avg_response' => '—',
            'avg_resolution' => '—',
            'student_tickets' => 0,
            'staff_tickets' => 0,
            'active_sessions' => 0,
        ];
        $counts = $conn->query(
            "SELECT
                COUNT(*) AS total_n,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_n
             FROM tickets
             WHERE 1=1 {$scope}"
        );
        if ($counts && ($row = $counts->fetch_assoc())) {
            $kpi['total'] = (int) $row['total_n'];
            $kpi['resolved'] = (int) $row['resolved_n'];
            $kpi['open'] = max(0, $kpi['total'] - $kpi['resolved']);
            if ($kpi['total'] > 0) {
                $kpi['resolution_rate'] = (string) (int) round(($kpi['resolved'] / $kpi['total']) * 100) . '%';
            }
        }
        $avgR = $conn->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, responded_at)) AS mins
             FROM tickets
             WHERE created_at IS NOT NULL AND responded_at IS NOT NULL {$scope}"
        );
        if ($avgR && ($row = $avgR->fetch_assoc()) && $row['mins'] !== null) {
            $kpi['avg_response'] = iso20000_minutes_label($row['mins']);
        }
        $avgZ = $conn->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS mins
             FROM tickets
             WHERE status = 'resolved'
               AND created_at IS NOT NULL
               AND resolved_at IS NOT NULL {$scope}"
        );
        if ($avgZ && ($row = $avgZ->fetch_assoc()) && $row['mins'] !== null) {
            $kpi['avg_resolution'] = iso20000_minutes_label($row['mins']);
        }
        if ($assignedTo === 0 && function_exists('account_kind_ensure') && account_kind_ensure($conn)) {
            $split = $conn->query(
                "SELECT LOWER(IFNULL(u.account_kind,'student')) AS kind, COUNT(*) AS n
                 FROM tickets t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE u.role = 'user'
                 GROUP BY kind"
            );
            if ($split) {
                while ($row = $split->fetch_assoc()) {
                    if (($row['kind'] ?? '') === 'staff') {
                        $kpi['staff_tickets'] = (int) $row['n'];
                    } else {
                        $kpi['student_tickets'] = (int) $row['n'];
                    }
                }
            }
            $kpi['active_sessions'] = account_active_sessions($conn, 15);
        }
        return $kpi;
    }

    function iso20000_last_capacity(mysqli $conn)
    {
        $tbl = $conn->query("SHOW TABLES LIKE 'capacity_checks'");
        if (!$tbl || $tbl->num_rows === 0) {
            return null;
        }
        $row = $conn->query(
            'SELECT * FROM capacity_checks ORDER BY id DESC LIMIT 1'
        );
        return $row ? $row->fetch_assoc() : null;
    }

    function iso25010_characteristics(mysqli $conn, array $kpi, $lastCapacity = null)
    {
        $last = 'Not run yet';
        if (is_array($lastCapacity) && !empty($lastCapacity['ran_at'])) {
            $last = (int) ($lastCapacity['ok_n'] ?? 0) . '/' . (int) ($lastCapacity['total_n'] ?? 0)
                . ' health checks ok, p95 '
                . (int) ($lastCapacity['p95_ms'] ?? 0) . ' ms ('
                . (string) $lastCapacity['ran_at'] . ')';
        }
        return [
            [
                'clause' => 'Functional suitability',
                'how' => 'Users submit IT tickets; classification sets category and Low–Critical; Low stays on self-help until a technician is requested.',
                'evidence' => 'Hardware / Software / Network / Account / Other with Low–Critical bands',
            ],
            [
                'key' => 'capacity',
                'clause' => 'Performance efficiency',
                'how' => 'Office-wide resolution rate, average response and resolution time, plus an on-demand concurrent health check.',
                'evidence' => 'Rate ' . $kpi['resolution_rate']
                    . ' · response ' . $kpi['avg_response']
                    . ' · resolution ' . $kpi['avg_resolution']
                    . ' · ' . (int) $kpi['active_sessions'] . ' active sessions · ' . $last,
            ],
            [
                'clause' => 'Compatibility',
                'how' => 'PHP web app in current browsers; Gmail SMTP for verify, reset, and ticket mail.',
                'evidence' => 'Campus and Gmail addresses · SMTP from the configured mailbox',
            ],
            [
                'clause' => 'Interaction capability',
                'how' => 'Three roles, Student/Staff kind, theme, Filipino, and How was this visit? after Solved.',
                'evidence' => 'Student tickets ' . (int) $kpi['student_tickets']
                    . ' · staff tickets ' . (int) $kpi['staff_tickets'],
            ],
            [
                'clause' => 'Reliability',
                'how' => 'Ticket states through Confirming; the requester marks Solved or Not Solved Yet; classification retries after a quota miss.',
                'evidence' => (int) $kpi['open'] . ' open / ' . (int) $kpi['resolved'] . ' resolved',
            ],
            [
                'clause' => 'Security',
                'how' => 'Role pages, hashed passwords, CSRF, HTTPS, AES-256-GCM ticket text at rest, admin audit log.',
                'evidence' => 'See Privacy note · audit panel on Utilities',
            ],
            [
                'clause' => 'Maintainability',
                'how' => 'Utilities for accounts, hiring, and audit; category and urgency stored on the ticket.',
                'evidence' => (int) $kpi['total'] . ' tickets on record',
            ],
            [
                'clause' => 'Flexibility',
                'how' => 'Same product on local XAMPP and Hostinger; light and dark theme.',
                'evidence' => 'Theme on Settings · local and hosted runtimes',
            ],
            [
                'clause' => 'Safety',
                'how' => 'IT-only intake; role pages only; the requester confirms close.',
                'evidence' => 'IT-only Other handling · Solved / Not Solved Yet by the requester',
            ],
        ];
    }

    function iso20000_clauses(mysqli $conn, array $kpi, $lastCapacity = null)
    {
        return iso25010_characteristics($conn, $kpi, $lastCapacity);
    }
}
