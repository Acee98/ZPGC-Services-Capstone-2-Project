(function () {
    var SKIP = /ticket_mngmnt\.php|ticket_attachment_mngmnt\.php|user_mngmnt\.php|logout\.php|techn_apply_mngmnt\.php|capacity_mngmnt\.php|capacity_save_mngmnt\.php/;
    var STYLE_ID = 'zpgc-ajax-toast-css';
    var busy = false;

    function ensureToastCss() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var css = document.createElement('style');
        css.id = STYLE_ID;
        css.textContent = [
            '.zpgc-toast-host{position:fixed;right:20px;bottom:20px;z-index:140;display:flex;flex-direction:column;gap:8px;pointer-events:none}',
            '.zpgc-toast{pointer-events:auto;min-width:220px;max-width:min(420px,calc(100vw - 40px));padding:12px 16px;border-radius:12px;background:#1a1a1a;color:#fff;font-size:13px;line-height:1.4;box-shadow:0 12px 32px rgba(0,0,0,.28);opacity:0;transform:translateY(8px);transition:opacity .18s ease,transform .18s ease}',
            '.zpgc-toast.is-in{opacity:1;transform:none}',
            '.zpgc-toast.is-error{background:#7f1d1d}',
            '.zpgc-toast.is-ok{background:#14532d}',
            '.zpgc-dlg{position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(26,10,12,.55)}',
            '.zpgc-dlg[hidden]{display:none!important;pointer-events:none!important;visibility:hidden!important}',
            '.zpgc-dlg-card{width:min(440px,100%);background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 48px rgba(26,10,12,.28);max-height:min(88vh,640px);display:flex;flex-direction:column}',
            '.zpgc-dlg-head{background:#610107;color:#fff;padding:16px 20px;position:relative;flex:0 0 auto}',
            '.zpgc-dlg-kicker{margin:0 44px 6px 0;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.82)}',
            '.zpgc-dlg-head h2{margin:0 44px 0 0;font-size:20px;line-height:1.3;font-weight:750;color:#fff}',
            '.zpgc-dlg-close{position:absolute;top:12px;right:12px;width:34px;height:34px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:transparent;color:#fff;font-size:20px;line-height:1;cursor:pointer}',
            '.zpgc-dlg-close:hover{background:rgba(255,255,255,.16)}',
            '.zpgc-dlg-body{padding:18px 20px 20px;display:flex;flex-direction:column;gap:12px;overflow:auto;background:#fff}',
            '.zpgc-dlg-copy{margin:0;font-size:14px;line-height:1.55;color:#2a2a2a}',
            '.zpgc-dlg-note{margin:0;padding:10px 12px;background:#faf6f6;border:1px solid #efe4e5;border-radius:10px;font-size:13px;line-height:1.5;color:#5a4548}',
            '.zpgc-dlg-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}',
            '.zpgc-dlg-ok{background:#610107;color:#fff;border:0;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer}',
            '.zpgc-dlg-cancel{background:#fff;color:#4a3033;border:1px solid #e4d5d6;border-radius:10px;padding:10px 16px;font-weight:650;cursor:pointer}',
            '.zpgc-dlg-ok:hover{background:#4a0106}',
            '.zpgc-dlg-field{display:flex;flex-direction:column;gap:6px}',
            '.zpgc-dlg-field label{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8a6a6d}',
            '.zpgc-dlg-field input{height:44px;border:1px solid #e4d5d6;border-radius:10px;padding:0 12px;font-size:20px;letter-spacing:.28em;text-align:center}',
            'html[data-theme="dark"] .zpgc-dlg-card,html[data-theme="dark"] .zpgc-dlg-body{background:#222228}',
            'html[data-theme="dark"] .zpgc-dlg-copy{color:#f2f2f7}',
            'html[data-theme="dark"] .zpgc-dlg-note{background:#2a2a32;border-color:rgba(255,255,255,.08);color:#d7d7e0}',
            'html[data-theme="dark"] .zpgc-dlg-cancel{background:#2a2a32;color:#f2f2f7;border-color:rgba(255,255,255,.14)}',
            'html[data-theme="dark"] .zpgc-dlg-field input{background:#1c1c22;color:#f2f2f7;border-color:rgba(255,255,255,.14)}',
        ].join('');
        document.head.appendChild(css);
    }

    function dialogHost() {
        ensureToastCss();
        var el = document.getElementById('zpgc-app-dialog');
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = 'zpgc-app-dialog';
        el.className = 'zpgc-dlg';
        el.hidden = true;
        el.innerHTML = '<div class="zpgc-dlg-card" role="dialog" aria-modal="true" aria-labelledby="zpgc-dlg-title">'
            + '<header class="zpgc-dlg-head">'
            + '<p class="zpgc-dlg-kicker">ZPGC Services</p>'
            + '<h2 id="zpgc-dlg-title">Confirm</h2>'
            + '<button type="button" class="zpgc-dlg-close" data-dlg="no" aria-label="Close">×</button>'
            + '</header>'
            + '<div class="zpgc-dlg-body">'
            + '<p class="zpgc-dlg-copy" id="zpgc-dlg-copy"></p>'
            + '<div class="zpgc-dlg-actions">'
            + '<button type="button" class="zpgc-dlg-ok" data-dlg="yes">Continue</button>'
            + '<button type="button" class="zpgc-dlg-cancel" data-dlg="no">Cancel</button>'
            + '</div></div></div>';
        document.body.appendChild(el);
        return el;
    }

    function zpgcConfirm(opts) {
        opts = opts && typeof opts === 'object' ? opts : { message: String(opts || '') };
        return new Promise(function (resolve) {
            var el = dialogHost();
            var title = el.querySelector('#zpgc-dlg-title');
            var copy = el.querySelector('#zpgc-dlg-copy');
            var yes = el.querySelector('[data-dlg="yes"]');
            var noBtns = el.querySelectorAll('[data-dlg="no"]');
            if (title) {
                title.textContent = opts.title || 'Please confirm';
            }
            if (copy) {
                copy.textContent = opts.message || '';
            }
            if (yes) {
                yes.textContent = opts.okLabel || 'Continue';
                yes.style.display = '';
            }
            noBtns.forEach(function (btn) {
                if (btn.classList.contains('zpgc-dlg-cancel')) {
                    btn.textContent = opts.cancelLabel || 'Cancel';
                    btn.style.display = opts.alert ? 'none' : '';
                }
            });
            function finish(ok) {
                el.hidden = true;
                el.setAttribute('aria-hidden', 'true');
                el.style.pointerEvents = 'none';
                el.removeEventListener('click', onClick);
                document.removeEventListener('keydown', onKey);
                resolve(!!ok);
            }
            function onClick(ev) {
                if (ev.target === el) {
                    finish(false);
                    return;
                }
                var pick = ev.target.closest('[data-dlg]');
                if (!pick) {
                    return;
                }
                finish(pick.getAttribute('data-dlg') === 'yes');
            }
            function onKey(ev) {
                if (ev.key === 'Escape') {
                    finish(false);
                } else if (ev.key === 'Enter') {
                    finish(true);
                }
            }
            el.addEventListener('click', onClick);
            document.addEventListener('keydown', onKey);
            el.hidden = false;
            el.removeAttribute('aria-hidden');
            el.style.pointerEvents = '';
            if (yes) {
                yes.focus();
            }
        });
    }

    window.zpgcConfirm = zpgcConfirm;
    window.zpgcAlert = function (opts) {
        if (typeof opts === 'string') {
            opts = { message: opts };
        }
        opts = opts || {};
        opts.alert = true;
        opts.title = opts.title || 'Notice';
        opts.okLabel = opts.okLabel || 'OK';
        return zpgcConfirm(opts);
    };

    function toast(message, kind) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }
        ensureToastCss();
        var host = document.getElementById('zpgc-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'zpgc-toast-host';
            host.className = 'zpgc-toast-host';
            document.body.appendChild(host);
        }
        var el = document.createElement('div');
        el.className = 'zpgc-toast ' + (kind === 'error' ? 'is-error' : 'is-ok');
        el.setAttribute('role', 'status');
        el.textContent = text;
        host.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('is-in');
        });
        setTimeout(function () {
            el.classList.remove('is-in');
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 220);
        }, 4200);
    }

    function logicAction(url) {
        return /(?:^|\/)logic\//.test(String(url || ''));
    }

    function tabFromRedirect(url) {
        try {
            var parsed = new URL(url, window.location.href);
            return parsed.searchParams.get('tab') || document.body.getAttribute('data-page') || '';
        } catch (err) {
            return document.body.getAttribute('data-page') || '';
        }
    }

    function findUserRow(id) {
        var hit = document.querySelector('.ticket-row[data-user-id="' + id + '"]');
        if (hit) {
            return hit;
        }
        var rows = document.querySelectorAll('#utilities-users-body .ticket-row, .ticket-row');
        for (var i = 0; i < rows.length; i++) {
            var cell = rows[i].querySelector('.ucol-id');
            if (!cell) {
                continue;
            }
            var match = String(cell.textContent || '').match(/(\d+)/);
            if (match && parseInt(match[1], 10) === id) {
                return rows[i];
            }
        }
        return null;
    }

    function patchSetStatus(data) {
        var id = parseInt(data.id, 10) || 0;
        if (id <= 0) {
            return false;
        }
        var row = findUserRow(id);
        if (!row) {
            return false;
        }
        var status = data.status === 'inactive' ? 'inactive' : 'active';
        row.setAttribute('data-status', status);
        var badge = row.querySelector('.ucol-status .status-badge');
        if (badge) {
            badge.classList.toggle('active-account', status === 'active');
            badge.classList.toggle('inactive-account', status !== 'active');
            badge.textContent = data.status_label || (status === 'active' ? 'Active' : 'Pending');
        }
        var statusInput = row.querySelector('input[name="status"]');
        if (statusInput) {
            statusInput.value = data.next_status || (status === 'active' ? 'inactive' : 'active');
            var form = statusInput.form;
            var btn = form ? form.querySelector('[name="set_status"]') : null;
            if (btn) {
                var nextIcon = data.next_icon || (status === 'active' ? 'deactivate' : 'activate');
                var nextLabel = data.next_label || (status === 'active' ? 'Deactivate' : 'Activate');
                btn.setAttribute('data-icon', nextIcon);
                btn.setAttribute('aria-label', nextLabel + ' account');
                btn.setAttribute('title', nextLabel);
                var fallback = btn.querySelector('.ticket-icon-fallback');
                if (data.icon_html) {
                    btn.innerHTML = data.icon_html + '<span class="ticket-icon-fallback">' + nextLabel + '</span>';
                } else if (fallback) {
                    fallback.textContent = nextLabel;
                }
            }
        }
        if (window.ZpgcTables && typeof window.ZpgcTables.refresh === 'function') {
            window.ZpgcTables.refresh();
        }
        if (typeof window.applyUtilitiesFilter === 'function') {
            window.applyUtilitiesFilter(window.currentUtilitiesFilter || 'all');
        }
        return true;
    }

    function patchDeleteUser(data) {
        var id = parseInt(data.id, 10) || 0;
        var row = findUserRow(id);
        if (!row) {
            return false;
        }
        row.parentNode.removeChild(row);
        if (window.ZpgcTables && typeof window.ZpgcTables.refresh === 'function') {
            window.ZpgcTables.refresh();
        }
        return true;
    }

    function closeBlockingLayers() {
        document.querySelectorAll('.ticket-qv, .zpgc-dlg').forEach(function (el) {
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
            el.style.pointerEvents = 'none';
        });
        document.querySelectorAll('.modal-backdrop, .edit-user-modal, .add-user-modal').forEach(function (el) {
            el.hidden = true;
        });
    }

    function syncSurveyOverlay(doc) {
        document.querySelectorAll('.survey-overlay').forEach(function (el) {
            el.remove();
        });
        var next = doc && doc.querySelector('.survey-overlay');
        if (!next) {
            return;
        }
        document.body.appendChild(document.importNode(next, true));
        var first = document.querySelector('.survey-overlay input[name="satisfaction"]');
        if (first) {
            first.focus();
        }
    }

    function afterSwap(section) {
        if (window.ZpgcTables && typeof window.ZpgcTables.rebind === 'function') {
            window.ZpgcTables.rebind(section);
        }
        if (typeof window.zpgcBindUtilitiesSpecialty === 'function') {
            window.zpgcBindUtilitiesSpecialty();
        }
        if (typeof window.zpgcInitProfile === 'function') {
            window.zpgcInitProfile();
        }
        if (typeof window.zpgcInitProfileMenu === 'function') {
            window.zpgcInitProfileMenu();
        }
        if (typeof window.zpgcInitSearch === 'function') {
            window.zpgcInitSearch();
        }
    }

    function consumeFlash(tab) {
        var url = new URL(window.location.href);
        if (tab) {
            url.searchParams.set('tab', tab);
        }
        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(function () {});
    }

    function softRefresh(tab) {
        var id = 'page-' + tab;
        var el = document.getElementById(id);
        if (!el) {
            window.location.reload();
            return;
        }
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) {
            return res.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var next = doc.getElementById(id);
            if (!next) {
                window.location.reload();
                return;
            }
            el.replaceWith(next);
            afterSwap(document.getElementById(id));
            syncSurveyOverlay(doc);
        }).catch(function () {
            window.location.reload();
        });
    }

    function themeCookiePath() {
        var base = (document.body && document.body.getAttribute('data-web-base')) || '';
        if (!base || base === '/') {
            return '/';
        }
        return base.charAt(base.length - 1) === '/' ? base : base + '/';
    }

    function persistThemeCookie(theme) {
        var path = themeCookiePath();
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        if (theme === 'auto') {
            document.cookie = 'zpgc_theme=; Max-Age=0; path=' + path + '; SameSite=Lax' + secure;
            if (path !== '/') {
                document.cookie = 'zpgc_theme=; Max-Age=0; path=/; SameSite=Lax' + secure;
            }
            return;
        }
        document.cookie = 'zpgc_theme=' + encodeURIComponent(theme)
            + '; Max-Age=31536000; path=' + path + '; SameSite=Lax' + secure;
    }

    function applyTheme(theme) {
        var html = document.documentElement;
        if (theme === 'auto') {
            html.setAttribute('data-theme-auto', '1');
            html.setAttribute('data-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } else if (theme === 'dark' || theme === 'light') {
            html.setAttribute('data-theme-auto', '0');
            html.setAttribute('data-theme', theme);
        }
        persistThemeCookie(theme);
        document.querySelectorAll('.mailbox-container, .mailbox-threads, .mailbox-threads-header, .mailbox-threads-list').forEach(function (el) {
            el.removeAttribute('style');
        });
    }

    document.addEventListener('change', function (ev) {
        var sel = ev.target;
        if (!sel || sel.getAttribute('data-theme-select') === null) {
            return;
        }
        applyTheme(sel.value);
        var form = sel.form;
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else if (form) {
            form.submit();
        }
    });

    function handleSuccess(data, form) {
        var action = data.action || '';
        var flash = data.flash === 'error' ? 'error' : 'ok';
        if (data.message) {
            toast(data.message, flash);
        }
        closeBlockingLayers();
        if (action === 'email_changed') {
            window.location.href = data.redirect || '../pages/verify_pending.php';
            return;
        }
        if (data.theme) {
            applyTheme(data.theme);
        }

        if (action === 'set_status' && patchSetStatus(data)) {
            consumeFlash(tabFromRedirect(data.redirect || '') || 'utilities');
            return;
        }
        if (action === 'delete_user' && patchDeleteUser(data)) {
            consumeFlash(tabFromRedirect(data.redirect || '') || 'utilities');
            return;
        }
        if (action === 'send_message') {
            var body = form.querySelector('textarea, input[name="body"]');
            if (body) {
                body.value = '';
            }
            if (typeof window.loadMailboxMessages === 'function') {
                window.loadMailboxMessages();
            }
            consumeFlash(tabFromRedirect(data.redirect || '') || 'messages');
            return;
        }
        if (action === 'rate_ticket') {
            document.querySelectorAll('.survey-overlay').forEach(function (el) {
                el.remove();
            });
            consumeFlash('tickets');
            softRefresh('tickets');
            return;
        }
        if (action === 'confirm_ticket' || action === 'self_help_solved') {
            consumeFlash('tickets');
            softRefresh('tickets');
            return;
        }

        var tab = tabFromRedirect(data.redirect || '') || document.body.getAttribute('data-page') || '';
        if (tab && document.getElementById('page-' + tab)) {
            softRefresh(tab);
        }
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement) || ev.defaultPrevented) {
            return;
        }
        if (String(form.getAttribute('method') || 'get').toLowerCase() !== 'post') {
            return;
        }
        if (form.hasAttribute('data-no-ajax') || form.enctype === 'multipart/form-data') {
            return;
        }
        var submitter = ev.submitter || document.activeElement;
        var action = form.getAttribute('action') || '';
        if (submitter && submitter.getAttribute && submitter.getAttribute('formaction')) {
            action = submitter.getAttribute('formaction');
        }
        if (!logicAction(action) || SKIP.test(action)) {
            return;
        }

        ev.preventDefault();
        var confirmMsg = submitter && submitter.getAttribute ? submitter.getAttribute('data-confirm') : '';
        if (confirmMsg) {
            zpgcConfirm({
                title: submitter.getAttribute('data-confirm-title') || 'Please confirm',
                message: confirmMsg,
                okLabel: submitter.getAttribute('data-confirm-ok') || 'Continue',
                cancelLabel: submitter.getAttribute('data-confirm-cancel') || 'Cancel',
            }).then(function (ok) {
                if (ok) {
                    postForm(form, submitter, action);
                }
            });
            return;
        }
        postForm(form, submitter, action);
    });

    function postForm(form, submitter, action) {
        if (busy) {
            return;
        }
        busy = true;
        form.classList.add('is-ajax-busy');

        var fd = new FormData(form);
        if (submitter && submitter.name) {
            fd.append(submitter.name, submitter.value || '1');
        }

        fetch(action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (res) {
            var type = (res.headers.get('content-type') || '').toLowerCase();
            if (type.indexOf('application/json') === -1) {
                throw new Error('not-json');
            }
            return res.json().then(function (data) {
                data._http = res.status;
                return data;
            });
        }).then(function (data) {
            if (!data || data.ok === false) {
                toast((data && (data.message || data.error)) || 'That action could not be completed.', 'error');
                return;
            }
            handleSuccess(data, form);
        }).catch(function () {
            window.location.reload();
        }).then(function () {
            busy = false;
            form.classList.remove('is-ajax-busy');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureToastCss);
    } else {
        ensureToastCss();
    }
})();
