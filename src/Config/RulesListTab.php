<?php

namespace GlpiPlugin\Mattermostjetlag\Config;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\CriteriaFilter;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use GlpiPlugin\Mattermostjetlag\Config\EditorTab;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class RulesListTab
{
    private const TEMPLATE = '@mattermostjetlag/config/rules_list.html.twig';

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        $tab_editor = Config::getType() . '$3';
        $tab_rules  = Config::getType() . '$2';
        $editor_base = Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_editor);
        $rules_base  = Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rules);

        $sort_keys = ['name', 'target', 'event', 'recipient', 'date_creation', 'active'];
        $sort  = $_GET['sort'] ?? 'date_creation';
        $order = strtolower($_GET['order'] ?? 'desc');
        if (!in_array($sort, $sort_keys, true)) {
            $sort = 'date_creation';
        }
        if ($order !== 'asc' && $order !== 'desc') {
            $order = 'desc';
        }
        $search_raw = trim($_GET['mjl_search'] ?? '');

        global $DB;
        $fmt = 'd.m.y H:i';
        $iterator = $DB->request(['FROM' => NotificationRule::getTable()]);
        $rule_ids = [];
        $rules = [];
        foreach ($iterator as $row) {
            $rule_ids[] = (int) $row['id'];
            $rules[] = [
                'id'                 => (int) $row['id'],
                'name'               => $row['name'] ?? '',
                'target'             => $row['target'] ?? 'Ticket',
                'event'              => $row['event'] ?? 'New',
                'recipient'          => $row['recipient'] ?? '',
                'use_raw'            => (int) ($row['use_raw_payload'] ?? 0) === 1,
                'has_filter'         => false,
                'date_creation'      => isset($row['date_creation']) && $row['date_creation'] ? date($fmt, strtotime($row['date_creation'])) : '',
                'date_creation_raw'  => $row['date_creation'] ?? '',
                'active'             => (int) ($row['active'] ?? 1) === 1,
            ];
        }

        $cf_table = CriteriaFilter::getTable();
        if (!empty($rule_ids) && $DB->tableExists($cf_table)) {
            $cf_iterator = $DB->request([
                'SELECT' => ['items_id'],
                'FROM'   => $cf_table,
                'WHERE'  => [
                    'itemtype' => NotificationRule::class,
                    'items_id' => $rule_ids,
                ],
            ]);
            $ids_with_filter = [];
            foreach ($cf_iterator as $cf_row) {
                $ids_with_filter[(int) $cf_row['items_id']] = true;
            }
            foreach ($rules as &$r) {
                $r['has_filter'] = isset($ids_with_filter[$r['id']]);
            }
            unset($r);
        }

        foreach ($rules as &$r) {
            $r['edit_url'] = $editor_base . '&edit=' . $r['id'];
        }
        unset($r);

        usort($rules, function ($a, $b) use ($sort, $order) {
            $va = $sort === 'date_creation' ? ($a['date_creation_raw'] ?? '') : ($a[$sort] ?? '');
            $vb = $sort === 'date_creation' ? ($b['date_creation_raw'] ?? '') : ($b[$sort] ?? '');
            if ($sort === 'active') {
                $va = $va ? 1 : 0;
                $vb = $vb ? 1 : 0;
            }
            $cmp = is_numeric($va) && is_numeric($vb) ? $va <=> $vb : strcasecmp((string) $va, (string) $vb);
            return $order === 'desc' ? -$cmp : $cmp;
        });

        $columns = [
            'name'          => __('Rule name', 'mattermostjetlag'),
            'target'        => __('Target', 'mattermostjetlag'),
            'event'         => __('Event', 'mattermostjetlag'),
            'recipient'     => __('Recipient', 'mattermostjetlag'),
            'raw'           => __('Raw', 'mattermostjetlag'),
            'filtering'     => __('Filtering', 'mattermostjetlag'),
            'date_creation' => __('Creation date', 'mattermostjetlag'),
            'active'        => __('Active', 'mattermostjetlag'),
        ];

        $event_labels = [];
        foreach (EditorTab::RULE_EVENTS as $k => $v) {
            $event_labels[$k] = __($v, 'mattermostjetlag');
        }

        $per_page    = 15;
        $total       = count($rules);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $page        = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
        $offset      = ($page - 1) * $per_page;
        $visible     = array_slice($rules, $offset, $per_page);

        $ajax_url = Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1';

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'rules'              => $visible,
            'all_rules'          => $rules,
            'editor_add_url'     => $editor_base,
            'rules_base_url'     => $rules_base,
            'ajax_url'           => $ajax_url,
            'columns'            => $columns,
            'event_labels'       => $event_labels,
            'total'              => $total,
            'page'               => $page,
            'per_page'           => $per_page,
            'total_pages'        => $total_pages,
            'sort'               => $sort,
            'order'              => $order,
            'search'             => $search_raw,
        ]);
    }
}
