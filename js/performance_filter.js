(function () {
    var input = document.getElementById('perf-log-search');
    var body = document.getElementById('perf-log-body');
    if (!input || !body) {
        return;
    }
    var emptyEl = null;

    function ensureEmptyNotice() {
        if (emptyEl) {
            return emptyEl;
        }
        emptyEl = document.createElement('div');
        emptyEl.className = 'tickets-empty-state perf-filter-empty perf-filter-empty--hidden';
        emptyEl.innerHTML = '<p>No matching tickets.</p>';
        body.appendChild(emptyEl);
        return emptyEl;
    }

    function applyPerfFilter() {
        var q = (input.value || '').toLowerCase().trim();
        var visible = 0;
        body.querySelectorAll('.ticket-row').forEach(function (row) {
            var blob = row.getAttribute('data-perf-search') || '';
            var match = q === '' || blob.indexOf(q) !== -1;
            row.classList.toggle('perf-row-hidden', !match);
            if (match) {
                visible += 1;
            }
        });
        var notice = ensureEmptyNotice();
        var hasRows = body.querySelectorAll('.ticket-row').length > 0;
        var showEmpty = hasRows && q !== '' && visible === 0;
        notice.classList.toggle('perf-filter-empty--hidden', !showEmpty);
    }

    input.addEventListener('input', applyPerfFilter);
    input.addEventListener('search', applyPerfFilter);
    applyPerfFilter();
})();
