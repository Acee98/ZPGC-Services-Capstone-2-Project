<?php

require_once __DIR__ . '/techn_apply.php';

if (!function_exists('techn_queue_specialty')) {
    function techn_queue_specialty(mysqli $conn, $userId)
    {
        $app = function_exists('techn_apply_get_for_user')
            ? techn_apply_get_for_user($conn, (int) $userId)
            : null;
        return techn_apply_normalize_specialty((string) ($app['specialty'] ?? ''));
    }

    function techn_queue_category_key($specialty)
    {
        $spec = techn_apply_normalize_specialty($specialty);
        return $spec === '' ? '' : strtolower($spec);
    }

    /**
     * Hardware technicians only see hardware tickets (assigned or still unassigned).
     */
    function techn_queue_can_access(mysqli $conn, $ticketId, $techId)
    {
        $ticketId = (int) $ticketId;
        $techId = (int) $techId;
        $cat = techn_queue_category_key(techn_queue_specialty($conn, $techId));
        if ($ticketId <= 0 || $techId <= 0 || $cat === '') {
            return false;
        }
        $stmt = $conn->prepare(
            'SELECT id, assigned_to, category FROM tickets WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        if (strtolower(trim((string) ($row['category'] ?? ''))) !== $cat) {
            return false;
        }
        $assigned = (int) ($row['assigned_to'] ?? 0);
        return $assigned === 0 || $assigned === $techId;
    }

    function techn_queue_claim_if_open(mysqli $conn, $ticketId, $techId)
    {
        $ticketId = (int) $ticketId;
        $techId = (int) $techId;
        $stmt = $conn->prepare(
            'UPDATE tickets SET assigned_to = ? WHERE id = ? AND assigned_to IS NULL'
        );
        $stmt->bind_param('ii', $techId, $ticketId);
        $stmt->execute();
        $stmt->close();
    }
}
