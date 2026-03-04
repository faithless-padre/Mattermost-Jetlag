<?php

namespace GlpiPlugin\Mattermost;

/**
 * Простая обёртка для отправки сообщений в Mattermost (webhook).
 */
class MattermostClient
{
    /**
     * Отправить тестовое сообщение через Incoming Webhook.
     *
     * @param string      $webhookUrl   Полный URL вебхука Mattermost
     * @param string      $channel      Канал или @username
     * @param string      $text         Текст сообщения
     * @param string|null $nickname     Отображаемое имя отправителя (username)
     * @param string|null $avatar       Эмодзи (icon_emoji) или URL (icon_url)
     * @param string|null $error        Текст ошибки (если не удалось отправить)
     *
     * @return bool true при успешной отправке, false при ошибке
     */
    public static function sendTestWebhook(
        string $webhookUrl,
        string $channel,
        string $text,
        ?string $nickname = null,
        ?string $avatar = null,
        ?string &$error = null
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

        // Собираем payload - используем канал как есть, без нормализации
        $payload = [
            'text'    => $text,
            'channel' => $channel,
        ];

        if ($nickname !== '') {
            $payload['username'] = $nickname;
        }

        if ($avatar !== '') {
            // Не отправляем плейсхолдеры формы (константы из Config)
            $isPlaceholder = false;
            foreach (Config::PLACEHOLDER_AVATAR_PATTERNS as $pattern) {
                if (stripos($avatar, $pattern) !== false) {
                    $isPlaceholder = true;
                    break;
                }
            }

            if (!$isPlaceholder) {
                // Если это URL — используем icon_url, иначе считаем, что это emoji
                if (preg_match('~^https?://~i', $avatar)) {
                    $payload['icon_url'] = $avatar;
                } else {
                    $payload['icon_emoji'] = $avatar;
                }
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $error = 'Failed to encode JSON payload. JSON error: ' . json_last_error_msg();
            return false;
        }

        // Отправляем через cURL
        if (!function_exists('curl_init')) {
            $error = 'PHP cURL extension is not available.';
            return false;
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: GLPI-Mattermost-Plugin',
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
            $error = 'Mattermost webhook responded with HTTP ' . $httpCode . ' and body: ' . $responseBody;
            $error .= ' | Sent payload: ' . $json;
            $error .= ' | Channel: ' . $channel;
            return false;
        }

        return true;
    }
}

