<?php

/**
 * Полный код установки плагина Mattermost: создание/миграция таблиц.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Mattermost\Config;
use GlpiPlugin\Mattermost\NotificationRule;

/**
 * Запустить установку / миграции плагина.
 *
 * @return bool
 */
function mattermost_run_install(): bool
{
    global $DB;

    $migration = new \Migration(PLUGIN_MATTERMOST_VERSION);
    $dbu       = new \DbUtils();

    // 1. Таблица настроек плагина (Config)
    $config_table = $dbu->getTableForItemType(Config::class);

    if (!$DB->tableExists($config_table)) {
        $migration->displayMessage("Installing $config_table");
        $query = "CREATE TABLE IF NOT EXISTS `{$config_table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `connection_type` varchar(20) NOT NULL DEFAULT 'webhook',
            `webhook_url` varchar(2000) DEFAULT NULL,
            `webhook_bot_nickname` varchar(255) DEFAULT NULL,
            `webhook_bot_avatar` varchar(500) DEFAULT NULL,
            `mattermost_url` varchar(2000) DEFAULT NULL,
            `mattermost_login` varchar(255) DEFAULT NULL,
            `mattermost_password` varchar(255) DEFAULT NULL,
            `event_create` tinyint(1) NOT NULL DEFAULT 1,
            `event_update` tinyint(1) NOT NULL DEFAULT 1,
            `event_followup` tinyint(1) NOT NULL DEFAULT 1,
            `event_approval` tinyint(1) NOT NULL DEFAULT 1,
            `last_test_timestamp` int DEFAULT NULL,
            `last_test_success` tinyint(1) DEFAULT NULL,
            `last_test_error` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    } else {
        // Добавляем недостающие поля (миграции)
        if (!$DB->fieldExists($config_table, 'webhook_bot_nickname')) {
            $migration->displayMessage("Adding webhook_bot_nickname field");
            $migration->addField($config_table, 'webhook_bot_nickname', 'varchar(255) DEFAULT NULL', ['after' => 'webhook_url']);
        }
        if (!$DB->fieldExists($config_table, 'webhook_bot_avatar')) {
            $migration->displayMessage("Adding webhook_bot_avatar field");
            $migration->addField($config_table, 'webhook_bot_avatar', 'varchar(500) DEFAULT NULL', ['after' => 'webhook_bot_nickname']);
        }
        if (!$DB->fieldExists($config_table, 'last_test_timestamp')) {
            $migration->displayMessage("Adding last_test_timestamp field");
            $migration->addField($config_table, 'last_test_timestamp', 'int DEFAULT NULL', ['after' => 'event_approval']);
        }
        if (!$DB->fieldExists($config_table, 'last_test_success')) {
            $migration->displayMessage("Adding last_test_success field");
            $migration->addField($config_table, 'last_test_success', 'tinyint(1) DEFAULT NULL', ['after' => 'last_test_timestamp']);
        }
        if (!$DB->fieldExists($config_table, 'last_test_error')) {
            $migration->displayMessage("Adding last_test_error field");
            $migration->addField($config_table, 'last_test_error', 'text DEFAULT NULL', ['after' => 'last_test_success']);
        }
    }

    // Гарантируем, что есть запись с id = 1
    $config = new Config();
    if (!$config->getFromDB(1)) {
        $config->add([
            'id'              => 1,
            'connection_type' => Config::CONNECTION_WEBHOOK,
        ]);
    }

    // 2. Таблица правил оповещений (NotificationRule)
    $rules_table = $dbu->getTableForItemType(NotificationRule::class);

    if (!$DB->tableExists($rules_table)) {
        $migration->displayMessage("Installing $rules_table");
        $query = "CREATE TABLE IF NOT EXISTS `{$rules_table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `target` varchar(64) NOT NULL DEFAULT 'Ticket',
            `event` varchar(64) NOT NULL DEFAULT 'New',
            `recipient` varchar(255) DEFAULT NULL,
            `message` text DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `use_raw_payload` tinyint(1) NOT NULL DEFAULT 0,
            `raw_payload` text DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    } else {
        // Миграции для старых инсталляций
        if (!$DB->fieldExists($rules_table, 'target')) {
            $migration->addField($rules_table, 'target', 'string', ['null' => false, 'default' => 'Ticket']);
            $migration->executeMigration();
        }
        if (!$DB->fieldExists($rules_table, 'event')) {
            $migration->addField($rules_table, 'event', 'string', ['null' => false, 'default' => 'New']);
            $migration->executeMigration();
        }
        if (!$DB->fieldExists($rules_table, 'recipient')) {
            $migration->addField($rules_table, 'recipient', 'string');
            $migration->executeMigration();
        }
        if (!$DB->fieldExists($rules_table, 'message')) {
            $migration->addField($rules_table, 'message', 'text');
            $migration->executeMigration();
            if ($DB->fieldExists($rules_table, 'note')) {
                $DB->query("UPDATE `{$rules_table}` SET `message` = `note` WHERE `note` IS NOT NULL AND `note` != ''");
            }
        }
        if (!$DB->fieldExists($rules_table, 'active')) {
            $migration->addField($rules_table, 'active', 'bool', ['null' => false, 'default' => 1]);
            $migration->executeMigration();
        }
        if (!$DB->fieldExists($rules_table, 'use_raw_payload')) {
            $migration->addField($rules_table, 'use_raw_payload', 'bool', ['null' => false, 'default' => 0]);
            $migration->executeMigration();
        }
        if (!$DB->fieldExists($rules_table, 'raw_payload')) {
            $migration->addField($rules_table, 'raw_payload', 'text');
            $migration->executeMigration();
        }
    }

    return true;
}
