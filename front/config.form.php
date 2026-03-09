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

use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\EventLog;
use GlpiPlugin\Mattermostjetlag\MattermostClient;
use GlpiPlugin\Mattermostjetlag\NotificationRule;

include(__DIR__ . '/../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (!Plugin::isPluginActive('mattermostjetlag')) {
    Html::header(__('Setup'), '', 'config', 'plugin');
    echo "<div class='alert alert-important alert-warning d-flex'>";
    echo "<b>" . __('Please activate the plugin', 'mattermostjetlag') . "</b></div>";
    Html::footer();
    exit;
}

// ── AJAX error helper: log real exception, return safe generic message ──
function mjl_ajax_error(\Throwable $e, string $ctx = ''): string {
    $prefix = '[MattermostJetlag]' . ($ctx !== '' ? " [$ctx]" : '');
    error_log($prefix . ' ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    return 'An internal error occurred. Check the PHP error log for details.';
}

// ── AJAX: Save Extended Filter (core criteria_filter returns 400 for plugin itemtype) ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'save_rule_filter') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $items_id = (int) ($_POST['item_items_id'] ?? 0);
        $criteria = $_POST['criteria'] ?? [];
        if (is_string($criteria)) {
            $decoded = json_decode($criteria, true);
            $criteria = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($criteria)) {
            $criteria = [];
        }
        if ($items_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid item_items_id']);
            exit;
        }
        $rule = new NotificationRule();
        if (!$rule->getFromDB($items_id)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Rule not found']);
            exit;
        }
        if (!$rule->canUpdateItem()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not allowed']);
            exit;
        }
        if (!$rule->saveFilter($criteria)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Unable to save filter']);
            exit;
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'save_rule_filter')]);
    }
    exit;
}

// ── AJAX: Mass update (enable/disable) ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'mass_update') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ids = $_POST['rule_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_filter(array_map('intval', $ids));
        $action = $_POST['mass_action'] ?? '';
        if (!in_array($action, ['enable', 'disable'], true) || empty($ids)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }
        $active = $action === 'enable' ? 1 : 0;
        foreach ($ids as $id) {
            $rule = new NotificationRule();
            if ($rule->getFromDB($id) && $rule->canUpdateItem()) {
                $rule->update(['id' => $id, 'active' => $active]);
            }
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'mass_update')]);
    }
    exit;
}

// ── AJAX: Mass delete ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'mass_delete') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ids = $_POST['rule_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }
        foreach ($ids as $id) {
            $rule = new NotificationRule();
            if ($rule->getFromDB($id) && $rule->canDeleteItem()) {
                $rule->deleteFilter();
                $rule->delete(['id' => $id]);
            }
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'mass_delete')]);
    }
    exit;
}

// ── AJAX: Clone rule (single) ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'clone_rule') {
    Session::checkLoginUser();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $src_id = (int) ($_POST['rule_id'] ?? 0);
        if ($src_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid rule_id']);
            exit;
        }
        $src = new NotificationRule();
        if (!$src->getFromDB($src_id)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Rule not found']);
            exit;
        }
        if (!$src->canView()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not allowed']);
            exit;
        }
        $fields = $src->fields;
        $new_name = __('Clone of', 'mattermostjetlag') . ' (' . ($fields['name'] ?? '') . ')';
        $new_id = $src->add([
            'name'             => $new_name,
            'target'           => $fields['target'] ?? 'Ticket',
            'event'            => $fields['event'] ?? 'New',
            'recipient'        => $fields['recipient'] ?? '',
            'message'          => $fields['message'] ?? '',
            'active'           => 1,
            'use_raw_payload'  => (int) ($fields['use_raw_payload'] ?? 0),
            'raw_payload'      => $fields['raw_payload'] ?? '',
        ]);
        if (!$new_id) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Unable to clone rule']);
            exit;
        }
        $cf_table = \Glpi\Search\CriteriaFilter::getTable();
        global $DB;
        if ($DB->tableExists($cf_table)) {
            $cf_it = $DB->request([
                'FROM'   => $cf_table,
                'WHERE'  => [
                    'itemtype' => NotificationRule::class,
                    'items_id' => $src_id,
                ],
            ]);
            foreach ($cf_it as $cf_row) {
                $DB->insert($cf_table, [
                    'itemtype'        => NotificationRule::class,
                    'items_id'        => $new_id,
                    'search_itemtype' => $cf_row['search_itemtype'] ?? 'Ticket',
                    'search_criteria' => $cf_row['search_criteria'] ?? '[]',
                ]);
            }
        }
        echo json_encode(['ok' => true, 'new_id' => $new_id]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'clone_rule')]);
    }
    exit;
}

