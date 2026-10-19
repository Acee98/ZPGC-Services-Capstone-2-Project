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
            var idEl = document.getElementById("mailbox-chat-id");
            if (idEl) {
                idEl.textContent = mailboxTicketId > 0 ? "#" + mailboxTicketId : "";
            }
            var issueEl = document.getElementById("mailbox-chat-issue");
            if (issueEl) {
                issueEl.textContent = btn.getAttribute("data-description") || "";
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
        if (btn.dataset.bound === "1") {
            return;
        }
        btn.dataset.bound = "1";
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
    if (document.documentElement.getAttribute("data-profile-menu-bound") === "1") {
        return;
    }
    document.documentElement.setAttribute("data-profile-menu-bound", "1");
    document.addEventListener("click", function (event) {
        var btn = event.target.closest(".profile-menu-btn");
        var menu = btn ? btn.closest(".profile-menu") : null;
        var drop = menu ? menu.querySelector(".profile-dropdown") : null;
        document.querySelectorAll(".profile-dropdown").forEach(function (other) {
            if (other !== drop) {
                other.hidden = true;
            }
        });
        document.querySelectorAll(".profile-menu-btn").forEach(function (otherBtn) {
            if (otherBtn !== btn) {
                otherBtn.setAttribute("aria-expanded", "false");
            }
        });
        if (!btn || !drop) {
            return;
        }
        event.preventDefault();
        var willOpen = drop.hidden;
        drop.hidden = !willOpen;
        btn.setAttribute("aria-expanded", willOpen ? "true" : "false");
    });
}

