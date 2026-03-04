<?php

define('PLUGIN_MATTERMOST_VERSION', '1.0.8');

// Минимальная и максимальная версии GLPI (для 11-й ветки)
define('PLUGIN_MATTERMOST_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_MATTERMOST_MAX_GLPI_VERSION', '11.0.99');

/**
 * Инициализация плагина: регистрация хуков.
 */
function plugin_init_mattermost() {
    global $PLUGIN_HOOKS;

    // Хуки "item_add" — вызываются после добавления объектов
    $PLUGIN_HOOKS['item_add']['mattermost'] = [
        // Новая заявка
        'Ticket'           => 'plugin_mattermost_item_add_Ticket',
        // Новый комментарий (followup) к заявке
        'ITILFollowup'     => 'plugin_mattermost_item_add_ITILFollowup',
        // Новое согласование (approver) по заявке
        'TicketValidation' => 'plugin_mattermost_item_add_TicketValidation',
        // Добавление актора (requester/observer/assignee) к заявке
        'Ticket_User'      => 'plugin_mattermost_item_add_Ticket_User',
    ];

    // Хук "item_update" для типа Ticket — вызывается после обновления заявки
    $PLUGIN_HOOKS['item_update']['mattermost'] = [
        'Ticket' => 'plugin_mattermost_item_update_Ticket',
    ];

    // Страница настроек плагина (кнопка "Конфигурация" в списке плагинов)
    // Путь указывается относительно корня плагина
    $PLUGIN_HOOKS['config_page']['mattermost'] = 'front/config.form.php';
}

/**
 * Метаданные плагина (отображаются в Setup > Plugins).
 */
function plugin_version_mattermost() {
    return [
        'name'         => 'El Mattermost',
        'version'      => PLUGIN_MATTERMOST_VERSION,
        'author'       => 'Faithless Padre',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/faithless-padre',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_MATTERMOST_MIN_GLPI_VERSION,
                'max' => PLUGIN_MATTERMOST_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Проверка конфигурации плагина.
 * Сейчас ничего не проверяем — всегда true.
 */
function plugin_mattermost_check_config($verbose = false) {
    return true;
}


