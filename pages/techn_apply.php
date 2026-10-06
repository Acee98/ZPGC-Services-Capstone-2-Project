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
    <link rel="stylesheet" href="../css/ticket.css?v=1.6.19">
    <title>ZPGC Services | Technician application</title>
</head>
<body>
<div class="ticket-container">
    <form class="ticket-form" action="../logic/techn_apply_mngmnt.php" method="post" enctype="multipart/form-data">
        <?php echo zpgc_csrf_field(); ?>
        <h1 class="ticket-form-title">Technician application</h1>
        <p class="ticket-hint">Your email is verified. Choose a specialty and upload your resume. The administrator reviews this with Pending status. You can review or delete it here — you cannot activate your own account.</p>
        <?php if ($error !== '') { ?>
        <div class="ticket-notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <?php if ($success !== '') { ?>
        <div class="ticket-notice-ok"><?php echo htmlspecialchars($success); ?></div>
        <?php } ?>

        <?php if ($app) { ?>
        <div class="ticket-field">
            <label>Status</label>
            <p class="ticket-hint" style="margin:0;"><?php echo htmlspecialchars(techn_apply_status_label($app['status'] ?? '')); ?></p>
            <?php if ($awaiting) { ?>
            <p class="ticket-hint">Administrator proposed <strong><?php echo htmlspecialchars((string) $app['proposed_specialty']); ?></strong>
                (current: <?php echo htmlspecialchars((string) $app['specialty']); ?>). Confirm within 24 hours or this application is removed.</p>
            <?php } ?>
        </div>
        <div class="ticket-field">
            <label>Resume on file</label>
            <p class="ticket-hint" style="margin:0;">
                <a href="../logic/resume_file.php?id=<?php echo (int) $app['id']; ?>">
                    <?php echo htmlspecialchars((string) $app['resume_original_name']); ?>
                </a>
            </p>
        </div>
        <?php } ?>

        <div class="ticket-field">
            <label for="specialty">Technical role</label>
            <select id="specialty" name="specialty" required>
                <option value="" disabled <?php echo empty($app) ? 'selected' : ''; ?>>Select a role</option>
                <?php foreach ($specialties as $opt) {
                    $sel = $app && strcasecmp((string) $app['specialty'], $opt) === 0 ? 'selected' : '';
                    ?>
                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($opt); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="ticket-field">
            <label for="resume"><?php echo $app ? 'Replace resume (optional)' : 'Resume (PDF, DOC, or DOCX, max 5 MB)'; ?></label>
            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf" <?php echo $app ? '' : 'required'; ?>>
        </div>

        <div class="ticket-actions">
            <?php if (!$app) { ?>
            <button type="submit" name="submit_application" class="btn-submit-ticket">Submit application</button>
            <?php } else { ?>
            <button type="submit" name="update_application" class="btn-submit-ticket">Save changes</button>
            <?php if ($awaiting) { ?>
            <button type="submit" name="accept_role_change" class="btn-submit-ticket">Approve specialty change</button>
            <?php } ?>
            <button type="submit" name="delete_application" class="btn-cancel-ticket"
                onclick="return confirm('Delete this application? You can submit a new one later.');">Delete application</button>
            <?php } ?>
        </div>
        <p class="ticket-hint"><a href="../logic/logout.php">Sign out</a></p>
    </form>
</div>
<script>
(function () {
    var form = document.querySelector('.ticket-form');
    var input = document.getElementById('resume');
    if (!form || !input) {
        return;
    }
    var MAX = 5 * 1024 * 1024;
    var CHUNK = 400 * 1024;
    form.addEventListener('submit', function (e) {
        var action = (e.submitter && e.submitter.getAttribute('name')) || '';
        if (action === 'delete_application' || action === 'accept_role_change') {
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
        if (file.size <= CHUNK) {
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
            fetch('../logic/techn_apply_mngmnt.php', { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
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
