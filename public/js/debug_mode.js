/**
 * Debug Mode tab — event log (Graylog style), search, clear, toggles.
 */
window.mjlDebugModeInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-log-list-root');
    if (!root) return;

    var fullLogs = [];
    var allLogs  = [];

    var ajaxUrl   = root.dataset.ajaxUrl   || '';
    var csrfToken = root.dataset.csrfToken || '';
    var i18n = {
        noLogs:  root.dataset.i18nNoLogs  || 'No events recorded yet.',
        cleared: root.dataset.i18nCleared || 'Log cleared.',
    };

    try { fullLogs = JSON.parse(root.dataset.logs || '[]'); } catch (e) { fullLogs = []; }
    allLogs = fullLogs.slice();

    var clearBtn    = document.getElementById('mjl-log-clear-btn');
    var toggleBtn   = document.getElementById('mjl-log-toggle-btn');
    var simulateBtn = document.getElementById('mjl-simulate-toggle-btn');
    var searchInput = document.getElementById('mjl-log-search');
    var searchBtn   = document.getElementById('mjl-log-search-btn');
    var searchClear = document.getElementById('mjl-log-search-clear');
    var searchForm  = document.getElementById('mjl-log-search-form');

    /* ── Helpers ── */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function prettyJson(raw) {
        try { return JSON.stringify(JSON.parse(raw), null, 2); } catch (e) { return raw || ''; }
    }

    /* ── Render ── */
    function renderLogs(logs) {
        root.querySelectorAll('.mjl-tr-row, .mjl-tr-detail, .mjl-tr-empty').forEach(function (el) {
            el.remove();
        });

        if (logs.length === 0) {
            var emptyEl = document.createElement('div');
            emptyEl.className = 'mjl-tr-empty';
            emptyEl.textContent = i18n.noLogs;
            root.appendChild(emptyEl);
            return;
        }

        var html = '';
        logs.forEach(function (log) {
            var idx        = esc(log.id);
            var rulesClass = log.rules_count > 0 ? 'mjl-tr-val-green' : 'mjl-tr-val-muted';
            var ticketVal  = log.ticket_id ? '#' + esc(log.ticket_id) : '<span class="mjl-tr-val-muted">—</span>';
            var subjectVal = log.subject   ? esc(log.subject)         : '<span class="mjl-tr-val-muted">—</span>';

            html += '<div class="mjl-tr-row" data-tr-idx="' + idx + '">'
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
        });

        root.insertAdjacentHTML('beforeend', html);

        root.querySelectorAll('.mjl-tr-row').forEach(function (row) {
            if (row._mjlBound) return;
            row._mjlBound = true;
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

    /* ── Search ── */
    function applySearch(query) {
        var q = (query || '').trim();
        allLogs = q === '' ? fullLogs.slice() : fullLogs.filter(function (log) {
            return log.ticket_id !== null && String(log.ticket_id).indexOf(q) !== -1;
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

    /* ── Initial render ── */
    renderLogs(allLogs);
};
