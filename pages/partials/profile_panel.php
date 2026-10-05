<?php
require_once __DIR__ . '/../../logic/profile_user.php';
$profile = current_profile_user($conn);
if (!$profile) {
    return;
}
$profile_success = $_SESSION['profile_success'] ?? '';
$profile_error = $_SESSION['profile_error'] ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);
$fullName = trim((string) $profile['first_name'] . ' ' . (string) $profile['last_name']);
$initials = strtoupper(substr((string) $profile['first_name'], 0, 1) . substr((string) $profile['last_name'], 0, 1));
$phone = trim((string) ($profile['phone'] ?? ''));
$emailNotify = (int) ($profile['email_notify'] ?? 0) === 1;
$smsNotify = (int) ($profile['sms_notify'] ?? 0) === 1;
$language = (string) ($profile['preferred_language'] ?? 'English');
if ($language !== 'Filipino') {
    $language = 'English';
}
?>
<div class="page-content" id="page-profile">
    <div class="head">
        <header>
            <h1>My Profile</h1>
            <div class="search-bar-wrapper">
                <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
            </div>
            <?php include __DIR__ . '/profile_menu.php'; ?>
        </header>
    </div>
    <div class="settings-container profile-layout">
        <?php if ($profile_success !== '') { ?>
        <div class="utilities-notice"><?php echo htmlspecialchars($profile_success); ?></div>
        <?php } ?>
        <?php if ($profile_error !== '') { ?>
        <div class="utilities-notice-error"><?php echo htmlspecialchars($profile_error); ?></div>
        <?php } ?>
        <div class="profile-hero">
            <div class="profile-avatar-wrap">
                <div class="profile-hero-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials !== '' ? $initials : '?'); ?></div>
                <span class="profile-avatar-dot" aria-hidden="true"></span>
            </div>
            <p class="profile-hero-name"><?php echo htmlspecialchars($fullName); ?></p>
        </div>
        <form action="../logic/profile_mngmnt.php" method="post" class="profile-form">
