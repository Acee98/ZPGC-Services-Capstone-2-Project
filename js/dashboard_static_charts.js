/**
 * dashboard_static_charts.js
 * ----------------------------------------------------------------------
 * Chart.js sample charts for the admin Dashboard. Uses local chart.umd.js
 * (not CDN). Re-runs when body[data-page] becomes "dashboard" so switching
 * tabs does not leave empty cards.
 * ----------------------------------------------------------------------
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

    function initStaticCharts() {
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

        var maroon = '#610107';
        var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        destroyIfAny(reportCtx);
        destroyIfAny(catCtx);
        destroyIfAny(satCtx);
        destroyIfAny(sevCtx);

        if (reportCtx) {
            new Chart(reportCtx, {
                type: 'line',
                data: {
                    labels: days,
                    datasets: [
                        {
                            label: 'Submitted',
                            data: [10, 22, 28, 35, 40, 48, 50],
                            borderColor: maroon,
                            backgroundColor: maroon,
                            tension: 0.35,
                            pointRadius: 3,
                        },
                        {
                            label: 'Resolved',
                            data: [15, 18, 30, 45, 45, 45, 50],
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
                    scales: { y: { beginAtZero: true } },
                },
            });
        }

        if (catCtx) {
            new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: ['Hardware', 'Software', 'Network', 'Account', 'Other'],
                    datasets: [{
                        label: 'Tickets',
                        data: [70, 85, 65, 95, 45],
                        backgroundColor: maroon,
                        borderRadius: 4,
                        maxBarThickness: 42,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        }

        if (satCtx) {
            var satColors = ['#7ED9A8', '#2E8B8B', '#5BC8E8', '#F5A623', '#D9435E'];
            new Chart(satCtx, {
                type: 'bar',
                data: {
                    labels: ['Very satisfied', 'Satisfied', 'Not sure', 'Not satisfied', 'Hate it'],
                    datasets: [{
                        data: [35, 30, 20, 10, 5],
                        backgroundColor: satColors,
                        borderRadius: 4,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.parsed.y + '%';
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 40,
                            ticks: {
                                callback: function (v) {
                                    return v + '%';
                                },
                            },
                        },
                    },
                },
            });
        }

        if (sevCtx) {
            new Chart(sevCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Critical', 'Moderate', 'Low'],
                    datasets: [{
                        data: [30, 40, 30],
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

        // Chart.js needs a second layout pass after the dashboard block is display:block.
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
        initStaticCharts();
        // Re-draw when returning to Dashboard after visiting another tab.
        var observer = new MutationObserver(function () {
            if (document.body.getAttribute('data-page') === 'dashboard') {
                initStaticCharts();
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['data-page'] });
    });
})();