// ── AJAX: Delete Extended Filter ──
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
        $rule = new NotificationRule();
        if (!$rule->getFromDB($items_id)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Rule not found: ' . $items_id]);
            exit;
        }
        if (!$rule->canUpdateItem()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not allowed']);
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
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'delete_rule_filter')]);
    }
    exit;
}

// ── AJAX: Test webhook connection ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'test_webhook') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $webhookUrl = trim($_POST['test_webhook_url'] ?? '');
        $channel    = trim($_POST['test_channel'] ?? '');
        $message    = trim($_POST['test_message'] ?? '');
        $nickname   = trim($_POST['test_sender_nickname'] ?? '');
        $avatar     = trim($_POST['test_sender_avatar'] ?? '');

        $newCsrfToken = Session::getNewCSRFToken();

        if ($webhookUrl === '' || $channel === '' || $message === '') {
            http_response_code(400);
            echo json_encode([
                'ok'         => false,
                'error'      => 'Webhook URL, Channel and Message are required.',
                'csrf_token' => $newCsrfToken,
            ]);
            exit;
        }

        // SSRF protection: only allow http/https scheme
        $parsedUrl = parse_url($webhookUrl);
        if (!$parsedUrl || !in_array(strtolower($parsedUrl['scheme'] ?? ''), ['http', 'https'], true)) {
            http_response_code(400);
            echo json_encode([
                'ok'         => false,
                'error'      => 'Invalid webhook URL: only http and https schemes are allowed.',
                'csrf_token' => $newCsrfToken,
            ]);
            exit;
        }

        $error = null;
        $ok = MattermostClient::sendTestWebhook(
            $webhookUrl,
            $channel,
            $message,
            $nickname !== '' ? $nickname : null,
            $avatar !== '' ? $avatar : null,
            $error
        );

        // Persist test result
        try {
            global $DB;
            $table   = Config::getTable();
            $ts      = time();
            $success = $ok ? 1 : 0;
            $err     = $ok ? null : ($error ?? null);
            $DB->update($table, [
                'last_test_timestamp' => $ts,
                'last_test_success'   => $success,
                'last_test_error'     => $err,
            ], ['id' => 1]);
        } catch (\Throwable $e) {
            // don't break the response if status write fails
        }

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
            ]);
        }
    } catch (\Throwable $e) {
        try {
            global $DB;
            $table = Config::getTable();
            $DB->update($table, [
                'last_test_timestamp' => time(),
                'last_test_success'   => 0,
                'last_test_error'     => $e->getMessage(),
            ], ['id' => 1]);
        } catch (\Throwable $e2) {
            // ignore
        }
        http_response_code(500);
        echo json_encode([
            'ok'         => false,
            'error'      => mjl_ajax_error($e, 'test_webhook'),
            'csrf_token' => Session::getNewCSRFToken(),
        ]);
    }
    exit;
}

