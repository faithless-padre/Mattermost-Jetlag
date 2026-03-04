<?php

/**
 * -------------------------------------------------------------------------
 * Mattermost Jetlag plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * @copyright  Copyright (C) 2025
 * @license    MIT https://opensource.org/licenses/MIT
 * @link       https://github.com/faithless-padre
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Mattermostjetlag\Config;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\EventLog;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class DebugModeTab
{
    private const TEMPLATE  = '@mattermostjetlag/config/debug_mode.html.twig';
    private const LOG_LIMIT = 200;

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        global $DB;

        $fields       = $config->fields ?? [];
        $extended_log = (int) ($fields['extended_log'] ?? 0) === 1;

        $log_items = [];
        $table = EventLog::getTable();
        if ($DB->tableExists($table)) {
            $rows = $DB->request([
                'FROM'  => $table,
                'ORDER' => ['id DESC'],
                'LIMIT' => self::LOG_LIMIT,
            ]);
            $eventLabels = EditorTab::RULE_EVENTS;
            foreach ($rows as $row) {
                $eventKey   = (string) ($row['event'] ?? '');
                $payloadArr = json_decode((string) ($row['payload'] ?? '{}'), true) ?? [];
                $rulesCount = is_array($payloadArr['matching_rule_ids'] ?? null)
                    ? count($payloadArr['matching_rule_ids'])
                    : 0;
                $log_items[] = [
                    'id'            => (int) $row['id'],
                    'target'        => (string) ($row['target'] ?? 'Ticket'),
                    'event'         => $eventLabels[$eventKey] ?? $eventKey,
                    'ticket_id'     => $row['ticket_id'] ? (int) $row['ticket_id'] : null,
                    'payload'       => (string) ($row['payload'] ?? '{}'),
                    'date_creation' => (string) ($row['date_creation'] ?? ''),
                    'type'          => (string) ($payloadArr['type'] ?? ''),
                    'urgency'       => (string) ($payloadArr['urgency'] ?? ''),
                    'priority'      => (string) ($payloadArr['priority'] ?? ''),
                    'status'        => (string) ($payloadArr['status'] ?? ''),
                    'rules_count'   => $rulesCount,
                ];
            }
        }

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'config_id'    => (int) ($fields['id'] ?? 1),
            'extended_log' => $extended_log,
            'form_action'  => Toolbox::getItemTypeFormURL(Config::getType()),
            'can_update'   => Config::canCreate(),
            'log_items'    => $log_items,
            'log_items_json' => json_encode($log_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ajax_url'     => Toolbox::getItemTypeFormURL(Config::getType()),
        ]);
    }
}
