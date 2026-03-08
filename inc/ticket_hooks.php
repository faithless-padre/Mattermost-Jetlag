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

use Change;
use CommonDBTM;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\Config\VariablesOverrideTab;
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use GlpiPlugin\Mattermostjetlag\SendLog;
use Ticket;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/ticket_logger.php';

/**
 * Load variables_override from config (cached for the duration of the request).
 */
function plugin_mattermostjetlag_get_variables_override(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cfg = new Config();
    if (!$cfg->getFromDB(1)) {
        $cache = [];
        return $cache;
    }
    $raw = $cfg->fields['variables_override'] ?? null;
    if (!$raw) {
        $cache = [];
        return $cache;
    }
    $decoded = json_decode((string) $raw, true);
    $cache = is_array($decoded) ? $decoded : [];
    return $cache;
}

/**
 * action → event (DB value stored in rule.event). All map 1:1.
 */
function plugin_mattermostjetlag_map_action_to_rule_event(string $action): ?string
{
    $mapping = [
        // Ticket events
        'create'         => 'create',
        'update'         => 'update',
        'followup'       => 'followup',
        'approval'       => 'approval',
        'approved'       => 'approved',
        'rejected'       => 'rejected',
        'status_changed' => 'status_changed',
        'members_change' => 'members_change',
        'solution'          => 'solution',
        'solution_approved' => 'solution_approved',
        'solution_rejected' => 'solution_rejected',
        'delete'            => 'delete',
        // Change events (prefixed to avoid collision in DB rule.event column)
        'change_create'            => 'change_create',
        'change_update'            => 'change_update',
        'change_status_changed'    => 'change_status_changed',
        'change_delete'            => 'change_delete',
        'change_followup'          => 'change_followup',
        'change_solution'          => 'change_solution',
        'change_solution_approved' => 'change_solution_approved',
        'change_solution_rejected' => 'change_solution_rejected',
        'change_approval'          => 'change_approval',
        'change_approved'          => 'change_approved',
        'change_rejected'          => 'change_rejected',
    ];
    return $mapping[$action] ?? null;
}

/**
 * Determine the rule target (Ticket or Change) based on the action and item type.
 */
function plugin_mattermostjetlag_determine_rule_target(string $action, CommonDBTM $item): string
{
    $type = $item::getType();
    // Change-specific item types
    if (in_array($type, ['Change', 'ChangeValidation'], true)) {
        return 'Change';
    }
    // ITILFollowup and ITILSolution can belong to either Ticket or Change
    if (in_array($type, ['ITILFollowup', 'ITILSolution'], true)) {
        $parentType = $item->fields['itemtype'] ?? 'Ticket';
        if ($parentType === 'Change') {
            return 'Change';
        }
    }
    return 'Ticket';
}

/**
 * Проверка Extended Filter правила.
 */
