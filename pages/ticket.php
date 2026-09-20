<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_role('user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/ticket.css?v=20260920">
    <title>ZPGC Services | Ticket Creation</title>
</head>
<body>
    <div class="ticket-container">
        <form action="../logic/ticket_mngmnt.php" class="ticket-form" method="post" id="ticket-form">
            <h1 class="ticket-form-title">Submit New Ticket</h1>
            <p class="ticket-intro">Please provide the details regarding to your issue below:</p>

            <div class="ticket-field">
                <label for="subject">Subject:</label>
                <input type="text" id="subject" name="subject" placeholder="(e.g., Computer's monitor not turning on.)" required>
            </div>
            <div class="ticket-field">
                <label for="description">Description:</label>
                <textarea name="description" id="description" rows="5" placeholder="(e.g., The computer in laboratory room doesn't have network connection, only 1 is affected.)" required></textarea>
            </div>

            <div class="ai-suggest-row">
                <button type="button" id="btn-ai-suggest" class="btn-ai-suggest">Suggest with AI</button>
                <span id="ai-suggest-status" class="ai-suggest-status" aria-live="polite"></span>
            </div>
            <div id="ai-suggest-box" class="ai-suggest-box" hidden>
                <p class="ai-suggest-title">AI suggestion</p>
                <p id="ai-suggest-detail" class="ai-suggest-detail"></p>
            </div>

            <div class="ticket-field">
                <label for="category">Category:</label>
                <select name="category" id="category" required>
                    <option value="" disabled selected>Select an issue category</option>
                    <option value="hardware">Hardware</option>
                    <option value="software">Software</option>
                    <option value="account">Account</option>
                    <option value="network">Network</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="ticket-field">
                <label for="priority">Priority:</label>
                <select name="priority" id="priority">
                    <option value="" selected>None (admin can set later)</option>
                    <option value="critical">Critical</option>
                    <option value="moderate">Moderate</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div class="ticket-actions">
                <a href="../pages/user.php" class="btn-cancel-ticket">Cancel</a>
                <button type="submit" name="submit-ticket" class="btn-submit-ticket">Submit Ticket</button>
            </div>
        </form>
    </div>
    <script src="../js/ticket_ai.js"></script>
</body>
</html>