// ── AJAX: Toggle simulate send ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'toggle_simulate_send') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $cfgTable = Config::getTable();
        if (!$DB->fieldExists($cfgTable, 'simulate_send')) {
            $DB->doQuery("ALTER TABLE `$cfgTable` ADD COLUMN `simulate_send` tinyint(1) NOT NULL DEFAULT 0 AFTER `extended_log`");
        }
        $cfg = new Config();
        $cfg->getFromDB(1);
        $newValue = ((int) ($cfg->fields['simulate_send'] ?? 0)) === 1 ? 0 : 1;
        $cfg->update(['id' => 1, 'simulate_send' => $newValue]);
        echo json_encode(['ok' => true, 'simulate_send' => $newValue, 'csrf_token' => Session::getNewCSRFToken()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'toggle_simulate_send'), 'csrf_token' => Session::getNewCSRFToken()]);
    }
    exit;
}

// ── AJAX: Toggle extended log ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'toggle_extended_log') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $cfg = new Config();
        $cfg->getFromDB(1);
        $newValue = ((int) ($cfg->fields['extended_log'] ?? 0)) === 1 ? 0 : 1;
        $cfg->update(['id' => 1, 'extended_log' => $newValue]);
        echo json_encode(['ok' => true, 'extended_log' => $newValue, 'csrf_token' => Session::getNewCSRFToken()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'toggle_extended_log'), 'csrf_token' => Session::getNewCSRFToken()]);
    }
    exit;
}

// ── AJAX: Export rules (GET — read-only, no CSRF required) ──
if (isset($_GET['mattermost_ajax']) && $_GET['mattermost_ajax'] === 'export_rules') {
    Session::checkLoginUser();
    Session::checkRight('config', READ);

    $ids = $_GET['rule_ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_filter(array_map('intval', $ids));

    if (empty($ids)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No rules selected']);
        exit;
    }

    global $DB;
    $cf_table = \Glpi\Search\CriteriaFilter::getTable();
    $rules = [];

    foreach ($ids as $id) {
        $rule = new NotificationRule();
        if (!$rule->getFromDB($id) || !$rule->canView()) {
            continue;
        }
        $f = $rule->fields;

        // Decode raw_payload to object for cleaner JSON export
        $rawPayload = null;
        if ((int) ($f['use_raw_payload'] ?? 0) === 1 && !empty($f['raw_payload'])) {
            $decoded = json_decode($f['raw_payload'], true);
            $rawPayload = is_array($decoded) ? $decoded : $f['raw_payload'];
        }

        // Fetch extended filter
        $extFilter = null;
        if ($DB->tableExists($cf_table)) {
            $cf_it = $DB->request([
                'FROM'  => $cf_table,
                'WHERE' => ['itemtype' => NotificationRule::class, 'items_id' => $id],
                'LIMIT' => 1,
            ]);
            foreach ($cf_it as $cfRow) {
                $criteria = $cfRow['search_criteria'] ?? '[]';
                $extFilter = [
                    'search_itemtype' => $cfRow['search_itemtype'] ?? 'Ticket',
                    'search_criteria' => json_decode($criteria, true) ?? $criteria,
                ];
            }
        }

        $rules[] = [
            'name'            => $f['name'] ?? '',
            'target'          => $f['target'] ?? 'Ticket',
            'event'           => $f['event'] ?? '',
            'recipient'       => $f['recipient'] ?? '',
            'message'         => $f['message'] ?? '',
            'active'          => (int) ($f['active'] ?? 1),
            'use_raw_payload' => (int) ($f['use_raw_payload'] ?? 0),
            'raw_payload'     => $rawPayload,
            'extended_filter' => $extFilter,
        ];
    }

    $export = [
        'version'     => '1.0',
        'plugin'      => 'mattermostjetlag',
        'exported_at' => date('c'),
        'rules'       => $rules,
    ];

    $filename = 'mattermostjetlag-rules-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── AJAX: Import rules ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'import_rules') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');

    try {
        $jsonContent = $_POST['rules_json'] ?? '';
        if (empty($jsonContent)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Empty JSON content']);
            exit;
        }

        $data = json_decode($jsonContent, true);
        if (!is_array($data) || !isset($data['rules']) || !is_array($data['rules'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON format: missing "rules" array']);
            exit;
        }

        global $DB;
        $cf_table = \Glpi\Search\CriteriaFilter::getTable();
        $created  = 0;

        foreach ($data['rules'] as $ruleData) {
            if (!is_array($ruleData)) {
                continue;
            }

            // Re-encode raw_payload back to JSON string if it was exported as object
            $rawPayload = null;
            if (!empty($ruleData['raw_payload'])) {
                if (is_array($ruleData['raw_payload'])) {
                    $rawPayload = json_encode($ruleData['raw_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $rawPayload = (string) $ruleData['raw_payload'];
                }
            }

            $rule   = new NotificationRule();
            $new_id = $rule->add([
                'name'            => trim($ruleData['name'] ?? ''),
                'target'          => trim($ruleData['target'] ?? 'Ticket'),
                'event'           => trim($ruleData['event'] ?? ''),
                'recipient'       => trim($ruleData['recipient'] ?? ''),
                'message'         => trim($ruleData['message'] ?? ''),
                'active'          => (int) ($ruleData['active'] ?? 1),
                'use_raw_payload' => (int) ($ruleData['use_raw_payload'] ?? 0),
                'raw_payload'     => $rawPayload,
            ]);

            if (!$new_id) {
                continue;
            }
            $created++;

            // Import extended filter
            $ef = $ruleData['extended_filter'] ?? null;
            if (!empty($ef) && is_array($ef) && $DB->tableExists($cf_table)) {
                $criteria = $ef['search_criteria'] ?? '[]';
                $DB->insert($cf_table, [
                    'itemtype'        => NotificationRule::class,
                    'items_id'        => $new_id,
                    'search_itemtype' => $ef['search_itemtype'] ?? 'Ticket',
                    'search_criteria' => is_array($criteria)
                        ? json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : $criteria,
                ]);
            }
        }

        echo json_encode(['ok' => true, 'created' => $created, 'csrf_token' => Session::getNewCSRFToken()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'import_rules')]);
    }
    exit;
}

// ── AJAX: Clear event log ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'clear_event_log') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $table = EventLog::getTable();
        if ($DB->tableExists($table)) {
            $DB->doQuery("DELETE FROM `$table`");
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'clear_event_log')]);
    }
    exit;
}

