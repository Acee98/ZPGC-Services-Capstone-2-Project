<?php
$themeNow = function_exists('current_ui_theme') ? current_ui_theme() : '';
if ($themeNow !== 'dark') {
    $themeNow = 'light';
}
$settings_success = $_SESSION['settings_success'] ?? '';
$settings_error = $_SESSION['settings_error'] ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);
?>
<div class="settings-container">
    <?php if ($settings_success !== '') { ?>
    <div class="utilities-notice"><?php echo htmlspecialchars($settings_success); ?></div>
    <?php } ?>
    <?php if ($settings_error !== '') { ?>
    <div class="utilities-notice-error"><?php echo htmlspecialchars($settings_error); ?></div>
    <?php } ?>
    <div class="settings-card">
        <div class="settings-card-header">
            <h2><?php echo htmlspecialchars(zpgc_t('Appearance')); ?></h2>
        </div>
        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form" id="settings-appearance-form">
<?php echo zpgc_csrf_field(); ?>
            <input type="hidden" name="save_appearance" value="1">
            <div class="settings-pref-row">
                <span class="settings-pref-label"><?php echo htmlspecialchars(zpgc_t('Theme')); ?></span>
                <select name="theme" class="settings-pref-select" data-theme-select>
                    <option value="light" <?php echo $themeNow === 'light' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zpgc_t('Light')); ?></option>
                    <option value="dark" <?php echo $themeNow === 'dark' ? 'selected' : ''; ?>><?php echo htmlspecialchars(zpgc_t('Dark')); ?></option>
                </select>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.querySelector('[data-theme-select]');
            var applied = document.documentElement.getAttribute('data-theme');
            if (sel && (applied === 'dark' || applied === 'light')) {
                sel.value = applied;
            }
        })();
        </script>
    </div>
    <div class="settings-card">
        <div class="settings-card-header">
            <h2>Security</h2>
        </div>
        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form settings-password-form">
<?php echo zpgc_csrf_field(); ?>
            <div class="settings-form-field">
                <label for="settings-current-password">Current password</label>
                <input type="password" id="settings-current-password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="settings-form-field">
                <label for="settings-new-password">New password</label>
                <input type="password" id="settings-new-password" name="new_password" minlength="8" required autocomplete="new-password">
            </div>
            <div class="settings-form-field">
                <label for="settings-confirm-password">Confirm new password</label>
                <input type="password" id="settings-confirm-password" name="confirm_password" minlength="8" required autocomplete="new-password">
            </div>
            <button type="submit" name="change_password" class="btn-save-ticket settings-save-btn">Update password</button>
        </form>
    </div>
</div>
