/**
 * Debug Mode tab — event log (Graylog style), search, clear, toggles, live polling.
 */
window.mjlDebugModeInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-log-list-root');
    if (!root) return;

    var POLL_INTERVAL = 5000; // ms
    var PAGE_SIZE     = 20;

    var fullLogs = [];
    var allLogs  = [];
    var lastId   = 0;
    var pollTimer = null;
    var activeSearch = '';

    var ajaxUrl   = root.dataset.ajaxUrl   || '';
    var csrfToken = root.dataset.csrfToken || '';
    var i18n = {
        noLogs:  root.dataset.i18nNoLogs  || 'No events recorded yet.',
        cleared: root.dataset.i18nCleared || 'Log cleared.',
    };

    try { fullLogs = JSON.parse(root.dataset.logs || '[]'); } catch (e) { fullLogs = []; }
    allLogs = fullLogs.slice();
    lastId  = fullLogs.length > 0 ? fullLogs[0].id : 0;

    var clearBtn      = document.getElementById('mjl-log-clear-btn');
    var toggleBtn     = document.getElementById('mjl-log-toggle-btn');
    var simulateBtn   = document.getElementById('mjl-simulate-toggle-btn');
    var searchInput   = document.getElementById('mjl-log-search');
    var searchBtn     = document.getElementById('mjl-log-search-btn');
    var searchClear   = document.getElementById('mjl-log-search-clear');
    var searchForm    = document.getElementById('mjl-log-search-form');
    var showMoreWrap  = document.getElementById('mjl-log-show-more-wrap');
    var showMoreBtn   = document.getElementById('mjl-log-show-more');

    /* ── Live indicator ── */
    var liveDot = document.getElementById('mjl-live-dot');

    function setLiveState(active) {
        if (!liveDot) return;
        liveDot.classList.toggle('mjl-live-dot-active', active);
        liveDot.classList.toggle('mjl-live-dot-error', false);
    }

    function setLiveError() {
        if (!liveDot) return;
        liveDot.classList.remove('mjl-live-dot-active');
        liveDot.classList.add('mjl-live-dot-error');
    }

    /* ── Helpers ── */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function prettyJson(raw) {
        try { return JSON.stringify(JSON.parse(raw), null, 2); } catch (e) { return raw || ''; }
    }

    function buildRowHtml(log, withAppear) {
        var idx        = esc(log.id);
        var rulesClass = log.rules_count > 0 ? 'mjl-tr-val-green' : 'mjl-tr-val-muted';
        var ticketVal  = log.ticket_id ? '#' + esc(log.ticket_id) : '<span class="mjl-tr-val-muted">—</span>';
        var subjectVal = log.subject   ? esc(log.subject)         : '<span class="mjl-tr-val-muted">—</span>';
        var rowClass   = 'mjl-tr-row' + (withAppear ? ' mjl-tr-appearing' : '');

        return '<div class="' + rowClass + '" data-tr-idx="' + idx + '">'
            + '<span class="mjl-tr-chevron">▶</span>'
            + '<span class="mjl-tr-ts">' + esc(log.date_creation) + '</span>'
            + '<span class="mjl-tr-fields">'
            + '<span class="mjl-tr-kv"><span class="mjl-tr-key">target</span>=<span class="mjl-tr-val-blue">' + esc(log.target) + '</span></span>'
            + '<span class="mjl-tr-kv"><span class="mjl-tr-key">event</span>=<span class="mjl-tr-val-azure">' + esc(log.event) + '</span></span>'
            + '<span class="mjl-tr-kv"><span class="mjl-tr-key">ticket</span>=<span class="mjl-tr-val">' + ticketVal + '</span></span>'
            + '<span class="mjl-tr-kv"><span class="mjl-tr-key">subject</span>=<span class="mjl-tr-val-subj">' + subjectVal + '</span></span>'
            + '<span class="mjl-tr-kv"><span class="mjl-tr-key">rules</span>=<span class="' + rulesClass + '">' + esc(log.rules_count) + '</span></span>'
            + '</span>'
            + '</div>'
            + '<div class="mjl-tr-detail mjl-hidden" data-tr-detail="' + idx + '">'
            + '<pre>' + esc(prettyJson(log.payload)) + '</pre>'
            + '</div>';
    }

    /* ── Row click binding ── */
    function bindNewRows() {
        root.querySelectorAll('.mjl-tr-row:not([data-mjl-bound])').forEach(function (row) {
            row.dataset.mjlBound = '1';
            row.addEventListener('click', function () {
                var id     = row.dataset.trIdx;
                var detail = root.querySelector('[data-tr-detail="' + id + '"]');
                if (!detail) return;
                var open = row.classList.contains('is-open');
                row.classList.toggle('is-open', !open);
                detail.classList.toggle('mjl-hidden', open);
            });
        });
    }

    /* ── Show more button ── */
    function updateShowMore(totalCount) {
        if (!showMoreWrap) return;
        var shown = root.querySelectorAll('.mjl-tr-row').length;
        showMoreWrap.style.display = shown < totalCount ? '' : 'none';
    }

    /* ── Render full list (first PAGE_SIZE items) ── */
    function renderLogs(logs) {
        root.querySelectorAll('.mjl-tr-row, .mjl-tr-detail, .mjl-tr-empty').forEach(function (el) {
            el.remove();
        });
        if (logs.length === 0) {
            var emptyEl = document.createElement('div');
            emptyEl.className = 'mjl-tr-empty';
            emptyEl.textContent = i18n.noLogs;
            root.appendChild(emptyEl);
            updateShowMore(0);
            return;
        }
        var html = '';
        logs.slice(0, PAGE_SIZE).forEach(function (log) { html += buildRowHtml(log, false); });
        root.insertAdjacentHTML('beforeend', html);
        bindNewRows();
        updateShowMore(logs.length);
    }

    /* ── Prepend new rows (live) ── */
    function prependLogs(newLogs) {
        var empty = root.querySelector('.mjl-tr-empty');
        if (empty) empty.remove();

        var html = '';
        newLogs.forEach(function (log) { html += buildRowHtml(log, true); });

        var firstExisting = root.querySelector('.mjl-tr-row');
        if (firstExisting) {
            firstExisting.insertAdjacentHTML('beforebegin', html);
        } else {
            root.insertAdjacentHTML('beforeend', html);
        }
        bindNewRows();
        updateShowMore(allLogs.length);
    }

    /* ── Show more click ── */
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function () {
            var shown = root.querySelectorAll('.mjl-tr-row').length;
            var nextBatch = allLogs.slice(shown, shown + PAGE_SIZE);
            if (nextBatch.length === 0) { updateShowMore(allLogs.length); return; }
            var html = '';
            nextBatch.forEach(function (log) { html += buildRowHtml(log, false); });
            root.insertAdjacentHTML('beforeend', html);
            bindNewRows();
            updateShowMore(allLogs.length);
        });
    }

    /* ── Search ── */
    function matchesSearch(log, q) {
        return log.ticket_id !== null && String(log.ticket_id).indexOf(q) !== -1;
    }

    function applySearch(query) {
        activeSearch = (query || '').trim();
        allLogs = activeSearch === '' ? fullLogs.slice() : fullLogs.filter(function (log) {
            return matchesSearch(log, activeSearch);
        });
        renderLogs(allLogs);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            applySearch(searchInput ? searchInput.value : '');
        });
    }
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applySearch(searchInput ? searchInput.value : '');
        });
    }
    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            applySearch('');
        });
    }

    /* ── Toggle helpers ── */
    function updateToggleBtn(btn, enabled, onClass, offClass, onIcon, offIcon) {
        var icon = btn.querySelector('i');
        if (icon) { icon.className = 'ti ' + (enabled ? onIcon : offIcon) + ' me-1'; }
        var labelText = enabled
            ? (btn.dataset.i18nDisable || 'Disable')
            : (btn.dataset.i18nEnable  || 'Enable');
        var nodes = btn.childNodes;
        for (var i = nodes.length - 1; i >= 0; i--) {
            if (nodes[i].nodeType === 3) { nodes[i].textContent = labelText; break; }
        }
        if (enabled) { btn.classList.remove(offClass); btn.classList.add(onClass); }
        else         { btn.classList.remove(onClass);  btn.classList.add(offClass); }
    }

    /* ── Toggle extended log ── */
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('mattermost_ajax', 'toggle_extended_log');
            fd.append('_glpi_csrf_token', csrfToken);
            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.csrf_token) { csrfToken = data.csrf_token; }
                    if (!data.ok) { return; }
                    updateToggleBtn(toggleBtn, data.extended_log === 1,
                        'btn-outline-success', 'btn-outline-secondary', 'ti-toggle-right', 'ti-toggle-left');
                })
                .catch(function () {});
        });
    }

    /* ── Toggle notify simulation ── */
    if (simulateBtn) {
        simulateBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('mattermost_ajax', 'toggle_simulate_send');
            fd.append('_glpi_csrf_token', csrfToken);
            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.csrf_token) { csrfToken = data.csrf_token; }
                    if (!data.ok) { return; }
                    updateToggleBtn(simulateBtn, data.simulate_send === 1,
                        'btn-outline-warning', 'btn-outline-secondary', 'ti-bell', 'ti-bell-off');
                })
                .catch(function () {});
        });
    }

    /* ── Clear log ── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('mattermost_ajax', 'clear_event_log');
            fd.append('_glpi_csrf_token', csrfToken);
            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        fullLogs = [];
                        allLogs  = [];
                        lastId   = 0;
                        activeSearch = '';
                        if (searchInput) searchInput.value = '';
                        renderLogs([]);
                        if (typeof glpi_toast_info === 'function') {
                            glpi_toast_info(i18n.cleared);
                        }
                    }
                })
                .catch(function () {});
        });
    }

    /* ── Live polling ── */
    function poll() {
        var fd = new FormData();
        fd.append('mattermost_ajax', 'get_event_log');
        fd.append('_glpi_csrf_token', csrfToken);
        fd.append('since_id', lastId);
        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf_token) { csrfToken = data.csrf_token; }
                setLiveState(true);
                if (!data.ok || !Array.isArray(data.items) || data.items.length === 0) {
                    return;
                }
                // items are ordered DESC — reverse to get chronological order for prepend
                var newItems = data.items.slice().reverse();
                lastId = data.items[0].id; // highest id (first in DESC list)

                // Prepend to fullLogs (newest first)
                fullLogs = data.items.concat(fullLogs);

                // If no active search — show new rows live
                if (activeSearch === '') {
                    allLogs = fullLogs.slice();
                    prependLogs(newItems.slice().reverse()); // newest at top after prepend
                } else {
                    // Re-filter: new matching rows appear live
                    var matching = data.items.filter(function (log) {
                        return matchesSearch(log, activeSearch);
                    });
                    allLogs = fullLogs.filter(function (log) {
                        return matchesSearch(log, activeSearch);
                    });
                    if (matching.length > 0) {
                        prependLogs(matching.slice().reverse());
                    }
                }
            })
            .catch(function () {
                setLiveError();
            });
    }

    function startPolling() {
        if (pollTimer) return;
        setLiveState(true);
        pollTimer = setInterval(poll, POLL_INTERVAL);
    }

    /* ── Initial render + start polling ── */
    renderLogs(allLogs);
    startPolling();

    /* ══════════════════════════════════════
       Self-Test
    ══════════════════════════════════════ */
    var selfTestBtn     = document.getElementById('mjl-self-test-btn');
    var selfTestConfirm = document.getElementById('mjl-self-test-confirm');
    var selfTestModal   = document.getElementById('mjl-self-test-modal');
    var stProgress      = document.getElementById('mjl-self-test-progress');
    var stBar           = document.getElementById('mjl-st-bar');
    var stLabel         = document.getElementById('mjl-st-label');

    if (!selfTestConfirm) return;

    var ST_STEPS = [
        { key: 'create',           label: 'Creating test ticket',      delay: 0     },
        { key: 'add_observer',     label: 'Adding observer (tech)',     delay: 12000 },
        { key: 'add_followup',     label: 'Adding comment',             delay: 3000  },
        { key: 'request_approval', label: 'Requesting approval',        delay: 3000  },
        { key: 'reject_approval',  label: 'Rejecting approval',         delay: 3000  },
        { key: 'request_approval', label: 'Re-requesting approval',     delay: 3000  },
        { key: 'approve',          label: 'Approving',                  delay: 3000  },
        { key: 'add_solution',     label: 'Proposing solution',         delay: 3000  },
        { key: 'reject_solution',  label: 'Rejecting solution',         delay: 3000  },
        { key: 'add_solution',     label: 'Re-proposing solution',      delay: 3000  },
        { key: 'approve_solution', label: 'Approving solution',         delay: 3000  },
        { key: 'delete_ticket',    label: 'Cleaning up',                delay: 3000  },
    ];
    var ST_TOTAL = ST_STEPS.length;

    var stRunning  = false;
    var stTicketId = 0;
    var stTechId   = 0;
    var stValId    = 0;
    var stSolId    = 0;

    function stSetProgress(stepIdx, label) {
        if (!stBar || !stLabel || !stProgress) return;
        var pct = Math.round((stepIdx / ST_TOTAL) * 100);
        stBar.style.width = pct + '%';
        stLabel.textContent = 'Step ' + stepIdx + '/' + ST_TOTAL + ': ' + label;
    }

    function stShow() {
        if (stProgress) { stProgress.classList.remove('d-none'); stProgress.classList.add('d-flex'); }
        if (selfTestBtn) selfTestBtn.disabled = true;
        if (stBar) stBar.style.width = '0%';
    }

    function stHide() {
        if (stProgress) { stProgress.classList.add('d-none'); stProgress.classList.remove('d-flex'); }
        if (selfTestBtn) selfTestBtn.disabled = false;
        stRunning  = false;
        stTicketId = 0;
        stTechId   = 0;
        stValId    = 0;
        stSolId    = 0;
    }

    function stPost(action, extra) {
        var fd = new FormData();
        fd.append('mattermost_ajax', action);
        fd.append('_glpi_csrf_token', csrfToken);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        return fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf_token) csrfToken = data.csrf_token;
                return data;
            });
    }

    /* ── Self-Test: final poll + report ── */
    function stRenderReport(ticketId) {
        var EXPECTED = [
            'create', 'members_change', 'followup',
            'approval', 'rejected', 'approved',
            'status_changed', 'solution', 'solution_rejected',
            'solution_approved', 'delete',
        ];

        var ticketLogs = fullLogs.filter(function (log) {
            return log.ticket_id !== null && String(log.ticket_id) === String(ticketId);
        });

        var found = {};
        ticketLogs.forEach(function (log) { found[log.event_key || log.event] = true; });

        var passed = 0;
        var badgesHtml = EXPECTED.map(function (ev) {
            var ok    = !!found[ev];
            if (ok) passed++;
            var color = ok ? '#56d364' : '#f85149';
            return '<span style="color:' + color + ';white-space:nowrap;margin-right:1.1rem;">'
                + (ok ? '✓' : '✗') + '&nbsp;' + esc(ev) + '</span>';
        }).join('');

        var allOk       = passed === EXPECTED.length;
        var accentColor = allOk ? '#56d364' : '#e3b341';
        var statusText  = allOk ? 'ALL PASSED' : (passed + '/' + EXPECTED.length + ' detected');

        var html = '<div class="mjl-st-report" style="border-left-color:' + accentColor + ';">'
            + '<div class="mjl-st-report-title">'
            + '▷ SELF-TEST &nbsp;'
            + '<span class="mjl-st-report-ticket">ticket&nbsp;#' + esc(ticketId) + '</span>'
            + ' &mdash; <span style="color:' + accentColor + ';">' + esc(statusText) + '</span>'
            + '</div>'
            + '<div class="mjl-st-report-events">' + badgesHtml + '</div>'
            + '</div>';

        var empty = root.querySelector('.mjl-tr-empty');
        if (empty) empty.remove();

        var anchor = root.querySelector('.mjl-tr-row, .mjl-st-report');
        if (anchor) {
            anchor.insertAdjacentHTML('beforebegin', html);
        } else {
            root.insertAdjacentHTML('beforeend', html);
        }
    }

    function stFinalPollAndReport(ticketId) {
        var fd = new FormData();
        fd.append('mattermost_ajax', 'get_event_log');
        fd.append('_glpi_csrf_token', csrfToken);
        fd.append('since_id', lastId);
        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf_token) csrfToken = data.csrf_token;
                if (data.ok && Array.isArray(data.items) && data.items.length > 0) {
                    var newItems = data.items.slice().reverse();
                    lastId = data.items[0].id;
                    fullLogs = data.items.concat(fullLogs);
                    if (activeSearch === '') {
                        allLogs = fullLogs.slice();
                        prependLogs(newItems.slice().reverse());
                    }
                }
                stRenderReport(ticketId);
            })
            .catch(function () { stRenderReport(ticketId); });
    }

    function stRunStep(idx) {
        if (idx >= ST_TOTAL) {
            var finishTicketId = stTicketId;
            stHide();
            // Wait for last GLPI events to arrive, then poll once and render report
            setTimeout(function () { stFinalPollAndReport(finishTicketId); }, 6000);
            return;
        }

        var step = ST_STEPS[idx];
        stSetProgress(idx + 1, step.label);

        function execute() {
            var promise;
            if (step.key === 'create') {
                promise = stPost('self_test_start').then(function (data) {
                    if (!data.ok) throw new Error(data.error || 'Step failed');
                    stTicketId = data.ticket_id;
                    stTechId   = data.tech_id || 0;
                });
            } else {
                var extra = { step: step.key, ticket_id: stTicketId, tech_id: stTechId };
                if (step.key === 'reject_approval' || step.key === 'approve') extra.val_id = stValId;
                if (step.key === 'reject_solution' || step.key === 'approve_solution') extra.sol_id = stSolId;
                promise = stPost('self_test_step', extra).then(function (data) {
                    if (!data.ok) throw new Error(data.error || 'Step failed');
                    if (data.val_id) stValId = data.val_id;
                    if (data.sol_id) stSolId = data.sol_id;
                });
            }

            promise
                .then(function () { stRunStep(idx + 1); })
                .catch(function (err) {
                    stHide();
                    if (typeof glpi_toast_error === 'function') {
                        glpi_toast_error('Self-Test error at step "' + step.label + '": ' + err.message);
                    }
                });
        }

        if (step.delay > 0) {
            setTimeout(execute, step.delay);
        } else {
            execute();
        }
    }

    selfTestConfirm.addEventListener('click', function () {
        if (stRunning) return;
        stRunning = true;

        // Close modal
        if (selfTestModal && window.bootstrap) {
            var bsModal = bootstrap.Modal.getInstance(selfTestModal);
            if (bsModal) bsModal.hide();
        }

        stShow();
        stRunStep(0);
    });
};
