<?php

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

    function priority_queue_allocate($counts)
    {
        $keys = priority_queue_tier_keys();
        $take = [];
        foreach ($keys as $key) {
            $take[$key] = min((int) ($counts[$key] ?? 0), 3);
        }
        $free = 9 - array_sum($take);
        $borrowed = ['critical' => 0, 'moderate' => 0, 'low' => 0];
        foreach ($keys as $key) {
            $waiting = max(0, (int) ($counts[$key] ?? 0) - $take[$key]);
            $extra = min($waiting, $free);
            $take[$key] += $extra;
            $borrowed[$key] = $extra;
            $free -= $extra;
        }
        return ['take' => $take, 'borrowed' => $borrowed];
    }

    function priority_queue_build_bands($active, $base)
    {
        $alloc = priority_queue_allocate($active);
        $bands = [];
        foreach (priority_queue_tier_keys() as $tierKey) {
            $meta = priority_queue_tier_meta($tierKey);
            $used = (int) $alloc['take'][$tierKey];
            $borrowed = (int) $alloc['borrowed'][$tierKey];
            $baseLimit = (int) ($base[$tierKey] ?? 3);
            $bands[$tierKey] = [
                'used' => $used,
                'base' => $baseLimit,
                'limit' => $baseLimit + $borrowed,
                'borrowed' => $borrowed,
                'waiting' => (int) ($active[$tierKey] ?? 0),
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

        $sql = "SELECT LOWER(TRIM(IFNULL(priority, ''))) AS sev, COUNT(*) AS cnt
                FROM tickets
                WHERE status <> 'resolved'
                GROUP BY sev";
        $result = $conn->query($sql);
        if (!$result) {
            return $counts;
        }
        while ($row = $result->fetch_assoc()) {
            $sev = priority_queue_normalize_priority($row['sev']);
            $counts[$sev] += (int) $row['cnt'];
        }
        return $counts;
    }

    function priority_queue_snapshot($conn)
    {
        $base = priority_queue_base_slots();
        $active = priority_queue_active_counts($conn);
        $bands = priority_queue_build_bands($active, $base);
        $activeTotal = $active['critical'] + $active['moderate'] + $active['low'];
        $batchTotal = 0;
        foreach (priority_queue_tier_keys() as $tierKey) {
            $batchTotal += (int) ($bands[$tierKey]['used'] ?? 0);
        }
        return [
            'bands' => $bands,
            'active_total' => $batchTotal,
            'waiting_total' => $activeTotal,
            'max_total' => 9,
            'borrow_m' => (int) ($bands['moderate']['borrowed'] ?? 0),
            'borrow_l' => (int) ($bands['low']['borrowed'] ?? 0),
        ];
    }
}
