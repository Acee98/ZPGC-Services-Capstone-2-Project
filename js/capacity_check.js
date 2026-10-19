(function () {
    function healthUrl() {
        try {
            return new URL("health.php", window.location.href).toString();
        } catch (e) {
            return "../pages/health.php";
        }
    }

    function percentile(ms, p) {
        if (!ms.length) {
            return 0;
        }
        ms = ms.slice().sort(function (a, b) { return a - b; });
        return ms[Math.floor((p / 100) * (ms.length - 1))] || 0;
    }

    function pingOne(url) {
        var started = performance.now();
        return fetch(url + (url.indexOf("?") >= 0 ? "&" : "?") + "n=" + Math.random(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }).then(function (res) {
            return res.json().then(function (data) {
                return {
                    ok: res.ok && data && data.ok === true,
                    ms: Math.round(performance.now() - started),
                };
            });
        }).catch(function () {
            return { ok: false, ms: Math.round(performance.now() - started) };
        });
    }

    function runRound(url, concurrent) {
        var jobs = [];
        var i;
        for (i = 0; i < concurrent; i++) {
            jobs.push(pingOne(url));
        }
        return Promise.all(jobs);
    }

    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function setBanner(host, tone, text) {
        var el = host.querySelector("[data-capacity-banner]");
        if (!el) {
            return;
        }
        el.hidden = !text;
        el.className = tone === "error"
            ? "utilities-notice-error"
            : (tone === "warn" ? "utilities-notice-warn" : "utilities-notice");
        el.textContent = text || "";
    }

    function bind(panel) {
        if (!panel || panel.getAttribute("data-capacity-bound") === "1") {
            return;
        }
        panel.setAttribute("data-capacity-bound", "1");
        var btn = panel.querySelector("[data-capacity-run]");
        var lastEl = panel.querySelector("[data-capacity-last]");
        var evidenceEl = panel.querySelector("[data-iso-capacity-evidence]");
        var csrf = panel.querySelector('input[name="_csrf"]');
        if (!btn) {
            return;
        }
        btn.addEventListener("click", function () {
            if (btn.disabled) {
                return;
            }
            var concurrent = 10;
            var rounds = 3;
            btn.disabled = true;
            btn.textContent = "Running…";
            setBanner(panel, "ok", "Running " + concurrent + " overlapping health checks, " + rounds + " rounds. Stay on this tab.");
            var url = healthUrl();
            var times = [];
            var okN = 0;
            var failN = 0;
            var round = 0;
            function next() {
                if (round >= rounds) {
                    finish();
                    return;
                }
                round += 1;
                setBanner(panel, "ok", "Round " + round + " of " + rounds + "…");
                runRound(url, concurrent).then(function (rows) {
                    rows.forEach(function (row) {
                        times.push(row.ms);
                        if (row.ok) {
                            okN += 1;
                        } else {
                            failN += 1;
                        }
                    });
                    return wait(350);
                }).then(next);
            }
            function finish() {
                var total = okN + failN;
                var body = new FormData();
                if (csrf) {
                    body.append("_csrf", csrf.value);
                }
                body.append("concurrent", String(concurrent));
                body.append("rounds", String(rounds));
                body.append("total_n", String(total));
                body.append("ok_n", String(okN));
                body.append("fail_n", String(failN));
                body.append("p50_ms", String(percentile(times, 50)));
                body.append("p95_ms", String(percentile(times, 95)));
                fetch("../logic/capacity_save_mngmnt.php", {
                    method: "POST",
                    body: body,
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                }).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (!data || data.ok === false) {
                        throw new Error((data && data.message) || "Could not save the check.");
                    }
                    if (lastEl) {
                        lastEl.textContent = data.last_run || lastEl.textContent;
                        lastEl.hidden = !lastEl.textContent;
                    }
                    if (evidenceEl && data.evidence) {
                        evidenceEl.textContent = data.evidence;
                    }
                    setBanner(panel, data.tone || "ok", data.message || "");
                }).catch(function (err) {
                    setBanner(panel, "error", err && err.message ? err.message : "The check finished in the browser but could not be saved.");
                }).then(function () {
                    btn.disabled = false;
                    btn.textContent = "Run concurrent user check";
                });
            }
            next();
        });
    }

    function init() {
        document.querySelectorAll("[data-capacity-panel]").forEach(bind);
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}());
