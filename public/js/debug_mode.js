/**
 * Debug Mode tab — event log list, expand/collapse, search, pagination, clear.
 */
window.mjlDebugModeInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-log-list-root');
    if (!root) return;

    var PER_PAGE  = 15;
    var fullLogs  = [];   // all logs from server
    var allLogs   = [];   // filtered set
    var totalPages = 1;
    var currentPage = 1;

    var ajaxUrl   = root.dataset.ajaxUrl    || '';
    var csrfToken = root.dataset.csrfToken  || '';
    var i18n = {
        noLogs:   root.dataset.i18nNoLogs   || 'No events recorded yet.',
        cleared:  root.dataset.i18nCleared  || 'Log cleared.',
        ticket:   root.dataset.i18nTicket   || '#',
    };

    try { fullLogs = JSON.parse(root.dataset.logs || '[]'); } catch (e) { fullLogs = []; }
    allLogs    = fullLogs.slice();
    totalPages = Math.max(1, Math.ceil(allLogs.length / PER_PAGE));

    var listEl      = document.getElementById('mjl-log-list');
    var showMoreBtn = document.getElementById('mjl-log-show-more');
    var paginator   = document.querySelector('.mjl-log-paginator');
    var clearBtn      = document.getElementById('mjl-log-clear-btn');
    var toggleBtn     = document.getElementById('mjl-log-toggle-btn');
    var simulateBtn   = document.getElementById('mjl-simulate-toggle-btn');
    var searchInput = document.getElementById('mjl-log-search');
    var searchBtn   = document.getElementById('mjl-log-search-btn');
    var searchClear = document.getElementById('mjl-log-search-clear');

    /* ── Helpers ── */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function buildPair(log, withAppear) {
        var rowClass = 'mjl-log-row' + (withAppear ? ' mjl-row-appearing' : '');
        var ticketText = log.ticket_id ? esc(i18n.ticket) + esc(log.ticket_id) : '—';
        var rulesCount = log.rules_count != null ? log.rules_count : 0;
        var rulesBadge = '<span class="badge ' + (rulesCount > 0 ? 'bg-success' : 'bg-secondary') + '">'
            + esc(rulesCount) + '</span>';

        var row = '<div class="' + rowClass + '" data-log-idx="' + esc(log.id) + '">'
            + '<span class="mjl-log-id">' + esc(log.id) + '</span>'
            + '<span class="mjl-log-target"><span class="badge">' + esc(log.target) + '</span></span>'
            + '<span class="mjl-log-event"><span class="badge bg-primary">' + esc(log.event) + '</span></span>'
            + '<span class="mjl-log-status">' + (log.status ? esc(log.status) : '—') + '</span>'
            + '<span class="mjl-log-type">' + (log.type ? esc(log.type) : '—') + '</span>'
            + '<span class="mjl-log-urgency">' + (log.urgency ? esc(log.urgency) : '—') + '</span>'
            + '<span class="mjl-log-priority">' + (log.priority ? esc(log.priority) : '—') + '</span>'
            + '<span class="mjl-log-ticket">' + ticketText + '</span>'
            + '<span class="mjl-log-rules">' + rulesBadge + '</span>'
            + '<span class="mjl-log-date">' + esc(log.date_creation) + '</span>'
            + '<span class="mjl-log-chevron"><i class="ti ti-chevron-right"></i></span>'
            + '</div>';

        var detail = '<div class="mjl-log-detail mjl-hidden" data-log-detail="' + esc(log.id) + '">'
            + '<pre class="mjl-log-json">' + esc(prettyJson(log.payload)) + '</pre>'
            + '</div>';

        return row + detail;
    }

    function prettyJson(raw) {
        try {
            return JSON.stringify(JSON.parse(raw), null, 2);
        } catch (e) {
            return raw || '';
        }
    }

    function clearRows() {
        listEl.querySelectorAll('.mjl-log-row, .mjl-log-detail, .mjl-log-empty').forEach(function (el) {
            el.remove();
        });
    }

    function showEmpty() {
        var div = document.createElement('div');
        div.className = 'mjl-log-empty';
        div.style.gridColumn = '1 / -1';
        div.textContent = i18n.noLogs;
        listEl.appendChild(div);
    }

    function renderPage(page, animateNew) {
        var start = (page - 1) * PER_PAGE;
        var batch = allLogs.slice(start, start + PER_PAGE);
        var html = '';
        batch.forEach(function (log) { html += buildPair(log, animateNew); });
        listEl.insertAdjacentHTML('beforeend', html);
        bindRowClicks();
    }

    function goToPage(page) {
        page = Math.max(1, Math.min(page, totalPages));
        currentPage = page;
        clearRows();
        if (allLogs.length === 0) {
            showEmpty();
        } else {
            renderPage(page, false);
        }
        updatePaginator();
        updateShowMore();
    }

    /* ── Row expand/collapse ── */
    function bindRowClicks() {
        listEl.querySelectorAll('.mjl-log-row').forEach(function (row) {
            if (row._mjlBound) return;
            row._mjlBound = true;
            row.addEventListener('click', function () {
                var id = row.dataset.logIdx;
                var detail = listEl.querySelector('[data-log-detail="' + id + '"]');
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
        if (q === '') {
            allLogs = fullLogs.slice();
        } else {
            allLogs = fullLogs.filter(function (log) {
                return log.ticket_id !== null && String(log.ticket_id).indexOf(q) !== -1;
            });
        }
        totalPages = Math.max(1, Math.ceil(allLogs.length / PER_PAGE));
        goToPage(1);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            applySearch(searchInput ? searchInput.value : '');
        });
    }
    var searchForm = document.getElementById('mjl-log-search-form');
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

    /* ── Show more ── */
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function () {
            if (currentPage >= totalPages) return;
            currentPage++;
            renderPage(currentPage, true);
            updatePaginator();
            updateShowMore();
        });
    }

    /* ── Paginator ── */
    function updatePaginator() {
        if (!paginator) return;
        var prevBtn = paginator.querySelector('[data-page="prev"]');
        var nextBtn = paginator.querySelector('[data-page="next"]');
        paginator.querySelectorAll('[data-page-num]').forEach(function (b) { b.remove(); });

        var fragment = document.createDocumentFragment();
        var maxVisible = 5;
        var half = Math.floor(maxVisible / 2);
        var start = Math.max(1, currentPage - half);
        var end   = Math.min(totalPages, start + maxVisible - 1);
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        for (var p = start; p <= end; p++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mjl-page-link' + (p === currentPage ? ' active' : '');
            btn.dataset.pageNum = p;
            btn.textContent = p;
            (function (pg) {
                btn.addEventListener('click', function () { goToPage(pg); });
            }(p));
            fragment.appendChild(btn);
        }

        paginator.insertBefore(fragment, nextBtn);

        if (prevBtn) prevBtn.classList.toggle('disabled', currentPage <= 1);
        if (nextBtn) nextBtn.classList.toggle('disabled', currentPage >= totalPages);
    }

    function updateShowMore() {
        if (!showMoreBtn) return;
        showMoreBtn.style.display = (currentPage >= totalPages) ? 'none' : '';
    }

    /* ── Wire paginator prev/next ── */
    if (paginator) {
        var prevBtn = paginator.querySelector('[data-page="prev"]');
        var nextBtn = paginator.querySelector('[data-page="next"]');
        if (prevBtn) prevBtn.addEventListener('click', function () { goToPage(currentPage - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToPage(currentPage + 1); });
    }

    function updateToggleBtn(btn, enabled, onClass, offClass, onIcon, offIcon) {
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'ti ' + (enabled ? onIcon : offIcon) + ' me-1';
        }
        var labelText = enabled
            ? (btn.dataset.i18nDisable || 'Disable')
            : (btn.dataset.i18nEnable  || 'Enable');
        var nodes = btn.childNodes;
        for (var i = nodes.length - 1; i >= 0; i--) {
            if (nodes[i].nodeType === 3) { nodes[i].textContent = labelText; break; }
        }
        if (enabled) {
            btn.classList.remove(offClass);
            btn.classList.add(onClass);
        } else {
            btn.classList.remove(onClass);
            btn.classList.add(offClass);
        }
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
                        'btn-warning', 'btn-danger', 'ti-toggle-right', 'ti-toggle-left');
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
                        'btn-warning', 'btn-danger', 'ti-bell', 'ti-bell-off');
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
                        totalPages = 1;
                        currentPage = 1;
                        if (searchInput) searchInput.value = '';
                        clearRows();
                        showEmpty();
                        updatePaginator();
                        updateShowMore();
                        if (typeof glpi_toast_info === 'function') {
                            glpi_toast_info(i18n.cleared);
                        }
                    }
                })
                .catch(function () {});
        });
    }

    /* ── Initial render ── */
    goToPage(1);
};
