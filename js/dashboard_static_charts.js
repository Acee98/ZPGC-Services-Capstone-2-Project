/**
 * Admin dashboard charts — original Chart.js look, live MySQL data
 * from window.DASHBOARD_CHART_DATA (admin.php + dashboard_stats.php).
 * Matplotlib PNGs remain available via logic/dashboard_chart_png.php for the paper requirement.
 */
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
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
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

    document.addEventListener('DOMContentLoaded', function () {
        initLiveCharts();
        var observer = new MutationObserver(function () {
            if (document.body.getAttribute('data-page') === 'dashboard') {
                initLiveCharts();
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['data-page'] });
    });
})();
