<?php
/**
 * Вкладка Rules List: список правил, тулбар, пагинация.
 * Ожидает $config (GlpiPlugin\Mattermost\Config).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
$rule = new \GlpiPlugin\Mattermost\NotificationRule();
$tab_all = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_ALL_RULES;
$tab_editor = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_RULE_EDITOR;
$base_url = \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_all);

$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['name', 'target', 'event', 'recipient', 'date_creation', 'active'], true) ? $_GET['sort'] : 'date_creation';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'asc' : 'desc';
$search_raw = trim((string) ($_GET['mattermost_search'] ?? ''));
$base_url .= '&sort=' . urlencode($sort) . '&order=' . urlencode($order);
if ($search_raw !== '') {
    $base_url .= '&mattermost_search=' . urlencode($search_raw);
}

$all_rules = $rule->find([], []);
if ($search_raw !== '') {
    $search_lower = mb_strtolower($search_raw);
    $all_rules = array_filter($all_rules, function ($r) use ($search_lower) {
        $name = (string) ($r['name'] ?? '');
        return $name === '' || mb_strpos(mb_strtolower($name), $search_lower) !== false;
    });
    $all_rules = array_values($all_rules);
}
$total = count($all_rules);

$sort_columns = ['name' => 'name', 'target' => 'target', 'event' => 'event', 'recipient' => 'recipient', 'date_creation' => 'date_creation', 'active' => 'active'];
$sort_field = $sort_columns[$sort];
usort($all_rules, function ($a, $b) use ($sort_field, $order) {
    $va = $a[$sort_field] ?? '';
    $vb = $b[$sort_field] ?? '';
    if ($sort_field === 'date_creation') {
        $cmp = strcmp($va, $vb);
    } elseif ($sort_field === 'active') {
        $cmp = (int) $va - (int) $vb;
    } else {
        $cmp = strcasecmp((string) $va, (string) $vb);
    }
    return $order === 'asc' ? $cmp : -$cmp;
});

$per_page = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$rules = array_slice($all_rules, $offset, $per_page);

$sort_toggle = $order === 'asc' ? 'desc' : 'asc';
$sort_url_base = \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_all);
if ($search_raw !== '') {
    $sort_url_base .= '&mattermost_search=' . urlencode($search_raw);
}
$form_action = \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType());
$search_value = htmlescape($search_raw);
$csrf = \Session::getNewCSRFToken();
// Правила с сохранённым Extended Filter (CriteriaFilter)
$rules_with_filter = [];
if (!empty($rules)) {
    $rule_ids = array_column($rules, 'id');
    $cf = new \Glpi\Search\CriteriaFilter();
    $found = $cf->find(['itemtype' => \GlpiPlugin\Mattermost\NotificationRule::class, 'items_id' => $rule_ids]);
    $rules_with_filter = array_unique(array_column($found, 'items_id'));
}
$col = '36px 44px minmax(140px, 1fr) 100px 80px minmax(120px, 1fr) 52px 70px 150px 70px';

$th_class = function ($c) use ($sort) { return 'mattermost-th' . ($sort === $c ? ' active' : ''); };
$th_link = function ($c, $label) use ($sort_url_base, $sort_toggle, $sort, $order) {
    $active = ($sort === $c);
    $order_val = $active ? $sort_toggle : 'asc';
    $url = $sort_url_base . '&sort=' . urlencode($c) . '&order=' . $order_val;
    $arrow = $active ? ($order === 'asc' ? 'ti-arrow-up' : 'ti-arrow-down') : 'ti-arrows-sort';
    return "<a href='" . htmlescape($url) . "' title='Sort'><span>" . htmlescape($label) . "</span><i class='ti " . $arrow . "'></i></a>";
};
?>
<div class="card border-0 shadow-none p-0 m-0 mt-2">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-list"></i></div>
<?php echo htmlescape('Rules List'); ?>
</h4>
</div>
</div>

<div class="mattermost-rules-toolbar card border mb-3" data-mass-action-url="<?php echo htmlescape($form_action); ?>" data-csrf="<?php echo htmlescape($csrf); ?>">
<div class="card-body py-2">
<div class="d-flex flex-wrap align-items-center gap-3">
<div class="d-flex flex-wrap align-items-center gap-2 mattermost-rules-toolbar-btns">
<a href="<?php echo htmlescape(\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_editor)); ?>" class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i><?php echo htmlescape('Add new rule'); ?></a>
<button type="button" class="btn btn-sm btn-outline-secondary mattermost-mass-btn" data-action="disable_selected"><i class="ti ti-toggle-right me-1"></i><?php echo htmlescape('Disable Selected'); ?></button>
<button type="button" class="btn btn-sm btn-outline-secondary mattermost-mass-btn" data-action="enable_selected"><i class="ti ti-toggle-left me-1"></i><?php echo htmlescape('Enable Selected'); ?></button>
<button type="button" class="btn btn-sm btn-outline-secondary mattermost-mass-btn" data-action="clone_selected"><i class="ti ti-copy me-1"></i><?php echo htmlescape('Clone Selected'); ?></button>
<span class="mattermost-toolbar-divider" style="width: 2px; height: 1.5rem; background: var(--tblr-body-color-muted, #6c757d); opacity: 0.8; align-self: center; border-radius: 1px;" aria-hidden="true"></span>
<button type="button" class="btn btn-sm btn-outline-danger mattermost-mass-btn" data-action="delete_selected"><i class="ti ti-trash me-1"></i><?php echo htmlescape('Delete Selected'); ?></button>
</div>
<form method="get" action="<?php echo htmlescape($form_action); ?>" class="d-flex align-items-center gap-2 ms-auto">
<input type="hidden" name="id" value="1" />
<input type="hidden" name="_glpi_tab" value="<?php echo htmlescape($tab_all); ?>" />
<input type="hidden" name="sort" value="<?php echo htmlescape($sort); ?>" />
<input type="hidden" name="order" value="<?php echo htmlescape($order); ?>" />
<input type="text" name="mattermost_search" id="mattermost-search-input" value="<?php echo $search_value; ?>" class="form-control form-control-sm" style="width: 200px;" placeholder="<?php echo htmlescape('Rule name'); ?>" />
<button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search me-1"></i><?php echo htmlescape('Search'); ?></button>
</form>
</div>
</div>
</div>

<style>.mattermost-rules-list { grid-template-columns: <?php echo $col; ?>; }</style>

<div class="mattermost-rules-list">
<div class="mattermost-rules-header">
<span class="d-flex align-items-center justify-content-center"><input type="checkbox" id="mattermost-rules-select-all" class="form-check-input mattermost-rule-select-all" title="Select all" aria-label="Select all" /></span>
<span></span>
<span class="<?php echo $th_class('name'); ?>"><?php echo $th_link('name', 'Rule name'); ?></span>
<span class="<?php echo $th_class('target'); ?>"><?php echo $th_link('target', 'Target'); ?></span>
<span class="<?php echo $th_class('event'); ?>"><?php echo $th_link('event', 'Event'); ?></span>
<span class="<?php echo $th_class('recipient'); ?>"><?php echo $th_link('recipient', 'Recipient'); ?></span>
<span class="mattermost-th"><?php echo htmlescape('Raw'); ?></span>
<span class="mattermost-th"><?php echo htmlescape('Filtering'); ?></span>
<span class="<?php echo $th_class('date_creation'); ?>"><?php echo $th_link('date_creation', 'Creation date'); ?></span>
<span class="<?php echo $th_class('active'); ?>"><?php echo $th_link('active', 'Active'); ?></span>
</div>
<?php if ($total === 0) { ?>
<div class="p-4 text-center text-muted" style="grid-column: 1 / -1;"><?php echo $search_raw !== '' ? 'No rules match your search.' : 'No notification rules yet.'; ?></div>
<?php } else {
    foreach ($rules as $row) {
        $date = $row['date_creation'] ?? '';
        if ($date !== '') {
            $date = \Html::convDateTime($date);
        }
        $edit_url = \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_editor) . '&edit=' . (int) $row['id'];
        $name = htmlescape($row['name'] ?? '');
        $target = htmlescape($row['target'] ?? '');
        $event = htmlescape($row['event'] ?? '');
        $recipient = htmlescape($row['recipient'] ?? '');
        $active = (int) ($row['active'] ?? 1);
        $active_label = $active ? 'Yes' : 'No';
        $active_badge_class = $active ? 'badge-active-yes' : 'badge-active-no';
        $use_raw = (int) ($row['use_raw_payload'] ?? 0);
        $has_filter = in_array($row['id'], $rules_with_filter);
?>
<div class="mattermost-rule-row">
<span class="d-flex align-items-center justify-content-center"><input type="checkbox" name="mattermost_rule_ids[]" value="<?php echo (int) $row['id']; ?>" class="form-check-input mattermost-rule-cb" aria-label="Select" /></span>
<span class="rule-edit"><a href="<?php echo htmlescape($edit_url); ?>" class="btn btn-sm btn-ghost-secondary" title="Edit"><i class="ti ti-pencil"></i></a></span>
<span class="rule-name"><?php echo $name; ?></span>
<span class="rule-target"><span class="badge"><?php echo $target; ?></span></span>
<span class="rule-event"><span class="badge bg-primary"><?php echo $event; ?></span></span>
<span class="rule-recipient" title="<?php echo $recipient; ?>"><?php echo $recipient; ?></span>
<span class="rule-raw" title="<?php echo $use_raw ? 'Raw payload enabled' : 'Raw payload disabled'; ?>"><span class="mattermost-indicator-<?php echo $use_raw ? 'on' : 'off'; ?>"><i class="ti ti-<?php echo $use_raw ? 'code' : 'code-off'; ?>"></i></span></span>
<span class="rule-filtering" title="<?php echo $has_filter ? 'Extended filter is set' : 'No extended filter'; ?>"><span class="mattermost-indicator-<?php echo $has_filter ? 'on' : 'off'; ?>"><i class="ti ti-<?php echo $has_filter ? 'filter' : 'filter-off'; ?>"></i></span></span>
<span class="rule-date"><?php echo $date; ?></span>
<span class="rule-active"><span class="badge <?php echo $active_badge_class; ?>"><?php echo htmlescape($active_label); ?></span></span>
</div>
<?php }
} ?>
</div>

<script>
(function() {
    var selectAll = document.getElementById('mattermost-rules-select-all');
    var rowCbs = document.querySelectorAll('.mattermost-rule-cb');
    if (!selectAll || !rowCbs.length) return;
    function updateSelectAll() {
        var checked = document.querySelectorAll('.mattermost-rule-cb:checked');
        selectAll.checked = checked.length === rowCbs.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < rowCbs.length;
    }
    selectAll.addEventListener('change', function() { rowCbs.forEach(function(cb) { cb.checked = selectAll.checked; }); });
    rowCbs.forEach(function(cb) { cb.addEventListener('change', updateSelectAll); });
})();
</script>
<script>
(function() {
    var toolbar = document.querySelector('.mattermost-rules-toolbar');
    if (!toolbar) return;
    var actionUrl = toolbar.getAttribute('data-mass-action-url');
    var csrf = toolbar.getAttribute('data-csrf');
    toolbar.querySelectorAll('.mattermost-mass-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var action = btn.getAttribute('data-action');
            var checked = document.querySelectorAll('.mattermost-rule-cb:checked');
            var ids = Array.prototype.map.call(checked, function(cb) { return cb.value; });
            if (action === 'clone_selected') {
                if (ids.length === 0) {
                    if (typeof glpi_toast_error === 'function') glpi_toast_error(<?php echo json_encode('Select a rule to clone.'); ?>);
                    return;
                }
                if (ids.length > 1) {
                    if (typeof glpi_toast_error === 'function') glpi_toast_error(<?php echo json_encode('Select only one rule.'); ?>);
                    return;
                }
            } else if (ids.length === 0) {
                if (typeof glpi_toast_error === 'function') glpi_toast_error(<?php echo json_encode('Select at least one rule.'); ?>);
                return;
            }
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            var inputCsrf = document.createElement('input');
            inputCsrf.type = 'hidden';
            inputCsrf.name = '_glpi_csrf_token';
            inputCsrf.value = csrf;
            form.appendChild(inputCsrf);
            var inputAction = document.createElement('input');
            inputAction.type = 'hidden';
            inputAction.name = 'mattermost_rules_action';
            inputAction.value = action;
            form.appendChild(inputAction);
            ids.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'mattermost_rule_ids[]';
                inp.value = id;
                form.appendChild(inp);
            });
            document.body.appendChild(form);
            form.submit();
        });
    });
})();
</script>

<?php
// Пагинация всегда отображается, даже если страница только одна
$prev_url = $page > 1 ? $base_url . '&page=' . ($page - 1) : '';
$next_url = $page < $total_pages ? $base_url . '&page=' . ($page + 1) : '';
$start = max(1, $page - 2);
$end = min($total_pages, $page + 2);
?>
<div class="mattermost-rules-pagination">
<?php if ($prev_url !== '') { ?>
<a href="<?php echo htmlescape($prev_url); ?>" class="btn btn-sm btn-ghost-secondary"><?php echo 'Previous'; ?></a>
<?php } else { ?>
<span class="disabled"><?php echo 'Previous'; ?></span>
<?php } ?>
<?php if ($start > 1) {
    echo "<a href='" . htmlescape($base_url . '&page=1') . "'>1</a>";
    if ($start > 2) echo "<span class='ellipsis'>…</span>";
}
for ($i = $start; $i <= $end; $i++) {
    if ($i === $page) {
        echo "<span class='active'>" . $i . "</span>";
    } else {
        echo "<a href='" . htmlescape($base_url . '&page=' . $i) . "'>" . $i . "</a>";
    }
}
if ($end < $total_pages) {
    if ($end < $total_pages - 1) echo "<span class='ellipsis'>…</span>";
    echo "<a href='" . htmlescape($base_url . '&page=' . $total_pages) . "'>" . $total_pages . "</a>";
}
if ($next_url !== '') { ?>
<a href="<?php echo htmlescape($next_url); ?>" class="btn btn-sm btn-ghost-secondary"><?php echo 'Next'; ?></a>
<?php } else { ?>
<span class="disabled"><?php echo 'Next'; ?></span>
<?php } ?>
</div>
