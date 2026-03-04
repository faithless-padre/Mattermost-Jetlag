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

use Glpi\Plugin\Hooks;

define('PLUGIN_MATTERMOSTJETLAG_VERSION', '0.1.0');
define('PLUGIN_MATTERMOSTJETLAG_MIN_GLPI', '11.0.0');
define('PLUGIN_MATTERMOSTJETLAG_MAX_GLPI', '12.0.0');

global $CFG_GLPI;
if (!defined('PLUGIN_MATTERMOSTJETLAG_WEBDIR')) {
    define('PLUGIN_MATTERMOSTJETLAG_WEBDIR', $CFG_GLPI['root_doc'] . '/plugins/mattermostjetlag');
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_mattermostjetlag()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['mattermostjetlag'] = true;

    require_once __DIR__ . '/inc/ticket_hooks.php';

    $PLUGIN_HOOKS['item_add']['mattermostjetlag'] = [
        'Ticket'           => 'plugin_mattermostjetlag_item_add_Ticket',
        'ITILFollowup'     => 'plugin_mattermostjetlag_item_add_ITILFollowup',
        'TicketValidation' => 'plugin_mattermostjetlag_item_add_TicketValidation',
        'Ticket_User'      => 'plugin_mattermostjetlag_item_add_Ticket_User',
        'ITILSolution'     => 'plugin_mattermostjetlag_item_add_ITILSolution',
    ];
    $PLUGIN_HOOKS['item_update']['mattermostjetlag'] = [
        'Ticket'           => 'plugin_mattermostjetlag_item_update_Ticket',
        'TicketValidation' => 'plugin_mattermostjetlag_item_update_TicketValidation',
        'ITILSolution'     => 'plugin_mattermostjetlag_item_update_ITILSolution',
    ];

    // Config page (Setup > Plugins > Mattermost Jetlag or direct link)
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['mattermostjetlag'] = 'front/config.form.php';
    }

    // Breadcrumb: Setup > Plugins > Mattermost Jetlag
    $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['mattermostjetlag'] = 'plugin_mattermostjetlag_redefine_menus';

    // Config page: CSS and JS for Connectivity tab
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, 'mattermostjetlag') !== false && strpos($request_uri, 'config.form') !== false) {
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['mattermostjetlag'] = [
            'css/connectivity.css',
            'css/rules_list.css',
            'css/editor.css',
            'css/debug_mode.css',
        ];
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['mattermostjetlag'] = [
            'js/connectivity.js',
            'js/rules_list.js',
            'js/editor.js',
            'js/debug_mode.js',
        ];
    }
}

/**
 * Get the name and the version of the plugin.
 * REQUIRED
 *
 * @return array
 */
function plugin_version_mattermostjetlag()
{
    return [
        'name'         => 'Mattermost Jetlag',
        'version'      => PLUGIN_MATTERMOSTJETLAG_VERSION,
        'author'       => 'Faithless Padre',
        'license'      => 'MIT',
        'homepage'     => 'https://github.com/faithless-padre',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_MATTERMOSTJETLAG_MIN_GLPI,
                'max' => PLUGIN_MATTERMOSTJETLAG_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Check configuration process.
 *
 * @param bool $verbose Whether to display message on failure
 * @return bool
 */
function plugin_mattermostjetlag_check_config($verbose = false)
{
    return true;
}

/**
 * Lazy column migrations — runs on every plugin load, no-op after first run.
 */
function plugin_mattermostjetlag_init()
{
    global $DB;
    $table = GlpiPlugin\Mattermostjetlag\Config::getTable();
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'simulate_send')) {
        $DB->doQuery("ALTER TABLE `$table` ADD COLUMN `simulate_send` tinyint(1) NOT NULL DEFAULT 0 AFTER `extended_log`");
    }
}
