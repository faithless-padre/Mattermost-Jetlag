<?php

/**
 * Страница настроек плагина Mattermost (вкладки: Connection, Rules List, Editor, Journal, Instructions).
 */

require_once __DIR__ . '/../hook.php';

use GlpiPlugin\Mattermost\Config;

Session::checkLoginUser();

if (!Plugin::isPluginActive('mattermost')) {
    Html::header('Mattermost', '', 'config', Plugin::getType(), '', false);
    echo "<div class='alert alert-important alert-warning d-flex'>";
    echo "<b>Please activate the plugin</b></div>";
    Html::footer();
    return;
}

if (!Config::canView()) {
    Html::header('Mattermost', '', 'config', Plugin::getType(), '', false);
    echo "<div class='alert alert-important alert-danger d-flex'>";
    echo "<b>" . __("You don't have permission to access this page.") . "</b></div>";
    Html::footer();
    return;
}

// Ajax: delete Extended Filter for a notification rule (avoids 400 from core criteria_filter.php with plugin itemtype)
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'delete_rule_filter') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $items_id = (int) ($_POST['item_items_id'] ?? $_POST['items_id'] ?? 0);
        if ($items_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid item_items_id']);
            exit;
        }
        $rule = new \GlpiPlugin\Mattermost\NotificationRule();
        if (!$rule->getFromDB($items_id)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Rule not found']);
            exit;
        }
        if (!$rule->canUpdateItem()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not allowed to update this rule']);
            exit;
        }
        if (!$rule->deleteFilter()) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Unable to delete filter']);
            exit;
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Ajax: save Extended Filter for a notification rule (avoids 400 from core criteria_filter.php with plugin itemtype)
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'save_rule_filter') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $items_id = (int) ($_POST['item_items_id'] ?? 0);
        $criteria = $_POST['criteria'] ?? [];
        if (!is_array($criteria)) {
            $criteria = [];
        }
        if ($items_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid item_items_id']);
            exit;
        }
        $rule = new \GlpiPlugin\Mattermost\NotificationRule();
        if (!$rule->getFromDB($items_id)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Rule not found']);
            exit;
        }
        if (!$rule->canUpdateItem()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not allowed to update this rule']);
            exit;
        }
        if (!$rule->saveFilter($criteria)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Unable to process data']);
            exit;
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

// Ajax actions (Test connection)
if (isset($_POST['mattermost_ajax'])) {
    $ajaxAction = $_POST['mattermost_ajax'];

    if ($ajaxAction === 'test_webhook') {
        // Ajax: тестирование подключения через Mattermost Webhook
        header('Content-Type: application/json; charset=utf-8');

        try {
            // Проверяем, что класс MattermostClient доступен
            if (!class_exists('\GlpiPlugin\Mattermost\MattermostClient')) {
                http_response_code(500);
                echo json_encode([
                    'ok'         => false,
                    'error'      => 'MattermostClient class not found. Plugin may not be properly installed.',
                    'csrf_token' => Session::getNewCSRFToken(),
                    'debug'      => [
                        'class_exists' => false,
                    ],
                ]);
                exit;
            }

            $webhookUrl = trim($_POST['test_webhook_url'] ?? '');
            $channel    = trim($_POST['test_channel'] ?? '');
            $message    = trim($_POST['test_message'] ?? '');
            $nickname   = trim($_POST['test_sender_nickname'] ?? '');
            $avatar     = trim($_POST['test_sender_avatar'] ?? '');

            // Генерируем новый CSRF токен для следующего запроса
            $newCsrfToken = Session::getNewCSRFToken();
            
            if ($webhookUrl === '' || $channel === '' || $message === '') {
                http_response_code(400);
                echo json_encode([
                    'ok'         => false,
                    'error'      => 'Webhook URL, Channel/username and Message Text are required.',
                    'csrf_token' => $newCsrfToken,
                    'debug'      => [
                        'webhook_url' => $webhookUrl === '' ? 'empty' : 'provided',
                        'channel'     => $channel === '' ? 'empty' : 'provided',
                        'message'     => $message === '' ? 'empty' : 'provided',
                    ],
                ]);
                exit;
            }

            $error = null;
            $ok = \GlpiPlugin\Mattermost\MattermostClient::sendTestWebhook(
                $webhookUrl,
                $channel,
                $message,
                $nickname !== '' ? $nickname : null,
                $avatar !== '' ? $avatar : null,
                $error
            );

            // Сохраняем результат теста в БД (last_test_timestamp в таблице — INT, Unix timestamp)
            try {
                global $DB;
                $table = Config::getTable();
                $ts = time();
                $success = $ok ? 1 : 0;
                $err = $ok ? null : ($error ?? null);
                $sql = 'UPDATE ' . $DB->quoteName($table) . ' SET '
                    . $DB->quoteName('last_test_timestamp') . ' = ' . (int) $ts . ', '
                    . $DB->quoteName('last_test_success') . ' = ' . (int) $success . ', '
                    . $DB->quoteName('last_test_error') . ' = ' . ($err === null ? 'NULL' : $DB->quoteValue($err))
                    . ' WHERE id = 1';
                $DB->doQuery($sql);
            } catch (\Throwable $e) {
                // не ломаем ответ теста при ошибке записи статуса
            }

            // Генерируем новый CSRF токен для следующего запроса
            $newCsrfToken = Session::getNewCSRFToken();
            
            if ($ok) {
                echo json_encode([
                    'ok'         => true,
                    'message'    => 'Test message successfully sent to Mattermost.',
                    'csrf_token' => $newCsrfToken,
                ]);
            } else {
                http_response_code(502);
                echo json_encode([
                    'ok'         => false,
                    'error'      => $error ?: 'Unknown error while sending test message.',
                    'csrf_token' => $newCsrfToken,
                    'debug'      => [
                        'webhook_url'      => substr($webhookUrl, 0, 50) . '...',
                        'channel'          => $channel,
                        'message_len'      => strlen($message),
                        'nickname_provided' => $nickname !== '',
                        'avatar_provided'  => $avatar !== '',
                        'full_error'       => $error,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            try {
                global $DB;
                $table = Config::getTable();
                $sql = 'UPDATE ' . $DB->quoteName($table) . ' SET '
                    . $DB->quoteName('last_test_timestamp') . ' = ' . time() . ', '
                    . $DB->quoteName('last_test_success') . ' = 0, '
                    . $DB->quoteName('last_test_error') . ' = ' . $DB->quoteValue($e->getMessage())
                    . ' WHERE id = 1';
                $DB->doQuery($sql);
            } catch (\Throwable $e2) {
                // игнорируем ошибку записи статуса
            }
            http_response_code(500);
            echo json_encode([
                'ok'         => false,
                'error'      => 'Internal server error: ' . $e->getMessage(),
                'csrf_token' => Session::getNewCSRFToken(),
                'debug'      => [
                    'exception_class' => get_class($e),
                    'exception_file'  => $e->getFile(),
                    'exception_line'  => $e->getLine(),
                    'trace'           => explode("\n", $e->getTraceAsString()),
                ],
            ]);
        }
        exit;
    }
}

global $DB;

$config = new \GlpiPlugin\Mattermost\Config();

$tab_rules = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_ALL_RULES;
$tab_rule_editor = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_RULE_EDITOR;
$redirect_rules = Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rules);
$redirect_rule_editor = Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rule_editor);

if (isset($_POST['mattermost_rules_action']) && isset($_POST['mattermost_rule_ids']) && is_array($_POST['mattermost_rule_ids'])) {
    $action = $_POST['mattermost_rules_action'];
    $ids = array_map('intval', $_POST['mattermost_rule_ids']);
    $ids = array_filter($ids, function ($id) { return $id > 0; });
    if ($ids !== [] && in_array($action, ['disable_selected', 'enable_selected', 'clone_selected', 'delete_selected'], true)) {
        $rule = new \GlpiPlugin\Mattermost\NotificationRule();
        foreach ($ids as $id) {
            if (!$rule->getFromDB($id)) {
                continue;
            }
            if ($action === 'delete_selected') {
                if ($rule->canDeleteItem()) {
                    $rule->delete(['id' => $id]);
                }
            } elseif ($action === 'clone_selected') {
                if (!$rule->canCreateItem()) {
                    continue;
                }
                $copy = [
                    'name'             => 'Copy of ' . ($rule->getField('name') ?: 'Rule'),
                    'target'           => $rule->getField('target') ?: 'Ticket',
                    'event'            => $rule->getField('event') ?: 'New',
                    'recipient'        => $rule->getField('recipient') ?: '',
                    'message'          => $rule->getField('message') ?: '',
                    'active'           => (int) $rule->getField('active'),
                    'use_raw_payload'  => (int) $rule->getField('use_raw_payload'),
                    'raw_payload'      => $rule->getField('raw_payload') ?: '',
                ];
                $new_id = $rule->add($copy);
                if ($new_id) {
                    // Copy Extended Filter from source rule (by raw DB row to avoid decode/encode issues)
                    global $DB;
                    $criteria_table = \Glpi\Search\CriteriaFilter::getTable();
                    $it = $DB->request([
                        'FROM'   => $criteria_table,
                        'WHERE'  => [
                            'itemtype' => $rule->getType(),
                            'items_id' => $id,
                        ],
                    ]);
                    if (count($it)) {
                        $row = $it->current();
                        $newFilter = new \Glpi\Search\CriteriaFilter();
                        $newFilter->add([
                            'itemtype'        => $rule->getType(),
                            'items_id'        => $new_id,
                            'search_itemtype' => $row['search_itemtype'] ?? '',
                            'search_criteria' => $row['search_criteria'] ?? '[]',
                        ]);
                    }
                }
            } else {
                if (!$rule->canUpdateItem()) {
                    continue;
                }
                $active = ($action === 'enable_selected') ? 1 : 0;
                $rule->update(['id' => $id, 'active' => $active]);
            }
        }
    }
    Html::redirect($redirect_rules);
} elseif (isset($_POST['update_config'])) {
    $config->update($_POST);
    Html::back();
} elseif (isset($_POST['add_notification_rule']) && !empty(trim($_POST['rule_name'] ?? ''))) {
    $rule = new \GlpiPlugin\Mattermost\NotificationRule();
    $new_id = $rule->add([
        'name'             => trim($_POST['rule_name']),
        'target'           => trim($_POST['rule_target'] ?? 'Ticket'),
        'event'            => trim($_POST['rule_event'] ?? 'New'),
        'recipient'        => trim($_POST['rule_recipient'] ?? ''),
        'message'          => trim($_POST['rule_message'] ?? ''),
        'active'           => (int) ($_POST['rule_active'] ?? 0),
        'use_raw_payload'  => (int) ($_POST['rule_use_raw_payload'] ?? 0),
        'raw_payload'      => trim($_POST['rule_raw_payload'] ?? ''),
    ]);
    if ($new_id) {
        // Новое правило создаётся без Extended Filter; удаляем возможную запись от предыдущей версии.
        $rule->getFromDB($new_id);
        $rule->deleteFilter();
    }
    Session::addMessageAfterRedirect('Rule saved');
    // После создания нового правила переходим в список правил (Rules List)
    Html::redirect($redirect_rules);
} elseif (isset($_POST['update_notification_rule']) && (int) ($_POST['rule_id'] ?? 0) > 0) {
    $rule_id = (int) $_POST['rule_id'];
    $rule = new \GlpiPlugin\Mattermost\NotificationRule();
    $rule->update([
        'id'               => $rule_id,
        'name'             => trim($_POST['rule_name'] ?? ''),
        'target'           => trim($_POST['rule_target'] ?? 'Ticket'),
        'event'            => trim($_POST['rule_event'] ?? 'New'),
        'recipient'        => trim($_POST['rule_recipient'] ?? ''),
        'message'          => trim($_POST['rule_message'] ?? ''),
        'active'           => (int) ($_POST['rule_active'] ?? 0),
        'use_raw_payload'  => (int) ($_POST['rule_use_raw_payload'] ?? 0),
        'raw_payload'      => trim($_POST['rule_raw_payload'] ?? ''),
    ]);
    Session::addMessageAfterRedirect('Rule saved');
    // После редактирования правила переходим в список правил (Rules List)
    Html::redirect($redirect_rules);
} else {
    // Гарантируем, что таблицы плагина существуют (одиночный вызов установщика)
    require_once __DIR__ . '/../config/install.php';
    mattermost_run_install();
    Html::header('Mattermost', '', 'config', \GlpiPlugin\Mattermost\Config::getType(), '', false);
    $css_file = __DIR__ . '/css/mattermost.css';
    if (is_readable($css_file)) {
        echo '<style>' . "\n" . file_get_contents($css_file) . "\n" . '</style>';
    }
    $config->getFromDB(1);
    $options = array_merge(['id' => 1, 'loaded' => true], $_GET);
    if (!isset($options['_glpi_tab'])) {
        $options['_glpi_tab'] = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_CONNECTION;
    }
    // При переходе по ссылке с _glpi_tab (например, карандаш → Editor&edit=ID) задаём активную вкладку в сессии,
    // иначе GLPI показывает предыдущую вкладку и правило не открывается в редакторе.
    if (!empty($_GET['_glpi_tab'])) {
        Session::setActiveTab(\GlpiPlugin\Mattermost\Config::getType(), $_GET['_glpi_tab']);
    }
    $config->display($options);
    echo "<script>document.title = 'GLPI - Mattermost';</script>";
    if (!empty($_GET['rule_saved'])) {
        $msg = 'Rule saved';
        echo "<script>$(function(){ setTimeout(function(){ if (typeof glpi_toast_info === 'function') glpi_toast_info(" . json_encode($msg) . "); }, 150); if (history.replaceState) history.replaceState(null, '', location.pathname + location.search.replace(/[?&]rule_saved=1(&|$)/, '$1').replace(/^&/, '?')); });</script>";
    }
    Html::footer();
}
