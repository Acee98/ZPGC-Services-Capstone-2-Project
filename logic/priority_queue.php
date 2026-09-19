<?php
/**
 * 9-slot priority queue (Stage 5): 3 Critical + 3 Moderate + 3 Low.
 * Board seats = tickets that have a priority set and are not resolved yet.
 * (Stage 5 admin flow: set Priority + Save puts the ticket on the board.)
 */

if (!function_exists('priority_queue_base_slots')) {

    function priority_queue_base_slots()
    {
        return [
            'critical' => 3,
            'moderate' => 3,
            'low' => 3,
        ];
    }

    function priority_queue_tier_keys()
    {
        return ['critical', 'moderate', 'low'];
    }

    function priority_queue_batch_tiers()
    {
        return [
            'critical' => [
                'tier_key' => 'critical',
                'batch_name' => 'Batch 1',
                'priority_label' => 'Critical',
            ],
            'moderate' => [
                'tier_key' => 'moderate',
                'batch_name' => 'Batch 2',
                'priority_label' => 'Moderate',
            ],
            'low' => [
                'tier_key' => 'low',
                'batch_name' => 'Batch 3',
                'priority_label' => 'Low',
            ],
        ];
    }

    function priority_queue_tier_meta($tierKey)
    {
        $tiers = priority_queue_batch_tiers();
        return $tiers[$tierKey] ?? [
            'tier_key' => $tierKey,
            'batch_name' => 'Batch',
            'priority_label' => ucfirst($tierKey),
        ];
    }

    function priority_queue_build_bands($active, $base)
    {
        $bands = [];
        foreach (priority_queue_tier_keys() as $tierKey) {
            $meta = priority_queue_tier_meta($tierKey);
            $used = (int) ($active[$tierKey] ?? 0);
            $baseLimit = (int) ($base[$tierKey] ?? 0);
            $bands[$tierKey] = [
                'used' => $used,
                'base' => $baseLimit,
                'limit' => $baseLimit,
                'borrowed' => max(0, $used - $baseLimit),
                'batch_name' => $meta['batch_name'],
                'priority_label' => $meta['priority_label'],
                'tier_key' => $tierKey,
            ];
        }
        return $bands;
    }

    function priority_queue_normalize_priority($priority)
    {
        $value = strtolower(trim((string) $priority));
        if (!in_array($value, ['critical', 'moderate', 'low'], true)) {
            return 'low';
        }
        return $value;
    }

    function priority_queue_active_counts($conn)
    {
        $counts = ['critical' => 0, 'moderate' => 0, 'low' => 0];
        // Count open tickets that already have a queue colour (A-039 / A-041).
        $sql = "SELECT LOWER(TRIM(priority)) AS sev
                FROM tickets
                WHERE priority IS NOT NULL
                  AND status <> 'resolved'";
        $result = $conn->query($sql);
        if (!$result) {
            return $counts;
        }
        while ($row = $result->fetch_assoc()) {
            $sev = priority_queue_normalize_priority($row['sev']);
            $counts[$sev]++;
        }
        return $counts;
    }

    function priority_queue_snapshot($conn)
    {
        $base = priority_queue_base_slots();
        $active = priority_queue_active_counts($conn);
        $bands = priority_queue_build_bands($active, $base);
        $activeTotal = $active['critical'] + $active['moderate'] + $active['low'];
        $maxTotal = $base['critical'] + $base['moderate'] + $base['low'];
        return [
            'bands' => $bands,
            'active_total' => $activeTotal,
            'max_total' => $maxTotal,
            'borrow_m' => (int) ($bands['moderate']['borrowed'] ?? 0),
            'borrow_l' => (int) ($bands['low']['borrowed'] ?? 0),
        ];
    }
}
