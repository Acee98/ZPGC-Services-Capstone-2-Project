<?php
/** Same rules as before: css/critical_ui_fixes.css (not inline). */
if (!function_exists('zpgc_asset')) {
    require_once dirname(__DIR__, 2) . '/logic/version.php';
}
?>
<link rel="stylesheet" id="zpgc-theme-overrides" href="<?php echo htmlspecialchars(zpgc_asset('../css/critical_ui_fixes.css')); ?>">
