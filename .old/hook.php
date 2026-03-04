<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/backend/Config.php';
require_once __DIR__ . '/backend/NotificationRule.php';
require_once __DIR__ . '/backend/MattermostClient.php';
require_once __DIR__ . '/backend/TicketHook.php';

/**
 * Установка плагина: создаём таблицы настроек и правил.
 * Весь код установки находится в config/install.php.
 */
function plugin_mattermost_install()
{
    require_once __DIR__ . '/config/install.php';
    return mattermost_run_install();
}

/**
 * Удаление плагина: удаляем таблицы настроек и правил.
 * Весь код удаления находится в config/uninstall.php.
 */
function plugin_mattermost_uninstall()
{
    require_once __DIR__ . '/config/uninstall.php';
    return mattermost_run_uninstall();
}
