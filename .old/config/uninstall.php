<?php

/**
 * Полный код удаления плагина Mattermost: удаление таблиц.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Mattermost\Config;
use GlpiPlugin\Mattermost\NotificationRule;

/**
 * Запустить удаление плагина.
 *
 * @return bool
 */
function mattermost_run_uninstall(): bool
{
    global $DB;

    $dbu = new \DbUtils();

    // Удаляем таблицу настроек
    $config_table = $dbu->getTableForItemType(Config::class);
    if ($DB->tableExists($config_table)) {
        $DB->doQuery("DROP TABLE IF EXISTS `{$config_table}`");
    }

    // Удаляем таблицу правил
    $rules_table = $dbu->getTableForItemType(NotificationRule::class);
    if ($DB->tableExists($rules_table)) {
        $DB->doQuery("DROP TABLE IF EXISTS `{$rules_table}`");
    }

    // Чистим связанные Extended Filters из glpi_criteriafilters
    // (itemtype = GlpiPlugin\Mattermost\NotificationRule)
    if ($DB->tableExists('glpi_criteriafilters')) {
        $itemtype = $DB->escape(NotificationRule::class);
        $DB->doQuery("DELETE FROM `glpi_criteriafilters` WHERE `itemtype` = '{$itemtype}'");
    }

    return true;
}

