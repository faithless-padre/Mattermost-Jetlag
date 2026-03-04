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
 * Запись события в БД при extended_log = 1.
 */
function plugin_mattermostjetlag_log_ticket(array $data): void
{
    if (!plugin_mattermostjetlag_extended_log_enabled()) {
        return;
    }

    if (!isset($data['datetime'])) {
        $data['datetime'] = date('c');
    }
    $data['_seq']       = plugin_mattermostjetlag_next_log_seq();
    $data['_microtime'] = microtime(true);

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

/** @deprecated File logging removed; events are stored in DB via log_ticket(). */
function plugin_mattermostjetlag_log_debug(
    string $hook,
    string $action,
    CommonDBTM $item,
    ?CommonDBTM $ticket = null,
    array $builtData = []
): void {
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
