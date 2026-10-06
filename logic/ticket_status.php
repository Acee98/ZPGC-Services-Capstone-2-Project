<?php

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

    function ticket_category_label($category)
    {
        $cat = strtolower(trim((string) $category));
        if ($cat === '') {
            return '—';
        }
        return ucfirst($cat);
    }

    function ticket_awaiting_confirmation($status)
    {
        return $status === 'awaiting_confirmation';
    }

    
    function ticket_techn_allowed_statuses()
    {
        return ['pending', 'ongoing', 'processing', 'awaiting_confirmation'];
    }

    
    function ticket_admin_allowed_statuses()
    {
        return ['pending', 'ongoing', 'processing', 'awaiting_confirmation', 'resolved'];
    }

    function ticket_sort_for_attention(array $tickets, array $replacementIds)
    {
        $lookup = [];
        foreach ($replacementIds as $id) {
            $lookup[(int) $id] = true;
        }
        usort($tickets, function ($a, $b) use ($lookup) {
            $rank = function ($row) use ($lookup) {
                $status = (string) ($row['status'] ?? '');
                if ($status === 'awaiting_confirmation' || isset($lookup[(int) ($row['id'] ?? 0)])) {
                    return 0;
                }
                if ($status === 'resolved') {
                    return 2;
                }
                return 1;
            };
            $left = $rank($a);
            $right = $rank($b);
            if ($left !== $right) {
                return $left <=> $right;
            }
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });
        return $tickets;
    }
}
