<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/techn_apply.php';
require_login();

$role = function_exists('auth_mail_normalize_role')
    ? auth_mail_normalize_role($_SESSION['role'] ?? '')
    : strtolower((string) ($_SESSION['role'] ?? ''));
if ($role !== 'techn') {
    header('Location: ' . zpgc_role_home($role !== '' ? $role : 'user'));
    exit();
}

$userId = current_user_id($conn);
$st = $conn->prepare('SELECT status, email_verified FROM users WHERE id = ? LIMIT 1');
$st->bind_param('i', $userId);
$st->execute();
$me = $st->get_result()->fetch_assoc();
$st->close();
if (!$me) {
    header('Location: login_signup.php');
    exit();
}
if (strtolower((string) $me['status']) === 'active') {
    unset($_SESSION['techn_applicant']);
    header('Location: techn.php');
    exit();
}
if ((int) ($me['email_verified'] ?? 0) !== 1) {
    $_SESSION['login_error'] = 'Verify your email with the 6-digit code first.';
    header('Location: login_signup.php');
    exit();
}

$_SESSION['techn_applicant'] = 1;
techn_apply_ready($conn);
techn_apply_expire_stale($conn, false);
$app = techn_apply_get_for_user($conn, $userId);
$error = $_SESSION['apply_error'] ?? '';
$success = $_SESSION['apply_success'] ?? '';
unset($_SESSION['apply_error'], $_SESSION['apply_success']);
$specialties = techn_apply_specialties();
$awaiting = $app && ($app['status'] ?? '') === 'awaiting_role_change';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/ticket.css?v=1.6.22">
    <link rel="stylesheet" href="../css/apply.css?v=1.6.2">
    <title>ZPGC Services | Technician application</title>
</head>
<body class="apply-page">
<?php
$roleCopy = [
    'Hardware' => 'Campus devices, monitors, and lab equipment',
    'Software' => 'Apps, operating systems, and installs',
    'Network' => 'Wi-Fi, LAN, and connectivity',
    'Account' => 'Logins, passwords, and access',
    'Other' => 'Anything that does not fit the roles above',
];
?>
<div class="apply-page-wrap">
    <form class="apply-shell ticket-form" action="../logic/techn_apply_mngmnt.php" method="post" enctype="multipart/form-data">
        <?php echo zpgc_csrf_field(); ?>
        <header class="apply-shell-head">
            <p class="apply-kicker">ZPGC Services</p>
            <h1>Technician application</h1>
            <p>Choose your technical role and attach a resume. An administrator reviews this before your account is activated.</p>
        </header>
        <?php if ($error !== '') { ?>
        <div class="ticket-notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <?php if ($success !== '') { ?>
        <div class="ticket-notice-ok"><?php echo htmlspecialchars($success); ?></div>
        <?php } ?>

        <?php if ($app) { ?>
        <div class="apply-current">
            <span class="apply-status-chip"><?php echo htmlspecialchars(techn_apply_status_label($app['status'] ?? '')); ?></span>
            <?php if ($awaiting) { ?>
            <p>Administrator offered <strong><?php echo htmlspecialchars((string) $app['proposed_specialty']); ?></strong>
                instead of <strong><?php echo htmlspecialchars((string) $app['specialty']); ?></strong>.
                Accept within 24 hours to activate your account, or decline to remove the application and account.</p>
            <?php } else { ?>
            <p>Your application is waiting for administrator review. You can update the role or resume below.</p>
            <?php } ?>
            <a class="apply-resume-link" href="../logic/resume_file.php?id=<?php echo (int) $app['id']; ?>">
                Resume on file: <?php echo htmlspecialchars((string) $app['resume_original_name']); ?>
            </a>
        </div>
        <?php } ?>

        <fieldset class="apply-fieldset">
            <legend>Technical role</legend>
            <div class="apply-roles">
                <?php foreach ($specialties as $opt) {
                    $checked = $app && strcasecmp((string) $app['specialty'], $opt) === 0;
                    ?>
                <label class="apply-role-card">
                    <input type="radio" name="specialty" value="<?php echo htmlspecialchars($opt); ?>" <?php echo $checked ? 'checked' : ''; ?> required>
                    <span class="apply-role-name"><?php echo htmlspecialchars($opt); ?></span>
                    <span class="apply-role-copy"><?php echo htmlspecialchars($roleCopy[$opt] ?? ''); ?></span>
                </label>
                <?php } ?>
            </div>
        </fieldset>

        <fieldset class="apply-fieldset">
            <legend><?php echo $app ? 'Replace resume (optional)' : 'Resume'; ?></legend>
            <label class="apply-drop" for="resume">
                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf" <?php echo $app ? '' : 'required'; ?>>
                <span class="apply-drop-title" data-empty="Drop or choose a PDF, DOC, or DOCX (max 5 MB)" data-picked="">Drop or choose a PDF, DOC, or DOCX (max 5 MB)</span>
                <span class="apply-drop-hint">Administrators download this during review.</span>
            </label>
        </fieldset>

        <div class="apply-actions">
            <?php if (!$app) { ?>
            <button type="submit" name="submit_application" class="apply-btn apply-btn-primary">Submit application</button>
            <?php } else { ?>
            <button type="submit" name="update_application" class="apply-btn apply-btn-primary">Save changes</button>
            <?php if ($awaiting) { ?>
            <button type="submit" name="accept_role_change" class="apply-btn apply-btn-secondary">Accept role and activate</button>
            <button type="submit" name="decline_role_change" class="apply-btn apply-btn-danger"
                onclick="return confirm('Decline this offer? Your application and account will be removed.');">Decline offer</button>
            <?php } ?>
            <button type="submit" name="delete_application" class="apply-btn apply-btn-danger"
                onclick="return confirm('Withdraw this application? Your technician account will be deleted.');">Withdraw application</button>
            <?php } ?>
            <a class="apply-btn apply-btn-ghost" href="../logic/logout.php">Sign out</a>
        </div>
    </form>
