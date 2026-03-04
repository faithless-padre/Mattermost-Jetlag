<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/TicketLogger.php';
require_once __DIR__ . '/NotificationRule.php';

/**
 * Преобразует тип события из хука в тип события правила.
 *
 * @param string $action Тип события из хука: create|update|followup|approval.
 * @return string Тип события правила: New|Update|Delete или null, если не поддерживается.
 */
function plugin_mattermost_map_action_to_rule_event(string $action): ?string {
    $mapping = [
        'create'   => 'New',
        'update'   => 'Update',
        'followup' => 'Update', // Followup считается обновлением
        'approval' => 'New',    // Approval для Approve target считается новым событием
    ];
    return $mapping[$action] ?? null;
}

/**
 * Определяет target правила на основе типа события и объекта.
 *
 * @param string     $action Тип события: create|update|followup|approval.
 * @param CommonDBTM $item   Объект события (Ticket, ITILFollowup, TicketValidation).
 * @return string Target правила: Ticket или Approve.
 */
function plugin_mattermost_determine_rule_target(string $action, CommonDBTM $item): string {
    // Если это событие approval (TicketValidation), то target = Approve
    if ($action === 'approval') {
        return 'Approve';
    }
    // Для всех остальных событий target = Ticket
    return 'Ticket';
}

/**
 * Проверяет, соответствует ли объект Extended Filter правила.
 *
 * ВАЖНО: используется стандартная логика GLPI из FilterableTrait::itemMatchFilter(),
 * чтобы не дублировать работу SearchEngine и CriteriaFilter.
 *
 * @param CommonDBTM $item Объект для проверки (Ticket или TicketValidation).
 * @param \GlpiPlugin\Mattermost\NotificationRule $rule Правило для проверки.
 * @return bool true, если объект соответствует фильтру правила.
 */
function plugin_mattermost_ticket_matches_rule_filter(CommonDBTM $item, \GlpiPlugin\Mattermost\NotificationRule $rule): bool {
    // Какой тип объектов должен фильтровать это правило (Ticket или TicketValidation)
    $itemtypeToFilter = $rule->getItemtypeToFilter();

    // Если тип объекта не совпадает с ожидаемым типом фильтра — сразу false
    if ($item::getType() !== $itemtypeToFilter) {
        return false;
    }

    try {
        // Делегируем проверку стандартной реализации GLPI
        // NotificationRule использует FilterableTrait, в котором есть itemMatchFilter()
        return $rule->itemMatchFilter($item);
    } catch (\Throwable $e) {
        // На всякий случай, если что-то пойдёт не так, просто считаем, что не совпало
        return false;
    }
}

/**
 * Получает все активные правила, которые соответствуют событию.
 *
 * @param string     $action Тип события: create|update|followup|approval.
 * @param CommonDBTM $item   Объект события (Ticket, ITILFollowup, TicketValidation).
 * @return array Массив объектов NotificationRule, которые соответствуют событию.
 */
function plugin_mattermost_get_matching_rules(string $action, CommonDBTM $item): array {
    global $DB;
    
    $matchingRules = [];
    
    // Определяем target и event для правила
    $ruleTarget = plugin_mattermost_determine_rule_target($action, $item);
    $ruleEvent = plugin_mattermost_map_action_to_rule_event($action);
    
    if ($ruleEvent === null) {
        return [];
    }
    
    // Получаем все активные правила с нужным target и event
    $rule = new \GlpiPlugin\Mattermost\NotificationRule();
    $rules = $DB->request([
        'FROM' => $rule->getTable(),
        'WHERE' => [
            'active' => 1,
            'target' => $ruleTarget,
            'event' => $ruleEvent,
        ],
    ]);
    
    // Определяем, какой объект будем проверять фильтром:
    // - для правил с target = Ticket фильтруем по Ticket
    // - для правил с target = Approve (TicketValidation) фильтруем по TicketValidation
    $filterItem = null;
    if ($ruleTarget === 'Ticket') {
        // Нужен объект Ticket
        if ($item::getType() === 'Ticket') {
            $filterItem = $item;
        } elseif ($item::getType() === 'ITILFollowup' && isset($item->fields['items_id'])) {
            $filterItem = new \Ticket();
            if (!$filterItem->getFromDB((int)$item->fields['items_id'])) {
                return [];
            }
        } elseif ($item::getType() === 'TicketValidation' && isset($item->fields['tickets_id'])) {
            $filterItem = new \Ticket();
            if (!$filterItem->getFromDB((int)$item->fields['tickets_id'])) {
                return [];
            }
        }
    } else {
        // target = Approve — фильтр задаётся по TicketValidation,
        // а в соответствующем хуке item как раз TicketValidation
        $filterItem = $item;
    }

    if ($filterItem === null) {
        return [];
    }
    
    // Проверяем каждое правило на соответствие Extended Filter
    foreach ($rules as $ruleData) {
        $ruleObj = new \GlpiPlugin\Mattermost\NotificationRule();
        if (!$ruleObj->getFromDB($ruleData['id'])) {
            continue;
        }
        
        // Проверяем соответствие фильтру (target, event уже отфильтрованы на уровне SQL)
        if (plugin_mattermost_ticket_matches_rule_filter($filterItem, $ruleObj)) {
            $matchingRules[] = $ruleObj;
        }
    }
    
    return $matchingRules;
}

