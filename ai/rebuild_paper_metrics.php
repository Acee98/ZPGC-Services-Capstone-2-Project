<?php
/**
 * Rebuild paper Table 3 metrics from existing benchmark JSON reports
 * (no OpenAI calls — uses confusion matrices already saved).
 *
 * Usage: php ai/rebuild_paper_metrics.php
 */
declare(strict_types=1);

require_once __DIR__ . '/metrics_paper.php';

$files = [
    'keyword_n200' => __DIR__ . '/data/benchmark_keyword_n200_seed42.json',
    'keyword_n3000' => __DIR__ . '/data/benchmark_keyword_report.json',
    'gpt-5.6-luna' => __DIR__ . '/data/benchmark_luna_report.json',
    'gpt-4o-mini' => __DIR__ . '/data/benchmark_gpt4o_mini_report.json',
    'gpt-4.1-nano' => __DIR__ . '/data/benchmark_gpt41_nano_report.json',
    'gpt-4.1-mini' => __DIR__ . '/data/benchmark_gpt41_mini_report.json',
    'gpt-5-mini' => __DIR__ . '/data/benchmark_gpt5_mini_report.json',
];

// Literature baseline rows from Capstone Paper Table 3 (not measured on ZPGC Kaggle remap).
$literatureTable3 = [
    [
        'model' => 'OpenAI (literature)',
        'accuracy' => '92.0%',
        'precision' => '90.0%',
        'recall' => '90.5%',
        'f1' => '91.0%',
        'processing_speed_tokens_per_sec' => '80-150',
        'model_training' => 'Low',
        'source' => 'Paper Table 3 (literature comparison)',
    ],
    [
        'model' => 'BERT (literature)',
        'accuracy' => '91.0%',
        'precision' => '90.8%',
        'recall' => '89.5%',
        'f1' => '90.1%',
        'processing_speed_tokens_per_sec' => '1,000-1,500',
        'model_training' => 'High',
        'source' => 'Paper Table 3 (literature comparison)',
    ],
    [
        'model' => 'RoBERTa (literature)',
        'accuracy' => '93.5%',
        'precision' => '91.5%',
        'recall' => '92.2%',
        'f1' => '92.7%',
        'processing_speed_tokens_per_sec' => '1,200',
        'model_training' => 'High',
        'source' => 'Paper Table 3 (literature comparison)',
    ],
    [
        'model' => 'DistilRoBERTa (literature)',
        'accuracy' => '88.0%',
        'precision' => '88.0%',
        'recall' => '88.0%',
        'f1' => '87.9%',
        'processing_speed_tokens_per_sec' => '2,000-4,000',
        'model_training' => 'Medium',
        'source' => 'Paper Table 3 (literature comparison)',
    ],
];

