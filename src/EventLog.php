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
 * Stores hook event log entries for display on the Debug tab.
 */
class EventLog extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return 'Event Log';
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
}
