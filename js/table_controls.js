(function (global) {
    var PAGE_SIZES = [10, 15, 25];
    var DEFAULT_SIZE = 15;
    var bound = [];

    function rowId(row) {
        var raw = row.getAttribute('data-ticket-id');
        if (raw) {
            return parseInt(raw, 10) || 0;
        }
        var idCell = row.querySelector('.tickets-col-id, .tcol-id, .ucol-id');
        if (!idCell) {
            return 0;
        }
        var m = String(idCell.textContent || '').match(/(\d+)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function isDataRow(row) {
        return row.classList.contains('ticket-row') && !row.classList.contains('tickets-empty-state');
    }

    function matchesFilters(row, status, priority) {
        if (row.classList.contains('ticket-row-search-hidden')) {
            return false;
        }
        var st = (row.getAttribute('data-status') || '').toLowerCase();
        var pri = (row.getAttribute('data-priority') || '').toLowerCase();
        var replace = row.getAttribute('data-replace') === '1';
        var wantSt = (status || 'all').toLowerCase();
        var wantPri = (priority || 'all').toLowerCase();
        var showSt = wantSt === 'all'
            || st === wantSt
            || (wantSt === 'replacement' && replace)
            || (wantSt === 'pending' && (st === 'inactive' || st === 'pending'));
        if (wantSt === 'user' || wantSt === 'techn' || wantSt === 'admin') {
            showSt = (row.getAttribute('data-role') || '').toLowerCase() === wantSt;
        }
        if (wantSt === 'student' || wantSt === 'staff') {
            var role = (row.getAttribute('data-role') || '').toLowerCase();
            var kind = (row.getAttribute('data-kind') || '').toLowerCase();
            if (role === 'user' && kind === '') {
                kind = 'student';
            }
            showSt = role === 'user' && kind === wantSt;
        }
        var showPri = wantPri === 'all' || pri === wantPri;
        return showSt && showPri;
    }

    function tableHost(body) {
        return (body && body.closest) ? (body.closest('.tickets-list') || body.parentElement) : (body && body.parentElement);
    }

    function bindHScrollSync(list) {
        var header = list.querySelector('.tickets-list-header');
        var body = list.querySelector('.tickets-list-body');
        if (!header || !body || header.getAttribute('data-hscroll') === '1') {
            return;
        }
        header.setAttribute('data-hscroll', '1');
        var lock = false;
        function onScroll(from, to) {
            from.addEventListener('scroll', function () {
                if (lock) {
                    return;
                }
                lock = true;
                if (to.scrollLeft !== from.scrollLeft) {
                    to.scrollLeft = from.scrollLeft;
                }
                lock = false;
            }, { passive: true });
        }
        onScroll(header, body);
        onScroll(body, header);
        var wrap = header.parentElement;
        if (wrap && wrap.classList.contains('tickets-scroll')) {
            wrap.addEventListener('scroll', function () {
                if (lock) {
                    return;
                }
                lock = true;
                header.scrollLeft = wrap.scrollLeft;
                body.scrollLeft = wrap.scrollLeft;
                lock = false;
            }, { passive: true });
        }
    }

    function wrapTableScroll() {
        if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
            return;
        }
        document.querySelectorAll('.tickets-list').forEach(function (list) {
            var header = list.querySelector(':scope > .tickets-list-header')
                || list.querySelector(':scope > .tickets-scroll > .tickets-list-header');
            var body = list.querySelector(':scope > .tickets-list-body')
                || list.querySelector(':scope > .tickets-scroll > .tickets-list-body');
            if (!header || !body) {
                return;
            }
            if (!list.querySelector(':scope > .tickets-scroll')) {
                var wrap = document.createElement('div');
                wrap.className = 'tickets-scroll';
                header.parentNode.insertBefore(wrap, header);
                wrap.appendChild(header);
                wrap.appendChild(body);
            }
            bindHScrollSync(list);
        });
    }

    function ensurePager(body) {
        var host = tableHost(body);
        if (!host) {
            return null;
        }
        var bar = host.querySelector(':scope > .table-pager');
        if (bar) {
            return bar;
        }
        bar = document.createElement('div');
        bar.className = 'table-pager';
        bar.innerHTML = '<span class="table-pager-meta"></span>'
            + '<div class="table-pager-pages"></div>'
            + '<label class="table-pager-size">Rows <select></select></label>';
        var sel = bar.querySelector('select');
        PAGE_SIZES.forEach(function (n) {
            var opt = document.createElement('option');
            opt.value = String(n);
            opt.textContent = String(n);
            if (n === DEFAULT_SIZE) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });
        host.classList.add('has-table-pager');
        host.appendChild(bar);
        return bar;
    }

    function bindBody(body) {
        if (!body || body.getAttribute('data-table-bound') === '1') {
            return;
        }
        body.setAttribute('data-table-bound', '1');
        var state = {
            body: body,
            page: 1,
            size: parseInt(body.getAttribute('data-page-size'), 10) || DEFAULT_SIZE,
            status: 'all',
            priority: 'all',
        };
        if (PAGE_SIZES.indexOf(state.size) === -1) {
            state.size = DEFAULT_SIZE;
        }
        var bar = ensurePager(body);
        var sizeSel = bar.querySelector('select');
        sizeSel.value = String(state.size);
        sizeSel.addEventListener('change', function () {
            state.size = parseInt(sizeSel.value, 10) || DEFAULT_SIZE;
            state.page = 1;
            render(state);
        });
        bar.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-page]');
            if (!btn) {
                return;
            }
            state.page = parseInt(btn.getAttribute('data-page'), 10) || 1;
            render(state);
        });
        bound.push(state);
        render(state);
        return state;
    }

    function visibleRows(state) {
        var rows = [];
        state.body.querySelectorAll('.ticket-row').forEach(function (row) {
            if (!isDataRow(row)) {
                return;
            }
            if (matchesFilters(row, state.status, state.priority)) {
                rows.push(row);
            }
        });
        return rows;
    }

    function render(state) {
        var rows = Array.prototype.slice.call(state.body.querySelectorAll('.ticket-row'));
        var matched = [];
        rows.forEach(function (row) {
            var ok = matchesFilters(row, state.status, state.priority);
            row.classList.toggle('ticket-row-filtered-out', !ok);
            if (ok) {
                row.removeAttribute('hidden');
                matched.push(row);
            } else {
                row.setAttribute('hidden', 'hidden');
                row.classList.remove('ticket-row-page-hidden');
            }
        });
        var pages = Math.max(1, Math.ceil(matched.length / state.size));
        if (state.page > pages) {
            state.page = pages;
        }
        if (state.page < 1) {
            state.page = 1;
        }
        var start = (state.page - 1) * state.size;
        var end = start + state.size;
        matched.forEach(function (row, i) {
            var onPage = i >= start && i < end;
            row.classList.toggle('ticket-row-page-hidden', !onPage);
        });
        var host = tableHost(state.body);
        var bar = host && host.querySelector(':scope > .table-pager');
        if (!bar) {
            return;
        }
        var meta = bar.querySelector('.table-pager-meta');
        var from = matched.length === 0 ? 0 : start + 1;
        var to = Math.min(end, matched.length);
        meta.textContent = matched.length
            ? ('Showing ' + from + '–' + to + ' of ' + matched.length)
            : 'No rows';
        var pagesEl = bar.querySelector('.table-pager-pages');
        pagesEl.innerHTML = '';
        function addBtn(label, page, current) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.setAttribute('data-page', String(page));
            if (current) {
                b.className = 'is-current';
            }
            pagesEl.appendChild(b);
        }
        if (pages > 1) {
            addBtn('Prev', Math.max(1, state.page - 1), false);
            for (var p = 1; p <= pages; p++) {
                addBtn(String(p), p, p === state.page);
            }
            addBtn('Next', Math.min(pages, state.page + 1), false);
        }
        bar.hidden = matched.length === 0;
        if (state.body.id === 'utilities-users-body') {
            var emptyEl = state.body.querySelector('.utilities-filter-empty');
            var hasAccounts = false;
            rows.forEach(function (row) {
                if (isDataRow(row)) {
                    hasAccounts = true;
                }
            });
            if (matched.length === 0 && hasAccounts) {
                if (!emptyEl) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'tickets-empty-state utilities-filter-empty';
                    emptyEl.innerHTML = '<p>No accounts match this filter.</p>';
                    state.body.appendChild(emptyEl);
                }
            } else if (emptyEl) {
                emptyEl.remove();
            }
        }
    }

    function refreshAll() {
        bound.forEach(render);
    }

    function bindFilters() {
        document.querySelectorAll('[data-status-tabs]').forEach(function (tabs) {
            if (tabs.getAttribute('data-bound') === '1') {
                return;
            }
            tabs.setAttribute('data-bound', '1');
            var target = tabs.getAttribute('data-status-tabs');
            tabs.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.filter-tab');
                if (!btn) {
                    return;
                }
                tabs.querySelectorAll('.filter-tab').forEach(function (t) {
                    t.classList.remove('active-tab');
                });
                btn.classList.add('active-tab');
                bound.forEach(function (state) {
                    if (state.body.id === target || (target && state.body.matches(target))) {
                        state.status = btn.getAttribute('data-filter') || 'all';
                        state.page = 1;
                        render(state);
                    }
                });
            });
        });
        document.querySelectorAll('[data-priority-filter]').forEach(function (sel) {
            if (sel.getAttribute('data-bound') === '1') {
                return;
            }
            sel.setAttribute('data-bound', '1');
            sel.addEventListener('change', function () {
                var target = sel.getAttribute('data-filter-target');
                bound.forEach(function (state) {
                    if (!target || state.body.id === target) {
                        state.priority = sel.value || 'all';
                        state.page = 1;
                        render(state);
                    }
                });
            });
        });
    }

    function wrapUtilities() {
        var orig = global.applyUtilitiesFilter;
        if (typeof orig !== 'function' || orig.__zpgcPaged) {
            return;
        }
        global.applyUtilitiesFilter = function (filter) {
            orig(filter);
            bound.forEach(function (state) {
                if (state.body.id === 'utilities-users-body') {
                    state.status = filter || 'all';
                    state.page = 1;
                    render(state);
                }
            });
        };
        global.applyUtilitiesFilter.__zpgcPaged = true;
    }

    function boot() {
        wrapTableScroll();
        wrapUtilities();
        document.querySelectorAll('.js-paged-table').forEach(bindBody);
        bindFilters();
        wrapUtilities();
        if (typeof global.applyUtilitiesFilter === 'function') {
            global.applyUtilitiesFilter(global.currentUtilitiesFilter || 'all');
        }
        applyStatusFromQuery();
    }

    function applyStatusFromQuery() {
        var st = '';
        try {
            st = new URLSearchParams(window.location.search).get('status') || '';
        } catch (err) {
            st = '';
        }
        if (!st) {
            return;
        }
        bound.forEach(function (state) {
            var id = state.body.id || '';
            if (id.indexOf('tickets-body') !== -1) {
                state.status = st;
                state.page = 1;
                render(state);
            }
        });
    }

    function rebind(root) {
        wrapTableScroll();
        (root || document).querySelectorAll('.js-paged-table').forEach(function (body) {
            bound = bound.filter(function (state) {
                return state.body !== body;
            });
            body.removeAttribute('data-table-bound');
            bindBody(body);
        });
        wrapUtilities();
        bindFilters();
        if (typeof global.applyUtilitiesFilter === 'function') {
            global.applyUtilitiesFilter(global.currentUtilitiesFilter || 'all');
        }
        applyStatusFromQuery();
    }

    function revealRow(row) {
        if (!row) {
            return;
        }
        row.classList.remove('ticket-row-search-hidden');
        row.removeAttribute('hidden');
        bound.forEach(function (state) {
            if (!state.body.contains(row)) {
                return;
            }
            state.status = 'all';
            state.priority = 'all';
            var matched = [];
            state.body.querySelectorAll('.ticket-row').forEach(function (r) {
                if (!isDataRow(r)) {
                    return;
                }
                r.classList.remove('ticket-row-search-hidden');
                if (matchesFilters(r, 'all', 'all')) {
                    matched.push(r);
                }
            });
            var idx = matched.indexOf(row);
            state.page = idx >= 0 ? Math.floor(idx / state.size) + 1 : 1;
            render(state);
        });
    }

    global.ZpgcTables = {
        boot: boot,
        refresh: refreshAll,
        rebind: rebind,
        rowId: rowId,
        wrapScroll: wrapTableScroll,
        revealRow: revealRow,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
