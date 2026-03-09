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
use CommonGLPI;
use GlpiPlugin\Mattermostjetlag\Config\ConnectivityTab;
use GlpiPlugin\Mattermostjetlag\Config\RulesListTab;
use GlpiPlugin\Mattermostjetlag\Config\EditorTab;
use GlpiPlugin\Mattermostjetlag\Config\DebugModeTab;
use GlpiPlugin\Mattermostjetlag\Config\EventJournalTab;
use GlpiPlugin\Mattermostjetlag\Config\VariablesOverrideTab;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Plugin configuration (settings page with tabs: Connectivity, Rules list).
 */
class Config extends CommonDBTM
{
    public static $rightname = 'config';

    protected $displaylist = false;

    public const CONNECTION_WEBHOOK        = 'webhook';
    public const CONNECTION_LOGIN           = 'login';
    public const CONNECTION_PERSONAL_TOKEN  = 'personal_token';

    public const PLACEHOLDER_WEBHOOK_URL   = 'https://mattermost.local/hooks/cfd199faf768be42fcd552113b52be2f';
    public const PLACEHOLDER_BOT_NICKNAME  = 'e.g.: GLPI Notifications Bot';
    public const PLACEHOLDER_BOT_AVATAR    = 'e.g.: :robot_face: or https://example.com/avatar.png';
    public const PLACEHOLDER_API_URL       = 'https://mattermost.local/api/v4/';
    public const PLACEHOLDER_LOGIN         = 'el.padre';
    public const PLACEHOLDER_TEST_CHANNEL  = 'e.g.: general (channel) or @el.padre (direct message)';
    public const PLACEHOLDER_TEST_MESSAGE  = 'e.g.: Test message to verify the connection is working';

    public static function getTypeName($nb = 0)
    {
        return __('Setup', 'mattermostjetlag');
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function getIcon()
    {
        return 'ti ti-settings';
    }

    public function __construct()
    {
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    /**
     * Tab labels for this config.
     * Variables Override and Debug Mode tabs are shown only when GLPI debug mode is active.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item->getType() === __CLASS__) {
            $tabs = [
                1 => self::createTabEntry(__('Connectivity', 'mattermostjetlag'), 0, null, 'ti ti-plug'),
                2 => self::createTabEntry(__('Rules List', 'mattermostjetlag'), 0, null, 'ti ti-filter-search'),
                3 => self::createTabEntry(__('Rule Editor', 'mattermostjetlag'), 0, null, 'ti ti-edit'),
                4 => self::createTabEntry(__('Events Journal', 'mattermostjetlag'), 0, null, 'ti ti-history'),
                6 => self::createTabEntry(__('Instructions', 'mattermostjetlag'), 0, null, 'ti ti-info-circle'),
            ];
            if (isset($_SESSION['glpi_use_mode']) && $_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                $tabs[5] = self::createTabEntry(__('Variables Override', 'mattermostjetlag'), 0, null, 'ti ti-variable');
                $tabs[8] = self::createTabEntry(__('Debug Mode', 'mattermostjetlag'), 0, null, 'ti ti-bug');
            }
            return $tabs;
        }
        return '';
    }

    /**
     * Content for each tab.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() !== __CLASS__) {
            return false;
        }

        switch ($tabnum) {
            case 1:
                ConnectivityTab::render($item);
                break;
            case 2:
                RulesListTab::render($item);
                break;
            case 3:
                EditorTab::render($item);
                break;
            case 4:
                EventJournalTab::render($item);
                break;
            case 5:
                VariablesOverrideTab::render($item);
                break;
            case 6:
                \Glpi\Application\View\TemplateRenderer::getInstance()
                    ->display('@mattermostjetlag/config/instructions.html.twig');
                break;
            case 8:
                DebugModeTab::render($item);
                break;
            default:
                echo '<div class="center p-3"></div>';
                break;
        }

        return true;
    }

    /**
     * Tabs definition: Connectivity, Rules list.
     * no_all_tab disables the default "All" tab added by GLPI.
     */
    public function defineTabs($options = [])
    {
        $ong = ['no_all_tab' => true];
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['extended_log'])) {
            $input['extended_log'] = (int) $input['extended_log'] ? 1 : 0;
        }
        if (isset($input['connection_type'])) {
            if ($input['connection_type'] === self::CONNECTION_WEBHOOK) {
                $rawUrl = trim($input['webhook_url'] ?? '');
                // If the submitted value still contains the display mask, the user didn't change it — preserve DB value
                if (str_contains($rawUrl, '****')) {
                    unset($input['webhook_url']);
                } else {
                    $input['webhook_url'] = $rawUrl !== '' ? $rawUrl : null;
                }
                $input['webhook_bot_nickname'] = trim($input['webhook_bot_nickname'] ?? '') !== '' ? trim($input['webhook_bot_nickname']) : null;
                $input['webhook_bot_avatar']   = trim($input['webhook_bot_avatar'] ?? '') !== '' ? trim($input['webhook_bot_avatar']) : null;
                $input['mattermost_url']      = null;
                $input['mattermost_login']    = null;
                $input['mattermost_password'] = null;
            } else {
                $input['webhook_url']         = null;
                $input['webhook_bot_nickname'] = null;
                $input['webhook_bot_avatar']   = null;
                $input['mattermost_url']  = trim($input['mattermost_url'] ?? '') !== '' ? trim($input['mattermost_url']) : null;
                $input['mattermost_login'] = trim($input['mattermost_login'] ?? '') !== '' ? trim($input['mattermost_login']) : null;
                $rawPassword = trim($input['mattermost_password'] ?? '');
                if ($rawPassword === '') {
                    unset($input['mattermost_password']);
                } else {
                    $input['mattermost_password'] = self::encryptPassword($rawPassword);
                }
            }
        }
        return $input;
    }

    public static function encryptPassword(string $password): string
    {
        if (class_exists('\GLPIKey')) {
            try {
                return (new \GLPIKey())->encrypt($password);
            } catch (\Throwable $e) {
                // fall through to openssl
            }
        }
        $key = substr(sha1(GLPI_ROOT . 'mjl_pwd_key', true), 0, 16);
        $iv  = substr(sha1('mjl_iv' . GLPI_ROOT, true), 0, 16);
        return base64_encode(openssl_encrypt($password, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv));
    }

    public static function decryptPassword(string $encrypted): string
    {
        if (class_exists('\GLPIKey')) {
            try {
                $result = (new \GLPIKey())->decrypt($encrypted);
                if ($result !== false && $result !== null) {
                    return (string) $result;
                }
            } catch (\Throwable $e) {
                // fall through to openssl
            }
        }
        $key  = substr(sha1(GLPI_ROOT . 'mjl_pwd_key', true), 0, 16);
        $iv   = substr(sha1('mjl_iv' . GLPI_ROOT, true), 0, 16);
        $data = openssl_decrypt(base64_decode($encrypted), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $data !== false ? $data : '';
    }
}