// ── AJAX: Poll new event log entries ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'get_event_log') {
    Session::checkLoginUser();
    Session::checkRight('config', READ);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $sinceId = max(0, (int) ($_POST['since_id'] ?? 0));
        $table   = EventLog::getTable();
        $items   = [];
        if ($DB->tableExists($table)) {
            $eventLabels = \GlpiPlugin\Mattermostjetlag\Config\EditorTab::RULE_EVENTS;
            $rows = $DB->request([
                'FROM'  => $table,
                'WHERE' => [['id' => ['>', $sinceId]]],
                'ORDER' => ['id DESC'],
                'LIMIT' => 50,
            ]);
            foreach ($rows as $row) {
                $eventKey   = (string) ($row['event'] ?? '');
                $payloadArr = json_decode((string) ($row['payload'] ?? '{}'), true) ?? [];
                $rulesCount = is_array($payloadArr['matching_rule_ids'] ?? null)
                    ? count($payloadArr['matching_rule_ids'])
                    : 0;
                $items[] = [
                    'id'            => (int) $row['id'],
                    'target'        => (string) ($row['target'] ?? 'Ticket'),
                    'event'         => $eventLabels[$eventKey] ?? $eventKey,
                    'event_key'     => $eventKey,
                    'ticket_id'     => $row['ticket_id'] ? (int) $row['ticket_id'] : null,
                    'subject'       => (string) ($payloadArr['subject'] ?? ''),
                    'payload'       => (string) ($row['payload'] ?? '{}'),
                    'date_creation' => (string) ($row['date_creation'] ?? ''),
                    'rules_count'   => $rulesCount,
                ];
            }
        }
        echo json_encode([
            'ok'         => true,
            'items'      => $items,
            'csrf_token' => Session::getNewCSRFToken(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'get_event_log')]);
    }
    exit;
}

