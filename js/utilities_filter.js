/**
 * Admin Utilities user list filters (All / User / Technician / Administrator / Pending).
 * Must init immediately (not wait for DOMContentLoaded) — this file is lazy-loaded
 * after DOMContentLoaded when the utilities tab opens.
 */
(function () {
    if (window.__zpgcUtilitiesFilterBound) {
        return;
    }
    window.__zpgcUtilitiesFilterBound = true;
    window.currentUtilitiesFilter = window.currentUtilitiesFilter || 'all';

    function rowMatchesFilter(row, filter) {
        if (filter === 'all') {
            return true;
        }
        var status = (row.getAttribute('data-status') || '').toLowerCase();
        if (filter === 'pending') {
            // Pending approval = inactive until admin activates
            return status === 'inactive' || status === 'pending';
        }
        var role = (row.getAttribute('data-role') || '').toLowerCase();
        return role === String(filter).toLowerCase();
    }

    window.applyUtilitiesFilter = function (filter) {
        filter = filter || 'all';
        window.currentUtilitiesFilter = filter;
        var container = document.getElementById('utilities-users-body');
        if (!container) {
            return;
        }

        var rows = container.querySelectorAll('.ticket-row');
        var anyVisible = false;

        rows.forEach(function (row) {
            var matches = rowMatchesFilter(row, filter);
            // Class + hidden: CSS uses display:grid !important on these rows,
            // so inline style.display = 'none' alone does not hide them.
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
            noMatchEl.style.display = '';
        } else if (noMatchEl) {
            noMatchEl.remove();
        }
    };

    function bindTabs() {
        var tabs = document.getElementById('utilities-filter-tabs');
        if (!tabs || tabs.getAttribute('data-filter-bound') === '1') {
            return;
        }
        tabs.setAttribute('data-filter-bound', '1');

        tabs.querySelectorAll('.filter-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                tabs.querySelectorAll('.filter-tab').forEach(function (b) {
                    b.classList.remove('active-tab');
                });
                btn.classList.add('active-tab');
                window.applyUtilitiesFilter(btn.getAttribute('data-filter') || 'all');
            });
        });

        var activeBtn = tabs.querySelector('.filter-tab.active-tab');
        window.applyUtilitiesFilter(activeBtn ? activeBtn.getAttribute('data-filter') : 'all');
    }

    bindTabs();
})();
