<?php
require_once __DIR__ . '/../../logic/profile_user.php';
$profile = current_profile_user($conn);
if (!$profile) {
    return;
}
$first = (string) $profile['first_name'];
$last = (string) $profile['last_name'];
$initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
$self = basename($_SERVER['PHP_SELF']);
?>
<div class="profile-menu">
    <button type="button" class="profile-circle profile-menu-btn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
        <?php echo htmlspecialchars($initials !== '' ? $initials : '?'); ?>
    </button>
    <div class="profile-dropdown" hidden>
        <a href="<?php echo htmlspecialchars($self); ?>?tab=profile">Profile</a>
        <a href="<?php echo htmlspecialchars($self); ?>?tab=settings">Settings</a>
        <a href="../logic/logout.php">Logout</a>
    </div>
</div>
