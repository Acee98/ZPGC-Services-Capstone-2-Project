(function () {
    var tabs = document.getElementById('admin-tickets-filter-tabs');
    var body = document.getElementById('admin-tickets-body');
    if (!tabs || !body) {
        return;
    }
    tabs.querySelectorAll('.filter-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabs.querySelectorAll('.filter-tab').forEach(function (t) {
                t.classList.remove('active-tab');
            });
            btn.classList.add('active-tab');
            var filter = btn.getAttribute('data-filter') || 'all';
            body.querySelectorAll('.ticket-row').forEach(function (row) {
                var status = row.getAttribute('data-status') || '';
                row.style.display = (filter === 'all' || status === filter) ? '' : 'none';
            });
        });
    });
})();
