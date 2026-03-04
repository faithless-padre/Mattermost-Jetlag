<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/TicketLogger.php';

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
 * @return array Готовый массив данных для записи.
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

    // Собираем ID участников: заявители, наблюдатели и исполнители
    $requesterIds = [];
    $observerIds  = [];
    $assigneeIds  = [];

    if (property_exists($ticket, 'input') && is_array($ticket->input)) {
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
        'status'           => $ticketStatus,
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
 * Обработка события "создание заявки".
 *
 * @param CommonDBTM $item Объект Ticket.
 */
function plugin_mattermost_item_add_Ticket(CommonDBTM $item) {
    $data = plugin_mattermost_build_ticket_log_data($item, 'create');
    if (empty($data)) {
        return;
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Обработка события "обновление заявки".
 *
 * @param CommonDBTM $item Объект Ticket.
 */
function plugin_mattermost_item_update_Ticket(CommonDBTM $item) {
    $data = plugin_mattermost_build_ticket_log_data($item, 'update');
    if (empty($data)) {
        return;
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Обработка события "новый комментарий (followup) по заявке".
 *
 * @param CommonDBTM $item Объект ITILFollowup.
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

    $data = plugin_mattermost_build_ticket_log_data($ticket, 'followup');
    if (empty($data)) {
        return;
    }

    plugin_mattermost_log_ticket($data);
}

/**
 * Обработка события "новое согласование (approval) по заявке".
 *
 * @param CommonDBTM $item Объект TicketValidation.
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

    $data = plugin_mattermost_build_ticket_log_data($ticket, 'approval', $extra);
    if (empty($data)) {
        return;
    }

    plugin_mattermost_log_ticket($data);
}