// ── AJAX: Self-test — create ticket ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'self_test_start') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $userId = Session::getLoginUserID();

        // Find 'tech' user by login; fall back to current user
        $techId = $userId;
        $techRows = $DB->request(['FROM' => User::getTable(), 'WHERE' => ['name' => 'tech'], 'LIMIT' => 1]);
        foreach ($techRows as $row) { $techId = (int) $row['id']; }

        $ticket   = new Ticket();
        $ticketId = $ticket->add([
            'name'                => '[MJL-TEST] Plugin Connectivity Check',
            'content'             => "This ticket was automatically created by the Mattermost Jetlag plugin as part of a self-diagnostic routine.\n\nIt will be automatically deleted at the end of the test.\nDo not modify or close this ticket manually.\n\nTest started: " . date('Y-m-d H:i:s'),
            'urgency'             => 1,
            'type'                => Ticket::INCIDENT_TYPE,
            'requesttypes_id'     => 1,
            '_users_id_requester' => $userId,
        ]);
        if (!$ticketId) {
            throw new \RuntimeException('Failed to create ticket');
        }
        echo json_encode([
            'ok'         => true,
            'ticket_id'  => (int) $ticketId,
            'tech_id'    => (int) $techId,
            'csrf_token' => Session::getNewCSRFToken(),
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'self_test_start')]);
    }
    exit;
}

// ── AJAX: Self-test — execute step ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'self_test_step') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        $step     = (string) ($_POST['step']      ?? '');
        $ticketId = (int)    ($_POST['ticket_id'] ?? 0);
        $valId    = (int)    ($_POST['val_id']    ?? 0);
        $solId    = (int)    ($_POST['sol_id']    ?? 0);
        $techId   = (int)    ($_POST['tech_id']   ?? 0);
        $userId   = Session::getLoginUserID();
        $result   = ['ok' => true, 'csrf_token' => Session::getNewCSRFToken()];

        switch ($step) {
            case 'add_observer':
                $tu = new Ticket_User();
                $tu->add([
                    'tickets_id' => $ticketId,
                    'users_id'   => $techId > 0 ? $techId : $userId,
                    'type'       => CommonITILActor::OBSERVER,
                ]);
                break;

            case 'add_followup':
                $fu = new ITILFollowup();
                $fu->add([
                    'items_id'   => $ticketId,
                    'itemtype'   => 'Ticket',
                    'content'    => '[MJL-TEST] Automated followup — diagnostic step',
                    'is_private' => 0,
                ]);
                break;

            case 'request_approval':
                $val   = new TicketValidation();
                $newId = $val->add([
                    'tickets_id'         => $ticketId,
                    'itemtype_target'    => 'User',
                    'items_id_target'    => $userId,
                    'comment_submission' => '[MJL-TEST] Automated approval request',
                ]);
                $result['val_id'] = (int) $newId;
                break;

            case 'reject_approval':
                if ($valId > 0) {
                    $val = new TicketValidation();
                    $val->update([
                        'id'                 => $valId,
                        'status'             => 4, // CommonITILValidation::REFUSED
                        'comment_validation' => '[MJL-TEST] Automated rejection',
                    ]);
                }
                break;

            case 'approve':
                if ($valId > 0) {
                    $val = new TicketValidation();
                    $val->update([
                        'id'                 => $valId,
                        'status'             => 3, // CommonITILValidation::ACCEPTED
                        'comment_validation' => '[MJL-TEST] Automated approval',
                    ]);
                }
                break;

            case 'change_status':
                $ticket = new Ticket();
                $ticket->update([
                    'id'     => $ticketId,
                    'status' => 3, // Processing
                ]);
                break;

            case 'add_solution':
                $sol   = new ITILSolution();
                $newId = $sol->add([
                    'itemtype' => 'Ticket',
                    'items_id' => $ticketId,
                    'content'  => '[MJL-TEST] Automated solution proposal',
                ]);
                $result['sol_id'] = (int) $newId;
                break;

            case 'reject_solution':
                if ($solId > 0) {
                    $sol = new ITILSolution();
                    $sol->update([
                        'id'     => $solId,
                        'status' => 4, // ITILSolution::REFUSED
                    ]);
                    // Direct ITILSolution update does not reset the ticket status.
                    // Move ticket back to Assigned so a new solution can be proposed.
                    $t = new Ticket();
                    $t->update(['id' => $ticketId, 'status' => 2]); // CommonITILObject::ASSIGNED
                }
                break;

            case 'approve_solution':
                if ($solId > 0) {
                    $sol = new ITILSolution();
                    $sol->update([
                        'id'     => $solId,
                        'status' => 3, // ITILSolution::ACCEPTED
                    ]);
                }
                break;

            case 'delete_ticket':
                $ticket = new Ticket();
                if (!$ticket->getFromDB($ticketId)) {
                    throw new \RuntimeException('Ticket #' . $ticketId . ' not found');
                }
                $ticket->delete(['id' => $ticketId]);
                break;

            default:
                throw new \RuntimeException('Unknown self-test step');
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'self_test_step')]);
    }
    exit;
}

