<?php
/**
 * Classification metrics matching ZPGC Capstone Paper (REVISED) Table 3 / §4.4.4.
 *
 * Formulas (multi-class one-vs-rest, same as Scikit-learn):
 *   For each class c:
 *     TP = conf[c][c]
 *     FN = sum_j conf[c][j] - TP
 *     FP = sum_i conf[i][c] - TP
 *     TN = N - TP - FN - FP
 *     Precision_c = TP / (TP + FP)   (0 if denom 0)
 *     Recall_c    = TP / (TP + FN)
 *     F1_c        = 2 * P * R / (P + R)
 *   Accuracy (CA) = sum_c TP_c / N   (= diagonal / N)
 *   Macro average = mean over classes (unweighted)
 *   Weighted average = support-weighted mean (handles class imbalance; paper F1 note)
 *
 * Primary Table 3 scalars use weighted averages (imbalanced ticket categories).
 */
declare(strict_types=1);

if (!function_exists('zpgc_metrics_from_confusion')) {
    /**
     * @param array<string,array<string,int>> $conf gold => pred => count
     * @param list<string> $labels
     * @return array<string,mixed>
     */
    function zpgc_metrics_from_confusion(array $conf, array $labels): array
    {
        $n = 0;
        $diag = 0;
        $per = [];
        foreach ($labels as $c) {
            $tp = (int) ($conf[$c][$c] ?? 0);
            $rowSum = 0;
            $colSum = 0;
            foreach ($labels as $j) {
                $rowSum += (int) ($conf[$c][$j] ?? 0);
                $colSum += (int) ($conf[$j][$c] ?? 0);
            }
            $fn = $rowSum - $tp;
            $fp = $colSum - $tp;
            $support = $rowSum;
            $n += $support;
            $diag += $tp;
            $tn = 0; // filled after N known
            $p = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
            $r = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
            $f1 = ($p + $r) > 0 ? (2.0 * $p * $r) / ($p + $r) : 0.0;
            $per[$c] = [
                'tp' => $tp,
                'fp' => $fp,
                'fn' => $fn,
                'tn' => null,
                'support' => $support,
                'precision' => round($p, 4),
                'recall' => round($r, 4),
                'f1' => round($f1, 4),
            ];
        }
        $n = max(1, $n);
        foreach ($labels as $c) {
            $tp = $per[$c]['tp'];
            $fp = $per[$c]['fp'];
            $fn = $per[$c]['fn'];
            $per[$c]['tn'] = $n - $tp - $fp - $fn;
        }

        $macroP = 0.0;
        $macroR = 0.0;
        $macroF = 0.0;
        $wP = 0.0;
        $wR = 0.0;
        $wF = 0.0;
        $k = count($labels);
        foreach ($labels as $c) {
            $macroP += $per[$c]['precision'];
            $macroR += $per[$c]['recall'];
            $macroF += $per[$c]['f1'];
            $s = $per[$c]['support'];
            $wP += $per[$c]['precision'] * $s;
            $wR += $per[$c]['recall'] * $s;
            $wF += $per[$c]['f1'] * $s;
        }
        $accuracy = $diag / $n;

        return [
            'n' => $n,
            'formulas' => [
                'accuracy' => 'CA = (sum TP_c) / N = correctly_classified / total',
                'precision_c' => 'PR_c = TP_c / (TP_c + FP_c)',
                'recall_c' => 'RE_c = TP_c / (TP_c + FN_c)',
                'f1_c' => 'F1_c = 2 * PR_c * RE_c / (PR_c + RE_c)',
                'table3_scalars' => 'Weighted averages (imbalanced categories); also report macro',
            ],
            'accuracy' => round($accuracy, 4),
            'accuracy_pct' => round($accuracy * 100, 2),
            'macro' => [
                'precision' => round($macroP / max(1, $k), 4),
                'recall' => round($macroR / max(1, $k), 4),
                'f1' => round($macroF / max(1, $k), 4),
            ],
            'weighted' => [
                'precision' => round($wP / $n, 4),
                'recall' => round($wR / $n, 4),
                'f1' => round($wF / $n, 4),
            ],
            // Table 3 primary columns (paper §Accuracy / Precision / Recall / F1)
            'table3' => [
                'accuracy' => round($accuracy, 4),
                'precision' => round($wP / $n, 4),
                'recall' => round($wR / $n, 4),
                'f1' => round($wF / $n, 4),
                'accuracy_pct' => round($accuracy * 100, 1) . '%',
                'precision_pct' => round(($wP / $n) * 100, 1) . '%',
                'recall_pct' => round(($wR / $n) * 100, 1) . '%',
                'f1_pct' => round(($wF / $n) * 100, 1) . '%',
            ],
            'per_class' => $per,
            'confusion' => $conf,
        ];
    }

    /**
     * Attach paper metrics to a benchmark report array (mutates and returns).
     */
    function zpgc_attach_paper_metrics(array $report): array
    {
        $cats = ['hardware', 'software', 'network', 'account', 'other'];
        $pris = ['critical', 'moderate', 'low'];
        if (!empty($report['category_confusion']) && is_array($report['category_confusion'])) {
            $report['paper_metrics_category'] = zpgc_metrics_from_confusion(
                $report['category_confusion'],
                $cats
            );
        }
        if (!empty($report['priority_confusion']) && is_array($report['priority_confusion'])) {
            $report['paper_metrics_priority'] = zpgc_metrics_from_confusion(
                $report['priority_confusion'],
                $pris
            );
        }
        return $report;
    }
}
