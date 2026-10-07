<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'csrf.php';
require_once 'dashboard_stats.php';
require_once 'zpgc_simple_pdf.php';
require_role('admin');
zpgc_csrf_require();

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
$range = dashboard_normalize_range($input['range'] ?? 'week');
$stats = dashboard_chart_data($conn, $range);
$rangeLabel = dashboard_range_label($range);

function zpgc_pdf_jpeg_from_b64($b64)
{
    $b64 = (string) $b64;
    if ($b64 === '' || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $bin = base64_decode($b64, true);
    if ($bin === false || $bin === '') {
        return null;
    }
    $im = @imagecreatefromstring($bin);
    if (!$im) {
        return null;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $bg = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefilledrectangle($bg, 0, 0, $w, $h, $white);
    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
    imagedestroy($im);
    ob_start();
    imagejpeg($bg, null, 82);
    $jpeg = ob_get_clean();
    imagedestroy($bg);
    if ($jpeg === false || $jpeg === '') {
        return null;
    }
    return ['data' => $jpeg, 'w' => $w, 'h' => $h];
}

function zpgc_pdf_sum(array $values)
{
    $n = 0;
    foreach ($values as $v) {
        $n += (int) $v;
    }
    return $n;
}

function zpgc_pdf_ascii($str)
{
    $str = str_replace(
        ["\u{2014}", "\u{2013}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
        ['-', '-', "'", "'", '"', '"'],
        (string) $str
    );
    return preg_replace('/[^\x20-\x7E]/', '', $str);
}

function zpgc_pdf_chrome(ZpgcSimplePdf $pdf, $rangeLabel, $generated, $pageNo)
{
    $pdf->fillRect(0, 0, $pdf->pageW, 78, 97, 1, 7);
    $pdf->fillRect(0, 78, $pdf->pageW, 3, 189, 128, 132);
    $pdf->text(36, 28, 11, 'ZPGC SERVICES', true, 255, 232, 232);
    $pdf->text(36, 48, 18, 'IT Helpdesk Dashboard Report', true, 255, 255, 255);
    $pdf->text(36, 68, 9, $rangeLabel . '   |   Generated ' . $generated, false, 255, 214, 216);

    $pdf->fillRect(0, $pdf->pageH - 32, $pdf->pageW, 32, 245, 242, 242);
    $pdf->text(36, $pdf->pageH - 20, 8, 'Tarlac State University  -  ZPGC Services Capstone', false, 97, 1, 7);
    $right = 'Confidential  |  Page ' . (int) $pageNo;
    $pdf->text($pdf->pageW - 36 - $pdf->textWidth($right, 8), $pdf->pageH - 20, 8, $right, false, 120, 120, 120);
}

function zpgc_pdf_section_title(ZpgcSimplePdf $pdf, $y, $title)
{
    $pdf->fillRect(36, $y, 4, 16, 97, 1, 7);
    $pdf->text(46, $y + 13, 12, $title, true, 97, 1, 7);
    return $y + 28;
}

function zpgc_pdf_kpi(ZpgcSimplePdf $pdf, $x, $y, $w, $h, $label, $value, $accentR, $accentG, $accentB)
{
    $pdf->fillRect($x, $y, $w, $h, 255, 255, 255);
    $pdf->strokeRect($x, $y, $w, $h, 230, 226, 226, 0.8);
    $pdf->fillRect($x, $y, 4, $h, $accentR, $accentG, $accentB);
    $pdf->text($x + 14, $y + 20, 9, $label, false, 120, 120, 120);
    $pdf->text($x + 14, $y + 42, 18, (string) $value, true, 26, 26, 26);
}

function zpgc_pdf_table(ZpgcSimplePdf $pdf, $x, $y, $colW, array $headers, array $rows, $maxRows = 40)
{
    $rowH = 16;
    $headH = 18;
    $tableW = array_sum($colW);
    $pdf->fillRect($x, $y, $tableW, $headH, 97, 1, 7);
    $cx = $x + 8;
    foreach ($headers as $i => $h) {
        $pdf->text($cx, $y + 13, 8, zpgc_pdf_ascii($h), true, 255, 255, 255);
        $cx += $colW[$i];
    }
    $y += $headH;
    $n = 0;
    foreach ($rows as $row) {
        if ($n >= $maxRows) {
            break;
        }
        if ($n % 2 === 0) {
            $pdf->fillRect($x, $y, $tableW, $rowH, 250, 247, 247);
        } else {
            $pdf->fillRect($x, $y, $tableW, $rowH, 255, 255, 255);
        }
        $cx = $x + 8;
        foreach ($row as $i => $cell) {
            $pdf->text($cx, $y + 12, 8, zpgc_pdf_ascii((string) $cell), false, 40, 40, 40);
            $cx += $colW[$i];
        }
        $pdf->line($x, $y + $rowH, $x + $tableW, $y + $rowH, 235, 230, 230, 0.3);
        $y += $rowH;
        $n++;
    }
    $pdf->strokeRect($x, $y - ($n * $rowH) - $headH, $tableW, ($n * $rowH) + $headH, 210, 200, 200, 0.6);
    return $y + 8;
}

function zpgc_pdf_bars(ZpgcSimplePdf $pdf, $x, $y, $w, array $labels, array $values, array $colors)
{
    $max = max(1, zpgc_pdf_sum($values) > 0 ? max($values) : 1);
    $barH = 14;
    $gap = 8;
    $n = min(count($labels), count($values));
    for ($i = 0; $i < $n; $i++) {
        $label = zpgc_pdf_ascii((string) $labels[$i]);
        $val = (int) $values[$i];
        $pdf->text($x, $y + 11, 8, $label, false, 70, 70, 70);
        $trackX = $x + 88;
        $trackW = $w - 130;
        $pdf->fillRect($trackX, $y + 2, $trackW, $barH, 240, 240, 240);
        $fill = $trackW * ($val / $max);
        $c = $colors[$i] ?? [97, 1, 7];
        if ($fill > 0) {
            $pdf->fillRect($trackX, $y + 2, max(2, $fill), $barH, $c[0], $c[1], $c[2]);
        }
        $pdf->text($trackX + $trackW + 8, $y + 11, 8, (string) $val, true, 40, 40, 40);
        $y += $barH + $gap;
    }
    return $y;
}

$generated = date('M j, Y g:i A');
$reportLabels = $stats['report']['labels'] ?? [];
$submitted = $stats['report']['submitted'] ?? [];
$resolved = $stats['report']['resolved'] ?? [];
$catLabels = $stats['categories']['labels'] ?? [];
$catData = $stats['categories']['data'] ?? [];
$satLabels = $stats['satisfaction']['labels'] ?? [];
$satData = $stats['satisfaction']['data'] ?? [];
$sevLabels = $stats['severity']['labels'] ?? [];
$sevData = $stats['severity']['data'] ?? [];

$totalSubmitted = zpgc_pdf_sum($submitted);
$totalResolved = zpgc_pdf_sum($resolved);
$totalRated = zpgc_pdf_sum($satData);
$critical = (int) ($sevData[0] ?? 0);

$imagesIn = is_array($input['images'] ?? null) ? $input['images'] : [];
$chartImgs = [];
foreach (['report', 'categories', 'satisfaction', 'severity'] as $key) {
    $img = zpgc_pdf_jpeg_from_b64($imagesIn[$key] ?? '');
    if ($img !== null) {
        $chartImgs[$key] = $img;
    }
}

$pdf = new ZpgcSimplePdf();
$page = 1;
$pdf->addPage();
zpgc_pdf_chrome($pdf, $rangeLabel, $generated, $page);
$pdf->fillRect(0, 81, $pdf->pageW, $pdf->pageH - 113, 252, 250, 250);

$y = 104;
$y = zpgc_pdf_section_title($pdf, $y, 'Executive summary');
$pdf->text(36, $y, 9, 'Snapshot of ticket activity, categories, severity, and customer ratings for ' . strtolower($rangeLabel) . '.', false, 90, 90, 90);
$y += 16;

$boxW = 126;
$boxH = 54;
$gap = 10;
$startX = 36;
zpgc_pdf_kpi($pdf, $startX, $y, $boxW, $boxH, 'Submitted', $totalSubmitted, 97, 1, 7);
zpgc_pdf_kpi($pdf, $startX + ($boxW + $gap), $y, $boxW, $boxH, 'Resolved', $totalResolved, 21, 128, 61);
zpgc_pdf_kpi($pdf, $startX + 2 * ($boxW + $gap), $y, $boxW, $boxH, 'Ratings collected', $totalRated, 13, 148, 136);
zpgc_pdf_kpi($pdf, $startX + 3 * ($boxW + $gap), $y, $boxW, $boxH, 'Critical tickets', $critical, 220, 38, 38);
$y += $boxH + 24;

$y = zpgc_pdf_section_title($pdf, $y, 'Category mix');
$y = zpgc_pdf_bars($pdf, 36, $y, 540, $catLabels, $catData, [
    [97, 1, 7],
    [189, 128, 132],
    [13, 148, 136],
    [124, 58, 237],
    [107, 114, 128],
]);
$y += 10;
$y = zpgc_pdf_section_title($pdf, $y, 'Severity mix');
$y = zpgc_pdf_bars($pdf, 36, $y, 540, $sevLabels, $sevData, [
    [220, 38, 38],
    [217, 119, 6],
    [21, 128, 61],
]);
$y += 10;
$y = zpgc_pdf_section_title($pdf, $y, 'Customer satisfaction');
zpgc_pdf_bars($pdf, 36, $y, 540, $satLabels, $satData, [
    [126, 217, 168],
    [46, 139, 139],
    [91, 200, 232],
    [245, 166, 35],
    [217, 67, 94],
]);

if ($chartImgs !== []) {
    $page++;
    $pdf->addPage();
    zpgc_pdf_chrome($pdf, $rangeLabel, $generated, $page);
    $pdf->fillRect(0, 81, $pdf->pageW, $pdf->pageH - 113, 252, 250, 250);
    $y = 104;
    $y = zpgc_pdf_section_title($pdf, $y, 'Charts');
    $titles = [
        'report' => 'Tickets submitted vs resolved',
        'categories' => 'Tickets by category',
        'satisfaction' => 'Customer satisfaction',
        'severity' => 'Severity level',
    ];
    $slots = [
        [36, $y, 258, 250],
        [318, $y, 258, 250],
        [36, $y + 268, 258, 250],
        [318, $y + 268, 258, 250],
    ];
    $i = 0;
    foreach ($titles as $key => $caption) {
        if (!isset($chartImgs[$key]) || !isset($slots[$i])) {
            $i++;
            continue;
        }
        [$sx, $sy, $sw, $sh] = $slots[$i];
        $pdf->fillRect($sx, $sy, $sw, $sh, 255, 255, 255);
        $pdf->strokeRect($sx, $sy, $sw, $sh, 230, 226, 226, 0.7);
        $pdf->text($sx + 10, $sy + 16, 9, $caption, true, 97, 1, 7);
        $img = $chartImgs[$key];
        $innerW = $sw - 16;
        $innerH = $sh - 32;
        $ratio = $img['h'] > 0 ? ($img['w'] / $img['h']) : 1.6;
        $drawW = $innerW;
        $drawH = $drawW / max(0.2, $ratio);
        if ($drawH > $innerH) {
            $drawH = $innerH;
            $drawW = $drawH * $ratio;
        }
        $ix = $sx + (($sw - $drawW) / 2);
        $iy = $sy + 22 + (($innerH - $drawH) / 2);
        $pdf->jpeg($ix, $iy, $drawW, $drawH, $img['data'], $img['w'], $img['h']);
        $i++;
    }
}

$page++;
$pdf->addPage();
zpgc_pdf_chrome($pdf, $rangeLabel, $generated, $page);
$pdf->fillRect(0, 81, $pdf->pageW, $pdf->pageH - 113, 252, 250, 250);
$y = 104;
$y = zpgc_pdf_section_title($pdf, $y, 'Detailed counts');

$periodRows = [];
$n = min(count($reportLabels), count($submitted), count($resolved));
for ($i = 0; $i < $n; $i++) {
    $periodRows[] = [
        (string) $reportLabels[$i],
        (string) ((int) $submitted[$i]),
        (string) ((int) $resolved[$i]),
    ];
}
$y = zpgc_pdf_table($pdf, 36, $y, [220, 150, 150], ['Period', 'Submitted', 'Resolved'], $periodRows, 32);

if ($y > 520) {
    $page++;
    $pdf->addPage();
    zpgc_pdf_chrome($pdf, $rangeLabel, $generated, $page);
    $pdf->fillRect(0, 81, $pdf->pageW, $pdf->pageH - 113, 252, 250, 250);
    $y = 104;
    $y = zpgc_pdf_section_title($pdf, $y, 'Categories, severity, and ratings');
}

$catRows = [];
foreach ($catLabels as $i => $lab) {
    $catRows[] = [(string) $lab, (string) ((int) ($catData[$i] ?? 0))];
}
$sevRows = [];
foreach ($sevLabels as $i => $lab) {
    $sevRows[] = [(string) $lab, (string) ((int) ($sevData[$i] ?? 0))];
}
$leftY = zpgc_pdf_table($pdf, 36, $y, [160, 80], ['Category', 'Tickets'], $catRows, 8);
zpgc_pdf_table($pdf, 316, $y, [160, 80], ['Severity', 'Tickets'], $sevRows, 8);
$y = max($leftY, $y + 90) + 8;

$satRows = [];
foreach ($satLabels as $i => $lab) {
    $satRows[] = [(string) $lab, (string) ((int) ($satData[$i] ?? 0))];
}
zpgc_pdf_table($pdf, 36, $y, [320, 80], ['Satisfaction', 'Responses'], $satRows, 8);

$out = $pdf->build();
$fname = 'ZPGC-Dashboard-Report-' . ucfirst($range) . '-' . date('Ymd') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Length: ' . strlen($out));
echo $out;
exit();
