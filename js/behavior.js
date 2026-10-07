function zpgcSyncUiUrl(tab, ticketId) {
    try {
        var params = new URLSearchParams(window.location.search);
        if (tab) {
            params.set("tab", tab);
        } else {
            params.delete("tab");
        }
        if (tab === "messages" && ticketId) {
            params.set("ticket_id", String(ticketId));
        } else if (tab !== "messages") {
            params.delete("ticket_id");
        }
        var qs = params.toString();
        var next = window.location.pathname + (qs ? "?" + qs : "") + window.location.hash;
        window.history.replaceState({}, "", next);
        try {
            sessionStorage.setItem("zpgc_last_tab", tab || "dashboard");
            if (tab === "messages" && ticketId) {
                sessionStorage.setItem("zpgc_last_ticket_id", String(ticketId));
            }
        } catch (e) {}
    } catch (err) {}
}

function setSelectedNavItem(item) {
    if (!item) {
        return;
    }
    const navId = item.getAttribute("data-nav");
    if (!navId) {
        return;
    }

    // Sync sidebar + mobile bottom tabs.
    document.querySelectorAll(".nav-list-item[data-nav], .mobile-tab-btn[data-nav]").forEach(function (navItem) {
        if (navItem.getAttribute("data-nav") === navId) {
            navItem.classList.add("selected");
        } else {
            navItem.classList.remove("selected");
        }
    });

    document.body.setAttribute("data-page", navId);
    var keepTicket = navId === "messages"
        ? (mailboxTicketId || new URLSearchParams(window.location.search).get("ticket_id") || "")
        : "";
    zpgcSyncUiUrl(navId, keepTicket);

    // Keep mobile drawer closed; bottom ribbon is the primary nav.
    var mainWrap = document.querySelector(".main-wrap");
    if (mainWrap) {
        mainWrap.classList.remove("nav-open");
    }
    document.body.classList.remove("nav-open");
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
            event.stopPropagation();
            setSelectedNavItem(item);
        });
    });
}

