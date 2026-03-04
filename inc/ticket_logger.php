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

use CommonDBTM;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\EventLog;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Запись лога срабатываний правил в matching.log (JSON).
 * Запись только при extended_log = 1.
 */
function plugin_mattermostjetlag_log_ticket(array $data): void
{
    if (!plugin_mattermostjetlag_extended_log_enabled()) {
        return;
    }

    $logDir  = GLPI_ROOT . '/files/_log/mattermostjetlag';
    $logFile = $logDir . '/matching.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0770, true);
    }

    if (!isset($data['datetime'])) {
        $data['datetime'] = date('c');
    }
    $data['_seq'] = plugin_mattermostjetlag_next_log_seq();
    $data['_microtime'] = microtime(true);

    $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    plugin_mattermostjetlag_log_ticket_to_db($data);
}

/**
 * Запись события в таблицу БД (только при extended_log = 1).
 * После вставки обрезает таблицу до 500 последних записей.
 */
function plugin_mattermostjetlag_log_ticket_to_db(array $data): void
{
    global $DB;

    $table = EventLog::getTable();
    if (!$DB->tableExists($table)) {
        return;
    }

    $DB->insert($table, [
        'target'        => 'Ticket',
        'event'         => (string) ($data['event'] ?? ''),
        'ticket_id'     => ($data['ticket_id'] ?? null) > 0 ? (int) $data['ticket_id'] : null,
        'payload'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'date_creation' => date('Y-m-d H:i:s'),
    ]);

    // Keep only the newest 500 rows
    $DB->doQuery(
        "DELETE FROM `$table` WHERE `id` NOT IN (
            SELECT `id` FROM (
                SELECT `id` FROM `$table` ORDER BY `id` DESC LIMIT 500
            ) AS `_keep`
        )"
    );
}

/**
 * Запись отладочного лога сработавших хуков в debug.log (блочный формат).
 * Все доступные поля item, input, ticket.
 */
function plugin_mattermostjetlag_log_debug(
    string $hook,
    string $action,
    CommonDBTM $item,
    ?CommonDBTM $ticket = null,
    array $builtData = []
): void {
    if (!plugin_mattermostjetlag_extended_log_enabled()) {
        return;
    }

    $logDir  = GLPI_ROOT . '/files/_log/mattermostjetlag';
    $logFile = $logDir . '/debug.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0770, true);
    }

    $flatten = function ($v): string {
        if (is_array($v) || is_object($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) ($v ?? '');
    };

    $lines = [
        '----BEGIN EVENT----',
        'seq: ' . plugin_mattermostjetlag_next_log_seq(),
        'microtime: ' . microtime(true),
        'datetime: ' . date('c'),
        'hook: ' . $hook,
        'action: ' . $action,
        'itemtype: ' . $item::getType(),
        'item_id: ' . (method_exists($item, 'getID') ? $item->getID() : ($item->fields['id'] ?? '')),
    ];

    foreach ($item->fields ?? [] as $k => $v) {
        $lines[] = 'item_field_' . $k . ': ' . $flatten($v);
    }

    if (property_exists($item, 'input') && is_array($item->input ?? null)) {
        $lines[] = 'item_input: ' . $flatten($item->input);
    }

    if ($ticket !== null && $ticket::getType() === 'Ticket') {
        $lines[] = '--- linked_ticket ---';
        foreach ($ticket->fields ?? [] as $k => $v) {
            $lines[] = 'ticket_field_' . $k . ': ' . $flatten($v);
        }
        if (property_exists($ticket, 'input') && is_array($ticket->input ?? null)) {
            $lines[] = 'ticket_input: ' . $flatten($ticket->input);
        }
    }

    if (!empty($builtData)) {
        $lines[] = '--- built_data ---';
        foreach ($builtData as $k => $v) {
            $lines[] = $k . ': ' . $flatten($v);
        }
    }

    $lines[] = '----END EVENT----';
    $block = implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL;
    @file_put_contents($logFile, $block, FILE_APPEND | LOCK_EX);
}

function plugin_mattermostjetlag_next_log_seq(): int
{
    static $seq = 0;
    return ++$seq;
}

function plugin_mattermostjetlag_extended_log_enabled(): bool
{
    $config = new Config();
    if (!$config->getFromDB(1)) {
        return false;
    }
    return (int) ($config->fields['extended_log'] ?? 0) === 1;
}
