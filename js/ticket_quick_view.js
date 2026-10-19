(function () {
    var STYLE_ID = 'zpgc-ticket-qv-css';

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ensureStyles() {
        var css = document.getElementById(STYLE_ID);
        if (!css) {
            css = document.createElement('style');
            css.id = STYLE_ID;
            document.head.appendChild(css);
        }
        css.textContent = [
            '.ticket-qv{position:fixed;inset:0;z-index:120;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(17,10,12,.58);backdrop-filter:blur(6px);pointer-events:auto}',
            '.ticket-qv[hidden]{display:none!important;pointer-events:none!important;visibility:hidden!important}',
            '.ticket-qv-card{width:min(560px,100%);max-height:88vh;overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 64px rgba(26,10,12,.28);position:relative}',
            '.ticket-qv-head{background:#610107;color:#fff;padding:22px 24px 20px;position:relative;border-bottom:1px solid #4a0106}',
            '.ticket-qv-kicker{margin:0 48px 10px 0;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.78)}',
            '.ticket-qv-id{margin:0 0 6px;font-size:13px;font-weight:700;letter-spacing:.04em;color:#f3d27a}',
            '.ticket-qv-card h2,.ticket-qv-head h2{margin:0 40px 14px 0;font-size:22px;line-height:1.25;font-weight:800;color:#fff}',
            '.ticket-qv-badges{display:flex;flex-wrap:wrap;gap:8px}',
            '.ticket-qv-rating{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#15803D;color:#fff}',
            '.ticket-qv-close{position:absolute;top:16px;right:16px;width:36px;height:36px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(255,255,255,.12);color:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}',
            '.ticket-qv-close:hover{background:rgba(255,255,255,.24)}',
            '.ticket-qv-body{padding:20px 24px 24px;display:flex;flex-direction:column;gap:16px}',
            '.ticket-qv-block{background:#faf6f6;border:1px solid #efe4e5;border-radius:12px;padding:14px 16px}',
            '.ticket-qv-block h3,.ticket-qv-fact-label{margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8a6a6d}',
            '.ticket-qv-desc{margin:0;font-size:14px;line-height:1.55;color:#1a1a1a;white-space:pre-wrap;word-break:break-word}',
            '.ticket-qv-facts{display:grid;grid-template-columns:1fr 1fr;gap:10px}',
            '.ticket-qv-fact{background:#fff;border:1px solid #eee4e5;border-radius:12px;padding:12px 14px;min-width:0}',
            '.ticket-qv-fact--wide{grid-column:1/-1}',
            '.ticket-qv-fact-value{display:block;font-size:14px;font-weight:650;line-height:1.45;color:#1a1a1a;word-break:break-word}',
            '@media (max-width:768px){.ticket-qv{padding:10px;align-items:flex-end}.ticket-qv-card{width:100%;max-height:min(92dvh,100%);border-radius:16px 16px 0 0}.ticket-qv-facts{grid-template-columns:1fr}.ticket-qv-head,.ticket-qv-body{padding-left:16px;padding-right:16px}}',
            'html[data-theme="dark"] .ticket-qv-card{background:#222228}',
            'html[data-theme="dark"] .ticket-qv-block{background:#2a2a32;border-color:rgba(255,255,255,.08)}',
            'html[data-theme="dark"] .ticket-qv-fact{background:#1c1c22;border-color:rgba(255,255,255,.08)}',
            'html[data-theme="dark"] .ticket-qv-desc,html[data-theme="dark"] .ticket-qv-fact-value{color:#f2f2f7}',
            'html[data-theme="dark"] .ticket-qv-block h3,html[data-theme="dark"] .ticket-qv-fact-label{color:#c4c4d0}'
        ].join('');
    }

    function modal() {
        ensureStyles();
        var el = document.getElementById('ticket-quick-view');
        if (el && !el.querySelector('.ticket-qv-body')) {
            el.remove();
            el = null;
        }
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = 'ticket-quick-view';
        el.className = 'ticket-qv';
        el.hidden = true;
        el.innerHTML = '<div class="ticket-qv-card" role="dialog" aria-modal="true" aria-labelledby="ticket-qv-title">'
            + '<header class="ticket-qv-head">'
            + '<p class="ticket-qv-kicker">Ticket preview</p>'
            + '<p class="ticket-qv-id"></p>'
            + '<h2 id="ticket-qv-title">Ticket</h2>'
            + '<div class="ticket-qv-badges"></div>'
            + '<button type="button" class="ticket-qv-close" aria-label="Close">'
            + '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12 5.7 16.89a1 1 0 1 0 1.41 1.41L12 13.41l4.89 4.89a1 1 0 0 0 1.41-1.41L13.41 12l4.89-4.89a1 1 0 0 0 0-1.4Z"/></svg>'
            + '</button>'
            + '</header>'
            + '<div class="ticket-qv-body">'
            + '<section class="ticket-qv-block"><h3>Description</h3><p class="ticket-qv-desc"></p></section>'
            + '<div class="ticket-qv-facts"></div>'
            + '</div>'
            + '</div>';
        document.body.appendChild(el);
        function closeQv() {
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
            el.style.pointerEvents = 'none';
        }
        el.addEventListener('click', function (ev) {
            if (ev.target === el || ev.target.closest('.ticket-qv-close')) {
                closeQv();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !el.hidden) {
                closeQv();
            }
        });
        return el;
    }

    function fact(label, value, wide) {
        if (!value) {
            return '';
        }
        return '<div class="ticket-qv-fact' + (wide ? ' ticket-qv-fact--wide' : '') + '">'
            + '<span class="ticket-qv-fact-label">' + esc(label) + '</span>'
            + '<span class="ticket-qv-fact-value">' + esc(value) + '</span>'
            + '</div>';
    }

    function showFromButton(btn) {
        var box = modal();
        var id = btn.getAttribute('data-qv-id') || '';
        var subject = btn.getAttribute('data-qv-subject') || ('Ticket #' + id);
        var kicker = box.querySelector('.ticket-qv-kicker');
        if (kicker) {
            kicker.textContent = id ? ('Ticket #' + id) : 'Ticket preview';
        }
        box.querySelector('.ticket-qv-id').textContent = id ? ('#' + id) : '';
        box.querySelector('#ticket-qv-title').textContent = subject;
        var statusLabel = btn.getAttribute('data-qv-status') || '—';
        var statusClass = btn.getAttribute('data-qv-status-class') || '';
        var priLabel = btn.getAttribute('data-qv-priority') || 'None';
        var priClass = btn.getAttribute('data-qv-priority-class') || 'undefined';
        var rating = btn.getAttribute('data-qv-satisfaction') || '';
        var badges = '<span class="status-badge ' + esc(statusClass) + '">' + esc(statusLabel) + '</span>'
            + '<span class="priority-badge ' + esc(priClass) + '">' + esc(priLabel) + '</span>';
        if (rating) {
            badges += '<span class="ticket-qv-rating">' + esc(rating) + '</span>';
        }
        box.querySelector('.ticket-qv-badges').innerHTML = badges;
        box.querySelector('.ticket-qv-desc').textContent = btn.getAttribute('data-qv-description') || '—';
        box.querySelector('.ticket-qv-facts').innerHTML = [
            fact('Category', btn.getAttribute('data-qv-category') || '—'),
            fact('Assigned to', btn.getAttribute('data-qv-assigned')),
            fact('Registered', btn.getAttribute('data-qv-registered')),
            fact('Rating', rating)
        ].join('');
        box.hidden = false;
        box.removeAttribute('aria-hidden');
        box.style.pointerEvents = '';
        var closeBtn = box.querySelector('.ticket-qv-close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-ticket-quick-view]');
        if (!btn) {
            return;
        }
        ev.preventDefault();
        showFromButton(btn);
    });
})();
