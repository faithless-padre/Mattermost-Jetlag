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

namespace GlpiPlugin\Mattermostjetlag\Config;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Mattermostjetlag\Config;
use Html;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class ConnectivityTab
{
    private const TEMPLATE = '@mattermostjetlag/config/connectivity.html.twig';
    private const MASK     = '****';

    /**
     * Mask the token part of a webhook URL (everything after /hooks/).
     * e.g. https://mm.example.com/hooks/abc123 → https://mm.example.com/hooks/****
     */
    private static function maskWebhookUrl(string $url): string
    {
        $pos = stripos($url, '/hooks/');
        if ($pos !== false) {
            return substr($url, 0, $pos + 7) . self::MASK;
        }
        // Fallback: mask everything after the last slash
        $last = strrpos($url, '/');
        if ($last !== false && $last < strlen($url) - 1) {
            return substr($url, 0, $last + 1) . self::MASK;
        }
        return $url;
    }

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        $fields = $config->fields;

        $connection_type     = $fields['connection_type'] ?? Config::CONNECTION_WEBHOOK;
        $webhook_url         = trim($fields['webhook_url'] ?? '');
        $webhook_bot_nickname = trim($fields['webhook_bot_nickname'] ?? '');
        $webhook_bot_avatar  = trim($fields['webhook_bot_avatar'] ?? '');
        $mattermost_url      = trim($fields['mattermost_url'] ?? '');
        $mattermost_login    = trim($fields['mattermost_login'] ?? '');

        $last_test_success_val = isset($fields['last_test_success']) ? (int) $fields['last_test_success'] : null;
        $last_test_timestamp   = $fields['last_test_timestamp'] ?? null;

        $last_test_ok    = ($last_test_success_val === 1);
        $last_test_label = $last_test_ok
            ? __('Success')
            : ($last_test_success_val === 0 ? __('Failed') : __('Unknown'));
        $last_test_css   = $last_test_ok
            ? 'border-success text-success'
            : 'border-danger text-danger';
        $last_test_dot   = $last_test_ok ? 'bg-success' : 'bg-danger';

        $last_test_date = null;
        if ($last_test_timestamp !== null && $last_test_timestamp !== '' && (int) $last_test_timestamp > 0) {
            $last_test_date = Html::convDateTime(date('Y-m-d H:i:s', (int) $last_test_timestamp));
        }

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'config_id'          => (int) ($fields['id'] ?? 1),
            'connection_type'    => $connection_type,
            'webhook_url'        => $webhook_url !== '' ? self::maskWebhookUrl($webhook_url) : Config::PLACEHOLDER_WEBHOOK_URL,
            'webhook_url_real'   => $webhook_url,
            'webhook_bot_nickname' => $webhook_bot_nickname !== '' ? $webhook_bot_nickname : Config::PLACEHOLDER_BOT_NICKNAME,
            'webhook_bot_avatar' => $webhook_bot_avatar !== '' ? $webhook_bot_avatar : Config::PLACEHOLDER_BOT_AVATAR,
            'mattermost_url'     => $mattermost_url !== '' ? $mattermost_url : Config::PLACEHOLDER_API_URL,
            'mattermost_login'   => $mattermost_login !== '' ? $mattermost_login : Config::PLACEHOLDER_LOGIN,
            'form_action'        => Toolbox::getItemTypeFormURL(Config::getType()),
            'can_update'         => Config::canCreate(),
            'placeholders'       => [
                'webhook_url'        => Config::PLACEHOLDER_WEBHOOK_URL,
                'bot_nickname'       => Config::PLACEHOLDER_BOT_NICKNAME,
                'bot_avatar'         => Config::PLACEHOLDER_BOT_AVATAR,
                'api_url'            => Config::PLACEHOLDER_API_URL,
                'login'              => Config::PLACEHOLDER_LOGIN,
                'test_channel'       => Config::PLACEHOLDER_TEST_CHANNEL,
                'test_message'       => Config::PLACEHOLDER_TEST_MESSAGE,
            ],
            'connection_types'   => [
                Config::CONNECTION_WEBHOOK        => __('Webhook URL request', 'mattermostjetlag'),
                Config::CONNECTION_LOGIN          => __('Login / Password connection', 'mattermostjetlag'),
                Config::CONNECTION_PERSONAL_TOKEN => __('Personal Token', 'mattermostjetlag'),
            ],
            'last_test' => [
                'success' => $last_test_ok,
                'label'   => $last_test_label,
                'css'     => $last_test_css,
                'dot'     => $last_test_dot,
                'date'    => $last_test_date,
            ],
        ]);
    }
}