/** Always-visible bottom tabs on phone/tablet so users can switch pages without the drawer. */
function initMobileTabBar() {
    if (document.querySelector(".mobile-tabbar")) {
        return;
    }
    var navList = document.querySelector(".nav-list");
    if (!navList) {
        return;
    }
    var items = navList.querySelectorAll(".nav-list-item[data-nav]");
    if (!items.length) {
        return;
    }

    var bar = document.createElement("nav");
    bar.className = "mobile-tabbar";
    bar.setAttribute("aria-label", "Primary");

    items.forEach(function (item) {
        var navId = item.getAttribute("data-nav");
        if (!navId) {
            return;
        }
        // Profile/Settings stay in the avatar menu — not the bottom ribbon.
        if (navId === "settings" || navId === "profile") {
            return;
        }
        var link = item.querySelector(".nav-link");
        var labelEl = item.querySelector(".link-text");
        var svg = link ? link.querySelector("svg") : null;
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "mobile-tab-btn" + (item.classList.contains("selected") ? " selected" : "");
        btn.setAttribute("data-nav", navId);
        btn.setAttribute("aria-label", labelEl ? labelEl.textContent.trim() : navId);
        if (svg) {
            btn.appendChild(svg.cloneNode(true));
        }
        var caption = document.createElement("span");
        caption.textContent = labelEl ? labelEl.textContent.trim() : navId;
        btn.appendChild(caption);
        btn.addEventListener("click", function () {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set("tab", navId);
                if (navId !== "messages") {
                    url.searchParams.delete("ticket_id");
                }
                window.location.href = url.pathname + "?" + url.searchParams.toString();
            } catch (e) {
                window.location.href = "?tab=" + encodeURIComponent(navId);
            }
        });
        bar.appendChild(btn);
    });

    document.body.appendChild(bar);
}
function initSidebarCollapse() {
    const mainWrap = document.querySelector(".main-wrap");
    const mainHead = document.querySelector(".main-head");
    const showcaseToggler = document.querySelector(".showcase-toggler");
    if (!mainHead || !mainWrap) {
        return;
    }

    const mq = window.matchMedia("(max-width: 768px)");
    let desktopBound = false;

    function closeMobileNav() {
        mainWrap.classList.remove("nav-open");
        document.body.classList.remove("nav-open");
        var old = document.getElementById("zpgc-mobile-nav-toggle");
        if (old) {
            old.remove();
        }
        document.querySelectorAll(".mobile-nav-toggle, .mobile-nav-toggle-fixed").forEach(function (btn) {
            btn.remove();
        });
    }

    window.zpgcEnsureMobileNav = function () {
        closeMobileNav();
        return null;
    };

    function onDesktopEnter() {
        if (!mq.matches) {
            mainHead.classList.remove("active");
        }
    }

    function onDesktopLeave() {
        if (!mq.matches) {
            mainHead.classList.add("active");
        }
    }

    function onShowcaseToggle(event) {
        // Mobile uses the bottom ribbon only — ignore sidebar toggler.
        if (mq.matches) {
            event.preventDefault();
            return;
        }
        mainHead.classList.remove("active");
    }

    function bindDesktopHover() {
        if (desktopBound) {
            return;
        }
        mainHead.addEventListener("mouseenter", onDesktopEnter);
        mainHead.addEventListener("mouseleave", onDesktopLeave);
        desktopBound = true;
    }

    function unbindDesktopHover() {
        if (!desktopBound) {
            return;
        }
        mainHead.removeEventListener("mouseenter", onDesktopEnter);
        mainHead.removeEventListener("mouseleave", onDesktopLeave);
        desktopBound = false;
    }

    function applyMode() {
        if (mq.matches) {
            unbindDesktopHover();
            mainHead.classList.remove("active");
            closeMobileNav();
        } else {
            closeMobileNav();
            mainHead.classList.add("active");
            bindDesktopHover();
        }
    }

    if (showcaseToggler) {
        showcaseToggler.addEventListener("click", onShowcaseToggle);
    }

    applyMode();
    if (typeof mq.addEventListener === "function") {
        mq.addEventListener("change", applyMode);
    } else if (typeof mq.addListener === "function") {
        mq.addListener(applyMode);
    }
}
function initTabFromQuery() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get("tab") || document.body.getAttribute("data-page") || "";
    if (!tab) {
        try {
            tab = sessionStorage.getItem("zpgc_last_tab") || "";
        } catch (e) {
            tab = "";
        }
    }
    if (!tab) {
        return;
    }
    if (tab === "profile" || tab === "settings") {
        document.body.setAttribute("data-page", tab);
        document.querySelectorAll(".nav-list-item").forEach(function (navItem) {
            navItem.classList.remove("selected");
        });
        zpgcSyncUiUrl(tab, "");
        return;
    }
    var item = document.querySelector('.nav-list-item[data-nav="' + tab + '"]');
    if (item) {
        setSelectedNavItem(item);
    } else {
        zpgcSyncUiUrl(tab, params.get("ticket_id") || "");
    }
}

var POLL_MS = 15000;
var mailboxTimer = null;
var mailboxTicketId = 0;
var mailboxLastSig = "";

function mailboxWebBase() {
    var raw = (document.body && document.body.getAttribute("data-web-base")) || "";
    raw = String(raw).replace(/\/+$/, "");
    if (raw) {
        return raw;
    }
    // Fallback when data-web-base is missing: derive from /.../pages/*.php
    var path = String(window.location.pathname || "");
    var idx = path.lastIndexOf("/pages/");
    if (idx >= 0) {
        return path.substring(0, idx);
    }
    return "";
}

function mailboxApiUrl(path) {
    var base = mailboxWebBase();
    var rel = String(path || "").replace(/^\/+/, "");
    if (base) {
        return base + "/" + rel;
    }
    // Same-folder-relative from /pages/*.php (works on Azure root and local /CP2_V1.6).
    return "../" + rel;
}

function mailboxImageUrl(id, size) {
    // Default to full file (most reliable). Thumb is optional optimization.
    return mailboxApiUrl("logic/ticket_image.php")
        + "?id=" + encodeURIComponent(id)
        + "&size=" + encodeURIComponent(size || "full");
}

function mailboxPayloadSig(payload) {
    if (!payload || !payload.ok) {
        return "";
    }
    var t = Array.isArray(payload.timeline) ? payload.timeline : [];
    if (!t.length) {
        t = [].concat(payload.messages || [], payload.attachments || []);
    }
    return t.map(function (item) {
        return String(item.type || "") + ":" + String(item.id || "") + ":" + String(item.sort_ts || item.created_at || "");
    }).join("|");
}

