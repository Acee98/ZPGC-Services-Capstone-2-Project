<?php
if (!isset($mailbox_tickets) || !isset($current_user_id)) {
    return;
}
$active_tid = (int) ($_GET['ticket_id'] ?? 0);
?>
                <div class="mailbox-container">
                    <aside class="mailbox-threads">
                        <div class="mailbox-threads-header">
                            <span class="mailbox-threads-title">Tickets</span>
                        </div>
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
                                data-ticket-id="<?php echo $mid; ?>">
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
                            <span class="mailbox-chat-header-subject" id="mailbox-chat-subject">Select a ticket</span>
                        </div>
                        <div class="mailbox-chat-messages" id="mailbox-chat-messages">
                            <div class="mailbox-chat-empty">
                                <p class="mailbox-chat-empty-title">No conversation selected</p>
                                <p class="mailbox-chat-empty-sub">Pick a ticket on the left to view messages.</p>
                            </div>
                        </div>
                        <form class="mailbox-chat-input-row" id="mailbox-send-form"
                            action="../logic/message_mngmnt.php" method="post">
                            <input type="hidden" name="ticket_id" id="mailbox-ticket-id" value="<?php echo $active_tid; ?>">
                            <input type="text" name="body" id="mailbox-body" class="mailbox-chat-input"
                                placeholder="Type a message" autocomplete="off">
                            <button type="submit" name="send_message" class="mailbox-chat-send" aria-label="Send">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                                </svg>
                            </button>
                        </form>
                    </section>
                </div>