function initPageSearchBars() {
    var placeholders = {
        dashboard: "Search tickets, ID, subject…",
        tickets: "Search tickets, ID, subject…",
        messages: "Search mailbox tickets…",
        performance: "Search resolved tickets…",
        utilities: "Search users by name or email…",
        profile: "Search profile fields…",
        settings: "Search settings…",
    };
    var tabRank = { tickets: 1, dashboard: 2, performance: 3, messages: 4, utilities: 5, profile: 6, settings: 7 };
    var active = -1;
    var lastHits = [];

    function ensureCss() {
        if (document.getElementById("zpgc-search-css")) {
            return;
        }
        var css = document.createElement("style");
        css.id = "zpgc-search-css";
        css.textContent = [
            ".search-bar-wrapper{position:relative}",
            ".zpgc-search-results{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:90;background:#fff;border:1px solid #eadfdf;border-radius:12px;box-shadow:0 12px 32px rgba(26,10,12,.16);max-height:min(380px,62vh);overflow:auto;padding:6px}",
            ".zpgc-search-hit{display:block;width:100%;text-align:left;border:0;background:transparent;border-radius:8px;padding:9px 12px;cursor:pointer}",
            ".zpgc-search-hit:hover,.zpgc-search-hit.is-active{background:#fdf0f0}",
            ".zpgc-search-hit-kicker{display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8a6a6d;margin-bottom:2px}",
            ".zpgc-search-hit-title{display:block;font-size:14px;font-weight:650;color:#1a1a1a;line-height:1.3}",
            ".zpgc-search-hit-snip{display:block;font-size:12px;color:#6b6b6b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
            ".zpgc-search-empty{padding:12px;font-size:13px;color:#6b6b6b}",
            ".zpgc-search-flash{box-shadow:inset 0 0 0 2px #610107 !important;background:#fff6f6 !important}",
            "html[data-theme=\"dark\"] .zpgc-search-results{background:#222228;border-color:rgba(255,255,255,.12)}",
            "html[data-theme=\"dark\"] .zpgc-search-hit:hover,html[data-theme=\"dark\"] .zpgc-search-hit.is-active{background:rgba(139,74,82,.35)}",
            "html[data-theme=\"dark\"] .zpgc-search-hit-title{color:#f2f2f7}",
            "html[data-theme=\"dark\"] .zpgc-search-hit-snip,html[data-theme=\"dark\"] .zpgc-search-empty{color:#c4c4d0}"
        ].join("");
        document.head.appendChild(css);
    }

    function pageIdFromNode(node) {
        var page = node && node.closest ? node.closest(".page-content") : null;
        var id = page && page.id ? page.id.replace(/^page-/, "") : "";
        return id || (document.body.getAttribute("data-page") || "");
    }

    function goTab(tab) {
        var item = document.querySelector('.nav-list-item[data-nav="' + tab + '"], .mobile-tab-btn[data-nav="' + tab + '"]');
        if (item && typeof setSelectedNavItem === "function") {
            setSelectedNavItem(item);
        }
    }

    function textOf(el, sel) {
        var node = el.querySelector(sel);
        return node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "";
    }

    function indexItems() {
        var items = [];
        document.querySelectorAll(".ticket-row").forEach(function (row) {
            if (row.classList.contains("tickets-empty-state")) {
                return;
            }
            var tab = pageIdFromNode(row);
            var id = row.getAttribute("data-ticket-id") || "";
            if (!id && window.ZpgcTables) {
                id = String(window.ZpgcTables.rowId(row) || "");
            }
            var subject = textOf(row, ".tickets-col-subject, .tcol-subject, .ucol-name")
                || textOf(row, "[class*='subject']");
            var desc = textOf(row, ".tickets-col-description, .tcol-description");
            var extra = textOf(row, ".ucol-email, .tickets-col-category, .tcol-category");
            var title = subject || ("#" + id);
            var kind = tab === "utilities" ? "user" : "ticket";
            if (tab === "utilities" && !subject) {
                title = textOf(row, ".ucol-name") || title;
            }
            items.push({
                el: row,
                tab: tab,
                kind: kind,
                id: id,
                title: title,
                snip: [id ? "#" + id : "", extra, desc].filter(Boolean).join(" · "),
                hay: (id + " " + title + " " + desc + " " + extra + " " + (row.getAttribute("data-perf-search") || "") + " " + (row.textContent || "")).toLowerCase()
            });
        });
        document.querySelectorAll(".mailbox-thread-item").forEach(function (row) {
            var id = row.getAttribute("data-ticket-id") || "";
            var title = row.getAttribute("data-subject") || textOf(row, ".mailbox-thread-subject");
            var desc = row.getAttribute("data-description") || "";
            items.push({
                el: row,
                tab: "messages",
                kind: "message",
                id: id,
                title: title || ("Ticket #" + id),
                snip: id ? "Mailbox · #" + id : "Mailbox",
                hay: (id + " " + title + " " + desc).toLowerCase()
            });
        });
        document.querySelectorAll(".profile-info-row, .profile-pref-row, .settings-card").forEach(function (row) {
            var tab = pageIdFromNode(row) || "profile";
            var title = textOf(row, ".profile-info-label, .profile-pref-label, h2, h3") || "Profile";
            var snip = textOf(row, ".profile-info-value, .profile-pref-select") || "";
            items.push({
                el: row,
                tab: tab,
                kind: "page",
                id: "",
                title: title,
                snip: snip,
                hay: (title + " " + snip + " " + (row.textContent || "")).toLowerCase()
            });
        });
        return items;
    }

    function scoreHit(q, item) {
        var hay = item.hay;
        var title = String(item.title || "").toLowerCase();
        var id = String(item.id || "");
        var digits = q.replace(/^#/, "");
        if (id && (q === "#" + id || q === id || digits === id)) {
            return 100;
        }
        if (title === q) {
            return 95;
        }
        if (title.indexOf(q) === 0) {
            return 88;
        }
        if (title.indexOf(q) !== -1) {
            return 76;
        }
        var tokens = q.split(/\s+/).filter(Boolean);
        var all = tokens.every(function (t) { return hay.indexOf(t) !== -1; });
        if (!all) {
            return 0;
        }
        var pos = hay.indexOf(q);
        return pos === -1 ? 48 : Math.max(40, 70 - Math.min(pos, 30));
    }

    function uniqueHits(hits) {
        var seen = {};
        var out = [];
        hits.sort(function (a, b) {
            if (b.score !== a.score) {
                return b.score - a.score;
            }
            return (tabRank[a.tab] || 9) - (tabRank[b.tab] || 9);
        });
        hits.forEach(function (hit) {
            var key = hit.kind + ":" + (hit.id || hit.title) + ":" + hit.tab;
            if (hit.id) {
                key = hit.kind + ":" + hit.id;
            }
            if (seen[key]) {
                return;
            }
            seen[key] = 1;
            out.push(hit);
        });
        return out.slice(0, 8);
    }

    function resultsBox(input) {
        var wrap = input.closest(".search-bar-wrapper") || input.parentElement;
        var box = wrap.querySelector(".zpgc-search-results");
        if (!box) {
            box = document.createElement("div");
            box.className = "zpgc-search-results";
            box.hidden = true;
            box.setAttribute("role", "listbox");
            wrap.appendChild(box);
        }
        return box;
    }

    function hideBoxes() {
        document.querySelectorAll(".zpgc-search-results").forEach(function (box) {
            box.hidden = true;
            box.innerHTML = "";
        });
        active = -1;
        lastHits = [];
    }

    function renderHits(input, hits, q) {
        var box = resultsBox(input);
        box.innerHTML = "";
        if (!q) {
            box.hidden = true;
            lastHits = [];
            return;
        }
        if (!hits.length) {
            box.hidden = false;
            box.innerHTML = '<div class="zpgc-search-empty">No matches for "' + q.replace(/[<>&"]/g, "") + '".</div>';
            lastHits = [];
            return;
        }
        lastHits = hits;
        active = 0;
        hits.forEach(function (hit, i) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "zpgc-search-hit" + (i === 0 ? " is-active" : "");
            btn.setAttribute("role", "option");
            var kicker = hit.kind === "user" ? "User" : (hit.kind === "message" ? "Mailbox" : (hit.kind === "page" ? hit.tab : "Ticket"));
            btn.innerHTML = '<span class="zpgc-search-hit-kicker">' + kicker + (hit.id ? " · #" + hit.id : "") + "</span>"
                + '<span class="zpgc-search-hit-title"></span>'
                + '<span class="zpgc-search-hit-snip"></span>';
            btn.querySelector(".zpgc-search-hit-title").textContent = hit.title;
            btn.querySelector(".zpgc-search-hit-snip").textContent = hit.snip || ("Open in " + hit.tab);
            btn.addEventListener("mousedown", function (ev) {
                ev.preventDefault();
                goToHit(hit);
            });
            box.appendChild(btn);
        });
        box.hidden = false;
    }

    function markActive(input) {
        var box = resultsBox(input);
        var btns = box.querySelectorAll(".zpgc-search-hit");
        btns.forEach(function (btn, i) {
            btn.classList.toggle("is-active", i === active);
        });
        if (btns[active]) {
            btns[active].scrollIntoView({ block: "nearest" });
        }
    }

    function goToHit(hit) {
        if (!hit || !hit.el) {
            return;
        }
        hideBoxes();
        goTab(hit.tab);
        window.setTimeout(function () {
            if (hit.kind === "message") {
                hit.el.click();
                return;
            }
            if (window.ZpgcTables && typeof window.ZpgcTables.revealRow === "function") {
                window.ZpgcTables.revealRow(hit.el);
            }
            try {
                hit.el.scrollIntoView({ block: "center", behavior: "smooth" });
            } catch (err) {
                hit.el.scrollIntoView();
            }
            hit.el.classList.add("zpgc-search-flash");
            window.setTimeout(function () {
                hit.el.classList.remove("zpgc-search-flash");
            }, 2200);
            var view = hit.el.querySelector("[data-ticket-quick-view]");
            if (view && hit.kind === "ticket") {
                view.click();
            }
        }, 60);
    }

    function runSearch(input) {
        var q = String(input.value || "").trim().toLowerCase();
        if (q === "") {
            hideBoxes();
            return;
        }
        var scored = [];
        indexItems().forEach(function (item) {
            var s = scoreHit(q, item);
            if (s > 0) {
                item.score = s;
                scored.push(item);
            }
        });
        renderHits(input, uniqueHits(scored), q);
    }

    ensureCss();
    document.querySelectorAll(".page-content .search-bar, .main-content .search-bar").forEach(function (input) {
        if (input.id === "mailbox-ticket-search" || input.id === "perf-log-search") {
            return;
        }
        var page = pageIdFromNode(input);
        if (placeholders[page]) {
            input.placeholder = placeholders[page];
            input.setAttribute("aria-label", placeholders[page]);
        }
        input.setAttribute("autocomplete", "off");
        if (input.dataset.searchBound === "1") {
            return;
        }
        input.dataset.searchBound = "1";
        input.addEventListener("input", function () {
            runSearch(input);
        });
        input.addEventListener("focus", function () {
            if (String(input.value || "").trim()) {
                runSearch(input);
            }
        });
        input.addEventListener("keydown", function (ev) {
            if (ev.key === "ArrowDown") {
                ev.preventDefault();
                if (lastHits.length) {
                    active = Math.min(lastHits.length - 1, active + 1);
                    markActive(input);
                }
            } else if (ev.key === "ArrowUp") {
                ev.preventDefault();
                if (lastHits.length) {
                    active = Math.max(0, active - 1);
                    markActive(input);
                }
            } else if (ev.key === "Enter") {
                ev.preventDefault();
                if (lastHits[active]) {
                    goToHit(lastHits[active]);
                } else {
                    runSearch(input);
                    if (lastHits[0]) {
                        goToHit(lastHits[0]);
                    }
                }
            } else if (ev.key === "Escape") {
                hideBoxes();
                input.blur();
            }
        });
    });
    document.addEventListener("click", function (ev) {
        if (!ev.target.closest(".search-bar-wrapper")) {
            hideBoxes();
        }
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
    window.zpgcInitProfile = initProfileEdits;
    window.zpgcInitProfileMenu = initProfileMenu;
    window.zpgcInitSearch = initPageSearchBars;
    initMailbox();
    initPageSearchBars();
})