function openMailboxZoom(fullSrc) {
    var old = document.getElementById("mailbox-zoom");
    if (old) {
        old.remove();
    }
    var layer = document.createElement("div");
    layer.id = "mailbox-zoom";
    layer.setAttribute("role", "dialog");
    layer.setAttribute("aria-label", "Zoomed image");
    layer.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;z-index:80;cursor:zoom-out;";
    var big = document.createElement("img");
    big.alt = "Zoomed image";
    big.decoding = "async";
    big.fetchPriority = "high";
    big.style.cssText = "max-width:92vw;max-height:92vh;border-radius:8px;background:#fff;";
    big.src = fullSrc;
    layer.appendChild(big);
    layer.addEventListener("click", function () {
        layer.remove();
    });
    document.body.appendChild(layer);
}

function mailboxShowMissingImage(img) {
    if (!img || !img.parentNode) {
        return;
    }
    var ph = document.createElement("div");
    ph.className = "ticket-shot-missing";
    ph.textContent = "Image unavailable";
    ph.title = "This file is no longer on the server. Re-attach the photo to restore it.";
    img.parentNode.replaceChild(ph, img);
}

function bindMailboxImage(img, thumbSrc, fullSrc) {
    if (!img) {
        return;
    }
    var primary = fullSrc || thumbSrc;
    img.classList.remove("is-loaded", "is-missing");
    img.onload = function () {
        img.classList.add("is-loaded");
        img.classList.remove("is-missing");
    };
    img.onerror = function () {
        var tried = img.getAttribute("data-tried") || "";
        if (tried === "" && thumbSrc && primary && thumbSrc !== primary) {
            img.setAttribute("data-tried", "alt");
            img.src = thumbSrc;
            return;
        }
        if (tried !== "fetch" && primary && window.fetch) {
            img.setAttribute("data-tried", "fetch");
            fetch(primary, { credentials: "same-origin", cache: "no-store" })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error("bad status");
                    }
                    return res.blob();
                })
                .then(function (blob) {
                    if (!blob || blob.size < 32) {
                        throw new Error("empty");
                    }
                    if (blob.type && blob.type.indexOf("image/") !== 0) {
                        throw new Error("not image");
                    }
                    img.src = URL.createObjectURL(blob);
                })
                .catch(function () {
                    mailboxShowMissingImage(img);
                });
            return;
        }
        mailboxShowMissingImage(img);
    };
    img.src = primary;
}

function mailboxFormatWhen(createdAt) {
    if (!createdAt) {
        return "";
    }
    var d = new Date(String(createdAt).replace(" ", "T"));
    if (isNaN(d.getTime())) {
        return String(createdAt);
    }
    var hh = String(d.getHours()).padStart(2, "0");
    var mm = String(d.getMinutes()).padStart(2, "0");
    return hh + ":" + mm;
}

function mailboxItemSortTs(item) {
    if (!item) {
        return 0;
    }
    if (typeof item.sort_ts === "number" && item.sort_ts > 0) {
        return item.sort_ts;
    }
    var parsed = Date.parse(String(item.created_at || "").replace(" ", "T"));
    return isNaN(parsed) ? 0 : Math.floor(parsed / 1000);
}

function mailboxBuildTimeline(payload) {
    var timeline = Array.isArray(payload.timeline) ? payload.timeline.slice() : [];
    if (!timeline.length) {
        (payload.messages || []).forEach(function (msg) {
            timeline.push(Object.assign({ type: "message" }, msg));
        });
        (payload.attachments || []).forEach(function (shot) {
            timeline.push(Object.assign({ type: "attachment" }, shot));
        });
    }
    // Always sort by send/upload time only — never group text vs images.
    timeline.sort(function (a, b) {
        var ta = mailboxItemSortTs(a);
        var tb = mailboxItemSortTs(b);
        if (ta !== tb) {
            return ta - tb;
        }
        return (Number(a.id) || 0) - (Number(b.id) || 0);
    });
    return timeline;
}

