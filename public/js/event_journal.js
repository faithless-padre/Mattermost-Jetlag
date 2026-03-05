/**
 * Events Journal tab — two-level list, expand/collapse, search, pagination, clear.
 */
window.mjlEventJournalInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-ej-list-root');
    if (!root) return;

    var PER_PAGE    = 15;
    var fullGroups  = [];
    var allGroups   = [];
    var totalPages  = 1;
    var currentPage = 1;

    var ajaxUrl   = root.dataset.ajaxUrl   || '';
    var csrfToken = root.dataset.csrfToken || '';
    var i18n = {
        noLogs:    root.dataset.i18nNoLogs    || 'No events recorded yet.',
        cleared:   root.dataset.i18nCleared   || 'Log cleared.',
        rule:      root.dataset.i18nRule      || 'Rule',
        event:     root.dataset.i18nEvent     || 'Event',
        recipient: root.dataset.i18nRecipient || 'Recipient',
        status:    root.dataset.i18nStatus    || 'Status',
        date:      root.dataset.i18nDate      || 'Date',
        simulated: root.dataset.i18nSimulated || 'Simulated',
    };

    try { fullGroups = JSON.parse(root.dataset.groups || '[]'); } catch (e) { fullGroups = []; }
    allGroups  = fullGroups.slice();
    totalPages = Math.max(1, Math.ceil(allGroups.length / PER_PAGE));

    var listEl      = document.getElementById('mjl-ej-list');
    var showMoreBtn = document.getElementById('mjl-ej-show-more');
    var paginator   = document.querySelector('.mjl-ej-paginator');
    var clearBtn    = document.getElementById('mjl-ej-clear-btn');
    var searchInput = document.getElementById('mjl-ej-search');
    var searchBtn   = document.getElementById('mjl-ej-search-btn');
    var searchClear = document.getElementById('mjl-ej-search-clear');

    /* ── Helpers ── */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    /* ── Level-2: sends sub-table ── */
    function buildSendsTable(sends) {
        if (!sends || sends.length === 0) {
            return '<div class="mjl-ej-send-empty">—</div>';
        }
        var html = '<div class="mjl-ej-sends">'
            + '<div class="mjl-ej-send-header">'
            + '<span>' + esc(i18n.rule)      + '</span>'
            + '<span>' + esc(i18n.event)     + '</span>'
            + '<span>' + esc(i18n.recipient) + '</span>'
            + '<span>' + esc(i18n.status)    + '</span>'
            + '<span>' + esc(i18n.date)      + '</span>'
            + '</div>';

        sends.forEach(function (s) {
            var ok          = s.http_status === 200;
            var isSimulated = s.simulate && s.http_status === 0;
            var statusClass = ok ? 'bg-success' : (isSimulated ? 'bg-secondary' : 'bg-danger');
            var statusText  = ok ? '200 OK' : (isSimulated ? i18n.simulated : (s.http_status > 0 ? s.http_status : '—'));
            var errAttr     = s.error ? ' title="' + esc(s.error) + '"' : '';

            html += '<div class="mjl-ej-send-row"' + errAttr + '>'
                + '<span class="mjl-ej-send-rule" title="' + esc(s.rule_name) + '">' + esc(s.rule_name) + '</span>'
                + '<span class="mjl-ej-send-event"><span class="badge bg-primary">' + esc(s.event) + '</span></span>'
                + '<span class="mjl-ej-send-recipient" title="' + esc(s.recipient) + '">' + esc(s.recipient) + '</span>'
                + '<span class="mjl-ej-send-status"><span class="badge ' + statusClass + '">' + esc(statusText) + '</span></span>'
                + '<span class="mjl-ej-send-date">' + esc(s.date_creation) + '</span>'
                + '</div>';
        });

        html += '</div>';
        return html;
    }

    /* ── Level-1: group row ── */
    function buildGroupRow(group, withAppear) {
        var rowClass = 'mjl-ej-row' + (withAppear ? ' mjl-row-appearing' : '');

        var countOk   = group.count_ok   || 0;
        var countFail = group.count_fail || 0;
        var countHtml = '<span class="mjl-ej-count-ok">' + countOk + '</span>'
            + '<span class="mjl-ej-count-sep">/</span>'
            + '<span class="mjl-ej-count-fail">' + countFail + '</span>';

        var ticketName = group.ticket_name || '—';
        var row = '<div class="' + rowClass + '" data-ej-key="' + esc(group.key) + '">'
            + '<span class="mjl-ej-chevron"><i class="ti ti-chevron-right"></i></span>'
            + '<span class="mjl-ej-ticket"><strong>' + esc(group.target_id) + '</strong></span>'
            + '<span class="mjl-ej-target"><i class="ti ti-ticket"></i><span class="badge">' + esc(group.target) + '</span></span>'
            + '<span class="mjl-ej-name"><i class="ti ti-file-text"></i><strong title="' + esc(ticketName) + '">' + esc(ticketName) + '</strong></span>'
            + '<span class="mjl-ej-initiator"><i class="ti ti-user"></i>' + esc(group.initiator || '—') + '</span>'
            + '<span class="mjl-ej-urgency"><i class="ti ti-flame"></i>' + esc(group.urgency || '—') + '</span>'
            + '<span class="mjl-ej-count"><i class="ti ti-bell"></i>' + countHtml + '</span>'
            + '<span class="mjl-ej-date"><i class="ti ti-clock"></i>' + esc(group.last_date) + '</span>'
            + '</div>';

        var detail = '<div class="mjl-ej-detail mjl-hidden" data-ej-detail="' + esc(group.key) + '">'
            + buildSendsTable(group.sends)
            + '</div>';

        return row + detail;
    }

    function clearRows() {
        listEl.querySelectorAll('.mjl-ej-row, .mjl-ej-detail, .mjl-ej-empty').forEach(function (el) {
            el.remove();
        });
    }

    function showEmpty() {
        var div = document.createElement('div');
        div.className = 'mjl-ej-empty';
        div.style.gridColumn = '1 / -1';
        div.textContent = i18n.noLogs;
        listEl.appendChild(div);
    }

    function renderPage(page, animateNew) {
        var start = (page - 1) * PER_PAGE;
        var batch = allGroups.slice(start, start + PER_PAGE);
        var html  = '';
        batch.forEach(function (g) { html += buildGroupRow(g, animateNew); });
        listEl.insertAdjacentHTML('beforeend', html);
        bindRowClicks();
    }

    function goToPage(page) {
        page = Math.max(1, Math.min(page, totalPages));
        currentPage = page;
        clearRows();
        if (allGroups.length === 0) { showEmpty(); } else { renderPage(page, false); }
        updatePaginator();
        updateShowMore();
    }

    /* ── Expand/collapse ── */
    function bindRowClicks() {
        listEl.querySelectorAll('.mjl-ej-row').forEach(function (row) {
            if (row._mjlBound) return;
            row._mjlBound = true;
            row.addEventListener('click', function () {
                var key    = row.dataset.ejKey;
                var detail = listEl.querySelector('[data-ej-detail="' + key + '"]');
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
        allGroups = q === ''
            ? fullGroups.slice()
            : fullGroups.filter(function (g) { return String(g.target_id).indexOf(q) !== -1; });
        totalPages = Math.max(1, Math.ceil(allGroups.length / PER_PAGE));
        goToPage(1);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function () { applySearch(searchInput ? searchInput.value : ''); });
    }
    var searchForm = document.getElementById('mjl-ej-search-form');
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
        var maxVisible = 5, half = Math.floor(maxVisible / 2);
        var start = Math.max(1, currentPage - half);
        var end   = Math.min(totalPages, start + maxVisible - 1);
        if (end - start + 1 < maxVisible) { start = Math.max(1, end - maxVisible + 1); }

        for (var p = start; p <= end; p++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mjl-page-link' + (p === currentPage ? ' active' : '');
            btn.dataset.pageNum = p;
            btn.textContent = p;
            (function (pg) { btn.addEventListener('click', function () { goToPage(pg); }); }(p));
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

    if (paginator) {
        var prevBtn = paginator.querySelector('[data-page="prev"]');
        var nextBtn = paginator.querySelector('[data-page="next"]');
        if (prevBtn) prevBtn.addEventListener('click', function () { goToPage(currentPage - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToPage(currentPage + 1); });
    }

    /* ── Clear log ── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('mattermost_ajax', 'clear_send_log');
            fd.append('_glpi_csrf_token', csrfToken);
            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.csrf_token) { csrfToken = data.csrf_token; }
                    if (data.ok) {
                        fullGroups = []; allGroups = [];
                        totalPages = 1; currentPage = 1;
                        if (searchInput) searchInput.value = '';
                        clearRows(); showEmpty();
                        updatePaginator(); updateShowMore();
                        if (typeof glpi_toast_info === 'function') { glpi_toast_info(i18n.cleared); }
                    }
                }).catch(function () {});
        });
    }

    /* ── Initial render ── */
    goToPage(1);
};
