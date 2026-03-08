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

use Glpi\Search\CriteriaFilter;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\EventLog;
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use GlpiPlugin\Mattermostjetlag\SendLog;

/**
 * Inject plugin entry under Setup > Plugins in the breadcrumb.
 *
 * @param array $menu Full GLPI menu array passed by REDEFINE_MENUS hook
 * @return array Modified menu
 */
function plugin_mattermostjetlag_redefine_menus(array $menu): array
{
    if (!Session::haveRight('config', UPDATE)) {
        return $menu;
    }
    if (!isset($menu['config']['content']['plugin'])) {
        return $menu;
    }
    $url = \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermostjetlag\Config::getType()) . '?id=1';
    $menu['config']['content']['plugin']['options']['mattermostjetlag'] = [
        'title' => __('Mattermost Jetlag', 'mattermostjetlag'),
        'page'  => $url,
        'icon'  => \GlpiPlugin\Mattermostjetlag\Config::getIcon(),
    ];
    return $menu;
}

/**
 * Plugin install process.
 *
 * @return bool
 */
function plugin_mattermostjetlag_install()
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    $migration = new \Migration(PLUGIN_MATTERMOSTJETLAG_VERSION);

    $table = Config::getTable();
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `connection_type` varchar(20) NOT NULL DEFAULT 'webhook',
            `webhook_url` varchar(2000) DEFAULT NULL,
            `webhook_bot_nickname` varchar(255) DEFAULT NULL,
            `webhook_bot_avatar` varchar(500) DEFAULT NULL,
            `mattermost_url` varchar(2000) DEFAULT NULL,
            `mattermost_login` varchar(255) DEFAULT NULL,
            `mattermost_password` varchar(255) DEFAULT NULL,
            `last_test_timestamp` int DEFAULT NULL,
            `last_test_success` tinyint(1) DEFAULT NULL,
            `last_test_error` text DEFAULT NULL,
            `extended_log` tinyint(1) NOT NULL DEFAULT 0,
            `simulate_send` tinyint(1) NOT NULL DEFAULT 0,
            `variables_override` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
        $DB->insert($table, [
            'id'                 => 1,
            'connection_type'    => 'webhook',
            'variables_override' => plugin_mattermostjetlag_default_variables_override(),
        ]);
    } else {
        if (!$DB->fieldExists($table, 'connection_type')) {
            $migration->addField($table, 'connection_type', "varchar(20) NOT NULL DEFAULT 'webhook'", ['after' => 'id']);
        }
        if (!$DB->fieldExists($table, 'webhook_url')) {
            $migration->addField($table, 'webhook_url', 'varchar(2000) DEFAULT NULL', ['after' => 'connection_type']);
        }
        if (!$DB->fieldExists($table, 'webhook_bot_nickname')) {
            $migration->addField($table, 'webhook_bot_nickname', 'varchar(255) DEFAULT NULL', ['after' => 'webhook_url']);
        }
        if (!$DB->fieldExists($table, 'webhook_bot_avatar')) {
            $migration->addField($table, 'webhook_bot_avatar', 'varchar(500) DEFAULT NULL', ['after' => 'webhook_bot_nickname']);
        }
        if (!$DB->fieldExists($table, 'mattermost_url')) {
            $migration->addField($table, 'mattermost_url', 'varchar(2000) DEFAULT NULL', ['after' => 'webhook_bot_avatar']);
        }
        if (!$DB->fieldExists($table, 'mattermost_login')) {
            $migration->addField($table, 'mattermost_login', 'varchar(255) DEFAULT NULL', ['after' => 'mattermost_url']);
        }
        if (!$DB->fieldExists($table, 'mattermost_password')) {
            $migration->addField($table, 'mattermost_password', 'varchar(255) DEFAULT NULL', ['after' => 'mattermost_login']);
        }
        if (!$DB->fieldExists($table, 'last_test_timestamp')) {
            $migration->addField($table, 'last_test_timestamp', 'int DEFAULT NULL', ['after' => 'mattermost_password']);
        }
        if (!$DB->fieldExists($table, 'last_test_success')) {
            $migration->addField($table, 'last_test_success', 'tinyint(1) DEFAULT NULL', ['after' => 'last_test_timestamp']);
        }
        if (!$DB->fieldExists($table, 'last_test_error')) {
            $migration->addField($table, 'last_test_error', 'text DEFAULT NULL', ['after' => 'last_test_success']);
        }
        if (!$DB->fieldExists($table, 'extended_log')) {
            $migration->addField($table, 'extended_log', 'tinyint(1) NOT NULL DEFAULT 0', ['after' => 'last_test_error']);
        }
        if (!$DB->fieldExists($table, 'simulate_send')) {
            $migration->addField($table, 'simulate_send', 'tinyint(1) NOT NULL DEFAULT 0', ['after' => 'extended_log']);
        }
        if (!$DB->fieldExists($table, 'variables_override')) {
            $migration->addField($table, 'variables_override', 'text DEFAULT NULL', ['after' => 'simulate_send']);
            $migration->executeMigration();
            // Pre-populate with Russian defaults for existing installations
            $DB->update($table, ['variables_override' => plugin_mattermostjetlag_default_variables_override()], ['id' => 1]);
        } elseif ($DB->fieldExists($table, 'variables_override')) {
            // Apply Russian defaults if field is still empty (e.g. after reinstall without uninstall)
            $row = $DB->request(['SELECT' => ['variables_override'], 'FROM' => $table, 'WHERE' => ['id' => 1]])->current();
            if ($row && empty($row['variables_override'])) {
                $DB->update($table, ['variables_override' => plugin_mattermostjetlag_default_variables_override()], ['id' => 1]);
            }
        }
    }

    $rules_table = NotificationRule::getTable();
    if (!$DB->tableExists($rules_table)) {
        $query = "CREATE TABLE `$rules_table` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `target` varchar(64) NOT NULL DEFAULT 'Ticket',
            `event` varchar(64) NOT NULL DEFAULT 'New',
            `recipient` varchar(255) DEFAULT NULL,
            `message` text DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `use_raw_payload` tinyint(1) NOT NULL DEFAULT 0,
            `raw_payload` text DEFAULT NULL,
            `search_criteria` text DEFAULT NULL,
            `search_itemtype` varchar(100) DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    } else {
        if (!$DB->fieldExists($rules_table, 'search_criteria')) {
            $migration->addField($rules_table, 'search_criteria', 'text DEFAULT NULL', ['after' => 'raw_payload']);
        }
        if (!$DB->fieldExists($rules_table, 'search_itemtype')) {
            $migration->addField($rules_table, 'search_itemtype', 'varchar(100) DEFAULT NULL', ['after' => 'search_criteria']);
        }
    }

    $logs_table = EventLog::getTable();
    if (!$DB->tableExists($logs_table)) {
        $query = "CREATE TABLE `$logs_table` (
            `id`            int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `target`        varchar(64) NOT NULL DEFAULT 'Ticket',
            `event`         varchar(64) NOT NULL DEFAULT '',
            `ticket_id`     int DEFAULT NULL,
            `payload`       longtext DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    $sendlogs_table = SendLog::getTable();
    if (!$DB->tableExists($sendlogs_table)) {
        $query = "CREATE TABLE `$sendlogs_table` (
            `id`            int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `target`        varchar(64) NOT NULL DEFAULT 'Ticket',
            `target_id`     int DEFAULT NULL,
            `ticket_name`   varchar(500) DEFAULT NULL,
            `initiator`     varchar(255) DEFAULT NULL,
            `urgency`       varchar(64) DEFAULT NULL,
            `event`         varchar(64) NOT NULL DEFAULT '',
            `rule_id`       int DEFAULT NULL,
            `recipient`     varchar(500) DEFAULT NULL,
            `http_status`   int NOT NULL DEFAULT 0,
            `simulate`      tinyint(1) NOT NULL DEFAULT 0,
            `error`         text DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    } else {
        // Migrate existing table: add missing columns
        if (!$DB->fieldExists($sendlogs_table, 'ticket_name')) {
            $DB->doQuery("ALTER TABLE `$sendlogs_table` ADD COLUMN `ticket_name` varchar(500) DEFAULT NULL AFTER `target_id`");
        }
        if (!$DB->fieldExists($sendlogs_table, 'initiator')) {
            $DB->doQuery("ALTER TABLE `$sendlogs_table` ADD COLUMN `initiator` varchar(255) DEFAULT NULL AFTER `ticket_name`");
        }
        if (!$DB->fieldExists($sendlogs_table, 'urgency')) {
            $DB->doQuery("ALTER TABLE `$sendlogs_table` ADD COLUMN `urgency` varchar(64) DEFAULT NULL AFTER `initiator`");
        }
    }

    $migration->executeMigration();

    // Migrate existing filters from plugin table to glpi_criteriafilters (standard storage)
    $rules_table = NotificationRule::getTable();
    $cf_table    = CriteriaFilter::getTable();
    if ($DB->tableExists($rules_table) && $DB->tableExists($cf_table)
        && $DB->fieldExists($rules_table, 'search_criteria')) {
        $rit = $DB->request(['FROM' => $rules_table]);
        foreach ($rit as $row) {
            $crit = trim((string) ($row['search_criteria'] ?? ''));
            if ($crit === '') {
                continue;
            }
            $rule_id = (int) $row['id'];
            $criteria = $row['search_criteria'] ?? '';
            $itemtype = $row['search_itemtype'] ?? 'Ticket';
            // Check if already in glpi_criteriafilters
            $existing = $DB->request([
                'COUNT'  => 'c',
                'FROM'   => $cf_table,
                'WHERE'  => [
                    'itemtype' => NotificationRule::class,
                    'items_id' => $rule_id,
                ],
            ])->current();
            if ((int) ($existing['c'] ?? 0) === 0) {
                $DB->insert($cf_table, [
                    'itemtype'        => NotificationRule::class,
                    'items_id'        => $rule_id,
                    'search_itemtype' => $itemtype,
                    'search_criteria' => $criteria,
                ]);
            }
        }
    }

    return true;
}

/**
 * Plugin uninstall process.
 *
 * @return bool
 */
function plugin_mattermostjetlag_uninstall()
{
    global $DB;

    $itemtype = NotificationRule::class;
    $cf_table = CriteriaFilter::getTable();
    if ($DB->tableExists($cf_table)) {
        $DB->doQuery("DELETE FROM `$cf_table` WHERE `itemtype` = " . $DB->quote($itemtype));
    }

    $sendlogs_table = SendLog::getTable();
    if ($DB->tableExists($sendlogs_table)) {
        $DB->doQuery("DROP TABLE `$sendlogs_table`");
    }

    $logs_table = EventLog::getTable();
    if ($DB->tableExists($logs_table)) {
        $DB->doQuery("DROP TABLE `$logs_table`");
    }

    $rules_table = NotificationRule::getTable();
    if ($DB->tableExists($rules_table)) {
        $DB->doQuery("DROP TABLE `$rules_table`");
    }

    $table = Config::getTable();
    if ($DB->tableExists($table)) {
        $DB->doQuery("DROP TABLE `$table`");
    }

    return true;
}
