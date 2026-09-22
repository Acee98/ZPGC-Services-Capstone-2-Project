<?php
/**
 * Automatic technician assignment — least open tickets wins.
 */

if (!function_exists('assign_least_busy_technician')) {
    function assign_least_busy_technician(mysqli $conn)
    {
        $sql = "SELECT u.id,
                       SUM(CASE
                           WHEN t.id IS NOT NULL
                            AND t.status IN ('pending','ongoing','processing','awaiting_confirmation')
                           THEN 1 ELSE 0 END) AS open_count
                FROM users u
                LEFT JOIN tickets t ON t.assigned_to = u.id
                WHERE u.role = 'techn' AND u.status = 'active'
                GROUP BY u.id
                ORDER BY open_count ASC, u.id ASC
                LIMIT 1";

        $result = $conn->query($sql);
        if (!$result) {
            return null;
        }
        $row = $result->fetch_assoc();
        if (!$row) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * Assign ticket to least-busy tech and set status ongoing.
     * Returns technician id or null if none available.
     */
    function ticket_auto_assign(mysqli $conn, $ticket_id, $status = 'ongoing')
    {
        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0) {
            return null;
        }

        $tech_id = assign_least_busy_technician($conn);
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

        return $ok ? $tech_id : null;
    }
}