/**
 * Загружает акторов заявки из БД (таблица glpi_tickets_users).
 *
 * Используется когда у Ticket нет свойства input (например, загружен через getFromDB).
 *
 * @param int $ticketId ID заявки.
 * @return array Массив с ключами: requester_ids, observer_ids, assignee_ids.
 */
function plugin_mattermost_load_ticket_actors_from_db(int $ticketId): array {
    global $DB;

    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if ($ticketId <= 0) {
        return [
            'requester_ids' => [],
            'observer_ids'  => [],
            'assignee_ids'  => [],
        ];
    }

    // Загружаем акторов из таблицы glpi_tickets_users
    $actors = $DB->request([
        'FROM' => 'glpi_tickets_users',
        'WHERE' => [
            'tickets_id' => $ticketId,
        ],
    ]);

    foreach ($actors as $actor) {
        $userId = isset($actor['users_id']) ? (int)$actor['users_id'] : 0;
        $type   = isset($actor['type']) ? (int)$actor['type'] : 0;

        if ($userId <= 0) {
            continue;
        }

        // CommonITILActor::REQUESTER = 1, ASSIGN = 2, OBSERVER = 3
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
 * Собирает данные о заявке для записи в лог.
 *
 * Вся доменная логика по работе с Ticket (статусы, типы, акторы)
 * живёт здесь, а не в логгере.
 *
 * @param CommonDBTM $ticket Объект Ticket.
 * @param string     $action Тип события: create|update|followup|approval.
 * @param array      $extra  Дополнительные данные для лога.
 *
 * @return array Готовый массив данных для записи или пустой массив, если это не Ticket.
 */
function plugin_mattermost_build_ticket_log_data(CommonDBTM $ticket, string $action, array $extra = []): array {
    // На всякий случай убеждаемся, что это именно Ticket
    if ($ticket::getType() !== 'Ticket') {
        return [];
    }

    // Номер, тема, тип и статус заявки
    $ticketId   = $ticket->getID();
    $ticketName = isset($ticket->fields['name']) ? $ticket->fields['name'] : '';

    // Статус: конвертируем числовое значение GLPI в человекочитаемое
    $ticketStatusCode = isset($ticket->fields['status']) ? (int)$ticket->fields['status'] : null;
    $statusMap = [
        1 => 'New',
        2 => 'Assigned',
        3 => 'Processing',
        4 => 'Pending',
        5 => 'Solved',
        6 => 'Closed',
    ];
    $ticketStatus = $ticketStatusCode !== null && isset($statusMap[$ticketStatusCode])
        ? $statusMap[$ticketStatusCode]
        : null;

    // Тип заявки: 1 — Incident, 2 — Request
    $ticketTypeCode = isset($ticket->fields['type']) ? (int)$ticket->fields['type'] : null;
    $typeMap = [
        1 => 'Incident',
        2 => 'Request',
    ];
    $ticketType = $ticketTypeCode !== null && isset($typeMap[$ticketTypeCode])
        ? $typeMap[$ticketTypeCode]
        : null;

    // Важность (urgency) и приоритет (priority)
    $ticketUrgencyCode  = isset($ticket->fields['urgency']) ? (int)$ticket->fields['urgency'] : null;
    $ticketPriorityCode = isset($ticket->fields['priority']) ? (int)$ticket->fields['priority'] : null;
    $ticketUrgency      = $ticketUrgencyCode !== null ? \CommonITILObject::getUrgencyName($ticketUrgencyCode) : null;
    $ticketPriority     = $ticketPriorityCode !== null ? \CommonITILObject::getPriorityName($ticketPriorityCode) : null;

    // Категория
    $ticketCategory = null;
    if (!empty($ticket->fields['itilcategories_id'])) {
        $catId = (int)$ticket->fields['itilcategories_id'];
        if ($catId > 0) {
            $cat = new \ITILCategory();
            if ($cat->getFromDB($catId)) {
                $ticketCategory = $cat->getName();
            }
        }
    }

    // Собираем ID участников: заявители, наблюдатели и исполнители
    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    // Сначала пытаемся извлечь из input (если Ticket создаётся/обновляется через формы)
    if (property_exists($ticket, 'input') && is_array($ticket->input) && !empty($ticket->input)) {
        $input = $ticket->input;

        // Входные данные приходят в виде:
        // "_users_id_requester" => [ "_actors_2" => "2", "_actors_7" => "7" ]
        $extractActorIds = function (array $source, string $key): array {
            if (!isset($source[$key]) || !is_array($source[$key])) {
                return [];
            }

            $ids = [];
            foreach ($source[$key] as $value) {
                // Значения обычно строки с числом, приводим к int
                $id = (int)$value;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }

            // Убираем дубликаты и пересобираем индексы
            return array_values(array_unique($ids));
        };

        $requesterIds = $extractActorIds($input, '_users_id_requester');
        $observerIds  = $extractActorIds($input, '_users_id_observer');
        $assigneeIds  = $extractActorIds($input, '_users_id_assign');
    }

    // Если из input ничего не получилось (Ticket загружен через getFromDB) — загружаем из БД
    if (empty($requesterIds) && empty($observerIds) && empty($assigneeIds)) {
        $actorsFromDb = plugin_mattermost_load_ticket_actors_from_db($ticketId);
        $requesterIds = $actorsFromDb['requester_ids'];
        $observerIds  = $actorsFromDb['observer_ids'];
        $assigneeIds  = $actorsFromDb['assignee_ids'];
    }

    // Преобразуем ID пользователей в их логины (имена учётных записей)
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
                // В GLPI логин обычно хранится в поле 'name', но
                // на всякий случай проверяем и 'login', если он есть.
                $login = $user->fields['login'] ?? $user->fields['name'] ?? null;
                if (!empty($login)) {
                    $result[] = $login;
                }
            }
        }

        // Убираем возможные дубликаты логинов
        return array_values(array_unique($result));
    };

    $requesterLogins = $idsToLogins($requesterIds);
    $observerLogins  = $idsToLogins($observerIds);
    $assigneeLogins  = $idsToLogins($assigneeIds);

    // Формируем компактную запись только с нужной информацией
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
        'event'            => plugin_mattermost_map_action_to_rule_event($action) ?? '',
        'requester_logins' => $requesterLogins,
        'observer_logins'  => $observerLogins,
        'assignee_logins'  => $assigneeLogins,
    ];

    // Добавляем дополнительные данные (например, для согласований)
    if (!empty($extra)) {
        $data = array_merge($data, $extra);
    }

    return $data;
}

