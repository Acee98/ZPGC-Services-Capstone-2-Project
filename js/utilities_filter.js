/**
 * Admin Utilities role filters — event delegation so clicks always work
 * (does not depend on lazy-load timing / DOMContentLoaded).
 */
(function () {
    if (window.__zpgcUtilitiesFilterReady) {
        return;
    }
    window.__zpgcUtilitiesFilterReady = true;
    window.currentUtilitiesFilter = window.currentUtilitiesFilter || 'all';

    function rowMatchesFilter(row, filter) {
        filter = String(filter || 'all').toLowerCase();
        if (filter === 'all') {
            return true;
        }
        var status = (row.getAttribute('data-status') || '').toLowerCase();
        if (filter === 'pending') {
            return status === 'inactive' || status === 'pending';
        }
        // user | techn | admin — show every account with that role
        var role = (row.getAttribute('data-role') || '').toLowerCase();
        return role === filter;
    }

    window.applyUtilitiesFilter = function (filter) {
        filter = String(filter || 'all').toLowerCase();
        window.currentUtilitiesFilter = filter;
        var container = document.getElementById('utilities-users-body');
        if (!container) {
            return;
        }

        var rows = container.querySelectorAll('.ticket-row');
        var anyVisible = false;
        rows.forEach(function (row) {
            var matches = rowMatchesFilter(row, filter);
            row.classList.toggle('ticket-row-filtered-out', !matches);
            if (matches) {
                row.removeAttribute('hidden');
                row.style.removeProperty('display');
                anyVisible = true;
            } else {
                row.setAttribute('hidden', 'hidden');
            }
        });

        var noMatchEl = container.querySelector('.utilities-filter-empty');
        if (!anyVisible && rows.length > 0) {
            if (!noMatchEl) {
                noMatchEl = document.createElement('div');
                noMatchEl.className = 'tickets-empty-state utilities-filter-empty';
                noMatchEl.innerHTML = '<p>No accounts match this filter.</p>';
                container.appendChild(noMatchEl);
            }
        } else if (noMatchEl) {
            noMatchEl.remove();
        }
    };

    // Capture-phase delegation: works even if other handlers stop bubbling.
    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest
            ? event.target.closest('#utilities-filter-tabs .filter-tab')
            : null;
        if (!btn) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        var tabs = document.getElementById('utilities-filter-tabs');
        if (!tabs) {
            return;
        }
        tabs.querySelectorAll('.filter-tab').forEach(function (b) {
            b.classList.remove('active-tab');
        });
        btn.classList.add('active-tab');
        window.applyUtilitiesFilter(btn.getAttribute('data-filter') || 'all');
    }, true);

    function syncActive() {
        var tabs = document.getElementById('utilities-filter-tabs');
        if (!tabs) {
            return;
        }
        var activeBtn = tabs.querySelector('.filter-tab.active-tab');
        window.applyUtilitiesFilter(activeBtn ? activeBtn.getAttribute('data-filter') : 'all');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncActive);
    } else {
        syncActive();
    }
})();
