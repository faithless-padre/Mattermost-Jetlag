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

namespace GlpiPlugin\Mattermostjetlag;

use CommonDBTM;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Stores individual notification send results for display on the Events Journal tab.
 * One row = one recipient of one rule for one event.
 */
class SendLog extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return 'Send Log';
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    /**
     * Insert one send result and keep only the newest 500 rows.
     */
    public static function record(
        string $target,
        int $targetId,
        string $event,
        int $ruleId,
        string $recipient,
        int $httpStatus,
        bool $simulate,
        ?string $error
    ): void {
        global $DB;

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            return;
        }

        $DB->insert($table, [
            'target'        => $target,
            'target_id'     => $targetId,
            'event'         => $event,
            'rule_id'       => $ruleId,
            'recipient'     => $recipient,
            'http_status'   => $httpStatus,
            'simulate'      => $simulate ? 1 : 0,
            'error'         => $error,
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
}