// ── AJAX: Clear send log (Events Journal) ──
if (isset($_POST['mattermost_ajax']) && $_POST['mattermost_ajax'] === 'clear_send_log') {
    Session::checkLoginUser();
    Session::checkRight('config', UPDATE);
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $DB;
        $table = \GlpiPlugin\Mattermostjetlag\SendLog::getTable();
        if ($DB->tableExists($table)) {
            $DB->doQuery("DELETE FROM `$table`");
        }
        echo json_encode(['ok' => true, 'csrf_token' => Session::getNewCSRFToken()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => mjl_ajax_error($e, 'clear_send_log')]);
    }
    exit;
}

// ── Form POST: Save config ──
$config = new Config();

$tab_editor = Config::getType() . '$3'; // Rule Editor tab
$tab_rules  = Config::getType() . '$2'; // Rules List tab
$tab_debug  = Config::getType() . '$8'; // Debug Mode tab
$base_url   = \Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1';
$redirect_editor = $base_url . '&_glpi_tab=' . urlencode($tab_editor);
$redirect_rules  = $base_url . '&_glpi_tab=' . urlencode($tab_rules);
$redirect_debug  = $base_url . '&_glpi_tab=' . urlencode($tab_debug);

if (isset($_POST['update_config'])) {
    $allowed = ['connection_type', 'webhook_url', 'webhook_bot_nickname', 'webhook_bot_avatar',
                'mattermost_url', 'mattermost_login', 'mattermost_password'];
    $config->update(array_intersect_key($_POST, array_flip($allowed)) + ['id' => 1]);
    Html::back();
}

if (isset($_POST['update_debug_config'])) {
    $config->update([
        'id'           => 1,
        'extended_log' => (int) ($_POST['extended_log'] ?? 0),
    ]);
    Session::addMessageAfterRedirect(__('Saved', 'mattermostjetlag'));
    Html::redirect($redirect_debug);
}