function renderMailboxMessages(payload) {
    var box = document.getElementById("mailbox-chat-messages");
    if (!box || !payload || !payload.ok) {
        return;
    }
    // Skip full DOM rebuild when nothing changed (keeps loaded images + cuts CPU).
    var sig = mailboxPayloadSig(payload);
    if (sig !== "" && sig === mailboxLastSig && box.querySelector(".mailbox-chat-thread")) {
        return;
    }
    mailboxLastSig = sig;
    box.innerHTML = "";
    var timeline = mailboxBuildTimeline(payload);
    if (!timeline.length) {
        box.innerHTML = '<div class="mailbox-chat-empty"><p class="mailbox-chat-empty-title">No messages yet</p><p class="mailbox-chat-empty-sub">Send the first message below.</p></div>';
        return;
    }
    // Thread sits at the bottom; items render oldest → newest by time sent.
    var thread = document.createElement("div");
    thread.className = "mailbox-chat-thread";
    timeline.forEach(function (item) {
        var wrap = document.createElement("div");
        wrap.className = "mailbox-msg " + (item.mine ? "sent" : "received");
        var bubble = document.createElement("div");
        var meta = document.createElement("span");
        meta.className = "mailbox-msg-time";
        var when = mailboxFormatWhen(item.created_at);
        if (item.type === "attachment") {
            // Same maroon/white chat bubble as text, with media padding inside.
            bubble.className = "mailbox-msg-bubble mailbox-msg-bubble--media";
            var img = document.createElement("img");
            var fullSrc = item.url || mailboxImageUrl(item.id, "full");
            var thumbSrc = item.thumb_url || mailboxImageUrl(item.id, "thumb");
            img.className = "ticket-shot";
            img.alt = "Attached image";
            img.loading = "eager";
            img.decoding = "async";
            img.setAttribute("data-full-src", fullSrc);
            img.addEventListener("click", function () {
                if (img.classList.contains("is-missing") || bubble.querySelector(".ticket-shot-missing")) {
                    return;
                }
                openMailboxZoom(img.getAttribute("data-full-src") || fullSrc);
            });
            bindMailboxImage(img, thumbSrc, fullSrc);
            bubble.appendChild(img);
            meta.textContent = (item.sender || "") + (when ? " · " + when : "");
        } else {
            bubble.className = "mailbox-msg-bubble";
            bubble.textContent = item.body || "";
            meta.textContent = (item.name || "") + (when ? " · " + when : "");
        }
        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        thread.appendChild(wrap);
    });
    box.appendChild(thread);
    box.scrollTop = box.scrollHeight;
}

function loadMailboxMessages() {
    if (!mailboxTicketId) {
        return;
    }
    fetch(mailboxApiUrl("logic/fetch_messages.php") + "?ticket_id=" + encodeURIComponent(mailboxTicketId), {
        credentials: "same-origin",
        cache: "no-store"
    })
        .then(function (res) { return res.json(); })
        .then(renderMailboxMessages)
        .catch(function () {
            var box = document.getElementById("mailbox-chat-messages");
            if (!box || box.querySelector(".mailbox-chat-error")) {
                return;
            }
            var err = document.createElement("p");
            err.className = "mailbox-chat-empty-sub mailbox-chat-error";
            err.textContent = "Couldn’t load messages. Check your connection, then reopen Mailbox.";
            box.appendChild(err);
        });
}

function startMailboxPoll() {
    if (mailboxTimer) {
        clearInterval(mailboxTimer);
        mailboxTimer = null;
    }

    if (document.body.getAttribute("data-page") !== "messages") {
        return;
    }
    // Lazy: only poll while Mailbox is the active tab and the page is visible.
    loadMailboxMessages();
    mailboxTimer = setInterval(function () {
        if (document.hidden) {
            return;
        }
        loadMailboxMessages();
    }, POLL_MS);
}

function setMailboxMobileChat(open) {
    var container = document.querySelector(".mailbox-container");
    if (!container) {
        return;
    }
    if (open) {
        container.classList.add("is-chat-open");
    } else {
        container.classList.remove("is-chat-open");
    }
}

