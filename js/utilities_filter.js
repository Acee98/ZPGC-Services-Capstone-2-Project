/**
 * utilities_filter.js — All / User / Technician / Administrator / Pending Approval
 */
(function () {
    window.currentUtilitiesFilter = window.currentUtilitiesFilter || 'all';

    function rowMatchesFilter(row, filter) {
        if (filter === 'all') {
            return true;
        }
        var status = row.getAttribute('data-status') || '';
        if (filter === 'pending') {
            return status === 'inactive';
        }
        var role = row.getAttribute('data-role') || '';
        return role === filter;
    }

    window.applyUtilitiesFilter = function (filter) {
        window.currentUtilitiesFilter = filter;
        var container = document.getElementById('utilities-users-body');
        if (!container) {
            return;
        }

        var rows = container.querySelectorAll('.ticket-row');
        var anyVisible = false;

        rows.forEach(function (row) {
            var matches = rowMatchesFilter(row, filter);
            row.style.display = matches ? '' : 'none';
            if (matches) {
                anyVisible = true;
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

    document.addEventListener('DOMContentLoaded', function () {
        var tabs = document.getElementById('utilities-filter-tabs');
        if (!tabs) {
            return;
        }

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
    });
})();
