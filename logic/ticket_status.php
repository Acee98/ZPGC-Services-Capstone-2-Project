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
        $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        return function_exists('zpgc_t') ? zpgc_t($label) : $label;
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

    function ticket_priority_key($priority)
    {
        return preg_replace('/[^a-z]/', '', strtolower(trim((string) $priority)));
    }

    function ticket_satisfaction_labels()
    {
        return [
            5 => 'Very satisfied',
            4 => 'Satisfied',
            3 => 'Not sure',
            2 => 'Not satisfied',
            1 => 'Hate it',
        ];
    }

    function ticket_satisfaction_label($score)
    {
        $n = (int) $score;
        $labels = ticket_satisfaction_labels();
        return $labels[$n] ?? '';
    }

    function ticket_action_icon($name)
    {
        $icons = [
            'view' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9a3 3 0 1 0 0 6 3 3 0 1 0 0-6"></path><path d="M12 19c7.63 0 9.93-6.62 9.95-6.68.07-.21.07-.43 0-.63-.02-.07-2.32-6.68-9.95-6.68s-9.93 6.61-9.95 6.67c-.07.21-.07.43 0 .63.02.07 2.32 6.68 9.95 6.68Zm0-12c5.35 0 7.42 3.85 7.93 5-.5 1.16-2.58 5-7.93 5s-7.42-3.84-7.93-5c.5-1.16 2.58-5 7.93-5"></path></svg>',
            'edit' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21h14c1.1 0 2-.9 2-2v-7h-2v7H5V5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2"></path><path d="M7 13v3c0 .55.45 1 1 1h3c.27 0 .52-.11.71-.29l9-9a.996.996 0 0 0 0-1.41l-3-3a.996.996 0 0 0-1.41 0l-9.01 8.99A1 1 0 0 0 7 13m10-7.59L18.59 7 17.5 8.09 15.91 6.5zm-8 8 5.5-5.5 1.59 1.59-5.5 5.5H9z"></path></svg>',
            'review' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m19.94 7.68-.03-.09a.8.8 0 0 0-.2-.29l-5-5c-.09-.09-.19-.15-.29-.2l-.09-.03a.8.8 0 0 0-.26-.05c-.02 0-.04-.01-.06-.01H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-12s-.01-.04-.01-.06c0-.09-.02-.17-.05-.26ZM6 20V4h7v4c0 .55.45 1 1 1h4v11z"></path><path d="M8 11h8v2H8zm0 4h8v2H8zm0-8h3v2H8z"></path></svg>',
            'activate' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.51 18.87a1 1 0 0 0 1-.01l10-6c.3-.18.49-.51.49-.86s-.18-.68-.49-.86l-10-6a.99.99 0 0 0-1.01-.01c-.31.18-.51.51-.51.87v12c0 .36.19.69.51.87ZM8 7.77 15.06 12 8 16.23z"></path></svg>',
            'deactivate' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10 10-4.49 10-10S17.51 2 12 2M4 12c0-1.85.63-3.54 1.69-4.9L16.9 18.31A8 8 0 0 1 12 20c-4.41 0-8-3.59-8-8m14.31 4.9L7.1 5.69A8 8 0 0 1 12 4c4.41 0 8 3.59 8 8 0 1.85-.63 3.54-1.69 4.9"></path></svg>',
            'delete' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 6V4c0-1.1-.9-2-2-2H9c-1.1 0-2 .9-2 2v2H2v2h2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8h2V6zM9 4h6v2H9zM6 20V8h12v12z"></path><path d="M9 10h2v8H9zm4 0h2v8h-2z"></path></svg>',
        ];
        $key = strtolower(trim((string) $name));
        return $icons[$key] ?? '';
    }

    function ticket_quick_view_button(array $ticket, array $extra = [])
    {
        $id = (int) ($ticket['id'] ?? 0);
        $h = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $status = (string) ($ticket['status'] ?? '');
        $pri = trim((string) ($ticket['priority'] ?? ''));
        $attrs = [
            'type' => 'button',
            'class' => 'ticket-icon-btn',
            'data-ticket-quick-view' => '1',
            'data-icon' => 'view',
            'data-qv-id' => (string) $id,
            'data-qv-category' => ticket_category_label($ticket['category'] ?? ''),
            'data-qv-subject' => (string) ($ticket['subject'] ?? ''),
            'data-qv-description' => (string) ($ticket['description'] ?? ''),
            'data-qv-status' => ticket_status_label($status),
            'data-qv-status-class' => ticket_status_class($status),
            'data-qv-priority' => $pri !== '' ? ucfirst($pri) : 'None',
            'data-qv-priority-class' => $pri !== '' ? ticket_priority_key($pri) : 'undefined',
            'data-qv-satisfaction' => ticket_satisfaction_label($ticket['satisfaction'] ?? 0),
            'aria-label' => 'View ticket #' . $id,
            'title' => 'View ticket',
        ];
        foreach ($extra as $k => $v) {
            if ($v !== '' && $v !== null) {
                $attrs[$k] = (string) $v;
            }
        }
        $html = '<button';
        foreach ($attrs as $k => $v) {
            $html .= ' ' . $k . '="' . $h($v) . '"';
        }
        return $html . '>' . ticket_action_icon('view') . '<span class="ticket-icon-fallback">View</span></button>';
    }

    function ticket_priority_filter_markup($selectId, $targetId = '')
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $selectId);
        if ($id === '') {
            $id = 'priority-filter';
        }
        $target = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $targetId);
        $targetAttr = $target !== '' ? ' data-filter-target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<label class="tickets-priority-filter" for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
            . '<span>Priority</span>'
            . '<select id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="tickets-priority-select" data-priority-filter' . $targetAttr . '>'
            . '<option value="all">All</option>'
            . '<option value="critical">Critical</option>'
            . '<option value="moderate">Moderate</option>'
            . '<option value="low">Low</option>'
            . '</select></label>';
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
