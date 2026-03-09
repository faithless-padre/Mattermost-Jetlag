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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class MattermostClient
{
    /**
     * Send a test message via Mattermost Incoming Webhook.
     *
     * @param string      $webhookUrl  Full webhook URL
     * @param string      $channel     Channel name or @username
     * @param string      $text        Message body
     * @param string|null $nickname    Display name (username override)
     * @param string|null $avatar      Emoji (icon_emoji) or URL (icon_url)
     * @param string|null $error       Error description (out-param)
     *
     * @return bool true on success
     */
    public static function sendTestWebhook(
        string $webhookUrl,
        string $channel,
        string $text,
        ?string $nickname = null,
        ?string $avatar = null,
        ?string &$error = null,
        ?string &$payloadJson = null
    ): bool {
        $error = null;

        $webhookUrl = trim($webhookUrl);
        $channel    = trim($channel);
        $text       = trim($text);
        $nickname   = $nickname !== null ? trim($nickname) : '';
        $avatar     = $avatar !== null ? trim($avatar) : '';

        if ($webhookUrl === '' || $channel === '' || $text === '') {
            $error = 'Webhook URL, channel and message text are required.';
            return false;
        }

        $payload = [
            'text'    => $text,
            'channel' => $channel,
        ];

        if ($nickname !== '') {
            $payload['username'] = $nickname;
        }

        if ($avatar !== '') {
            $placeholderPatterns = [
                Config::PLACEHOLDER_BOT_AVATAR,
                'e.g.: :robot_face:',
            ];
            $isPlaceholder = false;
            foreach ($placeholderPatterns as $pattern) {
                if (stripos($avatar, $pattern) !== false) {
                    $isPlaceholder = true;
                    break;
                }
            }

            if (!$isPlaceholder) {
                if (preg_match('~^https?://~i', $avatar)) {
                    $payload['icon_url'] = $avatar;
                } else {
                    $payload['icon_emoji'] = $avatar;
                }
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadJson = $json !== false ? $json : null;
        if ($json === false) {
            $error = 'Failed to encode JSON payload: ' . json_last_error_msg();
            return false;
        }

        if (!function_exists('curl_init')) {
            $error = 'PHP cURL extension is not available.';
            return false;
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: GLPI-MattermostJetlag-Plugin',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($responseBody === false) {
            $error = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $safeBody = mb_substr(strip_tags((string) $responseBody), 0, 300);
            $error = 'Mattermost responded with HTTP ' . $httpCode . ': ' . $safeBody;
            return false;
        }

        return true;
    }

    /**
     * Send a notification message via Mattermost Incoming Webhook.
     * Alias for sendTestWebhook() — same signature and behaviour.
     */
    public static function send(
        string $webhookUrl,
        string $channel,
        string $text,
        ?string $nickname = null,
        ?string $avatar = null,
        ?string &$error = null,
        ?string &$payloadJson = null
    ): bool {
        return self::sendTestWebhook($webhookUrl, $channel, $text, $nickname, $avatar, $error, $payloadJson);
    }

    /**
     * Send a pre-built JSON payload directly to a Mattermost Incoming Webhook.
     * Used for raw_payload rules where the user controls the full JSON structure.
     */
    public static function sendRaw(
        string $webhookUrl,
        string $payloadJson,
        ?string &$error = null,
        ?int &$httpCode = null
    ): bool {
        $error      = null;
        $httpCode   = 0;
        $webhookUrl = trim($webhookUrl);

        if ($webhookUrl === '' || $payloadJson === '') {
            $error = 'Webhook URL and payload JSON are required.';
            return false;
        }

        if (!function_exists('curl_init')) {
            $error = 'PHP cURL extension is not available.';
            return false;
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: GLPI-MattermostJetlag-Plugin',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseBody = curl_exec($ch);
        $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($responseBody === false) {
            $error = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $safeBody = mb_substr(strip_tags((string) $responseBody), 0, 300);
            $error = 'Mattermost responded with HTTP ' . $httpCode . ': ' . $safeBody;
            return false;
        }

        return true;
    }
}