if (isset($_POST['update_variables_override'])) {
    $validTypes  = ['ticket', 'change', 'problem'];
    $validGroups = ['status', 'urgency', 'impact', 'priority', 'type'];
    $overrides   = [];
    foreach ($validTypes as $t) {
        $typeData = $_POST[$t] ?? [];
        if (!is_array($typeData)) {
            continue;
        }
        foreach ($validGroups as $g) {
            $vals = $typeData[$g] ?? [];
            if (!is_array($vals)) {
                continue;
            }
            foreach ($vals as $code => $val) {
                $val = trim((string) $val);
                if ($val !== '') {
                    $overrides[$t][$g][(string)(int) $code] = $val;
                }
            }
        }
    }
    $config->update([
        'id'                 => 1,
        'variables_override' => json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    Session::addMessageAfterRedirect(__('Saved', 'mattermostjetlag'));
    $tab_override = Config::getType() . '$5';
    Html::redirect($base_url . '&_glpi_tab=' . urlencode($tab_override));
}

if (isset($_POST['add_notification_rule']) && trim($_POST['rule_name'] ?? '') !== '') {
    $rule = new NotificationRule();
    $new_id = $rule->add([
        'name'             => trim($_POST['rule_name']),
        'target'           => trim($_POST['rule_target'] ?? 'Ticket'),
        'event'             => trim($_POST['rule_event'] ?? 'New'),
        'recipient'        => trim($_POST['rule_recipient'] ?? ''),
        'message'          => trim($_POST['rule_message'] ?? ''),
        'active'           => (int) ($_POST['rule_active'] ?? 1),
        'use_raw_payload'  => (int) ($_POST['rule_use_raw_payload'] ?? 0),
        'raw_payload'      => trim($_POST['rule_raw_payload'] ?? ''),
    ]);
    if ($new_id) {
        Session::addMessageAfterRedirect(__('Rule saved', 'mattermostjetlag'));
        Html::redirect($redirect_rules);
    } else {
        Session::addMessageAfterRedirect(__('Unable to create rule', 'mattermostjetlag'), false, ERROR);
        Html::back();
    }
}

if (isset($_POST['update_notification_rule']) && (int) ($_POST['rule_id'] ?? 0) > 0) {
    $rule_id = (int) $_POST['rule_id'];
    $rule = new NotificationRule();
    $rule->update([
        'id'               => $rule_id,
        'name'             => trim($_POST['rule_name'] ?? ''),
        'target'           => trim($_POST['rule_target'] ?? 'Ticket'),
        'event'            => trim($_POST['rule_event'] ?? 'New'),
        'recipient'        => trim($_POST['rule_recipient'] ?? ''),
        'message'          => trim($_POST['rule_message'] ?? ''),
        'active'           => (int) ($_POST['rule_active'] ?? 1),
        'use_raw_payload'  => (int) ($_POST['rule_use_raw_payload'] ?? 0),
        'raw_payload'      => trim($_POST['rule_raw_payload'] ?? ''),
    ]);
    Session::addMessageAfterRedirect(__('Rule saved', 'mattermostjetlag'));
    Html::redirect($redirect_rules);
}

// ── Default: Render config page ──
if (!isset($_GET['id'])) {
    $_GET['id'] = 1;
}
// Default tab: Rules List when none specified
if (empty($_GET['_glpi_tab'])) {
    $_GET['_glpi_tab'] = $tab_rules;
}
Session::setActiveTab(Config::getType(), $_GET['_glpi_tab']);
Html::header(__('Mattermost Jetlag', 'mattermostjetlag'), $_SERVER['PHP_SELF'], 'config', 'plugin', 'mattermostjetlag');

$simulateSendActive = (int) ($config->fields['simulate_send'] ?? 0) === 1;
$bannerHidden = $simulateSendActive ? '' : ' d-none';
echo '<div id="mjl-simulate-banner" class="alert alert-warning d-flex align-items-center mx-3 mt-3' . $bannerHidden . '" role="alert">';
echo '<span class="ti ti-alert-triangle me-2 fs-4"></span>';
echo '<div><strong>' . __('Notify Simulation is active', 'mattermostjetlag') . '</strong> — ';
echo __('Messages will not be sent to Mattermost. Disable simulation in the Debug Mode tab to resume real notifications.', 'mattermostjetlag');
echo '</div></div>';

$config->display($_GET);
Html::footer();