</div>
<script>
(function () {
    var form = document.querySelector('.ticket-form');
    var input = document.getElementById('resume');
    var dropTitle = document.querySelector('.apply-drop-title');
    if (!form || !input) {
        return;
    }
    input.addEventListener('change', function () {
        if (!dropTitle) {
            return;
        }
        dropTitle.textContent = (input.files && input.files[0])
            ? input.files[0].name
            : (dropTitle.getAttribute('data-empty') || dropTitle.textContent);
    });
    var MAX = 5 * 1024 * 1024;
    var CHUNK = 256 * 1024;
    form.addEventListener('submit', function (e) {
        var action = (e.submitter && e.submitter.getAttribute('name')) || '';
        if (action === 'delete_application' || action === 'accept_role_change' || action === 'decline_role_change') {
            return;
        }
        var file = input.files && input.files[0];
        if (!file) {
            return;
        }
        if (file.size > MAX) {
            e.preventDefault();
            alert('Resume must be 5 MB or smaller.');
            return;
        }
        e.preventDefault();
        var csrf = form.querySelector('input[name="_csrf"]');
        var specialty = form.querySelector('[name="specialty"]');
        var token = csrf ? csrf.value : '';
        var id = '';
        var bytes = new Uint8Array(16);
        if (window.crypto && crypto.getRandomValues) {
            crypto.getRandomValues(bytes);
            id = Array.from(bytes).map(function (b) {
                return ('0' + b.toString(16)).slice(-2);
            }).join('');
        } else {
            id = String(Date.now()) + String(Math.random()).replace('.', '');
        }
        var total = Math.ceil(file.size / CHUNK);
        var index = 0;
        var btn = e.submitter;
        if (btn) {
            btn.disabled = true;
        }
        function fail(msg) {
            if (btn) {
                btn.disabled = false;
            }
            alert(msg || 'Resume upload failed. Try a PDF under 5 MB.');
        }
        function sendChunk() {
            var start = index * CHUNK;
            var blob = file.slice(start, start + CHUNK);
            var data = new FormData();
            data.append('_csrf', token);
            data.append('resume_chunk', '1');
            data.append('upload_id', id);
            data.append('index', String(index));
            data.append('total', String(total));
            data.append('orig_name', file.name);
            data.append('chunk', blob, 'part.bin');
            fetch('../logic/techn_apply_mngmnt.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) {
                    if (res.status === 413) {
                        return { ok: false, j: { ok: false, error: 'Server rejected the resume as too large (HTTP 413). Use a PDF under 5 MB.' } };
                    }
                    var ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (ct.indexOf('json') === -1) {
                        return res.text().then(function (t) {
                            var msg = 'Resume upload failed.';
                            if (/413|request entity too large/i.test(t)) {
                                msg = 'Server rejected the resume as too large (HTTP 413). Use a PDF under 5 MB.';
                            }
                            return { ok: false, j: { ok: false, error: msg } };
                        });
                    }
                    return res.json().then(function (j) { return { ok: res.ok, j: j }; });
                })
                .then(function (out) {
                    if (!out.j || !out.j.ok) {
                        fail(out.j && out.j.error);
                        return;
                    }
                    index += 1;
                    if (index < total) {
                        sendChunk();
                        return;
                    }
                    var field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = action || 'submit_application';
                    field.value = '1';
                    form.appendChild(field);
                    input.removeAttribute('required');
                    input.value = '';
                    form.submit();
                })
                .catch(function () { fail('Network error while uploading the resume.'); });
        }
        sendChunk();
    });
})();
</script>
</body>
</html>
