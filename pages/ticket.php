<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/ticket_subjects.php';
require_role('user');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$formError = $_SESSION['ticket_form_error'] ?? '';
$old = $_SESSION['ticket_form_old'] ?? [];
unset($_SESSION['ticket_form_error'], $_SESSION['ticket_form_old']);
$oldSubject = (string) ($old['subject'] ?? '');
$oldDescription = (string) ($old['description'] ?? '');
$subjectLists = ticket_common_questions();
$categoryLabels = [
    'hardware' => 'Hardware',
    'software' => 'Software',
    'network' => 'Network',
    'account' => 'Account',
    'other' => 'Other',
];
$subjectLimit = ticket_subject_word_limit();
$descriptionLimit = ticket_description_word_limit();
$quota = ticket_account_quota($conn, current_user_id($conn));
$quotaLimit = (int) $quota['limit'];
$quotaUsed = (int) $quota['used'];
$quotaRemaining = (int) $quota['remaining'];
$quotaBlocked = !$quota['ok'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(zpgc_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <link rel="stylesheet" href="../css/ticket.css?v=1.6.22">
    <title>ZPGC Services | Ticket Creation</title>
</head>
<body>
    <div class="ticket-container">
        <form action="../logic/ticket_mngmnt.php" class="ticket-form" method="post" id="ticket-form">
<?php echo zpgc_csrf_field(); ?>
            <h1 class="ticket-form-title">Submit New Ticket</h1>
            <?php if ($formError !== '') { ?>
            <div class="ticket-notice-error"><?php echo htmlspecialchars($formError); ?></div>
            <?php } elseif ($quotaBlocked) { ?>
            <div class="ticket-notice-error">This account already created <?php echo $quotaLimit; ?> tickets in the last 24 hours. You can submit again after some of those tickets fall outside that window.</div>
            <?php } ?>
            <p class="ticket-hint">Each account can create up to <?php echo $quotaLimit; ?> tickets per 24 hours (<?php echo $quotaUsed; ?> used, <?php echo $quotaRemaining; ?> left).</p>

            <div class="ticket-field">
                <label for="subject">Subject:</label>
                <p class="ticket-hint">Common subjects appear as you type. Pick one, or type your own if none fit. Category is set by AI, not by this list.</p>
                <div class="subject-combo">
                    <input type="text" id="subject" name="subject" required autocomplete="off"
                        role="combobox" aria-expanded="false" aria-controls="subject-menu" aria-autocomplete="list"
                        maxlength="255"
                        placeholder="Type or choose a common subject"
                        value="<?php echo htmlspecialchars($oldSubject); ?>">
                    <button type="button" class="subject-open" id="subject-open" aria-label="Show common subjects">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5z"></path></svg>
                    </button>
                    <div class="subject-menu" id="subject-menu" hidden></div>
                </div>
                <p class="ticket-count" id="subject-count">0 / <?php echo (int) $subjectLimit; ?> words</p>
            </div>
            <div class="ticket-field">
                <label for="description">Description:</label>
                <textarea name="description" id="description" rows="5" maxlength="1200"
                    placeholder="(e.g., The computer in laboratory room doesn't have network connection, only 1 is affected.)"
                    required><?php echo htmlspecialchars($oldDescription); ?></textarea>
                <p class="ticket-count" id="description-count">0 / <?php echo (int) $descriptionLimit; ?> words.</p>
            </div>

            <div class="ticket-actions">
                <a href="../pages/user.php" class="btn-cancel-ticket">Cancel</a>
                <button type="submit" name="submit-ticket" class="btn-submit-ticket"<?php echo $quotaBlocked ? ' disabled' : ''; ?>>Submit Ticket</button>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var lists = <?php echo json_encode($subjectLists, JSON_UNESCAPED_UNICODE); ?>;
        var labels = <?php echo json_encode($categoryLabels, JSON_UNESCAPED_UNICODE); ?>;
        var order = ["hardware", "software", "network", "account", "other"];
        var subjectLimit = <?php echo (int) $subjectLimit; ?>;
        var descriptionLimit = <?php echo (int) $descriptionLimit; ?>;
        var input = document.getElementById("subject");
        var menu = document.getElementById("subject-menu");
        var opener = document.getElementById("subject-open");
        var subjectCount = document.getElementById("subject-count");
        var description = document.getElementById("description");
        var descriptionCount = document.getElementById("description-count");
        var activeIndex = -1;

        function words(value) {
            var text = String(value || "").trim().replace(/\s+/g, " ");
            if (!text) {
                return 0;
            }
            return text.split(" ").length;
        }

        function paintCount(node, count, limit) {
            node.textContent = count + " / " + limit + " words";
            node.classList.toggle("is-over", count > limit);
        }

        function refreshCounts() {
            paintCount(subjectCount, words(input.value), subjectLimit);
            paintCount(descriptionCount, words(description.value), descriptionLimit);
        }

        function matches(item, query) {
            return item.toLowerCase().indexOf(query) !== -1;
        }

        function render(openForced) {
            var query = input.value.trim().toLowerCase();
            var html = "";
            var count = 0;
            order.forEach(function (key) {
                var items = (lists[key] || []).filter(function (item) {
                    return !query || matches(item, query);
                });
                if (!items.length) {
                    return;
                }
                html += '<p class="subject-menu-label">' + labels[key] + "</p>";
                items.forEach(function (item) {
                    html += '<button type="button" class="subject-option" data-index="' + count + '" data-value="'
                        + item.replace(/"/g, "&quot;") + '">' + item + "</button>";
                    count += 1;
                });
            });
            html += '<button type="button" class="subject-option subject-option-custom" data-custom="1">Others — use the subject you typed</button>';
            menu.innerHTML = html;
            activeIndex = -1;
            if (openForced) {
                menu.hidden = false;
                input.setAttribute("aria-expanded", "true");
            }
        }

        function openMenu() {
            render(true);
        }

        function closeMenu() {
            menu.hidden = true;
            input.setAttribute("aria-expanded", "false");
            activeIndex = -1;
        }

        function choose(value) {
            input.value = value;
            refreshCounts();
            closeMenu();
            input.focus();
        }

        input.addEventListener("focus", openMenu);
        input.addEventListener("input", function () {
            refreshCounts();
            openMenu();
        });
        opener.addEventListener("click", function () {
            if (menu.hidden) {
                openMenu();
                input.focus();
            } else {
                closeMenu();
            }
        });
        menu.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) {
                return;
            }
            if (button.getAttribute("data-custom") === "1") {
                closeMenu();
                input.focus();
                return;
            }
            choose(button.getAttribute("data-value") || "");
        });
        document.addEventListener("click", function (event) {
            if (!event.target.closest(".subject-combo")) {
                closeMenu();
            }
        });
        input.addEventListener("keydown", function (event) {
            var options = menu.querySelectorAll(".subject-option");
            if (event.key === "Escape") {
                closeMenu();
                return;
            }
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                if (menu.hidden) {
                    openMenu();
                }
                options = menu.querySelectorAll(".subject-option");
                if (!options.length) {
                    return;
                }
                activeIndex += event.key === "ArrowDown" ? 1 : -1;
                if (activeIndex < 0) {
                    activeIndex = options.length - 1;
                }
                if (activeIndex >= options.length) {
                    activeIndex = 0;
                }
                options.forEach(function (option, index) {
                    option.classList.toggle("is-active", index === activeIndex);
                });
                options[activeIndex].scrollIntoView({ block: "nearest" });
            }
            if (event.key === "Enter" && !menu.hidden && activeIndex >= 0) {
                event.preventDefault();
                options[activeIndex].click();
            }
        });
        description.addEventListener("input", refreshCounts);
        document.getElementById("ticket-form").addEventListener("submit", function (event) {
            refreshCounts();
            if (words(input.value) > subjectLimit || words(description.value) > descriptionLimit || !input.value.trim()) {
                event.preventDefault();
                if (words(input.value) > subjectLimit || !input.value.trim()) {
                    input.focus();
                } else {
                    description.focus();
                }
            }
        });
        refreshCounts();
    })();
    </script>
</body>
</html>