/**
 * Формирует текст сообщения по правилам для записи в лог.
 *
 * Возвращает строку JSON вида {"message":"..."} или пустую строку,
 * если не удалось ничего срендерить.
 *
 * @param \GlpiPlugin\Mattermost\NotificationRule[] $matchingRules
 * @param array                                     $data          Данные, собранные plugin_mattermost_build_ticket_log_data().
 *
 * @return string
 */
function plugin_mattermost_render_rule_messages_for_ticket(array $matchingRules, array $data): string {
    if (empty($matchingRules)) {
        return '';
    }

    $ticketId        = isset($data['ticket_id']) ? (int)$data['ticket_id'] : 0;
    $subject         = (string)($data['subject'] ?? '');
    $status          = (string)($data['status'] ?? '');
    $type            = (string)($data['type'] ?? '');
    $urgency         = (string)($data['urgency'] ?? '');
    $priority        = (string)($data['priority'] ?? '');
    $category        = (string)($data['category'] ?? '');
    $event           = (string)($data['event'] ?? '');
    $requesterLogins = is_array($data['requester_logins'] ?? null) ? $data['requester_logins'] : [];
    $observerLogins  = is_array($data['observer_logins'] ?? null) ? $data['observer_logins'] : [];
    $assigneeLogins  = is_array($data['assignee_logins'] ?? null) ? $data['assignee_logins'] : [];

    $formatLogins = static function (array $logins): string {
        if (empty($logins)) {
            return '';
        }
        $parts = [];
        foreach ($logins as $login) {
            $login = (string)$login;
            if ($login === '') {
                continue;
            }
            $withAt = str_starts_with($login, '@') ? $login : '@' . $login;
            $parts[] = $withAt;
        }
        return implode(', ', $parts);
    };

    $link = '';
    if ($ticketId > 0) {
        // Относительная ссылка от корня GLPI
        $relative = \Ticket::getFormURLWithID($ticketId, false);
        // Полный URL: базовый адрес GLPI + относительный путь
        global $CFG_GLPI;
        $base = isset($CFG_GLPI['url_base']) ? (string)$CFG_GLPI['url_base'] : '';
        $link = $base . $relative;
    }

    $renderedMessage = null;

    foreach ($matchingRules as $rule) {
        if (!$rule instanceof \GlpiPlugin\Mattermost\NotificationRule) {
            continue;
        }
        $template = (string)($rule->getField('message') ?? '');
        if ($template === '') {
            continue;
        }

        $replacements = [
            '{id}'        => $ticketId > 0 ? (string)$ticketId : '',
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

        $renderedMessage = strtr($template, $replacements);
        // Берём первое успешно срендеренное сообщение
        break;
    }

    if ($renderedMessage === null) {
        return '';
    }

    return json_encode(
        ['message' => $renderedMessage],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/**
 * Хук после добавления заявки (Ticket).
 *
 * @param CommonDBTM $item Объект Ticket, только что созданный.
 */
function plugin_mattermost_item_add_Ticket(CommonDBTM $item) {
    $action = 'create';

    // Правила, под которые попадает событие (может быть пустой массив)
    $matchingRules = plugin_mattermost_get_matching_rules($action, $item);

    // Собираем данные для лога по Ticket
    $data = plugin_mattermost_build_ticket_log_data($item, $action);
    if (empty($data)) {
        return;
    }

    // Добавляем информацию о правилах (даже если их нет)
    $ruleIds = array_map(function ($rule) {
        return $rule->getID();
    }, $matchingRules);
    $data['matching_rule_ids'] = $ruleIds;
    if (empty($ruleIds)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermost_render_rule_messages_for_ticket($matchingRules, $data);
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Хук после обновления заявки (Ticket).
 *
 * @param CommonDBTM $item Объект Ticket, только что обновлённый.
 */
function plugin_mattermost_item_update_Ticket(CommonDBTM $item) {
    $action = 'update';

    // Правила, под которые попадает событие (может быть пустой массив)
    $matchingRules = plugin_mattermost_get_matching_rules($action, $item);

    // Собираем данные для лога по Ticket
    $data = plugin_mattermost_build_ticket_log_data($item, $action);
    if (empty($data)) {
        return;
    }

    // Добавляем информацию о правилах (даже если их нет)
    $ruleIds = array_map(function ($rule) {
        return $rule->getID();
    }, $matchingRules);
    $data['matching_rule_ids'] = $ruleIds;
    if (empty($ruleIds)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermost_render_rule_messages_for_ticket($matchingRules, $data);
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Хук после добавления комментария (followup) к заявке.
 *
 * Класс ITILFollowup используется для комментариев к объектам ITIL,
 * в нашем случае нас интересуют только комментарии к Ticket.
 *
 * @param CommonDBTM $item Объект ITILFollowup, только что созданный.
 */
function plugin_mattermost_item_add_ITILFollowup(CommonDBTM $item) {
    // Проверяем, что followup относится именно к заявке (Ticket)
    if (
        !isset($item->fields['itemtype'], $item->fields['items_id']) ||
        $item->fields['itemtype'] !== 'Ticket'
    ) {
        return;
    }

    $ticketId = (int)$item->fields['items_id'];
    if ($ticketId <= 0) {
        return;
    }

    // Загружаем саму заявку и логируем её как событие "followup"
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }

    $action = 'followup';
    
    // Правила, под которые попадает событие (может быть пустой массив)
    $matchingRules = plugin_mattermost_get_matching_rules($action, $item);

    // Собираем данные для лога по Ticket
    $data = plugin_mattermost_build_ticket_log_data($ticket, $action);
    if (empty($data)) {
        return;
    }

    // Добавляем информацию о правилах (даже если их нет)
    $ruleIds = array_map(function ($rule) {
        return $rule->getID();
    }, $matchingRules);
    $data['matching_rule_ids'] = $ruleIds;
    if (empty($ruleIds)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermost_render_rule_messages_for_ticket($matchingRules, $data);
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Хук после добавления согласования (TicketValidation) по заявке.
 *
 * TicketValidation связывается с заявкой по полю tickets_id.
 *
 * @param CommonDBTM $item Объект TicketValidation, только что созданный.
 */
function plugin_mattermost_item_add_TicketValidation(CommonDBTM $item) {
    // Валидации тикетов ссылаются на заявку через поле tickets_id
    $ticketId = isset($item->fields['tickets_id']) ? (int)$item->fields['tickets_id'] : 0;
    if ($ticketId <= 0) {
        return;
    }

    // Загружаем саму заявку
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }

    // Определяем, кому отправлено согласование
    $approvalTargetType  = $item->fields['itemtype_target'] ?? null;
    $approvalTargetId    = isset($item->fields['items_id_target']) ? (int)$item->fields['items_id_target'] : 0;
    $approvalTargetLabel = null;

    if ($approvalTargetType && $approvalTargetId > 0) {
        if ($approvalTargetType === User::class || $approvalTargetType === 'User') {
            // Согласование конкретному пользователю — вернём его логин (или имя)
            $user = new User();
            if ($user->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $user->fields['login'] ?? $user->fields['name'] ?? $user->getName();
            }
        } elseif (is_a($approvalTargetType, CommonDBTM::class, true)) {
            // Согласование, например, группе — берём человекочитаемое название
            $target = new $approvalTargetType();
            if ($target->getFromDB($approvalTargetId)) {
                $approvalTargetLabel = $target->getName();
            }
        }
    }

    $extra = [
        'approval_target_type'  => $approvalTargetType,
        'approval_target_id'    => $approvalTargetId ?: null,
        'approval_target'       => $approvalTargetLabel,
    ];

    $action = 'approval';
    
    // Правила, под которые попадает событие (может быть пустой массив)
    $matchingRules = plugin_mattermost_get_matching_rules($action, $item);

    // Собираем данные для лога по Ticket
    $data = plugin_mattermost_build_ticket_log_data($ticket, $action, $extra);
    if (empty($data)) {
        return;
    }

    // Добавляем информацию о правилах (даже если их нет)
    $ruleIds = array_map(function ($rule) {
        return $rule->getID();
    }, $matchingRules);
    $data['matching_rule_ids'] = $ruleIds;
    if (empty($ruleIds)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermost_render_rule_messages_for_ticket($matchingRules, $data);
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Хук после добавления актора (requester/observer/assignee) к заявке.
 *
 * Ticket_User связывает Ticket с User и определяет роль (type).
 *
 * @param CommonDBTM $item Объект Ticket_User, только что созданный.
 */
function plugin_mattermost_item_add_Ticket_User(CommonDBTM $item) {
    // Перестраховка: убеждаемся, что это именно Ticket_User
    if ($item::getType() !== 'Ticket_User') {
        return;
    }

    // Получаем ID заявки из связи
    $ticketId = isset($item->fields['tickets_id']) ? (int)$item->fields['tickets_id'] : 0;
    if ($ticketId <= 0) {
        return;
    }

    // Загружаем саму заявку
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }

    // Добавление/изменение участника считаем отдельным типом события в логе,
    // но для правил трактуем его как Update.
    $actionForRules = 'update';
    $actionForLog   = 'members_change';

    // Правила, под которые попадает событие (может быть пустой массив)
    $matchingRules = plugin_mattermost_get_matching_rules($actionForRules, $ticket);

    // Собираем данные для лога по Ticket (актёры берутся из БД внутри функции)
    $data = plugin_mattermost_build_ticket_log_data($ticket, $actionForLog);
    if (empty($data)) {
        return;
    }

    // Добавляем информацию о правилах (даже если их нет)
    $ruleIds = array_map(function ($rule) {
        return $rule->getID();
    }, $matchingRules);
    $data['matching_rule_ids'] = $ruleIds;
    if (empty($ruleIds)) {
        $data['matching_rules_note'] = 'no matching rules';
    } else {
        $data['rendered_messages'] = plugin_mattermost_render_rule_messages_for_ticket($matchingRules, $data);
    }

    plugin_mattermost_log_ticket($data);
}

