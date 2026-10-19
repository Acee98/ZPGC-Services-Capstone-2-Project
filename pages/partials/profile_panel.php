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
$emailNotify = (int) ($profile['email_notify'] ?? 0) === 1;
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
                <input type="search" class="search-bar" placeholder="Search this page" aria-label="Search this page">
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
            <div class="profile-hero-tags">
                <span class="profile-role-badge role-<?php echo htmlspecialchars((string) ($profile['role'] ?? 'user')); ?>">
                    <?php echo htmlspecialchars((string) ($profile['account_label'] ?? 'User')); ?>
                </span>
                <?php if (($profile['role'] ?? '') === 'techn') { ?>
                <span class="profile-spec-badge">
                    <?php echo htmlspecialchars((string) (($profile['specialty_label'] ?? '') !== '' ? $profile['specialty_label'] : 'Unspecified')); ?>
                </span>
                <?php } ?>
            </div>
        </div>
        <form action="../logic/profile_mngmnt.php" method="post" class="profile-form">
<?php echo zpgc_csrf_field(); ?>
            <div class="profile-row">
                <section class="profile-card">
                    <h2>Personal Information</h2>
                    <div class="profile-info-row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3m0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08s5.97 1.09 6 3.08c-1.29 1.94-3.5 3.22-6 3.22"></path></svg>
                        <div class="profile-info-text">
                            <span class="profile-info-label">Account type</span>
                            <span class="profile-info-value">
                                <?php echo htmlspecialchars((string) ($profile['account_label'] ?? 'User')); ?>
                                <?php if (($profile['role'] ?? '') === 'techn') { ?>
                                · <?php echo htmlspecialchars((string) (($profile['specialty_label'] ?? '') !== '' ? $profile['specialty_label'] : 'Unspecified')); ?>
                                <?php } ?>
                            </span>
                        </div>
                    </div>
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
                                <input type="email" name="email" required value="<?php echo htmlspecialchars((string) $profile['email']); ?>" aria-label="E-mail" title="Changing email sends a new verification code">
                            </span>
                        </div>
                        <button type="button" class="profile-info-edit" data-profile-pencil aria-label="Edit e-mail">
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
