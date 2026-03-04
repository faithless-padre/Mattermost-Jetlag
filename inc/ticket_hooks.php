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

use CommonDBTM;
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use Ticket;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/ticket_logger.php';

/**
 * action → event (DB value stored in rule.event). All map 1:1.
 */
function plugin_mattermostjetlag_map_action_to_rule_event(string $action): ?string
{
    $mapping = [
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
    ];
    return $mapping[$action] ?? null;
}

/**
 * All hooks target Ticket.
 */
function plugin_mattermostjetlag_determine_rule_target(string $action, CommonDBTM $item): string
{
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
        } elseif ($item::getType() === 'ITILFollowup' && isset($item->fields['items_id'])) {
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
 * Сбор данных заявки для лога (логика как в старом плагине).
 */
function plugin_mattermostjetlag_build_ticket_log_data(CommonDBTM $ticket, string $action, array $extra = []): array
{
    if ($ticket::getType() !== 'Ticket') {
        return [];
    }

    $ticketId   = $ticket->getID();
    $ticketName = $ticket->fields['name'] ?? '';

    $statusMap = [
        1 => 'New',
        2 => 'Assigned',
        3 => 'Processing',
        4 => 'Pending',
        5 => 'Solved',
        6 => 'Closed',
    ];
    $ticketStatus = $statusMap[(int) ($ticket->fields['status'] ?? 0)] ?? null;

    $typeMap = [1 => 'Incident', 2 => 'Request'];
    $ticketType = $typeMap[(int) ($ticket->fields['type'] ?? 0)] ?? null;

    $urgencyCode  = (int) ($ticket->fields['urgency'] ?? 0);
    $priorityCode = (int) ($ticket->fields['priority'] ?? 0);
    $ticketUrgency  = $urgencyCode > 0 ? \CommonITILObject::getUrgencyName($urgencyCode) : null;
    $ticketPriority = $priorityCode > 0 ? \CommonITILObject::getPriorityName($priorityCode) : null;

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
        'priority'         => $ticketPriority,
        'category'         => $ticketCategory,
        'status'           => $ticketStatus,
        'event'            => plugin_mattermostjetlag_map_action_to_rule_event($action) ?? '',
        'requester_logins' => $idsToLogins($requesterIds),
        'observer_logins'  => $idsToLogins($observerIds),
        'assignee_logins'  => $idsToLogins($assigneeIds),
    ];

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

    $ticketId        = (int) ($data['ticket_id'] ?? 0);
    $subject         = (string) ($data['subject'] ?? '');
    $status          = (string) ($data['status'] ?? '');
    $type            = (string) ($data['type'] ?? '');
    $urgency         = (string) ($data['urgency'] ?? '');
    $priority        = (string) ($data['priority'] ?? '');
    $category        = (string) ($data['category'] ?? '');
    $event           = (string) ($data['event'] ?? '');
    $requesterLogins = is_array($data['requester_logins'] ?? null) ? $data['requester_logins'] : [];
    $observerLogins  = is_array($data['observer_logins'] ?? null) ? $data['observer_logins'] : [];
    $assigneeLogins  = is_array($data['assignee_logins'] ?? null) ? $data['assignee_logins'] : [];

    $formatLogins = static function (array $logins): string {
        if (empty($logins)) {
            return '';
        }
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
    if ($ticketId > 0) {
        $relative = Ticket::getFormURLWithID($ticketId, false);
        global $CFG_GLPI;
        $link = ($CFG_GLPI['url_base'] ?? '') . $relative;
    }

    foreach ($matchingRules as $rule) {
        if (!$rule instanceof NotificationRule) {
            continue;
        }
        $template = (string) ($rule->getField('message') ?? '');
        if ($template === '') {
            continue;
        }
        $replacements = [
            '{id}'        => $ticketId > 0 ? (string) $ticketId : '',
            '{title}'     => $subject,
            '{status}'    => $status,
            '{type}'      => $type,
            '{event}'     => $event,
            '{urgency}'   => $urgency,
            '{priority}'  => $priority,
            '{category}'  => $category,
            '{link}'      => $link,
            '{requester}' => $formatLogins($requesterLogins),
            '{observer}'  => $formatLogins($observerLogins),
            '{assigned}'  => $formatLogins($assigneeLogins),
        ];
        return json_encode(
            ['message' => strtr($template, $replacements)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    return '';
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
    }
    plugin_mattermostjetlag_log_debug('item_add_Ticket', $action, $item, null, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_Ticket(CommonDBTM $item): void
{
    if (plugin_mattermostjetlag_ticket_is_too_fresh($item)) {
        return;
    }
    $newStatus = (int) ($item->input['status'] ?? 0);
    if ($newStatus > 0) {
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
        }
        plugin_mattermostjetlag_log_debug('item_update_Ticket', $action, $item, null, $data);
        plugin_mattermostjetlag_log_ticket($data);
    }
}

/**
 * Detects whether a followup was created as part of a solution review by the requester.
 *
 * Returns 'solution_approved' when the ticket was just closed (status=6, date_mod ≤30 s ago)
 * and has at least one ITILSolution record.
 *
 * Returns 'solution_rejected' when the ticket was just updated (date_mod ≤30 s ago) and its
 * most recent ITILSolution has status=4 (REFUSED).
 *
 * Returns null for ordinary followups.
 */
function plugin_mattermostjetlag_detect_solution_followup_event(Ticket $ticket): ?string
{
    global $DB;

    $dateMod = strtotime($ticket->fields['date_mod'] ?? '');
    if ($dateMod === false || (time() - $dateMod) > 30) {
        return null;
    }

    $ticketStatus = (int) ($ticket->fields['status'] ?? 0);

    // Approved: ticket is now Closed
    if ($ticketStatus === 6) {
        $rows = $DB->request([
            'FROM'  => \ITILSolution::getTable(),
            'WHERE' => ['itemtype' => 'Ticket', 'items_id' => $ticket->getID()],
            'LIMIT' => 1,
        ]);
        if (count($rows) > 0) {
            return 'solution_approved';
        }
        return null;
    }

    // Rejected: most recent solution has status=4 (REFUSED)
    $rows = $DB->request([
        'FROM'  => \ITILSolution::getTable(),
        'WHERE' => ['itemtype' => 'Ticket', 'items_id' => $ticket->getID()],
        'ORDER' => ['id DESC'],
        'LIMIT' => 1,
    ]);
    foreach ($rows as $row) {
        if ((int) ($row['status'] ?? 0) === 4) {
            return 'solution_rejected';
        }
    }

    return null;
}

function plugin_mattermostjetlag_item_add_ITILFollowup(CommonDBTM $item): void
{
    if (!isset($item->fields['itemtype'], $item->fields['items_id']) || $item->fields['itemtype'] !== 'Ticket') {
        return;
    }
    $ticketId = (int) $item->fields['items_id'];
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
    $action = plugin_mattermostjetlag_detect_solution_followup_event($ticket) ?? 'followup';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
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
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_TicketValidation', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_add_ITILSolution(CommonDBTM $item): void
{
    if ($item->fields['itemtype'] !== 'Ticket') {
        return;
    }
    $ticketId = (int) ($item->fields['items_id'] ?? 0);
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
    $action = 'solution';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_add_ITILSolution', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}

function plugin_mattermostjetlag_item_update_ITILSolution(CommonDBTM $item): void
{
    // ITILSolution: ACCEPTED = 3 — requester confirmed the solution
    $newStatus = (int) ($item->input['status'] ?? 0);
    if ($newStatus !== 3) {
        return;
    }
    if ($item->fields['itemtype'] !== 'Ticket') {
        return;
    }
    $ticketId = (int) ($item->fields['items_id'] ?? 0);
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
    $action = 'solution_approved';
    $matchingRules = plugin_mattermostjetlag_get_matching_rules($action, $item);
    $data = plugin_mattermostjetlag_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
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
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
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
    $data['matching_rule_ids'] = array_map(fn ($r) => $r->getID(), $matchingRules);
    if (empty($matchingRules)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermostjetlag_render_rule_messages_for_ticket($matchingRules, $data);
    }
    plugin_mattermostjetlag_log_debug('item_update_TicketValidation', $action, $item, $ticket, $data);
    plugin_mattermostjetlag_log_ticket($data);
}
