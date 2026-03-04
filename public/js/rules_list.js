/**
 * Rules List tab — select-all, mass actions, pagination, "show more".
 */
window.mjlRulesListInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-rules-list-root');
    if (!root) return;

    var i18n = {
        selectRule:    root.dataset.i18nSelectRule    || '',
        selectOne:     root.dataset.i18nSelectOne     || '',
        confirmDelete: root.dataset.i18nConfirmDelete || '',
        deleteBtn:     root.dataset.i18nDeleteBtn     || 'Delete',
        cancel:        root.dataset.i18nCancel        || 'Cancel',
        cloned:        root.dataset.i18nCloned        || '',
        updated:       root.dataset.i18nUpdated       || '',
        deleted:       root.dataset.i18nDeleted       || '',
        yes:           root.dataset.i18nYes          || 'Yes',
        no:            root.dataset.i18nNo           || 'No',
        rawOn:         root.dataset.i18nRawOn        || '',
        rawOff:        root.dataset.i18nRawOff        || '',
        filterOn:      root.dataset.i18nFilterOn     || '',
        filterOff:     root.dataset.i18nFilterOff    || '',
        select:        root.dataset.i18nSelect       || '',
        edit:         root.dataset.i18nEdit          || '',
    };

    var perPage     = parseInt(root.dataset.perPage, 10) || 15;
    var fullRules   = [];
    var allRules    = [];
    var totalPages  = 1;
    var ajaxUrl     = root.dataset.ajaxUrl || '';
    var editorBase  = root.dataset.editorBase || '';
    var rulesListUrl = root.dataset.rulesListUrl || '';
    var searchInput = document.getElementById('mjl-search-input');
    var searchForm  = document.getElementById('mjl-search-form');
    try { fullRules = JSON.parse(root.dataset.allRules || '[]'); } catch (e) {}
    allRules = fullRules.slice();

    var currentPage  = parseInt(root.dataset.initialPage, 10) || 1;
    var currentSort  = root.dataset.initialSort || 'date_creation';
    var currentOrder = root.dataset.initialOrder || 'desc';
    var grid         = document.querySelector('.mjl-rules-list');
    var header       = grid ? grid.querySelector('.mjl-rules-header') : null;
    var showMoreBtn  = document.getElementById('mjl-show-more');
    var paginator    = document.querySelector('.mjl-paginator');

    /* ── Helpers ── */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function buildRow(rule, withAppear) {
        var active = rule.active, useRaw = rule.use_raw, hasFilter = rule.has_filter;
        var rowClass = 'mjl-rule-row' + (withAppear ? ' mjl-row-appearing' : '');
        return '<div class="' + rowClass + '">'
            + '<span class="d-flex align-items-center justify-content-center">'
            + '<input type="checkbox" name="mjl_rule_ids[]" value="' + rule.id + '" class="form-check-input mjl-rule-cb" aria-label="' + esc(i18n.select) + '">'
            + '</span>'
            + '<span class="rule-edit">'
            + '<a href="' + esc(rule.edit_url || '#') + '" class="btn btn-sm btn-ghost-secondary mjl-edit-rule" data-rule-id="' + rule.id + '" title="' + esc(i18n.edit) + '"><i class="ti ti-pencil"></i></a>'
            + '</span>'
            + '<span class="rule-name">' + esc(rule.name) + '</span>'
            + '<span class="rule-target"><span class="badge">' + esc(rule.target) + '</span></span>'
            + '<span class="rule-event"><span class="badge bg-primary">' + esc(rule.event) + '</span></span>'
            + '<span class="rule-recipient" title="' + esc(rule.recipient) + '">' + esc(rule.recipient) + '</span>'
            + '<span class="rule-raw" title="' + esc(useRaw ? i18n.rawOn : i18n.rawOff) + '">'
            + '<span class="mjl-indicator-' + (useRaw ? 'on' : 'off') + '"><i class="ti ti-' + (useRaw ? 'code' : 'code-off') + '"></i></span>'
            + '</span>'
            + '<span class="rule-filtering" title="' + esc(hasFilter ? i18n.filterOn : i18n.filterOff) + '">'
            + '<span class="mjl-indicator-' + (hasFilter ? 'on' : 'off') + '"><i class="ti ti-' + (hasFilter ? 'filter' : 'filter-off') + '"></i></span>'
            + '</span>'
            + '<span class="rule-date">' + esc(rule.date_creation) + '</span>'
            + '<span class="rule-active">'
            + '<span class="badge ' + (active ? 'badge-active-yes' : 'badge-active-no') + '">' + esc(active ? i18n.yes : i18n.no) + '</span>'
            + '</span>'
            + '</div>';
    }

    function clearRows() {
        grid.querySelectorAll('.mjl-rule-row').forEach(function (r) { r.remove(); });
        var empty = grid.querySelector('.mjl-empty-state');
        if (empty) empty.remove();
    }

    function filterBySearch(term) {
        term = (term || '').trim().toLowerCase();
        if (term === '') {
            allRules = fullRules.slice();
        } else {
            allRules = fullRules.filter(function (r) {
                return ((r.name || '').toLowerCase().indexOf(term) !== -1);
            });
        }
        totalPages = Math.max(1, Math.ceil(allRules.length / perPage));
    }

    function showEmptyState(hasSearch) {
        var msg = hasSearch ? (root.dataset.i18nNoSearchMatch || '') : (root.dataset.i18nNoRules || '');
        var div = document.createElement('div');
        div.className = 'p-4 text-center text-muted mjl-empty-state';
        div.style.cssText = 'grid-column: 1 / -1;';
        div.textContent = msg;
        grid.appendChild(div);
    }

    function sortRules() {
        var key = currentSort;
        var ord = currentOrder;
        allRules.sort(function (a, b) {
            var va = key === 'date_creation' ? (a.date_creation_raw || '') : (a[key] ?? '');
            var vb = key === 'date_creation' ? (b.date_creation_raw || '') : (b[key] ?? '');
            if (key === 'active') { va = va ? 1 : 0; vb = vb ? 1 : 0; }
            var cmp = (typeof va === 'number' && typeof vb === 'number') ? va - vb
                : ('' + va).localeCompare('' + vb, undefined, { numeric: true });
            return ord === 'desc' ? -cmp : cmp;
        });
    }

    function updateSortHeader() {
        if (!header) return;
        header.querySelectorAll('.mjl-sortable').forEach(function (th) {
            var key = th.dataset.sortKey;
            var link = th.querySelector('.mjl-sort-link');
            var icon = link ? link.querySelector('.ti') : null;
            th.classList.toggle('active', key === currentSort);
            if (icon) {
                icon.className = 'ti ti-sort-' + (currentSort === key && currentOrder === 'desc' ? 'descending' : 'ascending');
            }
        });
    }

    function applySort(key) {
        if (key === currentSort) {
            currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = key;
            currentOrder = 'asc';
        }
        sortRules();
        updateSortHeader();
        goToPage(currentPage);
    }

    function renderPage(page, animateNew) {
        var start = (page - 1) * perPage;
        var batch = allRules.slice(start, start + perPage);
        var html = '';
        batch.forEach(function (rule) { html += buildRow(rule, animateNew); });
        grid.insertAdjacentHTML('beforeend', html);
    }

    function goToPage(page) {
        page = Math.max(1, Math.min(page, totalPages));
        currentPage = page;
        clearRows();
        if (allRules.length === 0) {
            showEmptyState((searchInput && searchInput.value.trim()) !== '');
        } else {
            renderPage(page);
        }
        updatePaginator();
        updateShowMore();
        bindSelectAll();
    }

    function updatePaginator() {
        if (!paginator) return;
        paginator.querySelectorAll('.mjl-page-link[data-page]').forEach(function (link) {
            var p = link.dataset.page;
            if (p === 'prev') {
                link.classList.toggle('disabled', currentPage <= 1);
            } else if (p === 'next') {
                link.classList.toggle('disabled', currentPage >= totalPages);
            } else {
                var num = parseInt(p, 10);
                link.classList.toggle('active', num === currentPage);
            }
        });
    }

    function updateShowMore() {
        if (!showMoreBtn) return;
        showMoreBtn.style.display = (currentPage >= totalPages) ? 'none' : '';
    }

    /* ── Search (client-side, no reload) ── */
    function applySearch() {
        var term = searchInput ? searchInput.value : '';
        filterBySearch(term);
        sortRules();
        updateSortHeader();
        goToPage(1);
    }
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applySearch();
        });
    }
    if (searchInput && document.querySelector('.mjl-search-btn')) {
        document.querySelector('.mjl-search-btn').addEventListener('click', function () {
            applySearch();
        });
    }
    var initialSearch = (root.dataset.initialSearch || '').trim();
    if (initialSearch) {
        filterBySearch(initialSearch);
        if (searchInput) searchInput.value = root.dataset.initialSearch;
    }
    totalPages = Math.max(1, Math.ceil(allRules.length / perPage));

    /* ── Sort header (client-side, no reload) ── */
    if (header) {
        sortRules();
        header.addEventListener('click', function (e) {
            var link = e.target.closest('.mjl-sort-link');
            if (!link) return;
            e.preventDefault();
            e.stopPropagation();
            applySort(link.dataset.sort);
        }, true);
    }

    /* ── Select-all ── */
    function bindSelectAll() {
        var selectAll = document.getElementById('mjl-rules-select-all');
        var rowCbs    = document.querySelectorAll('.mjl-rule-cb');
        if (!selectAll || !rowCbs.length) return;
        function update() {
            var checked = document.querySelectorAll('.mjl-rule-cb:checked');
            selectAll.checked = checked.length === rowCbs.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < rowCbs.length;
        }
        selectAll.onchange = function () {
            document.querySelectorAll('.mjl-rule-cb').forEach(function (cb) { cb.checked = selectAll.checked; });
        };
        rowCbs.forEach(function (cb) { cb.addEventListener('change', update); });
    }
    bindSelectAll();

    /* ── Mass actions ── */
    function toast(fn, text) {
        if (typeof fn === 'function') fn(text); else alert(text);
    }
    function getCsrfToken() {
        var inp = document.querySelector('input[name="_glpi_csrf_token"]');
        return inp ? inp.value : '';
    }
    function doDelete(ids, btn) {
        var fd = new FormData();
        fd.set('mattermost_ajax', 'mass_delete');
        ids.forEach(function (id) { fd.append('rule_ids[]', id); });
        fd.set('_glpi_csrf_token', getCsrfToken());
        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
                    .then(function (data) {
                        btn.disabled = false;
                        if (data && data.ok) {
                            if (typeof window.glpi_toast_info === 'function') window.glpi_toast_info(i18n.deleted);
                            if (rulesListUrl) { window.location.href = rulesListUrl; } else { window.location.reload(); }
                        } else {
                    toast(window.glpi_toast_error, (data && data.error) || 'Failed to delete');
                }
            })
            .catch(function () {
                btn.disabled = false;
                toast(window.glpi_toast_error, 'Request failed');
            });
    }
    document.querySelectorAll('.mjl-mass-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action  = btn.dataset.action;
            var checked = document.querySelectorAll('.mjl-rule-cb:checked');
            var ids     = Array.prototype.map.call(checked, function (cb) { return cb.value; });
            if (action === 'clone_selected') {
                if (ids.length === 0) { toast(window.glpi_toast_error, i18n.selectRule); return; }
                if (ids.length > 1)   { toast(window.glpi_toast_error, i18n.selectOne);  return; }
            } else if (ids.length === 0) {
                toast(window.glpi_toast_error, i18n.selectRule); return;
            }
            if (!ajaxUrl) { toast(window.glpi_toast_error, 'AJAX URL not configured'); return; }
            btn.disabled = true;
            if (action === 'clone_selected') {
                var fd = new FormData();
                fd.set('mattermost_ajax', 'clone_rule');
                fd.set('rule_id', ids[0]);
                fd.set('_glpi_csrf_token', getCsrfToken());
                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        btn.disabled = false;
                        if (data && data.ok && data.new_id) {
                            if (typeof window.glpi_toast_info === 'function') window.glpi_toast_info(i18n.cloned);
                            window.location.href = editorBase + '&edit=' + data.new_id;
                        } else {
                            toast(window.glpi_toast_error, (data && data.error) || 'Failed to clone');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        toast(window.glpi_toast_error, 'Request failed');
                    });
            } else if (action === 'enable_selected' || action === 'disable_selected') {
                var fd = new FormData();
                fd.set('mattermost_ajax', 'mass_update');
                fd.set('mass_action', action === 'enable_selected' ? 'enable' : 'disable');
                ids.forEach(function (id) { fd.append('rule_ids[]', id); });
                fd.set('_glpi_csrf_token', getCsrfToken());
                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        btn.disabled = false;
                        if (data && data.ok) {
                            if (typeof window.glpi_toast_info === 'function') window.glpi_toast_info(i18n.updated);
                            if (rulesListUrl) { window.location.href = rulesListUrl; } else { window.location.reload(); }
                        } else {
                            toast(window.glpi_toast_error, (data && data.error) || 'Failed to update');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        toast(window.glpi_toast_error, 'Request failed');
                    });
            } else if (action === 'delete_selected') {
                function reenableBtn() { btn.disabled = false; }
                if (typeof window.glpi_html_dialog === 'function') {
                    window.glpi_html_dialog({
                        title: i18n.confirmDelete,
                        body: i18n.confirmDelete,
                        buttons: [
                            { label: i18n.cancel, class: 'btn-secondary', click: reenableBtn },
                            { label: i18n.deleteBtn, class: 'btn-danger', click: function () { doDelete(ids, btn); } }
                        ],
                        close: reenableBtn
                    });
                } else if (typeof window.glpi_confirm === 'function') {
                    window.glpi_confirm({
                        title: i18n.confirmDelete,
                        message: i18n.confirmDelete,
                        confirm_callback: function () { doDelete(ids, btn); },
                        cancel_callback: reenableBtn,
                        close_callback: reenableBtn
                    });
                } else {
                    if (!confirm(i18n.confirmDelete)) { reenableBtn(); return; }
                    doDelete(ids, btn);
                }
                return;
            } else {
                btn.disabled = false;
            }
        });
    });

    /* ── Paginator clicks ── */
    if (paginator) {
        paginator.addEventListener('click', function (e) {
            var link = e.target.closest('.mjl-page-link');
            if (!link) return;
            e.preventDefault();
            var p = link.dataset.page;
            if (p === 'prev') { goToPage(currentPage - 1); }
            else if (p === 'next') { goToPage(currentPage + 1); }
            else { goToPage(parseInt(p, 10)); }
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
            bindSelectAll();
        });
    }

    /* ── Initial state ── */
    updatePaginator();
    updateShowMore();
};