<?php echo zpgc_csrf_field(); ?>
            <div class="profile-row">
                <section class="profile-card">
                    <h2>Personal Information</h2>
                    <div class="profile-info-row" data-profile-edit>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5m0-8c1.65 0 3 1.35 3 3s-1.35 3-3 3-3-1.35-3-3 1.35-3 3-3M4 22h16c.55 0 1-.45 1-1v-1c0-3.86-3.14-7-7-7h-4c-3.86 0-7 3.14-7 7v1c0 .55.45 1 1 1m6-7h4c2.76 0 5 2.24 5 5H5c0-2.76 2.24-5 5-5"></path></svg>
                        <div class="profile-info-text">
                            <span class="profile-info-label">Name</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars($fullName); ?></span>
                            <span class="profile-info-editfields" hidden>
                                <input type="text" name="first_name" required value="<?php echo htmlspecialchars((string) $profile['first_name']); ?>" aria-label="First name" placeholder="First name">
                                <input type="text" name="last_name" required value="<?php echo htmlspecialchars((string) $profile['last_name']); ?>" aria-label="Last name" placeholder="Last name">
                            </span>
                        </div>
                        <button type="button" class="profile-info-edit" data-profile-pencil aria-label="Edit name">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>
                        </button>
                    </div>
                    <div class="profile-info-row" data-profile-edit>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10c1.47 0 2.96-.37 4.44-1.1l-.89-1.79c-1.2.59-2.4.9-3.56.9-4.41 0-8-3.59-8-8S7.59 4 12 4s8 3.59 8 8v1c0 .69-.31 2-1.5 2-1.4 0-1.49-1.82-1.5-2V8h-2v.03C14.16 7.4 13.13 7 12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5c1.45 0 2.75-.63 3.66-1.62.52.89 1.41 1.62 2.84 1.62 2.27 0 3.5-2.06 3.5-4v-1c0-5.51-4.49-10-10-10m0 13c-1.65 0-3-1.35-3-3s1.35-3 3-3 3 1.35 3 3-1.35 3-3 3"></path></svg>
                        <div class="profile-info-text">
                            <span class="profile-info-label">E-mail</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars((string) $profile['email']); ?></span>
                            <span class="profile-info-editfields" hidden>
                                <input type="email" name="email" required readonly value="<?php echo htmlspecialchars((string) $profile['email']); ?>" aria-label="E-mail" title="Email cannot be changed here">
                            </span>
                        </div>
                        <button type="button" class="profile-info-edit" data-profile-pencil aria-label="Edit e-mail">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>
                        </button>
                    </div>
                    <div class="profile-info-row" data-profile-edit>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.07 22h.35c.47-.02.9-.26 1.17-.64l2.14-3.09c.23-.33.32-.74.24-1.14s-.31-.74-.64-.97l-4.64-3.09a1.47 1.47 0 0 0-.83-.25c-.41 0-.81.16-1.1.48l-1.47 1.59c-.69-.43-1.61-1.07-2.36-1.82-.72-.72-1.37-1.64-1.82-2.36l1.59-1.47c.54-.5.64-1.32.23-1.93L7.84 2.67c-.22-.33-.57-.57-.97-.64a1.46 1.46 0 0 0-1.13.24L2.65 4.41c-.39.27-.62.7-.64 1.17-.03.69-.16 6.9 4.68 11.74 4.35 4.35 9.81 4.69 11.38 4.69ZM6.88 10.05c-.16.15-.21.39-.11.59.05.09 1.15 2.24 2.74 3.84 1.6 1.6 3.75 2.7 3.84 2.75.2.1.44.06.59-.11l1.99-2.15 3.86 2.57-1.7 2.46c-1.16 0-6.13-.24-9.99-4.1S4 7.06 4 5.91l2.46-1.7 2.57 3.86-2.15 1.99Z"></path></svg>
                        <div class="profile-info-text">
                            <span class="profile-info-label">Phone Number</span>
                            <?php if ($phone === '') { ?>
                            <span class="profile-info-value profile-info-empty">Not set</span>
                            <?php } else { ?>
                            <span class="profile-info-value"><?php echo htmlspecialchars($phone); ?></span>
                            <?php } ?>
                            <span class="profile-info-editfields" hidden>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" aria-label="Phone number" placeholder="Phone number">
                            </span>
                        </div>
                        <button type="button" class="profile-info-edit" data-profile-pencil aria-label="Edit phone number">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>
                        </button>
                    </div>
                </section>
                <section class="profile-card">
                    <h2>Contact Preferences</h2>
                    <div class="profile-pref-row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10c1.47 0 2.96-.37 4.44-1.1l-.89-1.79c-1.2.59-2.4.9-3.56.9-4.41 0-8-3.59-8-8S7.59 4 12 4s8 3.59 8 8v1c0 .69-.31 2-1.5 2-1.4 0-1.49-1.82-1.5-2V8h-2v.03C14.16 7.4 13.13 7 12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5c1.45 0 2.75-.63 3.66-1.62.52.89 1.41 1.62 2.84 1.62 2.27 0 3.5-2.06 3.5-4v-1c0-5.51-4.49-10-10-10m0 13c-1.65 0-3-1.35-3-3s1.35-3 3-3 3 1.35 3 3-1.35 3-3 3"></path></svg>
                        <span class="profile-pref-label">E-mail Notifications</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notify" value="1" <?php echo $emailNotify ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="profile-pref-row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8.59 15.41 3c-.78-.78-2.05-.78-2.83 0L3 12.59c-.78.78-.78 2.05 0 2.83l5.59 5.59c.39.39.9.58 1.41.58s1.02-.19 1.41-.58L21 11.42c.78-.78.78-2.05 0-2.83m-11 11L4.41 14 14 4.41 19.59 10l-9.58 9.59Z"></path><path d="M8.29 14.29A.996.996 0 1 0 9.7 15.7a.996.996 0 1 0-1.41-1.41m6 6L15 21l.71.71 3-3 3-3L21 15l-.71-.71-3 3zM9.71 3.71 9 3l-.71-.71-3 3-3 3L3 9l.71.71 3-3z"></path></svg>
                        <span class="profile-pref-label">SMS Notifications</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_notify" value="1" <?php echo $smsNotify ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="profile-pref-row profile-pref-lang">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 11c-.4 0-.75.23-.91.59l-4 9 1.83.81 1.07-2.41h4.03l1.07 2.41 1.83-.81-4-9a1 1 0 0 0-.91-.59Zm-1.13 6L17 14.46 18.13 17zm-3.62-2.03.49-1.94c-.13-.03-1.6-.43-3.17-1.42 1.4-1.41 2.49-3.26 2.74-5.61h1.68V4h-5V2h-2v2H2v2h8.3c-.25 1.91-1.19 3.34-2.31 4.4C7.3 9.75 6.68 8.96 6.25 8H4.12c.5 1.44 1.33 2.63 2.3 3.61-1.57.99-3.04 1.39-3.17 1.42l.49 1.94c1.18-.3 2.76-.96 4.26-2.02 1.49 1.06 3.08 1.72 4.25 2.02"></path></svg>
                        <div class="profile-pref-lang-body">
                            <span class="profile-pref-label">Preferred Language</span>
                            <select name="preferred_language" class="profile-pref-select" aria-label="Preferred language">
                                <option value="English" <?php echo $language === 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="Filipino" <?php echo $language === 'Filipino' ? 'selected' : ''; ?>>Filipino</option>
                            </select>
                        </div>
                    </div>
                </section>
            </div>
            <button type="submit" name="save_profile" class="btn-save-ticket settings-save-btn">Save profile</button>
        </form>
    </div>
</div>