$systemRows = [];
$detailed = [];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "skip missing {$path}\n");
        continue;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || empty($raw['category_confusion'])) {
        fwrite(STDERR, "skip invalid {$path}\n");
        continue;
    }
    $enriched = zpgc_attach_paper_metrics($raw);
    // Persist enriched metrics back into the report file.
    file_put_contents(
        $path,
        json_encode($enriched, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $cat = $enriched['paper_metrics_category']['table3'] ?? null;
    $pri = $enriched['paper_metrics_priority']['table3'] ?? null;
    $modelName = (string) ($enriched['model'] ?? $label);
    if ($label === 'keyword_n200' || $label === 'keyword_n3000') {
        $modelName = $label;
    }

    $row = [
        'model' => $modelName,
        'label' => $label,
        'n' => (int) ($enriched['n'] ?? 0),
        'seed' => $enriched['seed'] ?? null,
        'task' => 'category',
        'accuracy' => $cat['accuracy_pct'] ?? null,
        'precision' => $cat['precision_pct'] ?? null,
        'recall' => $cat['recall_pct'] ?? null,
        'f1' => $cat['f1_pct'] ?? null,
        'processing_speed_tokens_per_sec' => $modelName === 'keyword_n200' || str_starts_with($label, 'keyword')
            ? 'N/A (offline rules)'
            : 'see OpenAI API latency',
        'model_training' => str_starts_with($label, 'keyword') ? 'None' : 'Low (prompt / zero-shot)',
        'openai_errors_or_fallbacks' => (int) ($enriched['openai_errors_or_fallbacks'] ?? 0),
        'source' => str_replace('\\', '/', $path),
        'priority_table3' => $pri,
        'category_macro' => $enriched['paper_metrics_category']['macro'] ?? null,
        'category_weighted' => $enriched['paper_metrics_category']['weighted'] ?? null,
        'per_class_category' => $enriched['paper_metrics_category']['per_class'] ?? null,
    ];
    $systemRows[] = $row;
    $detailed[$label] = [
        'category' => $enriched['paper_metrics_category'] ?? null,
        'priority' => $enriched['paper_metrics_priority'] ?? null,
    ];
    fwrite(STDERR, sprintf(
        "%s cat Acc=%s P=%s R=%s F1=%s\n",
        $label,
        $cat['accuracy_pct'] ?? '?',
        $cat['precision_pct'] ?? '?',
        $cat['recall_pct'] ?? '?',
        $cat['f1_pct'] ?? '?'
    ));
}

$out = [
    'title' => 'ZPGC system accuracy (paper Table 3 columns)',
    'paper_reference' => 'ZPGC-Capstone-Paper-REVISED-1.pdf — Table 3 Accuracy Table; §4.4.4 Accuracy Evaluation',
    'formulas' => [
        'accuracy' => 'CA = correctly_classified / N  (= sum TP_c / N)',
        'precision_c' => 'PR_c = TP_c / (TP_c + FP_c)',
        'recall_c' => 'RE_c = TP_c / (TP_c + FN_c)',
        'f1_c' => 'F1_c = 2*PR_c*RE_c / (PR_c+RE_c)',
        'table3_precision_recall_f1' => 'Weighted average over classes (handles imbalance; paper F1 note)',
        'automation' => 'Same metrics as Scikit-learn accuracy_score / precision_recall_fscore_support(average=weighted)',
    ],
    'classes_category' => ['hardware', 'software', 'network', 'account', 'other'],
    'classes_priority' => ['critical', 'moderate', 'low'],
    'note' => 'Literature Table 3 rows are published baselines from the paper. System rows are measured on the ZPGC remapped Kaggle protocol (heuristic gold labels) — absolute % will be lower than literature numbers on native label sets.',
    'literature_table3' => $literatureTable3,
    'system_table3_category' => $systemRows,
    'detailed' => $detailed,
];

$outPath = __DIR__ . '/data/benchmark_table3_system_accuracy.json';
file_put_contents($outPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, "Wrote {$outPath}\n");

// Human-readable markdown table
$md = "# System Accuracy Table (Paper Table 3 columns)\n\n";
$md .= "Measured on ZPGC Kaggle remap. Formulas: Accuracy, Precision, Recall, F1 (weighted).\n\n";
$md .= "## Literature baselines (from paper Table 3)\n\n";
$md .= "| Model | Accuracy | Precision | Recall | F1-Score | Speed | Training |\n";
$md .= "|-------|----------|-----------|--------|----------|-------|----------|\n";
foreach ($literatureTable3 as $r) {
    $md .= sprintf(
        "| %s | %s | %s | %s | %s | %s | %s |\n",
        $r['model'],
        $r['accuracy'],
        $r['precision'],
        $r['recall'],
        $r['f1'],
        $r['processing_speed_tokens_per_sec'],
        $r['model_training']
    );
}
$md .= "\n## ZPGC measured system results (category)\n\n";
$md .= "| Model | N | Accuracy | Precision | Recall | F1-Score | Training |\n";
$md .= "|-------|---|----------|-----------|--------|----------|----------|\n";
foreach ($systemRows as $r) {
    $md .= sprintf(
        "| %s | %d | %s | %s | %s | %s | %s |\n",
        $r['model'],
        $r['n'],
        $r['accuracy'] ?? '—',
        $r['precision'] ?? '—',
        $r['recall'] ?? '—',
        $r['f1'] ?? '—',
        $r['model_training']
    );
}
$mdPath = __DIR__ . '/data/benchmark_table3_system_accuracy.md';
file_put_contents($mdPath, $md);
fwrite(STDERR, "Wrote {$mdPath}\n");
echo "OK\n";
