<?php
$themeNow = function_exists('current_ui_theme') ? current_ui_theme() : 'light';
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
            <h2>Appearance</h2>
            <p>Stay in control of how the dashboard looks on this account.</p>
        </div>
        <form action="../logic/settings_mngmnt.php" method="post" class="settings-form">
<?php echo zpgc_csrf_field(); ?>
            <div class="settings-pref-row">
                <span class="settings-pref-label">Browser preferences</span>
                <select name="theme" class="settings-pref-select">
                    <option value="light" <?php echo $themeNow === 'light' ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo $themeNow === 'dark' ? 'selected' : ''; ?>>Dark</option>
                </select>
            </div>
            <button type="submit" name="save_appearance" class="btn-save-ticket settings-save-btn">Save appearance</button>
        </form>
    </div>
    <div class="settings-card">
        <div class="settings-card-header">
            <h2>Security</h2>
            <p>Sign-in methods. Keep this password up to date.</p>
        </div>
        <h3 class="settings-subheading">Password</h3>
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
