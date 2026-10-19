(function () {
    var INTERVAL_MS = 10000;
    var timer = null;
    var notice;

    function ticketsTab() {
        var page = document.body.getAttribute('data-page') || '';
        return page === 'tickets' || page === 'dashboard';
    }

    function ensureNotice() {
        if (notice) {
            return notice;
        }
        notice = document.createElement('div');
        notice.className = 'live-ticket-notice';
        notice.hidden = true;
        notice.innerHTML = '<span>Ticket statuses changed.</span> <button type="button" class="live-ticket-reload">Reload list</button>';
        notice.querySelector('button').addEventListener('click', function () {
            window.location.reload();
        });
        var host = document.querySelector('#page-tickets .tickets-toolbar, #page-dashboard .head, .main-wrap');
        if (host) {
            host.parentNode.insertBefore(notice, host.nextSibling);
        } else {
            document.body.appendChild(notice);
        }
        return notice;
    }

    function findRow(id) {
        var hit = document.querySelector('[data-ticket-id="' + id + '"]');
        if (hit) {
            return hit;
        }
        var rows = document.querySelectorAll('.ticket-row');
        for (var i = 0; i < rows.length; i++) {
            if (window.ZpgcTables && ZpgcTables.rowId(rows[i]) === id) {
                return rows[i];
            }
        }
        return null;
    }

    function paintRow(row, ticket) {
        var prev = (row.getAttribute('data-status') || '').toLowerCase();
        var next = String(ticket.status || '').toLowerCase();
        if (prev === next) {
            return false;
        }
        row.setAttribute('data-status', next);
        var select = row.querySelector('select[name="status"]');
        if (select && document.activeElement !== select) {
            select.value = next;
            select.setAttribute('data-value', next);
        }
        var badge = row.querySelector('.status-badge');
        if (badge) {
            badge.className = 'status-badge ' + (ticket.status_class || '');
            badge.textContent = ticket.status_label || next;
        }
        if (next === 'awaiting_confirmation' && !row.querySelector('.confirm-form, .btn-confirm-solved')) {
            ensureNotice().hidden = false;
        }
        return true;
    }

    function applyPayload(data) {
        if (!data || !data.ok || !data.tickets) {
            return;
        }
        var changed = false;
        var seen = {};
        data.tickets.forEach(function (t) {
            seen[t.id] = true;
            var row = findRow(t.id);
            if (row && paintRow(row, t)) {
                changed = true;
            }
        });
        if (data.counts) {
            Object.keys(data.counts).forEach(function (key) {
                var card = document.querySelector('.status-card[data-status="' + key + '"] .status-card-count, .status-card.status-' + key + ' .status-count');
                if (card) {
                    card.textContent = String(data.counts[key]);
                }
            });
        }
        if (changed && window.ZpgcTables) {
            ZpgcTables.refresh();
        }
    }

    function poll() {
        if (document.visibilityState === 'hidden' || !ticketsTab()) {
            return;
        }
        fetch('../logic/ticket_live.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(applyPayload)
            .catch(function () {});
    }

    function start() {
        if (timer) {
            return;
        }
        poll();
        timer = window.setInterval(poll, INTERVAL_MS);
    }

    function stop() {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            stop();
        } else {
            start();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