function initMailbox() {
    var list = document.getElementById("mailbox-threads-list");
    var form = document.getElementById("mailbox-send-form");
    var hidden = document.getElementById("mailbox-ticket-id");
    var subjectEl = document.getElementById("mailbox-chat-subject");
    var backBtn = document.getElementById("mailbox-back-btn");
    if (!list || !hidden) {
        return;
    }
    mailboxTicketId = parseInt(hidden.value, 10) || 0;
    if (backBtn) {
        backBtn.addEventListener("click", function () {
            setMailboxMobileChat(false);
        });
    }
    var search = document.getElementById("mailbox-ticket-search");
    if (search) {
        search.addEventListener("input", function () {
            var q = search.value.trim().toLowerCase().replace("#", "");
            var matches = [];
            list.querySelectorAll(".mailbox-thread-item").forEach(function (item) {
                var id = (item.getAttribute("data-ticket-id") || "");
                var subject = (item.getAttribute("data-subject") || "").toLowerCase();
                var show = q === "" || id.indexOf(q) !== -1 || subject.indexOf(q) !== -1;
                item.hidden = !show;
                if (show) {
                    matches.push(item);
                }
            });
            if (matches.length === 1) {
                matches[0].click();
            }
        });
    }
    list.querySelectorAll(".mailbox-thread-item").forEach(function (btn) {
        btn.addEventListener("click", function () {
            list.querySelectorAll(".mailbox-thread-item").forEach(function (b) {
                b.classList.remove("active");
            });
            btn.classList.add("active");
            mailboxTicketId = parseInt(btn.getAttribute("data-ticket-id"), 10) || 0;
            mailboxLastSig = "";
            hidden.value = mailboxTicketId;
            var attachId = document.getElementById("mailbox-attach-ticket-id");
            if (attachId) {
                attachId.value = mailboxTicketId;
            }
            if (subjectEl) {
                subjectEl.textContent = btn.getAttribute("data-subject") || ("Ticket #" + mailboxTicketId);
            }
            var issueEl = document.getElementById("mailbox-chat-issue");
            if (issueEl) {
                var detail = btn.getAttribute("data-description") || "";
                issueEl.textContent = "#" + mailboxTicketId + (detail !== "" ? " · " + detail : "");
            }
            setMailboxMobileChat(true);
            loadMailboxMessages();
            startMailboxPoll();
            zpgcSyncUiUrl("messages", mailboxTicketId);
        });
    });
    if (form) {
        form.addEventListener("submit", function () {
            startMailboxPoll();
        });
    }
    var attachForm = document.getElementById("mailbox-attach-form");
    var attachFile = document.getElementById("mailbox-attach-file");
    if (attachForm && attachFile) {
        attachFile.addEventListener("change", function () {
            if (!attachFile.files || !attachFile.files.length) {
                return;
            }
            var tid = parseInt(
                (document.getElementById("mailbox-attach-ticket-id") || {}).value || mailboxTicketId,
                10
            ) || 0;
            if (tid <= 0) {
                attachFile.value = "";
                window.alert("Select a ticket from the list before attaching an image.");
                return;
            }
            var attachId = document.getElementById("mailbox-attach-ticket-id");
            if (attachId) {
                attachId.value = String(tid);
            }
            attachForm.submit();
        });
    }
    if (mailboxTicketId) {
        setMailboxMobileChat(true);
        zpgcSyncUiUrl("messages", mailboxTicketId);
    } else {
        setMailboxMobileChat(false);
        var firstThread = list.querySelector(".mailbox-thread-item:not([hidden])");
        if (firstThread && document.body.getAttribute("data-page") === "messages") {
            firstThread.click();
        }
    }
    // Defer mailbox network work until the Messages tab is shown.
    if (window.ZpgcLazy && typeof window.ZpgcLazy.whenTab === "function") {
        window.ZpgcLazy.whenTab("messages", function () {
            startMailboxPoll();
        });
    } else if (document.body.getAttribute("data-page") === "messages") {
        startMailboxPoll();
    }
    if (typeof MutationObserver !== "undefined") {
        var mailObs = new MutationObserver(function () {
            if (document.body.getAttribute("data-page") === "messages") {
                startMailboxPoll();
            } else if (mailboxTimer) {
                clearInterval(mailboxTimer);
                mailboxTimer = null;
            }
        });
        mailObs.observe(document.body, { attributes: true, attributeFilter: ["data-page"] });
    }
}

function initProfileEdits() {
    document.querySelectorAll("[data-profile-pencil]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var row = btn.closest("[data-profile-edit]");
            if (!row) {
                return;
            }
            var fields = row.querySelector(".profile-info-editfields");
            var open = row.classList.toggle("is-editing");
            if (fields) {
                fields.hidden = !open;
            }
            btn.setAttribute("aria-pressed", open ? "true" : "false");
            if (open && fields) {
                var input = fields.querySelector("input");
                if (input) {
                    input.focus();
                }
            }
        });
    });
}

