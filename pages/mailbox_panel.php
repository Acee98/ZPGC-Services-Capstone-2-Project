<?php
if (!isset($mailbox_tickets) || !isset($current_user_id)) {
    return;
}
$active_tid = (int) ($_GET['ticket_id'] ?? 0);
$active_subject = 'Select a ticket';
$active_issue = '';
foreach ($mailbox_tickets as $mt) {
    if ((int) $mt['id'] === $active_tid) {
        $active_subject = (string) $mt['subject'];
        $active_issue = '#' . (int) $mt['id'] . ' · ' . (string) ($mt['description'] ?? '');
        break;
    }
}
$can_attach = in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['user', 'techn'], true);
?>
                <div class="mailbox-container">
                    <aside class="mailbox-threads">
                        <div class="mailbox-threads-header">
                            <span class="mailbox-threads-title">Tickets</span>
                        </div>
                        <input type="search" id="mailbox-ticket-search" class="mailbox-search" placeholder="Search ticket ID" aria-label="Search ticket ID">
                        <div class="mailbox-threads-list" id="mailbox-threads-list">
                            <?php if (empty($mailbox_tickets)) { ?>
                            <div class="mailbox-threads-empty">
                                <p>No tickets to message yet.</p>
                            </div>
                            <?php } else { ?>
                            <?php foreach ($mailbox_tickets as $mt) {
                                $mid = (int) $mt['id'];
                                $isActive = ($active_tid === $mid);
                            ?>
                            <button type="button" class="mailbox-thread-item<?php echo $isActive ? ' active' : ''; ?>"
                                data-ticket-id="<?php echo $mid; ?>"
                                data-subject="<?php echo htmlspecialchars($mt['subject']); ?>"
                                data-description="<?php echo htmlspecialchars((string) ($mt['description'] ?? '')); ?>">
                                <div class="mailbox-thread-meta">
                                    <span class="mailbox-thread-subject"><?php echo htmlspecialchars($mt['subject']); ?></span>
                                    <span class="mailbox-thread-time">#<?php echo $mid; ?></span>
                                </div>
                            </button>
                            <?php } ?>
                            <?php } ?>
                        </div>
                    </aside>
                    <section class="mailbox-chat">
                        <div class="mailbox-chat-header" id="mailbox-chat-header">
                            <button type="button" class="mailbox-back-btn" id="mailbox-back-btn" aria-label="Back to ticket list">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"></path></svg>
                                <span>Tickets</span>
                            </button>
                            <div class="mailbox-chat-header-text">
                                <span class="mailbox-chat-header-subject" id="mailbox-chat-subject"><?php echo htmlspecialchars($active_subject); ?></span>
                                <span class="mailbox-chat-header-sub" id="mailbox-chat-issue"><?php echo htmlspecialchars($active_issue); ?></span>
                            </div>
                        </div>
                        <div class="mailbox-chat-messages" id="mailbox-chat-messages">
                            <div class="mailbox-chat-empty">
                                <p class="mailbox-chat-empty-title">No conversation selected</p>
                                <p class="mailbox-chat-empty-sub">Pick a ticket on the left to view messages.</p>
                            </div>
                        </div>
                        <?php if ($can_attach) { ?>
                        <div class="mailbox-compose" id="mailbox-compose">
                            <form class="mailbox-attach-form" id="mailbox-attach-form" action="<?php echo htmlspecialchars(zpgc_web_path('logic/ticket_attachment_mngmnt.php'), ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
                                <?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" id="mailbox-attach-ticket-id" value="<?php echo $active_tid; ?>">
                                <label class="ticket-icon-btn mailbox-attach-btn" aria-label="Attach image">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 5H9c-3.86 0-7 3.14-7 7s3.14 7 7 7h9v-2H9c-2.76 0-5-2.24-5-5s2.24-5 5-5h8c.79 0 1.54.31 2.11.89a2.967 2.967 0 0 1 .01 4.23 3 3 0 0 1-2.12.89H9c-.26 0-.5-.11-.7-.3-.19-.2-.3-.44-.3-.7s.11-.51.3-.7.44-.3.7-.3h8v-2H9c-.79 0-1.54.32-2.11.89S6 11.22 6 12.01s.31 1.54.89 2.11c.57.57 1.32.89 2.11.89h8c1.32 0 2.58-.52 3.53-1.47S22 11.34 22 10.01s-.52-2.58-1.47-3.53a4.95 4.95 0 0 0-3.52-1.47Z"></path></svg>
                                    <input type="file" name="ticket_image" accept="image/jpeg,image/png,image/gif,image/webp" class="ticket-file-input" id="mailbox-attach-file">
                                </label>
                            </form>
                            <form class="mailbox-chat-input-row" id="mailbox-send-form" action="<?php echo htmlspecialchars(zpgc_web_path('logic/message_mngmnt.php'), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                <?php echo zpgc_csrf_field(); ?>
                                <input type="hidden" name="ticket_id" id="mailbox-ticket-id" value="<?php echo $active_tid; ?>">
                                <input type="text" name="body" id="mailbox-body" class="mailbox-chat-input" placeholder="Type a message" autocomplete="off">
                                <button type="submit" name="send_message" class="mailbox-chat-send" aria-label="Send">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                                </button>
                            </form>
                        </div>
                        <?php } else { ?>
                        <p class="mailbox-readonly-note">Only the user and the assigned technician can send messages on a ticket.</p>
                        <input type="hidden" id="mailbox-ticket-id" value="<?php echo $active_tid; ?>">
                        <?php } ?>
                    </section>
                </div>
