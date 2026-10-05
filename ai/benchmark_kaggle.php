<?php
/**
 * Kaggle multi-lang ticket benchmark for ZPGC category/priority.
 *
 * Usage (from project root or ai/):
 *   php ai/benchmark_kaggle.php --mode=keyword --n=3000
 *   php ai/benchmark_kaggle.php --mode=luna --n=200
 *   php ai/benchmark_kaggle.php --mode=luna --n=200 --model=gpt-4o-mini --out=ai/data/benchmark_gpt4o_mini_report.json
 *
 * --model overrides OPENAI_MODEL for this process only (does not edit ai/.env).
 * Gold labels are heuristically mapped from queue/tags/text into ZPGC labels
 * (same approach as the first keyword-only report).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

require_once dirname(__DIR__) . '/logic/ai_classify.php';
require_once __DIR__ . '/metrics_paper.php';

$opts = getopt('', ['mode::', 'n::', 'seed::', 'out::', 'model::']);
$mode = strtolower((string) ($opts['mode'] ?? 'luna'));
$n = max(1, (int) ($opts['n'] ?? ($mode === 'keyword' ? 3000 : 200)));
$seed = (int) ($opts['seed'] ?? 42);
$out = (string) ($opts['out'] ?? '');
$modelOverride = trim((string) ($opts['model'] ?? ''));
if ($modelOverride !== '') {
    // Process-only override so campus live .env / App Settings stay untouched.
    putenv('OPENAI_MODEL=' . $modelOverride);
    $_ENV['OPENAI_MODEL'] = $modelOverride;
    $_SERVER['OPENAI_MODEL'] = $modelOverride;
}

$csvPath = __DIR__ . '/data/kaggle_tickets_multi_lang.csv';
if (!is_file($csvPath)) {
    fwrite(STDERR, "Missing CSV: {$csvPath}\n");
    exit(1);
}

$queues = [
    'it support',
    'product support',
    'service outages and maintenance',
    'technical support',
];

function zpgc_map_priority(string $raw): string
{
    $p = strtolower(trim($raw));
    if (in_array($p, ['high', 'urgent', 'critical'], true)) {
        return 'critical';
    }
    if (in_array($p, ['low', 'minor'], true)) {
        return 'low';
    }
    return 'moderate';
}

function zpgc_map_category(string $subject, string $body, string $queue, array $tags): string
{
    $tagBlob = strtolower(implode(' ', $tags));
    $blob = strtolower(trim($subject . ' ' . $body . ' ' . $queue . ' ' . $tagBlob));

    if (preg_match('/\b(password|login|otp|2fa|username|lock(ed)?|account)\b/i', $blob)
        || preg_match('/\baccount\b/i', $tagBlob)
    ) {
        return 'account';
    }
    if (preg_match('/\b(wifi|wi-?fi|internet|network|lan|vpn|router|dns|offline|disconnect|connectivity|outage)\b/i', $blob)
        || preg_match('/\b(network|vpn|connectivity|outage)\b/i', $tagBlob)
    ) {
        return 'network';
    }
    if (preg_match('/\b(printer|monitor|keyboard|mouse|hardware|ram|ssd|hdd|battery|charger|headset|projector|laptop|switch|audio)\b/i', $blob)
        || preg_match('/\b(hardware|equipment|driver|audio|video)\b/i', $tagBlob)
    ) {
        return 'hardware';
    }
    if (preg_match('/\b(install|update|crash|freeze|slow|software|app|excel|word|chrome|outlook|license|saas|application|bug|feature)\b/i', $blob)
        || preg_match('/\b(software|bug|feature|product|compatibility)\b/i', $tagBlob)
    ) {
        return 'software';
    }
    return 'other';
}

function zpgc_read_filtered_rows(string $csvPath, array $queues): array
{
    $fh = fopen($csvPath, 'rb');
    if ($fh === false) {
        return [];
    }
    $header = fgetcsv($fh);
    if (!is_array($header)) {
        fclose($fh);
        return [];
    }
    $header = array_map(static fn($h) => strtolower(trim((string) $h)), $header);
    $idx = array_flip($header);
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (!is_array($row) || count($row) < 7) {
            continue;
        }
        $lang = strtolower(trim((string) ($row[$idx['language'] ?? -1] ?? '')));
        if ($lang !== 'en') {
            continue;
        }
        $queue = strtolower(trim((string) ($row[$idx['queue'] ?? -1] ?? '')));
        if (!in_array($queue, $queues, true)) {
            continue;
        }
        $subject = (string) ($row[$idx['subject'] ?? -1] ?? '');
        $body = (string) ($row[$idx['body'] ?? -1] ?? '');
        $priority = (string) ($row[$idx['priority'] ?? -1] ?? '');
        $tags = [];
        for ($t = 1; $t <= 8; $t++) {
            $key = 'tag_' . $t;
            if (!isset($idx[$key])) {
                continue;
            }
            $val = trim((string) ($row[$idx[$key]] ?? ''));
            if ($val !== '') {
                $tags[] = $val;
            }
        }
        $rows[] = [
            'subject' => $subject,
            'body' => $body,
            'queue' => (string) ($row[$idx['queue'] ?? -1] ?? ''),
            'priority' => $priority,
            'tags' => $tags,
            'gold_category' => zpgc_map_category($subject, $body, $queue, $tags),
            'gold_priority' => zpgc_map_priority($priority),
        ];
    }
    fclose($fh);
    return $rows;
}

$pool = zpgc_read_filtered_rows($csvPath, $queues);
if ($pool === []) {
    fwrite(STDERR, "No filtered rows found.\n");
    exit(1);
}

mt_srand($seed);
$order = range(0, count($pool) - 1);
shuffle($order);
$sample = [];
foreach ($order as $i) {
    $sample[] = $pool[$i];
    if (count($sample) >= $n) {
        break;
    }
}
$n = count($sample);

$keyPresent = ai_openai_key() !== '';
$model = ai_openai_model();
if ($mode === 'luna' && !$keyPresent) {
    fwrite(STDERR, "OPENAI_API_KEY not found. Put it in ai/.env (OPENAI_API_KEY=...) then re-run.\n");
    exit(2);
}

$cats = ['hardware', 'software', 'network', 'account', 'other'];
$pris = ['critical', 'moderate', 'low'];
$goldCat = array_fill_keys($cats, 0);
$goldPri = array_fill_keys($pris, 0);
$predCat = array_fill_keys($cats, 0);
$predPri = array_fill_keys($pris, 0);
$catConf = [];
$priConf = [];
foreach ($cats as $g) {
    $catConf[$g] = array_fill_keys($cats, 0);
}
foreach ($pris as $g) {
    $priConf[$g] = array_fill_keys($pris, 0);
}

$hitCat = 0;
$hitPri = 0;
$hitBoth = 0;
$methods = [];
$misses = [];
$errors = 0;

fwrite(STDERR, "Benchmark mode={$mode} n={$n} model={$model} key=" . ($keyPresent ? 'yes' : 'no') . "\n");

foreach ($sample as $i => $row) {
    if ($mode === 'keyword') {
        $pred = ai_keyword_classify($row['subject'], $row['body']);
    } else {
        // Force OpenAI/Luna path used by the website (skip local Flask to avoid keyword shadowing).
        $pred = ai_openai_classify($row['subject'], $row['body']);
        if (empty($pred['ok'])) {
            $errors++;
            $pred = ai_keyword_classify($row['subject'], $row['body']);
            $pred['fallback_reason'] = (string) ($pred['fallback_reason'] ?? 'openai_error');
            $pred['error'] = (string) ($pred['error'] ?? 'openai failed');
            $pred['method'] = 'kw-fallback';
        }
    }

    $gc = $row['gold_category'];
    $gp = $row['gold_priority'];
    $pc = (string) ($pred['category'] ?? 'other');
    $pp = (string) ($pred['priority'] ?? 'moderate');
    if (!isset($predCat[$pc])) {
        $pc = 'other';
    }
    if (!isset($predPri[$pp])) {
        $pp = 'moderate';
    }

    $goldCat[$gc]++;
    $goldPri[$gp]++;
    $predCat[$pc]++;
    $predPri[$pp]++;
    $catConf[$gc][$pc]++;
    $priConf[$gp][$pp]++;

    $method = (string) ($pred['method'] ?? $mode);
    $methods[$method] = ($methods[$method] ?? 0) + 1;

    if ($gc === $pc) {
        $hitCat++;
    }
    if ($gp === $pp) {
        $hitPri++;
    }
    if ($gc === $pc && $gp === $pp) {
        $hitBoth++;
    } elseif (count($misses) < 12) {
        $misses[] = [
            'subject' => mb_substr($row['subject'], 0, 120),
            'gold_category' => $gc,
            'pred_category' => $pc,
            'gold_priority' => $gp,
            'pred_priority' => $pp,
            'queue' => $row['queue'],
            'method' => $method,
        ];
    }

    if (($i + 1) % 25 === 0 || $i + 1 === $n) {
        fwrite(STDERR, '  processed ' . ($i + 1) . "/{$n}\n");
    }
}

$report = [
    'mode' => $mode === 'keyword' ? 'keyword_only' : 'openai_benchmark',
    'openai' => $mode !== 'keyword',
    'model' => $mode === 'keyword' ? null : $model,
    'model_override' => $modelOverride !== '' ? $modelOverride : null,
    'n' => $n,
    'seed' => $seed,
    'openai_errors_or_fallbacks' => $errors,
    'filters' => [
        'language' => 'en',
        'queues' => $queues,
        'note' => 'Gold categories are heuristically mapped from queue/tags/text into ZPGC labels.',
    ],
    'accuracy' => [
        'category' => round($hitCat / max(1, $n), 4),
        'priority' => round($hitPri / max(1, $n), 4),
        'category_and_priority' => round($hitBoth / max(1, $n), 4),
    ],
    'gold_category_distribution' => $goldCat,
    'gold_priority_distribution' => $goldPri,
    'pred_category_distribution' => $predCat,
    'pred_priority_distribution' => $predPri,
    'category_confusion' => $catConf,
    'priority_confusion' => $priConf,
    'sample_misses' => $misses,
    'methods' => $methods,
    'baseline_keyword_3000' => [
        'source' => 'ai/data/benchmark_keyword_report.json',
        'accuracy' => [
            'category' => 0.4647,
            'priority' => 0.4223,
            'category_and_priority' => 0.2047,
        ],
    ],
];

// Capstone paper §4.4.4 / Table 3: Accuracy, Precision, Recall, F1 from confusion matrix.
$report = zpgc_attach_paper_metrics($report);
if (!empty($report['paper_metrics_category']['table3'])) {
    $report['accuracy']['paper_table3_category'] = $report['paper_metrics_category']['table3'];
}
if (!empty($report['paper_metrics_priority']['table3'])) {
    $report['accuracy']['paper_table3_priority'] = $report['paper_metrics_priority']['table3'];
}

if ($out === '') {
    $out = __DIR__ . '/data/benchmark_' . ($mode === 'keyword' ? 'keyword' : 'luna') . '_report.json';
}
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, "Wrote {$out}\n");
echo json_encode([
    'simple_hit_rate' => $report['accuracy'],
    'paper_table3_category' => $report['paper_metrics_category']['table3'] ?? null,
    'paper_table3_priority' => $report['paper_metrics_priority']['table3'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;