function initProfileMenu() {
    document.querySelectorAll(".profile-menu").forEach(function (menu) {
        var btn = menu.querySelector(".profile-menu-btn");
        var drop = menu.querySelector(".profile-dropdown");
        if (!btn || !drop) {
            return;
        }
        btn.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            var willOpen = drop.hidden;
            document.querySelectorAll(".profile-dropdown").forEach(function (other) {
                other.hidden = true;
            });
            document.querySelectorAll(".profile-menu-btn").forEach(function (otherBtn) {
                otherBtn.setAttribute("aria-expanded", "false");
            });
            if (willOpen) {
                drop.hidden = false;
                btn.setAttribute("aria-expanded", "true");
            }
        });
    });
    document.addEventListener("click", function () {
        document.querySelectorAll(".profile-dropdown").forEach(function (drop) {
            drop.hidden = true;
        });
        document.querySelectorAll(".profile-menu-btn").forEach(function (btn) {
            btn.setAttribute("aria-expanded", "false");
        });
    });
}

function initPageSearchBars() {
    document.querySelectorAll(".page-content .search-bar, .main-content .search-bar").forEach(function (input) {
        if (input.id === "mailbox-ticket-search" || input.id === "perf-log-search") {
            return;
        }
        if (input.dataset.searchBound === "1") {
            return;
        }
        input.dataset.searchBound = "1";
        input.addEventListener("input", function () {
            var q = String(input.value || "").trim().toLowerCase();
            var page = input.closest(".page-content, [id^='page-'], .tab-panel") || document;
            var rows = page.querySelectorAll(".ticket-row, .mailbox-thread-item");
            if (!rows.length) {
                rows = document.querySelectorAll(
                    "body[data-page] .ticket-row, body[data-page] .mailbox-thread-item"
                );
            }
            rows.forEach(function (row) {
                if (row.classList.contains("ticket-row-filtered-out")) {
                    return;
                }
                var hay = (row.textContent || "").toLowerCase();
                var hide = q !== "" && hay.indexOf(q) === -1;
                row.classList.toggle("ticket-row-search-hidden", hide);
                if (hide) {
                    row.style.display = "none";
                } else if (row.style.display === "none" && !row.classList.contains("ticket-row-filtered-out")) {
                    row.style.display = "";
                }
            });
        });
    });
}

function initTicketSelectTones() {
    document.querySelectorAll("select.ticket-select-status, select.ticket-select-priority").forEach(function (el) {
        el.setAttribute("data-value", el.value || "");
        el.addEventListener("change", function () {
            el.setAttribute("data-value", el.value || "");
        });
    });
}

function zpgcFormatQueueDuration(seconds) {
    seconds = Math.max(0, Math.floor(seconds));
    var hours = Math.floor(seconds / 3600);
    var minutes = Math.floor((seconds % 3600) / 60);
    var secs = seconds % 60;
    function pad(n) {
        return n < 10 ? "0" + n : String(n);
    }
    return pad(hours) + ":" + pad(minutes) + ":" + pad(secs);
}

function initQueueTimers() {
    var nodes = document.querySelectorAll("[data-queue-start]");
    if (!nodes.length) {
        return;
    }
    function tick() {
        var now = Date.now() / 1000;
        nodes.forEach(function (el) {
            var start = parseInt(el.getAttribute("data-queue-start"), 10);
            if (!start) {
                return;
            }
            el.textContent = zpgcFormatQueueDuration(Math.max(0, now - start));
        });
    }
    tick();
    setInterval(tick, 1000);
}

document.addEventListener("DOMContentLoaded", function() {
    initTicketSelectTones();
    initQueueTimers();
    initNavClickSelection();
    initMobileTabBar();
    initSidebarCollapse();
    initTabFromQuery();
    // Only fall back to the marked/default nav when no tab was restored.
    if (!document.body.getAttribute("data-page")) {
        initNavSelection();
    }
    // Keep bottom tabs in sync with restored tab.
    var page = document.body.getAttribute("data-page");
    if (page) {
        document.querySelectorAll(".mobile-tab-btn[data-nav]").forEach(function (btn) {
            btn.classList.toggle("selected", btn.getAttribute("data-nav") === page);
        });
    }
    initProfileMenu();
    initProfileEdits();
    initMailbox();
    initPageSearchBars();
})
