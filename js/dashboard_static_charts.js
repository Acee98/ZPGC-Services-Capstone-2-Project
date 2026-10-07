

(function () {
    function destroyIfAny(canvas) {
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }
        var existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }
    }

    function payload() {
        return window.DASHBOARD_CHART_DATA || {
            report: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                submitted: [0, 0, 0, 0, 0, 0, 0],
                resolved: [0, 0, 0, 0, 0, 0, 0],
            },
            categories: {
                labels: ['Hardware', 'Software', 'Network', 'Account', 'Other'],
                data: [0, 0, 0, 0, 0],
            },
            severity: { labels: ['Critical', 'Moderate', 'Low'], data: [0, 0, 0] },
            satisfaction: {
                labels: ['Very satisfied', 'Satisfied', 'Not sure', 'Not satisfied', 'Hate it'],
                data: [0, 0, 0, 0, 0],
            },
        };
    }

    function initLiveCharts() {
        if (typeof Chart === 'undefined') {
            return;
        }
        if (document.body.getAttribute('data-page') !== 'dashboard') {
            return;
        }

        var reportCtx = document.getElementById('ticketsReportChart');
        var catCtx = document.getElementById('ticketsCategoriesChart');
        var satCtx = document.getElementById('satisfactionChart');
        var sevCtx = document.getElementById('severityChart');
        if (!reportCtx && !catCtx && !satCtx && !sevCtx) {
            return;
        }

        var data = payload();
        var maroon = '#610107';

        destroyIfAny(reportCtx);
        destroyIfAny(catCtx);
        destroyIfAny(satCtx);
        destroyIfAny(sevCtx);

        if (reportCtx) {
            new Chart(reportCtx, {
                type: 'line',
                data: {
                    labels: data.report.labels,
                    datasets: [
                        {
                            label: 'Submitted',
                            data: data.report.submitted,
                            borderColor: maroon,
                            backgroundColor: maroon,
                            tension: 0.35,
                            pointRadius: 3,
                        },
                        {
                            label: 'Resolved',
                            data: data.report.resolved,
                            borderColor: '#5BC8E8',
                            backgroundColor: '#5BC8E8',
                            tension: 0.35,
                            pointRadius: 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 11 } },
                        },
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: (data.report.labels || []).length > 12 ? 12 : 16,
                            },
                        },
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });
        }

        if (catCtx) {
            new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: data.categories.labels,
                    datasets: [{
                        label: 'Tickets',
                        data: data.categories.data,
                        backgroundColor: maroon,
                        borderRadius: 4,
                        maxBarThickness: 42,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        }

        if (satCtx) {
            var satColors = ['#7ED9A8', '#2E8B8B', '#5BC8E8', '#F5A623', '#D9435E'];
            var satMax = Math.max.apply(null, (data.satisfaction.data || []).concat([10]));
            new Chart(satCtx, {
                type: 'bar',
                data: {
                    labels: data.satisfaction.labels,
                    datasets: [{
                        data: data.satisfaction.data,
                        backgroundColor: satColors,
                        borderRadius: 4,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: Math.ceil(satMax * 1.2) || 40,
                            ticks: { precision: 0 },
                        },
                    },
                },
            });
        }

        if (sevCtx) {
            new Chart(sevCtx, {
                type: 'doughnut',
                data: {
                    labels: data.severity.labels,
                    datasets: [{
                        data: data.severity.data,
                        backgroundColor: ['#FF3B30', '#FF8D28', '#34C759'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 11 } },
                        },
                    },
                },
            });
        }

        window.setTimeout(function () {
            [reportCtx, catCtx, satCtx, sevCtx].forEach(function (canvas) {
                var chart = canvas && Chart.getChart ? Chart.getChart(canvas) : null;
                if (chart) {
                    chart.resize();
                }
            });
        }, 0);
    }

    var RANGES = [
        { key: 'week', label: 'This Week' },
        { key: 'month', label: 'This Month' },
        { key: 'year', label: 'This Year' },
    ];

    function rangeMeta(key) {
        for (var i = 0; i < RANGES.length; i++) {
            if (RANGES[i].key === key) {
                return RANGES[i];
            }
        }
        return RANGES[0];
    }

    function currentRange() {
        return window.DASHBOARD_CHART_RANGE || 'week';
    }

    function setRangeLabels(label) {
        document.querySelectorAll('[data-chart-range-label]').forEach(function (el) {
            el.textContent = label;
        });
        var btn = document.getElementById('dash-range-btn');
        if (btn) {
            btn.textContent = label;
            btn.setAttribute('data-range', currentRange());
        }
    }

    function loadRange(range) {
        var meta = rangeMeta(range);
        return fetch('../logic/dashboard_charts_api.php?range=' + encodeURIComponent(meta.key), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Could not load chart data');
                }
                return res.json();
            })
            .then(function (json) {
                if (!json || !json.ok || !json.data) {
                    throw new Error((json && json.error) || 'Could not load chart data');
                }
                window.DASHBOARD_CHART_DATA = json.data;
                window.DASHBOARD_CHART_RANGE = json.range || meta.key;
                setRangeLabels(json.range_label || meta.label);
                initLiveCharts();
            });
    }

    function canvasJpeg(canvas) {
        if (!canvas) {
            return '';
        }
        var w = canvas.width || canvas.offsetWidth || 1;
        var h = canvas.height || canvas.offsetHeight || 1;
        var maxW = 640;
        var scale = Math.min(1, maxW / w);
        var off = document.createElement('canvas');
        off.width = Math.max(1, Math.round(w * scale));
        off.height = Math.max(1, Math.round(h * scale));
        var ctx = off.getContext('2d');
        if (!ctx) {
            return '';
        }
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, off.width, off.height);
        ctx.drawImage(canvas, 0, 0, off.width, off.height);
        var url = off.toDataURL('image/jpeg', 0.55);
        var parts = url.split(',');
        return parts[1] || '';
    }

    function downloadPdf() {
        var btn = document.getElementById('dash-pdf-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Preparing PDF…';
        }
        var payload = {
            range: currentRange(),
            images: {
                report: canvasJpeg(document.getElementById('ticketsReportChart')),
                categories: canvasJpeg(document.getElementById('ticketsCategoriesChart')),
                satisfaction: canvasJpeg(document.getElementById('satisfactionChart')),
                severity: canvasJpeg(document.getElementById('severityChart')),
            },
        };
        fetch('../logic/dashboard_report_pdf.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/pdf',
                'X-CSRF-TOKEN': window.ZPGC_CSRF || '',
            },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('PDF download failed');
                }
                return res.blob();
            })
            .then(function (blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'zpgc-dashboard-' + currentRange() + '.pdf';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
            })
            .catch(function () {
                window.alert('Could not download the PDF. Try again in a moment.');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Download PDF';
                }
            });
    }

    function bindDashboardControls() {
        var rangeBtn = document.getElementById('dash-range-btn');
        if (rangeBtn && !rangeBtn.getAttribute('data-bound')) {
            rangeBtn.setAttribute('data-bound', '1');
            rangeBtn.addEventListener('click', function () {
                var idx = 0;
                var cur = currentRange();
                for (var i = 0; i < RANGES.length; i++) {
                    if (RANGES[i].key === cur) {
                        idx = i;
                        break;
                    }
                }
                var next = RANGES[(idx + 1) % RANGES.length];
                rangeBtn.disabled = true;
                loadRange(next.key).finally(function () {
                    rangeBtn.disabled = false;
                });
            });
        }
        var pdfBtn = document.getElementById('dash-pdf-btn');
        if (pdfBtn && !pdfBtn.getAttribute('data-bound')) {
            pdfBtn.setAttribute('data-bound', '1');
            pdfBtn.addEventListener('click', downloadPdf);
        }
        setRangeLabels(rangeMeta(currentRange()).label);
    }

    function bootCharts() {
        bindDashboardControls();
        initLiveCharts();
        if (!document.body || typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(function () {
            if (document.body.getAttribute('data-page') === 'dashboard') {
                bindDashboardControls();
                initLiveCharts();
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['data-page'] });
    }

    // Supports deferred/lazy load after DOMContentLoaded already fired.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCharts);
    } else {
        bootCharts();
    }
})();
