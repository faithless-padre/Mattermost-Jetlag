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
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use GlpiPlugin\Mattermostjetlag\SendLog;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class EventJournalTab
{
    private const TEMPLATE  = '@mattermostjetlag/config/event_journal.html.twig';
    private const LOG_LIMIT = 500;

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        global $DB;

        $log_groups = [];
        $table = SendLog::getTable();

        if ($DB->tableExists($table)) {
            // Fetch rule names
            $ruleNames = [];
            foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => NotificationRule::getTable()]) as $r) {
                $ruleNames[(int) $r['id']] = $r['name'];
            }

            $eventLabels = EditorTab::RULE_EVENTS;

            // Fetch recent rows, newest first
            $rows = $DB->request([
                'FROM'  => $table,
                'ORDER' => ['id DESC'],
                'LIMIT' => self::LOG_LIMIT,
            ]);

            // Group by (target, target_id)
            $grouped = [];
            foreach ($rows as $row) {
                $target   = (string) ($row['target']    ?? 'Ticket');
                $targetId = (int)    ($row['target_id'] ?? 0);
                $key      = $target . ':' . $targetId;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'key'         => $key,
                        'target'      => $target,
                        'target_id'   => $targetId,
                        'ticket_name' => (string) ($row['ticket_name'] ?? ''),
                        'urgency'     => (string) ($row['urgency'] ?? ''),
                        'count_ok'    => 0,
                        'count_fail'  => 0,
                        'last_date'   => (string) ($row['date_creation'] ?? ''),
                        'sends'       => [],
                    ];
                }

                $httpStatus = (int) ($row['http_status'] ?? 0);
                $isSimulate = (int) ($row['simulate'] ?? 0) === 1;
                $isOk = $isSimulate || ($httpStatus >= 200 && $httpStatus < 300);
                if ($isOk) {
                    $grouped[$key]['count_ok']++;
                } else {
                    $grouped[$key]['count_fail']++;
                }
                $eventKey = (string) ($row['event'] ?? '');
                $grouped[$key]['sends'][] = [
                    'rule_name'     => $ruleNames[(int) ($row['rule_id'] ?? 0)] ?? ('#' . (int) ($row['rule_id'] ?? 0)),
                    'event'         => $eventLabels[$eventKey] ?? $eventKey,
                    'recipient'     => (string) ($row['recipient']   ?? ''),
                    'http_status'   => (int)    ($row['http_status'] ?? 0),
                    'simulate'      => (int)    ($row['simulate']    ?? 0) === 1,
                    'error'         => $row['error'] ? (string) $row['error'] : null,
                    'date_creation' => (string) ($row['date_creation'] ?? ''),
                ];
            }

            // Most recently active first
            usort($grouped, fn($a, $b) => strcmp($b['last_date'], $a['last_date']));

            $log_groups = array_values($grouped);
        }

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'log_groups'      => $log_groups,
            'log_groups_json' => json_encode($log_groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ajax_url'        => Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1',
        ]);
    }
}
