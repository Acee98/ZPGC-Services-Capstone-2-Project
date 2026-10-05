/**
 * Load page scripts when needed.
 */
(function (global) {
    var loadedScripts = {};
    var tabWaiters = {};

    function loadScript(src) {
        if (!src) {
            return Promise.reject(new Error('missing script src'));
        }
        if (loadedScripts[src]) {
            return loadedScripts[src];
        }
        loadedScripts[src] = new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-lazy-src="' + src.replace(/"/g, '') + '"]');
            if (existing) {
                if (existing.getAttribute('data-lazy-ready') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () { reject(new Error('Failed ' + src)); });
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.setAttribute('data-lazy-src', src);
            s.onload = function () {
                s.setAttribute('data-lazy-ready', '1');
                resolve();
            };
            s.onerror = function () {
                reject(new Error('Failed ' + src));
            };
            document.head.appendChild(s);
        });
        return loadedScripts[src];
    }

    function currentTab() {
        return (document.body && document.body.getAttribute('data-page')) || '';
    }

    function whenTab(tab, fn) {
        if (typeof fn !== 'function' || !tab) {
            return;
        }
        if (!tabWaiters[tab]) {
            tabWaiters[tab] = [];
        }
        tabWaiters[tab].push(fn);
        if (currentTab() === tab) {
            try { fn(); } catch (e) {}
        }
    }

    function flushTab(tab) {
        var list = tabWaiters[tab] || [];
        list.forEach(function (fn) {
            try { fn(); } catch (e) {}
        });
    }

    function markImagesLazy(root) {
        var scope = root || document;
        scope.querySelectorAll('img:not([loading])').forEach(function (img) {
            if (img.closest('.logo') || img.classList.contains('terms-hero-logo')) {
                img.setAttribute('decoding', 'async');
                img.setAttribute('fetchpriority', 'high');
                return;
            }
            img.setAttribute('loading', 'lazy');
            img.setAttribute('decoding', 'async');
        });
    }

    function initTabObserver() {
        if (!document.body || typeof MutationObserver === 'undefined') {
            return;
        }
        var last = currentTab();
        var obs = new MutationObserver(function () {
            var now = currentTab();
            if (now && now !== last) {
                last = now;
                flushTab(now);
            }
        });
        obs.observe(document.body, { attributes: true, attributeFilter: ['data-page'] });
    }

    function initAdminLazyBundles() {
        var chartSrc = '../js/chart.umd.js';
        var chartsSrc = '../js/dashboard_static_charts.js?v=1.6.2';
        var ticketsSrc = '../js/tickets_filter.js?v=1.6.2';
        var utilitiesSrc = '../js/utilities_filter.js?v=1.6.2';

        whenTab('dashboard', function () {
            if (global.__zpgcChartsLoading) {
                return;
            }
            global.__zpgcChartsLoading = true;
            loadScript(chartSrc)
                .then(function () { return loadScript(chartsSrc); })
                .catch(function () { global.__zpgcChartsLoading = false; });
        });

        whenTab('tickets', function () {
            loadScript(ticketsSrc).catch(function () {});
        });

        whenTab('utilities', function () {
            loadScript(utilitiesSrc).catch(function () {});
        });
    }

    function boot() {
        markImagesLazy(document);
        initTabObserver();
        if (document.body && document.body.getAttribute('data-role') === 'admin') {
            initAdminLazyBundles();
        } else if (document.getElementById('ticketsReportChart') || document.getElementById('page-utilities')) {
            // Admin pages without data-role still get chart/filter lazy load.
            initAdminLazyBundles();
        }
    }

    global.ZpgcLazy = {
        loadScript: loadScript,
        whenTab: whenTab,
        markImagesLazy: markImagesLazy,
        currentTab: currentTab,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
