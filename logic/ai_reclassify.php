<?php

require_once __DIR__ . '/ai_classify.php';
require_once __DIR__ . '/severity_matrix.php';
if (is_file(__DIR__ . '/ticket_crypto.php')) {
    require_once __DIR__ . '/ticket_crypto.php';
}

if (!function_exists('ai_reclassify_pending')) {
    /**
     * When submit used keyword/quota fallback, retry OpenAI and store the model result.
     * Pending unassigned tickets may also update category and priority.
     */
    function ai_reclassify_pending(mysqli $conn, $limit = 4)
    {
        if (ai_openai_key() === '') {
            return 0;
        }
        $last = (int) ($_SESSION['_ai_retry_at'] ?? 0);
        if ($last > 0 && (time() - $last) < 45) {
            return 0;
        }
        $_SESSION['_ai_retry_at'] = time();
        $limit = max(1, min(8, (int) $limit));
        $hasMethod = false;
        if (function_exists('ticket_has_column')) {
            $hasMethod = ticket_has_column($conn, 'ai_method');
        } else {
            $chk = $conn->query("SHOW COLUMNS FROM tickets LIKE 'ai_method'");
            $hasMethod = ($chk && $chk->num_rows > 0);
        }
        if (!$hasMethod) {
            return 0;
        }
        $sql = "SELECT id, subject, description, category, status, assigned_to, priority
                FROM tickets
                WHERE LOWER(IFNULL(ai_method,'')) IN ('kw-quota','keyword','fallback','')
                ORDER BY id DESC
                LIMIT {$limit}";
        $res = $conn->query($sql);
        if (!$res) {
            return 0;
        }
        $n = 0;
        while ($row = $res->fetch_assoc()) {
            if (function_exists('ticket_reveal_row')) {
                ticket_reveal_row($row);
            }
            $id = (int) $row['id'];
            $subject = (string) ($row['subject'] ?? '');
            $description = (string) ($row['description'] ?? '');
            if ($subject === '') {
                continue;
            }
            $ai = ai_classify_ticket($subject, $description);
            if (empty($ai['ok']) || (($ai['method'] ?? '') !== 'openai' && ($ai['method'] ?? '') !== 'flask')) {
                continue;
            }
            $method = (string) ($ai['method'] ?? 'openai');
            $upd = $conn->prepare('UPDATE tickets SET ai_method = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('si', $method, $id);
                $upd->execute();
                $upd->close();
            }
            $status = strtolower((string) ($row['status'] ?? ''));
            $assigned = (int) ($row['assigned_to'] ?? 0);
            if ($status === 'pending' && $assigned === 0 && !empty($ai['in_scope'])) {
                $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
                $category = strtolower(trim((string) ($ai['category'] ?? '')));
                if (!in_array($category, $allowedCat, true)) {
                    $category = (string) ($row['category'] ?? 'other');
                }
                $axes = severity_estimate_axes($subject, $description);
                $urgency = (int) ($ai['urgency'] ?? $axes['urgency']);
                $impact = (int) ($ai['impact'] ?? $axes['impact']);
                $openSame = severity_identical_open_count($conn, $subject);
                $matrix = severity_apply_matrix($urgency, $impact, $openSame);
                $priority = $matrix['priority'];
                $hasScore = function_exists('ticket_has_column')
                    && ticket_has_column($conn, 'severity_score');
                if ($hasScore) {
                    $st = $conn->prepare(
                        'UPDATE tickets
                         SET category = ?, priority = ?, urgency = ?, impact_level = ?, severity_score = ?
                         WHERE id = ? AND status = \'pending\' AND assigned_to IS NULL'
                    );
                    if ($st) {
                        $urg = (int) $matrix['urgency'];
                        $imp = (int) $matrix['impact'];
                        $score = (int) $matrix['final_score'];
                        $st->bind_param('ssiiii', $category, $priority, $urg, $imp, $score, $id);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    $st = $conn->prepare(
                        'UPDATE tickets SET category = ?, priority = ?
                         WHERE id = ? AND status = \'pending\' AND assigned_to IS NULL'
                    );
                    if ($st) {
                        $st->bind_param('ssi', $category, $priority, $id);
                        $st->execute();
                        $st->close();
                    }
                }
            }
            $n++;
        }
        return $n;
    }
}
