<?php
if (!defined('ZPGC_PRODUCT_LABEL')) {
    require_once __DIR__ . '/../../logic/version.php';
}
echo zpgc_version_badge_html(isset($zpgc_version_class) ? $zpgc_version_class : 'app-version');
