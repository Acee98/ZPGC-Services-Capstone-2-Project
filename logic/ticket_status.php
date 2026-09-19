<?php
/**
 * Shared ticket status helpers (Stage 6 / v1.4).
 */

if (!function_exists('ticket_status_label')) {

    function ticket_status_label($status)
    {
        $labels = [
            'pending' => 'Pending',
            'ongoing' => 'Ongoing',
            'processing' => 'Processing',
            'awaiting_confirmation' => 'Confirming',
            'resolved' => 'Resolved',
        ];
        $key = (string) $status;
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    function ticket_status_class($status)
    {
        return preg_replace('/[^a-z]/', '', strtolower((string) $status));
    }

    function ticket_awaiting_confirmation($status)
    {
        return $status === 'awaiting_confirmation';
    }

    /** Statuses a technician may set (Stage 6: cannot close as resolved). */
    function ticket_techn_allowed_statuses()
    {
        return ['pending', 'ongoing', 'processing', 'awaiting_confirmation'];
    }

    /** Admin may set any workflow status including override to resolved. */
    function ticket_admin_allowed_statuses()
    {
        return ['pending', 'ongoing', 'processing', 'awaiting_confirmation', 'resolved'];
    }
}