function plugin_mattermostjetlag_ticket_matches_rule_filter(CommonDBTM $item, NotificationRule $rule): bool
{
    if ($item::getType() !== $rule->getItemtypeToFilter()) {
        return false;
    }
    try {
        return $rule->itemMatchFilter($item);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Активные правила, под которые попадает событие.
 */
function plugin_mattermostjetlag_get_matching_rules(string $action, CommonDBTM $item): array
{
    global $DB;

    $ruleTarget = plugin_mattermostjetlag_determine_rule_target($action, $item);
    $ruleEvent  = plugin_mattermostjetlag_map_action_to_rule_event($action);
    if ($ruleEvent === null) {
        return [];
    }

    $rule = new NotificationRule();
    $rules = $DB->request([
        'FROM'   => $rule->getTable(),
        'WHERE'  => ['active' => 1, 'target' => $ruleTarget, 'event' => $ruleEvent],
    ]);

    $filterItem = null;
    if ($ruleTarget === 'Ticket') {
        if ($item::getType() === 'Ticket') {
            $filterItem = $item;
        } elseif (in_array($item::getType(), ['ITILFollowup', 'ITILSolution'], true) && isset($item->fields['items_id'])) {
            $filterItem = new Ticket();
            if (!$filterItem->getFromDB((int) $item->fields['items_id'])) {
                return [];
            }
        } elseif ($item::getType() === 'TicketValidation' && isset($item->fields['tickets_id'])) {
            $filterItem = new Ticket();
            if (!$filterItem->getFromDB((int) $item->fields['tickets_id'])) {
                return [];
            }
        }
    } elseif ($ruleTarget === 'Change') {
        if ($item::getType() === 'Change') {
            $filterItem = $item;
        } elseif (in_array($item::getType(), ['ITILFollowup', 'ITILSolution'], true) && isset($item->fields['items_id'])) {
            $filterItem = new Change();
            if (!$filterItem->getFromDB((int) $item->fields['items_id'])) {
                return [];
            }
        } elseif ($item::getType() === 'ChangeValidation' && isset($item->fields['changes_id'])) {
            $filterItem = new Change();
            if (!$filterItem->getFromDB((int) $item->fields['changes_id'])) {
                return [];
            }
        }
    } else {
        $filterItem = $item;
    }

    if ($filterItem === null) {
        return [];
    }

    $matchingRules = [];
    foreach ($rules as $ruleData) {
        $ruleObj = new NotificationRule();
        if (!$ruleObj->getFromDB($ruleData['id'])) {
            continue;
        }
        if (plugin_mattermostjetlag_ticket_matches_rule_filter($filterItem, $ruleObj)) {
            $matchingRules[] = $ruleObj;
        }
    }
    return $matchingRules;
}

/**
 * Загрузка акторов из glpi_tickets_users.
 */
function plugin_mattermostjetlag_load_ticket_actors_from_db(int $ticketId): array
{
    global $DB;

    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if ($ticketId <= 0) {
        return ['requester_ids' => [], 'observer_ids' => [], 'assignee_ids' => []];
    }

    $actors = $DB->request([
        'FROM'   => 'glpi_tickets_users',
        'WHERE'  => ['tickets_id' => $ticketId],
    ]);

    foreach ($actors as $actor) {
        $userId = (int) ($actor['users_id'] ?? 0);
        $type   = (int) ($actor['type'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        if ($type === 1) {
            $requesterIds[] = $userId;
        } elseif ($type === 2) {
            $assigneeIds[] = $userId;
        } elseif ($type === 3) {
            $observerIds[] = $userId;
        }
    }

    return [
        'requester_ids' => array_values(array_unique($requesterIds)),
        'observer_ids'  => array_values(array_unique($observerIds)),
        'assignee_ids'  => array_values(array_unique($assigneeIds)),
    ];
}

/**
 * Загрузка акторов из glpi_changes_users.
 */
function plugin_mattermostjetlag_load_change_actors_from_db(int $changeId): array
{
    global $DB;

    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if ($changeId <= 0) {
        return ['requester_ids' => [], 'observer_ids' => [], 'assignee_ids' => []];
    }

    $actors = $DB->request([
        'FROM'   => 'glpi_changes_users',
        'WHERE'  => ['changes_id' => $changeId],
    ]);

    foreach ($actors as $actor) {
        $userId = (int) ($actor['users_id'] ?? 0);
        $type   = (int) ($actor['type'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        if ($type === 1) {
            $requesterIds[] = $userId;
        } elseif ($type === 2) {
            $assigneeIds[] = $userId;
        } elseif ($type === 3) {
            $observerIds[] = $userId;
        }
    }

    return [
        'requester_ids' => array_values(array_unique($requesterIds)),
        'observer_ids'  => array_values(array_unique($observerIds)),
        'assignee_ids'  => array_values(array_unique($assigneeIds)),
    ];
}

/**
 * Сбор данных изменения для лога.
 */
function plugin_mattermostjetlag_build_change_log_data(CommonDBTM $change, string $action, array $extra = []): array
{
    if ($change::getType() !== 'Change') {
        return [];
    }

    $changeId   = $change->getID();
    $changeName = $change->fields['name'] ?? '';

    $vo  = plugin_mattermostjetlag_get_variables_override();
    $voc = $vo['change'] ?? []; // change-specific overrides

    $statusMap = [
        1  => 'New',
        4  => 'Pending',
        5  => 'Applied',
        6  => 'Closed',
        7  => 'Accepted',
        8  => 'Review',
        9  => 'Evaluation',
        10 => 'Approval',
        11 => 'Testing',
        12 => 'Qualification',
        13 => 'Refused',
        14 => 'Cancelled',
    ];
    $statusCode   = (int) ($change->fields['status'] ?? 0);
    $changeStatus = $voc['status'][(string) $statusCode]
        ?? $statusMap[$statusCode]
        ?? null;

    $urgencyCode  = (int) ($change->fields['urgency'] ?? 0);
    $impactCode   = (int) ($change->fields['impact'] ?? 0);
    $priorityCode = (int) ($change->fields['priority'] ?? 0);
    $changeUrgency  = $urgencyCode > 0
        ? ($voc['urgency'][(string) $urgencyCode] ?? \CommonITILObject::getUrgencyName($urgencyCode))
        : null;
    $changeImpact   = $impactCode > 0
        ? ($voc['impact'][(string) $impactCode] ?? \CommonITILObject::getImpactName($impactCode))
        : null;
    $changePriority = $priorityCode > 0
        ? ($voc['priority'][(string) $priorityCode] ?? \CommonITILObject::getPriorityName($priorityCode))
        : null;

    $changeCategory = null;
    if (!empty($change->fields['itilcategories_id'])) {
        $catId = (int) $change->fields['itilcategories_id'];
        if ($catId > 0) {
            $cat = new \ITILCategory();
            if ($cat->getFromDB($catId)) {
                $changeCategory = $cat->getName();
            }
        }
    }

    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if (property_exists($change, 'input') && is_array($change->input ?? null) && !empty($change->input)) {
        $input = $change->input;
        $extractIds = function (array $src, string $key): array {
            if (!isset($src[$key]) || !is_array($src[$key])) {
                return [];
            }
            $ids = [];
            foreach ($src[$key] as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return array_values(array_unique($ids));
        };
        $requesterIds = $extractIds($input, '_users_id_requester');
        $observerIds  = $extractIds($input, '_users_id_observer');
        $assigneeIds  = $extractIds($input, '_users_id_assign');
    }

    if (empty($requesterIds) && empty($observerIds) && empty($assigneeIds)) {
        $actors = plugin_mattermostjetlag_load_change_actors_from_db($changeId);
        $requesterIds = $actors['requester_ids'];
        $observerIds  = $actors['observer_ids'];
        $assigneeIds  = $actors['assignee_ids'];
    }

    $idsToLogins = function (array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $user   = new User();
        $result = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            if ($user->getFromDB($id)) {
                $login = $user->fields['login'] ?? $user->fields['name'] ?? null;
                if (!empty($login)) {
                    $result[] = $login;
                }
            }
        }
        return array_values(array_unique($result));
    };

    $data = [
        'datetime'         => date('c'),
        'action'           => $action,
        'ticket_id'        => $changeId, // reuse ticket_id key for dispatch compatibility
        'subject'          => $changeName,
        'urgency'          => $changeUrgency,
        'impact'           => $changeImpact,
        'priority'         => $changePriority,
        'category'         => $changeCategory,
        'status'           => $changeStatus,
        'event'            => plugin_mattermostjetlag_map_action_to_rule_event($action) ?? '',
        'requester_logins' => $idsToLogins($requesterIds),
        'observer_logins'  => $idsToLogins($observerIds),
        'assignee_logins'  => $idsToLogins($assigneeIds),
        '_item_class'      => 'Change',
    ];

    if (property_exists($change, 'input') && is_array($change->input ?? null) && !empty($change->input)) {
        $data['hook_input'] = $change->input;
    }

    if (!empty($extra)) {
        $data = array_merge($data, $extra);
    }

    return $data;
}

/**
 * Сбор данных заявки для лога (логика как в старом плагине).
 */
function plugin_mattermostjetlag_build_ticket_log_data(CommonDBTM $ticket, string $action, array $extra = []): array
{
    if ($ticket::getType() !== 'Ticket') {
        return [];
    }

    $ticketId   = $ticket->getID();
    $ticketName = $ticket->fields['name'] ?? '';

    $vo  = plugin_mattermostjetlag_get_variables_override();
    $vot = $vo['ticket'] ?? []; // ticket-specific overrides

    $statusMap = [
        1  => 'New',
        10 => 'Approval',
        2  => 'Processing (assigned)',
        3  => 'Processing (planned)',
        4  => 'Pending',
        5  => 'Solved',
        6  => 'Closed',
    ];
    $statusCode   = (int) ($ticket->fields['status'] ?? 0);
    $ticketStatus = $vot['status'][(string) $statusCode]
        ?? $statusMap[$statusCode]
        ?? null;

    $typeMap  = [1 => 'Incident', 2 => 'Request'];
    $typeCode = (int) ($ticket->fields['type'] ?? 0);
    $ticketType = $vot['type'][(string) $typeCode]
        ?? $typeMap[$typeCode]
        ?? null;

    $urgencyCode  = (int) ($ticket->fields['urgency'] ?? 0);
    $impactCode   = (int) ($ticket->fields['impact'] ?? 0);
    $priorityCode = (int) ($ticket->fields['priority'] ?? 0);
    $ticketUrgency  = $urgencyCode > 0
        ? ($vot['urgency'][(string) $urgencyCode] ?? \CommonITILObject::getUrgencyName($urgencyCode))
        : null;
    $ticketImpact   = $impactCode > 0
        ? ($vot['impact'][(string) $impactCode] ?? \CommonITILObject::getImpactName($impactCode))
        : null;
    $ticketPriority = $priorityCode > 0
        ? ($vot['priority'][(string) $priorityCode] ?? \CommonITILObject::getPriorityName($priorityCode))
        : null;

    $ticketCategory = null;
    if (!empty($ticket->fields['itilcategories_id'])) {
        $catId = (int) $ticket->fields['itilcategories_id'];
        if ($catId > 0) {
            $cat = new \ITILCategory();
            if ($cat->getFromDB($catId)) {
                $ticketCategory = $cat->getName();
            }
        }
    }

    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if (property_exists($ticket, 'input') && is_array($ticket->input ?? null) && !empty($ticket->input)) {
        $input = $ticket->input;
        $extractIds = function (array $src, string $key): array {
            if (!isset($src[$key]) || !is_array($src[$key])) {
                return [];
            }
            $ids = [];
            foreach ($src[$key] as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return array_values(array_unique($ids));
        };
        $requesterIds = $extractIds($input, '_users_id_requester');
        $observerIds  = $extractIds($input, '_users_id_observer');
        $assigneeIds  = $extractIds($input, '_users_id_assign');
    }

    if (empty($requesterIds) && empty($observerIds) && empty($assigneeIds)) {
        $actors = plugin_mattermostjetlag_load_ticket_actors_from_db($ticketId);
        $requesterIds = $actors['requester_ids'];
        $observerIds  = $actors['observer_ids'];
        $assigneeIds  = $actors['assignee_ids'];
    }

    $idsToLogins = function (array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $user   = new User();
        $result = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            if ($user->getFromDB($id)) {
                $login = $user->fields['login'] ?? $user->fields['name'] ?? null;
                if (!empty($login)) {
                    $result[] = $login;
                }
            }
        }
        return array_values(array_unique($result));
    };

    $data = [
        'datetime'         => date('c'),
        'action'           => $action,
        'ticket_id'        => $ticketId,
        'subject'          => $ticketName,
        'type'             => $ticketType,
        'urgency'          => $ticketUrgency,
        'impact'           => $ticketImpact,
        'priority'         => $ticketPriority,
        'category'         => $ticketCategory,
        'status'           => $ticketStatus,
        'event'            => plugin_mattermostjetlag_map_action_to_rule_event($action) ?? '',
        'requester_logins' => $idsToLogins($requesterIds),
        'observer_logins'  => $idsToLogins($observerIds),
        'assignee_logins'  => $idsToLogins($assigneeIds),
    ];

    if (property_exists($ticket, 'input') && is_array($ticket->input ?? null) && !empty($ticket->input)) {
        $data['hook_input'] = $ticket->input;
    }

    if (!empty($extra)) {
        $data = array_merge($data, $extra);
    }

    return $data;
}

/**
 * Рендер сообщения по правилам для лога.
 */
function plugin_mattermostjetlag_render_rule_messages_for_ticket(array $matchingRules, array $data): string
{
    if (empty($matchingRules)) {
        return '';
    }

    foreach ($matchingRules as $rule) {
        if (!$rule instanceof NotificationRule) {
            continue;
        }
        $template = (string) ($rule->getField('message') ?? '');
        if ($template === '') {
            continue;
        }
        $replacements = plugin_mattermostjetlag_build_replacements($data);
        return json_encode(
            ['message' => strtr($template, $replacements)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    return '';
}

/**
 * Разворачивает строку получателей из правила в плоский массив адресатов.
 * Макросы 'assigned', 'requester', 'observer' заменяются на @login акторов заявки.
 * Прочие токены (#channel, @user) передаются как есть.
 * Дубли удаляются через array_unique.
 */
function plugin_mattermostjetlag_expand_recipients(string $recipientStr, array $data): array
{
    $tokens = array_filter(array_map('trim', explode(',', $recipientStr)));
    $result = [];

    $addLogins = static function (array $logins) use (&$result): void {
        foreach ($logins as $login) {
            $login = (string) $login;
            if ($login === '') {
                continue;
            }
            $result[] = str_starts_with($login, '@') ? $login : '@' . $login;
        }
    };

    foreach ($tokens as $token) {
        $normalized = trim($token, '{}');
        if ($normalized === 'assigned') {
            $addLogins(is_array($data['assignee_logins'] ?? null) ? $data['assignee_logins'] : []);
        } elseif ($normalized === 'requester') {
            $addLogins(is_array($data['requester_logins'] ?? null) ? $data['requester_logins'] : []);
        } elseif ($normalized === 'observer') {
            $addLogins(is_array($data['observer_logins'] ?? null) ? $data['observer_logins'] : []);
        } elseif ($normalized === 'approver') {
            $approver = (string) ($data['approval_target'] ?? '');
            if ($approver !== '') {
                $result[] = str_starts_with($approver, '@') ? $approver : '@' . $approver;
            }
        } else {
            $result[] = $token;
        }
    }

    return array_values(array_unique($result));
}

/**
 * Строит массив замен макросов для данных заявки.
 * Используется как для text-шаблонов, так и для raw JSON payload.
 */
function plugin_mattermostjetlag_build_replacements(array $data): array
{
    $itemId          = (int) ($data['ticket_id'] ?? 0);
    $itemClass       = (string) ($data['_item_class'] ?? 'Ticket');
    $requesterLogins = is_array($data['requester_logins'] ?? null) ? $data['requester_logins'] : [];
    $observerLogins  = is_array($data['observer_logins'] ?? null) ? $data['observer_logins'] : [];
    $assigneeLogins  = is_array($data['assignee_logins'] ?? null) ? $data['assignee_logins'] : [];

    $formatLogins = static function (array $logins): string {
        $parts = [];
        foreach ($logins as $login) {
            $login = (string) $login;
            if ($login === '') {
                continue;
            }
            $parts[] = str_starts_with($login, '@') ? $login : '@' . $login;
        }
        return implode(', ', $parts);
    };

    $link = '';
    if ($itemId > 0) {
        global $CFG_GLPI;
        if ($itemClass === 'Change') {
            $relative = Change::getFormURLWithID($itemId, false);
        } else {
            $relative = Ticket::getFormURLWithID($itemId, false);
        }
        $link = ($CFG_GLPI['url_base'] ?? '') . $relative;
    }

    $approverLogin = (string) ($data['approval_target'] ?? '');
    $approverFormatted = $approverLogin !== ''
        ? (str_starts_with($approverLogin, '@') ? $approverLogin : '@' . $approverLogin)
        : '';

    return [
        '{id}'        => $itemId > 0 ? (string) $itemId : '',
        '{title}'     => (string) ($data['subject'] ?? ''),
        '{status}'    => (string) ($data['status'] ?? ''),
        '{type}'      => (string) ($data['type'] ?? ''),
        '{event}'     => (string) ($data['event'] ?? ''),
        '{urgency}'   => (string) ($data['urgency'] ?? ''),
        '{impact}'    => (string) ($data['impact'] ?? ''),
        '{priority}'  => (string) ($data['priority'] ?? ''),
        '{category}'  => (string) ($data['category'] ?? ''),
        '{link}'      => $link,
        '{requester}' => $formatLogins($requesterLogins),
        '{observer}'  => $formatLogins($observerLogins),
        '{assigned}'  => $formatLogins($assigneeLogins),
        '{approver}'  => $approverFormatted,
    ];
}

/**
 * Рендерит сообщение одного правила, подставляя макросы из данных заявки.
 * Возвращает plain text для отправки в Mattermost.
 */
function plugin_mattermostjetlag_render_single_rule_message(
    \GlpiPlugin\Mattermostjetlag\NotificationRule $rule,
    array $data
): string {
    $template = (string) ($rule->getField('message') ?? '');
    if ($template === '') {
        return '';
    }
    return strtr($template, plugin_mattermostjetlag_build_replacements($data));
}

/**
 * Применяет макросы к raw JSON payload через decode → strtr → encode.
 * json_encode на выходе гарантирует валидный JSON независимо от спецсимволов в значениях.
 * Возвращает null если raw_payload содержит невалидный JSON.
 */
function plugin_mattermostjetlag_apply_macros_to_raw_payload(string $rawPayload, array $replacements): ?string
{
    $decoded = json_decode($rawPayload, true);
    if (!is_array($decoded)) {
        return null;
    }
    array_walk_recursive($decoded, static function (&$value) use ($replacements): void {
        if (is_string($value)) {
            $value = strtr($value, $replacements);
        }
    });
    $result = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $result !== false ? $result : null;
}

/** @deprecated File logging removed; send results are stored in dispatch_results inside DB event log. */
function plugin_mattermostjetlag_log_mattermost_send(
    int $ruleId,
    string $webhookUrl,
    ?string $payloadJson,
    bool $simulate,
    bool $ok,
    string $error
): void {
}

/**
 * Диспатч уведомлений по всем совпавшим правилам.
 * Для каждого правила разворачивает получателей и отправляет отдельный запрос каждому.
 * При simulate_send=1 — имитирует отправку без реального HTTP-запроса.
 *
 * raw_payload — дополнение к базовому payload (text + channel).
 * Поля text и channel из raw_payload игнорируются: они всегда формируются плагином.
 *
 * Возвращает массив результатов для записи в лог.
 */
function plugin_mattermostjetlag_dispatch_notifications(array $matchingRules, array $data): array
{
    $config = new \GlpiPlugin\Mattermostjetlag\Config();
    if (!$config->getFromDB(1)) {
        return [];
    }

    $fields     = $config->fields ?? [];
    $simulate   = (int) ($fields['simulate_send'] ?? 0) === 1;
    $connType   = (string) ($fields['connection_type'] ?? '');
    $webhookUrl = (string) ($fields['webhook_url'] ?? '');
    $nickname   = ($fields['webhook_bot_nickname'] ?? '') ?: null;
    $avatar     = ($fields['webhook_bot_avatar'] ?? '') ?: null;

    $notConfigured = ($connType !== \GlpiPlugin\Mattermostjetlag\Config::CONNECTION_WEBHOOK || $webhookUrl === '');

    $results = [];

    foreach ($matchingRules as $rule) {
        if (!$rule instanceof \GlpiPlugin\Mattermostjetlag\NotificationRule) {
            continue;
        }

        $message = plugin_mattermostjetlag_render_single_rule_message($rule, $data);
        if ($message === '') {
            continue;
        }

        $recipientStr = (string) ($rule->getField('recipient') ?? '');
        $recipients   = plugin_mattermostjetlag_expand_recipients($recipientStr, $data);

        // Pre-decode raw_payload once (outside recipient loop).
        // text and channel are stripped — they are always controlled by the plugin.
        $rawExtra       = [];
        $rawDecodeError = null;
        if ((int) ($rule->getField('use_raw_payload') ?? 0) === 1) {
            $rawTemplate = (string) ($rule->getField('raw_payload') ?? '');
            if ($rawTemplate !== '') {
                $replacements = plugin_mattermostjetlag_build_replacements($data);
                $resolved     = plugin_mattermostjetlag_apply_macros_to_raw_payload($rawTemplate, $replacements);
                if ($resolved === null) {
                    $rawDecodeError = 'raw_payload contains invalid JSON';
                } else {
                    $decoded = json_decode($resolved, true);
                    if (!is_array($decoded)) {
                        $rawDecodeError = 'raw_payload decoded to non-array';
                    } else {
                        unset($decoded['text'], $decoded['channel']);
                        $rawExtra = $decoded;
                    }
                }
            }
        }

        foreach ($recipients as $recipient) {
            $entry = [
                'rule_id'   => $rule->getID(),
                'recipient' => $recipient,
                'simulate'  => $simulate,
            ];

            if ($rawDecodeError !== null) {
                $entry['ok']    = false;
                $entry['error'] = $rawDecodeError;
                $results[] = $entry;
                continue;
            }

            // Build base payload
            $payload = ['text' => $message, 'channel' => $recipient];
            if ($nickname !== null && $nickname !== '') {
                $payload['username'] = $nickname;
            }
            if ($avatar !== null && $avatar !== '') {
                if (preg_match('~^https?://~i', $avatar)) {
                    $payload['icon_url'] = $avatar;
                } else {
                    $payload['icon_emoji'] = $avatar;
                }
            }

            // Merge raw_payload extras on top (text/channel already stripped)
            if (!empty($rawExtra)) {
                $payload = array_merge($payload, $rawExtra);
            }

            $payloadJson      = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $entry['payload'] = $payloadJson;

            $sendHttpCode = 0;
            if ($notConfigured) {
                $entry['ok']    = false;
                $entry['error'] = 'Not configured';
                $sendHttpCode   = 666;
            } elseif ($simulate) {
                $entry['ok']  = true;
                $sendHttpCode = 0;
            } else {
                $error        = null;
                $entry['ok']  = \GlpiPlugin\Mattermostjetlag\MattermostClient::sendRaw($webhookUrl, $payloadJson, $error, $sendHttpCode);
                if (!$entry['ok']) {
                    $entry['error'] = $error;
                }
            }

            SendLog::record(
                (string) ($data['_item_class'] ?? 'Ticket'),
                (int) ($data['ticket_id'] ?? 0),
                (string) ($data['event'] ?? ''),
                $rule->getID(),
                $recipient,
                $sendHttpCode,
                $simulate,
                $entry['error'] ?? null,
                ($data['subject'] ?? '') !== '' ? (string) $data['subject'] : null,
                !empty($data['requester_logins']) ? (string) $data['requester_logins'][0] : null,
                ($data['urgency'] ?? '') !== '' ? (string) $data['urgency'] : null
            );

            if (!$notConfigured) {
                plugin_mattermostjetlag_log_mattermost_send(
                    $rule->getID(), $webhookUrl, $payloadJson, $simulate, $entry['ok'], $entry['error'] ?? ''
                );
            }
            $results[] = $entry;
        }
    }

    return $results;
}

/**
 * Возвращает true, если заявка была создана менее 10 секунд назад.
 * Используется для игнорирования «мусорных» событий update/approval/followup/members_change,
 * которые GLPI генерирует сразу после создания тикета.
 */
function plugin_mattermostjetlag_ticket_is_too_fresh(CommonDBTM $ticket): bool
{
    $created = strtotime($ticket->fields['date_creation'] ?? '');
    return $created !== false && (time() - $created) < 10;
}

// ── Hooks ──

function plugin_mattermostjetlag_pre_item_delete_Ticket(CommonDBTM $item): void
{
    $action = 'delete';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($item, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('pre_item_delete_Ticket', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_Ticket(CommonDBTM $item): void
{
    $action = 'create';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($item, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_Ticket', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_Ticket(CommonDBTM $item): void
{
    if (plugin_mattermostjetlag_ticket_is_too_fresh($item)) {
        return;
    }
    $updates = $item->updates ?? [];
    // Skip updates that only touch validation side-effect fields (GLPI sets these when
    // a TicketValidation is added/accepted/refused, which is already logged separately).
    $validationSideEffects = ['global_validation', 'validation_percent'];
    if (!empty($updates) && empty(array_diff($updates, $validationSideEffects))) {
        return;
    }
    if (in_array('status', $updates)) {
        $actions = ['status_changed'];
    } else {
        $actions = ['update'];
    }

    $baseData = plugin_mattermostjetlag_build_ticket_log_data($item, $actions[0]);
    if (empty($baseData)) {
        return;
    }

    foreach ($actions as $action) {
        $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
        $data = $baseData;
        $data['action'] = $action;
        $data['event']  = plugin_mattermostjetlag_map_action_to_rule_event($action) ?? '';
        $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
        if (empty($matchingRules)) {
            $data['matching_rules_note'] = 'no matching rules';
        } else {
            $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
            $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
        }
        plugin_mattermostjetlag_log_debug('item_update_Ticket', $action, $item, null, $data);
        plugin_mattermostjetlag_log_ticket($data);
    }
}

/**
 * Detects whether a followup was created as part of a solution review.
 * Solution approval/rejection are now handled directly by item_update_ITILSolution,
 * so this always returns null to avoid double events when the UI creates a followup
 * alongside the solution status update.
 */
function plugin_mattermostjetlag_detect_solution_followup_event(Ticket $ticket): ?string
{
    return null;
}

function plugin_mattermostjetlag_item_add_ITILFollowup(CommonDBTM $item): void
{
    if (!isset($item->fields['itemtype'], $item->fields['items_id'])) {
        return;
    }
    $parentType = $item->fields['itemtype'];
    $parentId   = (int) $item->fields['items_id'];
    if ($parentId <= 0) {
        return;
    }

    if ($parentType === 'Change') {
        $change = new Change();
        if (!$change->getFromDB($parentId)) {
            return;
        }
        if (plugin_mattermostjetlag_ticket_is_too_fresh($change)) {
            return;
        }
        $action = 'change_followup';
        $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
        $data = plugin_mattermostjetlag_build_change_log_data($change, $action);
        if (empty($data)) {
            return;
        }
        if (!empty($item->input) && is_array($item->input)) {
            $data['hook_input'] = $item->input;
        }
        $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
        if (empty($matchingRules)) {
            $data['matching_rules_note'] = 'no matching rules';
        } else {
            $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
            $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
        }
        plugin_mattermostjetlag_log_debug('item_add_ITILFollowup[Change]', $action, $item, $change, $data);
        plugin_mattermostjetlag_log_ticket($data);
        return;
    }

    if ($parentType !== 'Ticket') {
        return;
    }
    $ticket = new Ticket();
    if (!$ticket->getFromDB($parentId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($ticket)) {
        return;
    }
    $action = plugin_mattermostjetlag_detect_solution_followup_event($ticket) ?? 'followup';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_ITILFollowup', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_TicketValidation(CommonDBTM $item): void
{
    $ticketId = (int) ($item->fields['tickets_id'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($ticket)) {
        return;
    }
    $approvalTargetType  = $item->fields['itemtype_target'] ?? null;
    $approvalTargetId    = (int) ($item->fields['items_id_target'] ?? 0);
    $approvalTargetLabel = null;
    if ($approvalTargetType && $approvalTargetId > 0) {
        if ($approvalTargetType === User::class || $approvalTargetType === 'User') {
            $user = new User();
            if ($user->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $user->fields['login'] ?? $user->fields['name'] ?? $user->getName();
            }
        } elseif (is_a($approvalTargetType, CommonDBTM::class, true)) {
            $target = new $approvalTargetType();
            if ($target->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $target->getName();
            }
        }
    }
    $extra = [
        'approval_target_type' => $approvalTargetType,
        'approval_target_id'   => $approvalTargetId ?: null,
        'approval_target'      => $approvalTargetLabel,
    ];
    $action = 'approval';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action, $extra);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_TicketValidation', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_ITILSolution(CommonDBTM $item): void
{
    $parentType = $item->fields['itemtype'] ?? '';
    $parentId   = (int) ($item->fields['items_id'] ?? 0);
    if ($parentId <= 0) {
        return;
    }

    if ($parentType === 'Change') {
        $change = new Change();
        if (!$change->getFromDB($parentId)) {
            return;
        }
        if (plugin_mattermostjetlag_ticket_is_too_fresh($change)) {
            return;
        }
        $action = 'change_solution';
        $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
        $data = plugin_mattermostjetlag_build_change_log_data($change, $action);
        if (empty($data)) {
            return;
        }
        if (!empty($item->input) && is_array($item->input)) {
            $data['hook_input'] = $item->input;
        }
        $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
        if (empty($matchingRules)) {
            $data['matching_rules_note'] = 'no matching rules';
        } else {
            $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
            $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
        }
        plugin_mattermostjetlag_log_debug('item_add_ITILSolution[Change]', $action, $item, $change, $data);
        plugin_mattermostjetlag_log_ticket($data);
        return;
    }

    if ($parentType !== 'Ticket') {
        return;
    }
    $ticket = new Ticket();
    if (!$ticket->getFromDB($parentId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($ticket)) {
        return;
    }
    $action = 'solution';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_ITILSolution', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_ITILSolution(CommonDBTM $item): void
{
    // ITILSolution: ACCEPTED = 3, REFUSED = 4
    $newStatus = (int) ($item->input['status'] ?? 0);
    if ($newStatus !== 3 && $newStatus !== 4) {
        return;
    }
    $parentType = $item->fields['itemtype'] ?? '';
    $parentId   = (int) ($item->fields['items_id'] ?? 0);
    if ($parentId <= 0) {
        return;
    }

    if ($parentType === 'Change') {
        $action = $newStatus === 3 ? 'change_solution_approved' : 'change_solution_rejected';
        $change = new Change();
        if (!$change->getFromDB($parentId)) {
            return;
        }
        if (plugin_mattermostjetlag_ticket_is_too_fresh($change)) {
            return;
        }
        $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
        $data = plugin_mattermostjetlag_build_change_log_data($change, $action);
        if (empty($data)) {
            return;
        }
        if (!empty($item->input) && is_array($item->input)) {
            $data['hook_input'] = $item->input;
        }
        $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
        if (empty($matchingRules)) {
            $data['matching_rules_note'] = 'no matching rules';
        } else {
            $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
            $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
        }
        plugin_mattermostjetlag_log_debug('item_update_ITILSolution[Change]', $action, $item, $change, $data);
        plugin_mattermostjetlag_log_ticket($data);
        return;
    }

    if ($parentType !== 'Ticket') {
        return;
    }
    $action = $newStatus === 3 ? 'solution_approved' : 'solution_rejected';
    $ticket = new Ticket();
    if (!$ticket->getFromDB($parentId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($ticket)) {
        return;
    }
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_update_ITILSolution', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_Ticket_User(CommonDBTM $item): void
{
    if ($item::getType() !== 'Ticket_User') {
        return;
    }
    $ticketId = (int) ($item->fields['tickets_id'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($ticket)) {
        return;
    }
    $actionForLog  = 'members_change';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($actionForLog, $ticket);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $actionForLog);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_Ticket_User', $actionForLog, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_TicketValidation(CommonDBTM $item): void
{
    // CommonITILValidation: ACCEPTED = 3, REFUSED = 4
    $newStatus = (int) ($item->input['status'] ?? 0);
    if ($newStatus !== 3 && $newStatus !== 4) {
        return;
    }

    $ticketId = (int) ($item->fields['tickets_id'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }

    $action = $newStatus === 3 ? 'approved' : 'rejected';

    $approverType  = $item->fields['itemtype_target'] ?? null;
    $approverId    = (int) ($item->fields['items_id_target'] ?? 0);
    $approverLabel = null;
    if ($approverType && $approverId > 0 && ($approverType === User::class || $approverType === 'User')) {
        $user = new User();
        if ($user->getFromDB($approverId)) {
            $approverLabel = $user->fields['login'] ?? $user->fields['name'] ?? $user->getName();
        }
    }

    $extra = [
        'approval_target_type' => $approverType,
        'approval_target_id'   => $approverId ?: null,
        'approval_target'      => $approverLabel,
    ];

    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action, $extra);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_update_TicketValidation', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

// ── Change Hooks ──

function plugin_mattermostjetlag_item_add_Change(CommonDBTM $item): void
{
    $action = 'change_create';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_change_log_data($item, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_Change', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_Change(CommonDBTM $item): void
{
    if (plugin_mattermostjetlag_ticket_is_too_fresh($item)) {
        return;
    }
    $updates = $item->updates ?? [];
    if (in_array('status', $updates)) {
        $action = 'change_status_changed';
    } else {
        $action = 'change_update';
    }

    $baseData = plugin_mattermostjetlag_build_change_log_data($item, $action);
    if (empty($baseData)) {
        return;
    }

    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = $baseData;
    $data['action'] = $action;
    $data['event']  = plugin_mattermostjetlag_map_action_to_rule_event($action) ?? '';
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_update_Change', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_pre_item_delete_Change(CommonDBTM $item): void
{
    $action = 'change_delete';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_change_log_data($item, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('pre_item_delete_Change', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_ChangeValidation(CommonDBTM $item): void
{
    $changeId = (int) ($item->fields['changes_id'] ?? 0);
    if ($changeId <= 0) {
        return;
    }
    $change = new Change();
    if (!$change->getFromDB($changeId)) {
        return;
    }
    if (plugin_mattermostjetlag_ticket_is_too_fresh($change)) {
        return;
    }
    $approvalTargetType  = $item->fields['itemtype_target'] ?? null;
    $approvalTargetId    = (int) ($item->fields['items_id_target'] ?? 0);
    $approvalTargetLabel = null;
    if ($approvalTargetType && $approvalTargetId > 0) {
        if ($approvalTargetType === User::class || $approvalTargetType === 'User') {
            $user = new User();
            if ($user->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $user->fields['login'] ?? $user->fields['name'] ?? $user->getName();
            }
        } elseif (is_a($approvalTargetType, CommonDBTM::class, true)) {
            $target = new $approvalTargetType();
            if ($target->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $target->getName();
            }
        }
    }
    $extra = [
        'approval_target_type' => $approvalTargetType,
        'approval_target_id'   => $approvalTargetId ?: null,
        'approval_target'      => $approvalTargetLabel,
    ];
    $action = 'change_approval';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_change_log_data($change, $action, $extra);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_ChangeValidation', $action, $item, $change, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_ChangeValidation(CommonDBTM $item): void
{
    // CommonITILValidation: ACCEPTED = 3, REFUSED = 4
    $newStatus = (int) ($item->input['status'] ?? 0);
    if ($newStatus !== 3 && $newStatus !== 4) {
        return;
    }

    $changeId = (int) ($item->fields['changes_id'] ?? 0);
    if ($changeId <= 0) {
        return;
    }
    $change = new Change();
    if (!$change->getFromDB($changeId)) {
        return;
    }

    $action = $newStatus === 3 ? 'change_approved' : 'change_rejected';

    $approverType  = $item->fields['itemtype_target'] ?? null;
    $approverId    = (int) ($item->fields['items_id_target'] ?? 0);
    $approverLabel = null;
    if ($approverType && $approverId > 0 && ($approverType === User::class || $approverType === 'User')) {
        $user = new User();
        if ($user->getFromDB($approverId)) {
            $approverLabel = $user->fields['login'] ?? $user->fields['name'] ?? $user->getName();
        }
    }

    $extra = [
        'approval_target_type' => $approverType,
        'approval_target_id'   => $approverId ?: null,
        'approval_target'      => $approverLabel,
    ];

    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_change_log_data($change, $action, $extra);
    if (empty($data)) {
        return;
    }
    if (!empty($item->input) && is_array($item->input)) {
        $data['hook_input'] = $item->input;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
        $data['dispatch_results']  = plugin_mattermostjetlag_dispatch_notifications($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_update_ChangeValidation', $action, $item, $change, $data);
    plugin_mattermostjetlag_log_ticket($data);
}
