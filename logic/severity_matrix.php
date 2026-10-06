<?php

if (!function_exists('severity_estimate_axes')) {
    function severity_estimate_axes($subject, $description)
    {
        $blob = strtolower($subject . ' ' . $description);

        // Urgency = how quickly the issue must be fixed (paper Severity Matrix).
        $urgency = 1;
        if (preg_match('/\b(outage|down|stopped|stoppage|cannot work|can\'t work|cannot log|can\'t log|blocker|emergency|total|no internet|won\'t turn on|will not turn on)\b/', $blob)) {
            $urgency = 3;
        } elseif (preg_match('/\b(slow|crash|error|degraded|not working|freeze|frozen|intermittent|keeps)\b/', $blob)) {
            $urgency = 2;
        }

        // Impact = scope: one person → group/class/lab → department/campus.
        $impact = 1;
        if (preg_match('/\b(server|department|everyone|all users|whole|campus|organization|organisational|entire|building)\b/', $blob)) {
            $impact = 3;
        } elseif (preg_match('/\b(group|several|multiple|class|faculty|laboratory|lab |we |our |room )\b/', $blob)) {
            $impact = 2;
        }

        return ['urgency' => $urgency, 'impact' => $impact];
    }

    

    function severity_from_score($score)
    {
        $score = (int) $score;
        // Capstone Table 4: 1–2 Low, 3–6 Moderate, 9 Critical (U×I). +40 escalation is also Critical.
        if ($score >= 9) {
            return 'critical';
        }
        if ($score >= 3) {
            return 'moderate';
        }
        return 'low';
    }

    
    function severity_escalation_points()
    {
        return 40;
    }

    function severity_identical_key($subject)
    {
        $key = strtolower(trim((string) $subject));
        $key = preg_replace('/\s+/', ' ', $key);
        return $key;
    }

    function severity_identical_open_count(mysqli $conn, $subject)
    {
        $key = severity_identical_key($subject);
        if ($key === '') {
            return 0;
        }
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM tickets
             WHERE LOWER(TRIM(subject)) = ? AND status <> 'resolved'"
        );
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['cnt'] ?? 0);
    }

    function severity_apply_matrix($urgency, $impact, $identicalReports)
    {
        $urgency = max(1, min(3, (int) $urgency));
        $impact = max(1, min(3, (int) $impact));
        $base = $urgency * $impact;
        $reports = (int) $identicalReports;
        $escalated = $reports >= 30;
        $final = $base + ($escalated ? severity_escalation_points() : 0);
        return [
            'urgency' => $urgency,
            'impact' => $impact,
            'base_score' => $base,
            'final_score' => $final,
            'escalated' => $escalated,
            'priority' => severity_from_score($final),
        ];
    }
}
