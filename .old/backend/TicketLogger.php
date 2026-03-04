<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Низкоуровневая функция записи строки лога заявки в файл.
 *
 * Здесь НЕТ никакой логики работы с объектом Ticket — только запись
 * уже собранных данных в файл.
 *
 * @param array $data Готовые данные для записи в лог.
 */
function plugin_mattermost_log_ticket(array $data) {
    // Каталог плагина для данных: glpi/files/_plugins/mattermost
    $logDir = GLPI_PLUGIN_DOC_DIR . '/mattermost';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0770, true);
    }

    $logFile = $logDir . '/tickets.log';

    if (!isset($data['datetime'])) {
        $data['datetime'] = date('c');
    }

    $line = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;

    // Пишем строку в лог; @ — чтобы не ронять GLPI, если вдруг нет прав на запись
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

