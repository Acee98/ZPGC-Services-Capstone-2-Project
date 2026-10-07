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
    imagejpeg($bg, null, 80);
    $jpeg = ob_get_clean();
    imagedestroy($bg);
    if ($jpeg === false || $jpeg === '') {
        return null;
    }
    return ['data' => $jpeg, 'w' => $w, 'h' => $h];
}

$pdf = new ZpgcSimplePdf();
$pdf->addPage();
$pdf->text(48, 48, 18, 'ZPGC Services — Dashboard Report');
$pdf->text(48, 72, 12, $rangeLabel . '  |  Generated ' . date('Y-m-d H:i'));
$y = 108;

$sections = [
    ['Tickets Report — Submitted', $stats['report']['labels'] ?? [], $stats['report']['submitted'] ?? []],
    ['Tickets Report — Resolved', $stats['report']['labels'] ?? [], $stats['report']['resolved'] ?? []],
    ['Tickets — Categories', $stats['categories']['labels'] ?? [], $stats['categories']['data'] ?? []],
    ['Customer Satisfaction', $stats['satisfaction']['labels'] ?? [], $stats['satisfaction']['data'] ?? []],
    ['Severity Level', $stats['severity']['labels'] ?? [], $stats['severity']['data'] ?? []],
];

foreach ($sections as $section) {
    [$title, $labels, $values] = $section;
    if ($y > 720) {
        $pdf->addPage();
        $y = 48;
    }
    $pdf->text(48, $y, 13, $title);
    $y += 18;
    $n = min(count($labels), count($values));
    for ($i = 0; $i < $n; $i++) {
        if ($y > 750) {
            $pdf->addPage();
            $y = 48;
        }
        $line = sprintf('%s: %s', (string) $labels[$i], (string) ((int) $values[$i]));
        $pdf->text(60, $y, 10, $line);
        $y += 14;
    }
    $y += 12;
}

$images = is_array($input['images'] ?? null) ? $input['images'] : [];
$chartTitles = [
    'report' => 'Tickets Report',
    'categories' => 'Tickets - Categories',
    'satisfaction' => 'Customer Satisfaction',
    'severity' => 'Severity Level',
];
foreach ($chartTitles as $key => $title) {
    $img = zpgc_pdf_jpeg_from_b64($images[$key] ?? '');
    if ($img === null) {
        continue;
    }
    $pdf->addPage();
    $pdf->text(48, 40, 14, $title . ' — ' . $rangeLabel);
    $maxW = 516.0;
    $maxH = 680.0;
    $ratio = $img['h'] > 0 ? ($img['w'] / $img['h']) : 1.6;
    $drawW = $maxW;
    $drawH = $drawW / max(0.2, $ratio);
    if ($drawH > $maxH) {
        $drawH = $maxH;
        $drawW = $drawH * $ratio;
    }
    $pdf->jpeg(48, 64, $drawW, $drawH, $img['data'], $img['w'], $img['h']);
}

$out = $pdf->build();
$fname = 'zpgc-dashboard-' . $range . '-' . date('Ymd') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Length: ' . strlen($out));
echo $out;
exit();
