(function () {
    var tabs = document.getElementById('admin-tickets-filter-tabs');
    var body = document.getElementById('admin-tickets-body');
    if (!tabs || !body) {
        return;
    }

    function applyFilter(filter) {
        filter = filter || 'all';
        body.querySelectorAll('.ticket-row').forEach(function (row) {
            var status = (row.getAttribute('data-status') || '').toLowerCase();
            var replace = row.getAttribute('data-replace') === '1';
            var want = (filter || 'all').toLowerCase();
            var show = want === 'all' || status === want || (want === 'replacement' && replace);
            // Class + !important CSS so mobile table grid rules cannot keep rows visible.
            row.classList.toggle('ticket-row-filtered-out', !show);
            if (show) {
                row.removeAttribute('hidden');
                row.style.removeProperty('display');
            } else {
                row.setAttribute('hidden', 'hidden');
            }
        });
    }

    tabs.querySelectorAll('.filter-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabs.querySelectorAll('.filter-tab').forEach(function (t) {
                t.classList.remove('active-tab');
            });
            btn.classList.add('active-tab');
            applyFilter(btn.getAttribute('data-filter') || 'all');
        });
    });

    var active = tabs.querySelector('.filter-tab.active-tab');
    applyFilter(active ? active.getAttribute('data-filter') : 'all');
})();
