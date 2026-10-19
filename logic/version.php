<?php
/**
 * Paper / product label. Stages 8–9 do not invent v1.7.
 * Cache-bust query strings may increment 1.6.xx without changing this label.
 */
if (!defined('ZPGC_PRODUCT_VERSION')) {
    define('ZPGC_PRODUCT_VERSION', 'V1.6');
}
if (!defined('ZPGC_PRODUCT_NAME')) {
    define('ZPGC_PRODUCT_NAME', 'ZPGC Services');
}
if (!defined('ZPGC_PRODUCT_LABEL')) {
    define('ZPGC_PRODUCT_LABEL', 'ZPGC Services V1.6');
}
if (!defined('ZPGC_DOC_FREEZE')) {
    define('ZPGC_DOC_FREEZE', '16 October 2026');
}
if (!defined('ZPGC_ASSET_VERSION')) {
    define('ZPGC_ASSET_VERSION', '1.6.100');
}

if (!function_exists('zpgc_page_title')) {
    function zpgc_page_title($section = '')
    {
        $section = trim((string) $section);
        return $section === '' ? ZPGC_PRODUCT_LABEL : ZPGC_PRODUCT_LABEL . ' | ' . $section;
    }
}

if (!function_exists('zpgc_asset')) {
    function zpgc_asset($path)
    {
        $path = (string) $path;
        $sep = str_contains($path, '?') ? '&' : '?';
        return $path . $sep . 'v=' . rawurlencode(ZPGC_ASSET_VERSION);
    }
}

if (!function_exists('zpgc_version_badge_html')) {
    function zpgc_version_badge_html($class = 'app-version')
    {
        $class = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string) $class);
        return '<p class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(ZPGC_PRODUCT_LABEL, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
