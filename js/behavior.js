function setSelectedNavItem(item) {
    if (!item) {
        return;
    }
    const navList = item.closest(".nav-list");
    if (!navList) {
        return;
    }
    navList.querySelectorAll(".nav-list-item").forEach(function (navItem) {
        navItem.classList.remove("selected");
    });
    item.classList.add("selected");
    
    const navId = item.getAttribute("data-nav");
    if (navId) {
        document.body.setAttribute("data-page", navId);
    } else {
        document.body.removeAttribute("data-page");
    }
}

function initNavSelection() {
    const navList = document.querySelector(".nav-list");
    if (!navList) {
        return;
    }
    const selectedItem = navList.querySelector(".nav-list-item.selected") || navList.querySelector('[data-nav="dashboard"]') || navList.querySelector(".nav-list-item:first-child");

    setSelectedNavItem(selectedItem);
}

function initNavClickSelection() {
    const navList = document.querySelector(".nav-list");
    if (!navList) {
        return;
    }
    navList.querySelectorAll(".nav-list-item").forEach(function (item) {
        const link = item.querySelector(".nav-link");
        if (!link) {
            return;
        }
        link.addEventListener("click", function(event) {
            const href = link.getAttribute("href") || "";
            if (href && href !== "#") {
                return;
            }
            event.preventDefault();
            setSelectedNavItem(item);
        });
    });
}
function initSidebarCollapse() {
    const mainHead = document.querySelector(".main-head");
    const showcaseToggler = document.querySelector(".showcase-toggler");
    if (!mainHead) {
        return;
    }
    mainHead.classList.add("active");
    if (!showcaseToggler) {
        return
    }
    showcaseToggler.addEventListener("click", function() {
        mainHead.classList.remove("active");
    });
    mainHead.addEventListener("mouseleave", function() {
        mainHead.classList.add("active");
    });
    mainHead.addEventListener("mouseenter", function() {
        mainHead.classList.remove("active");
    });
}
function initTabFromQuery() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get("tab");
    if (!tab) {
        return;
    }
    var item = document.querySelector('.nav-list-item[data-nav="' + tab + '"]');
    if (item) {
        setSelectedNavItem(item);
    }
}

var POLL_MS = 3000;
var mailboxTimer = null;
var mailboxTicketId = 0;

function renderMailboxMessages(payload) {
    var box = document.getElementById("mailbox-chat-messages");
    if (!box || !payload || !payload.ok) {
        return;
    }
    var meNote = "";
    box.innerHTML = "";
    if (!payload.messages.length) {
        box.innerHTML = '<div class="mailbox-chat-empty"><p class="mailbox-chat-empty-title">No messages yet</p><p class="mailbox-chat-empty-sub">Send the first message below.</p></div>';
        return;
    }
    payload.messages.forEach(function (msg) {
        var wrap = document.createElement("div");
        wrap.className = "mailbox-msg " + (msg.mine ? "sent" : "received");
        var bubble = document.createElement("div");
        bubble.className = "mailbox-msg-bubble";
        bubble.textContent = msg.body;
        var time = document.createElement("span");
        time.className = "mailbox-msg-time";
        time.textContent = msg.name;
        wrap.appendChild(bubble);
        wrap.appendChild(time);
        box.appendChild(wrap);
    });
    box.scrollTop = box.scrollHeight;
}

function loadMailboxMessages() {
    if (!mailboxTicketId) {
        return;
    }
    fetch("../logic/fetch_messages.php?ticket_id=" + encodeURIComponent(mailboxTicketId), {
        credentials: "same-origin"
    })
        .then(function (res) { return res.json(); })
        .then(renderMailboxMessages)
        .catch(function () {});
}

function startMailboxPoll() {
    if (mailboxTimer) {
        clearInterval(mailboxTimer);
        mailboxTimer = null;
    }
    // B-032: checks data-page === "message" (missing s) so poll never starts while Messages tab is "messages"
    if (document.body.getAttribute("data-page") !== "messages") {
        return;
    }
    mailboxTimer = setInterval(loadMailboxMessages, POLL_MS);
}

function initMailbox() {
    var list = document.getElementById("mailbox-threads-list");
    var form = document.getElementById("mailbox-send-form");
    var hidden = document.getElementById("mailbox-ticket-id");
    var subjectEl = document.getElementById("mailbox-chat-subject");
    if (!list || !hidden) {
        return;
    }
    mailboxTicketId = parseInt(hidden.value, 10) || 0;
    if (mailboxTicketId) {
        loadMailboxMessages();
    }
    list.querySelectorAll(".mailbox-thread-item").forEach(function (btn) {
        btn.addEventListener("click", function () {
            list.querySelectorAll(".mailbox-thread-item").forEach(function (b) {
                b.classList.remove("active");
            });
            btn.classList.add("active");
            mailboxTicketId = parseInt(btn.getAttribute("data-ticket-id"), 10) || 0;
            hidden.value = mailboxTicketId;
            if (subjectEl) {
                var sub = btn.querySelector(".mailbox-thread-subject");
                subjectEl.textContent = sub ? sub.textContent : "Ticket #" + mailboxTicketId;
            }
            loadMailboxMessages();
            startMailboxPoll();
        });
    });
    if (form) {
        form.addEventListener("submit", function () {
            startMailboxPoll();
        });
    }
    startMailboxPoll();
}

document.addEventListener("DOMContentLoaded", function() {
    initNavSelection();
    initNavClickSelection();
    initSidebarCollapse();
    initTabFromQuery();
    initMailbox();
})