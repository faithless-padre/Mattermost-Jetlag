<?php

/**
 * Конфигурация плагина Mattermost — вкладки: Connection, Rules List, Editor.
 */

namespace GlpiPlugin\Mattermost;

use CommonDBTM;
use CommonGLPI;
use Html;
use Session;
use Toolbox;

class Config extends CommonDBTM
{
    /** Отключаем кнопку «List» и навигацию по ID в шапке формы. */
    protected $displaylist = false;

    /**
     * Включён ли глобальный debug-режим GLPI.
     */
    public static function isDebugMode(): bool
    {
        return isset($_SESSION['glpi_use_mode']) && $_SESSION['glpi_use_mode'] === Session::DEBUG_MODE;
    }

    /**
     * Доступ к настройкам плагина — у кого есть право «Конфигурация», тот может смотреть и менять.
     */
    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb = 0)
    {
        return 'Mattermost configuration';
    }

    public static function getType($nb = 0)
    {
        return __CLASS__;
    }

    /**
     * Заголовок в шапке формы — только название, без «N/A» и «ID 1».
     */
    public function getHeaderName(): string
    {
        return (string) static::getTypeName(1);
    }

    /**
     * Шапка страницы настроек: только заголовок, без кнопки List и без ID.
     */
    public function showNavigationHeader($options = [])
    {
        echo "<div id='navigationheader' class='navigationheader'>";
        echo "<h3 class='navigationheader-title strong d-flex align-items-center'>";
        echo htmlescape($this->getHeaderName());
        echo "</h3>";
        echo "</div>";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === self::getType()) {
            $tabs = [
                self::TAB_CONNECTION   => 'Connection',
                self::TAB_ALL_RULES    => 'Rules List',
                self::TAB_RULE_EDITOR  => 'Editor',
                self::TAB_JOURNAL      => 'Journal',
                self::TAB_INSTRUCTIONS => 'Instructions',
            ];

            if (self::isDebugMode()) {
                $tabs[self::TAB_DEBUG] = 'Debug';
                $tabs[self::TAB_VARIABLES] = 'Variables';
            }

            return $tabs;
        }
        return '';
    }

    /**
     * Вкладки: Connection, Rules List, Editor, Journal, Instructions.
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $ong[static::getType() . '$' . static::TAB_CONNECTION]   = static::createTabEntry('Connection', 0, null, 'ti ti-plug-connected');
        $ong[static::getType() . '$' . static::TAB_ALL_RULES]    = static::createTabEntry('Rules List', 0, null, 'ti ti-list');
        $ong[static::getType() . '$' . static::TAB_RULE_EDITOR]  = static::createTabEntry('Editor', 0, null, 'ti ti-message');
        $ong[static::getType() . '$' . static::TAB_JOURNAL]      = static::createTabEntry('Journal', 0, null, 'ti ti-notes');
        $ong[static::getType() . '$' . static::TAB_INSTRUCTIONS] = static::createTabEntry('Instructions', 0, null, 'ti ti-info-circle');
        if (self::isDebugMode()) {
            $ong[static::getType() . '$' . static::TAB_DEBUG] = static::createTabEntry('Debug', 0, null, 'ti ti-bug');
            $ong[static::getType() . '$' . static::TAB_VARIABLES] = static::createTabEntry('Variables', 0, null, 'ti ti-code');
        }
        $ong['no_all_tab'] = true;
        return $ong;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() !== self::getType()) {
            return false;
        }
        switch ($tabnum) {
            case self::TAB_CONNECTION:
                $item->showFormMattermost();
                break;
            case self::TAB_ALL_RULES:
                $item->showFormAllRules();
                break;
            case self::TAB_RULE_EDITOR:
                $item->showFormRuleEditorTab();
                break;
            case self::TAB_JOURNAL:
                $item->showFormJournalTab();
                break;
            case self::TAB_INSTRUCTIONS:
                $item->showFormInstructionsTab();
                break;
            case self::TAB_DEBUG:
                $item->showFormDebugTab();
                break;
            case self::TAB_VARIABLES:
                $item->showFormVariablesTab();
                break;
        }
        return true;
    }

    /** Режим подключения: вебхук */
    const CONNECTION_WEBHOOK = 'webhook';
    /** Режим подключения: логин и пароль */
    const CONNECTION_LOGIN = 'login';
    /** Режим подключения: персональный токен */
    const CONNECTION_PERSONAL_TOKEN = 'personal_token';

    /** Плейсхолдеры полей формы Connection (единый источник для view и MattermostClient). */
    const PLACEHOLDER_WEBHOOK_URL    = 'https://mattermost.local/hooks/cfd199faf768be42fcd552113b52be2f';
    const PLACEHOLDER_BOT_NICKNAME   = 'e.g.: GLPI Notifications Bot';
    const PLACEHOLDER_BOT_AVATAR    = 'e.g.: :robot_face: or https://example.com/avatar.png';
    const PLACEHOLDER_API_URL       = 'https://mattermost.local/api/v4/';
    const PLACEHOLDER_LOGIN         = 'el.padre';
    const PLACEHOLDER_TEST_CHANNEL  = 'e.g.: general (channel) or @el.padre (direct message)';
    const PLACEHOLDER_TEST_MESSAGE  = 'e.g.: Test message to verify the connection is working';

    /** Подстроки, по которым значение аватара считается плейсхолдером (не отправлять в Mattermost). */
    const PLACEHOLDER_AVATAR_PATTERNS = [
        self::PLACEHOLDER_BOT_AVATAR,
        'e.g.: :robot_face:',
    ];

    /**
     * Вкладка «MatterMost»: выпадающий список «Вариант подключения» (Webhook / Авторизация).
     * При Webhook — одно поле «Адрес вебхука». При Авторизация — три поля: адрес, логин, пароль.
     */
    public function showFormMattermost()
    {
        $this->getFromDB($this->getID());
        $config = $this;
        include __DIR__ . '/../front/views/connection.php';
    }

    /** Ключ вкладки «Connection». */
    const TAB_CONNECTION = 1;

    /** Ключ вкладки «Rules List» (только список правил). */
    const TAB_ALL_RULES = 2;

    /** Ключ вкладки «Editor» (создание и редактирование одного правила). */
    const TAB_RULE_EDITOR = 3;

    /** Ключ вкладки «Journal». */
    const TAB_JOURNAL = 4;

    /** Ключ вкладки «Instructions». */
    const TAB_INSTRUCTIONS = 5;

    /** Ключ вкладки «Debug» (видна только при включённом debug-режиме GLPI). */
    const TAB_DEBUG = 6;

    /** Ключ вкладки «Variables» (видна только при включённом debug-режиме GLPI). */
    const TAB_VARIABLES = 7;

    /**
     * Вкладка «Editor»: по умолчанию — форма создания правила; при edit=ID — форма редактирования.
     */
    public function showFormRuleEditorTab()
    {
        if (!empty($_GET['edit']) && (int) $_GET['edit'] > 0) {
            $this->showFormEventsEditRule((int) $_GET['edit'], self::TAB_RULE_EDITOR);
        } else {
            $this->showFormRuleEditor(self::TAB_RULE_EDITOR);
        }
    }

    /** Варианты Target — типы сущностей, от которых приходят события. */
    const RULE_TARGETS = ['Ticket' => 'Ticket', 'Approve' => 'Approve'];

    /** Макросы для подстановки в сообщение по каждому Target (порядок определяет отображение в подсказке). */
    const RULE_MACROS_BY_TARGET = [
        'Ticket'  => ['id', 'title', 'urgency', 'priority', 'type', 'category', 'assigned', 'requester', 'observer', 'event', 'status', 'link'],
        'Approve' => ['id', 'title', 'urgency', 'priority', 'type', 'category', 'assigned', 'requester', 'observer', 'event', 'status', 'link', 'approver'],
    ];

    /** Варианты Recipient по каждому Target (получатель уведомления). */
    const RECIPIENT_OPTIONS_BY_TARGET = [
        'Ticket'  => ['assigned' => 'assigned', 'requester' => 'requester', 'observer' => 'observer'],
        'Approve' => ['assigned' => 'assigned', 'requester' => 'requester', 'observer' => 'observer', 'approver' => 'approver'],
    ];

    /** Варианты Event — тип события. */
    const RULE_EVENTS = ['New' => 'New', 'Update' => 'Update', 'Delete' => 'Delete'];

    /** Варианты Active — активность правила. */
    const RULE_ACTIVE_OPTIONS = [1 => 'Active', 0 => 'Disable'];

    /** Значение по умолчанию для Raw Payload (JSON) (шаблон). */
    const DEFAULT_RAW_PAYLOAD_TEMPLATE = "{\n  \"icon_emoji\": \"{ICON_EMOJI}\",\n  \"username\": \"{USERNAME}\",\n  \"metadata\": {\n    \"priority\": {\n      \"priority\": \"urgent\",\n      \"requested_ack\": true\n    }\n  },\n  \"props\": {\n    \"attachments\": [\n      {\n        \"pretext\": \"Attachment title\",\n        \"text\": \"Text in attach\"\n      }\n    ]\n  }\n}";

    /**
     * Вернёт дефолтный Raw Payload c подставленными значениями для icon_emoji/username
     * из настроек подключения (Sender default avatar/nickname). Если не заданы — поля пустые.
     */
    public function getDefaultRawPayload(): string
    {
        $icon  = trim((string) ($this->fields['webhook_bot_avatar'] ?? ''));
        $user  = trim((string) ($this->fields['webhook_bot_nickname'] ?? ''));
        $icon  = $icon !== '' ? addcslashes($icon, "\"\\") : '';
        $user  = $user !== '' ? addcslashes($user, "\"\\") : '';

        $payload = str_replace(
            ['{ICON_EMOJI}', '{USERNAME}'],
            [$icon, $user],
            static::DEFAULT_RAW_PAYLOAD_TEMPLATE
        );

        return $payload;
    }

    /**
     * Форма добавления нового правила (вызывается из вкладки Editor).
     */
    public function showFormRuleEditor($in_tab = null)
    {
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        if ($edit_id > 0) {
            $this->showFormEventsEditRule($edit_id, $in_tab ?? self::TAB_RULE_EDITOR);
            return;
        }
        $config = $this;
        include __DIR__ . '/../front/views/rule_editor_add.php';
    }

    /**
     * Вкладка «Journal» — журнал работы/событий (заглушка).
     */
    public function showFormJournalTab()
    {
        $config = $this;
        include __DIR__ . '/../front/views/journal.php';
    }

    /**
     * Вкладка «Debug» — отладочная информация (отображается только в debug-режиме GLPI).
     */
    public function showFormDebugTab()
    {
        $config = $this;
        include __DIR__ . '/../front/views/debug.php';
    }

    /**
     * Вкладка «Variables» — переопределение названий переменных (отображается только в debug-режиме GLPI).
     */
    public function showFormVariablesTab()
    {
        $config = $this;
        include __DIR__ . '/../front/views/variables.php';
    }

    /**
     * Вкладка «Instructions» — инструкции по настройке (заглушка).
     */
    public function showFormInstructionsTab()
    {
        $config = $this;
        include __DIR__ . '/../front/views/instructions.php';
    }

    /**
     * Вкладка «Rules List»: только список правил. Добавление и редактирование — во вкладке Editor.
     */
    public function showFormAllRules()
    {
        $config = $this;
        include __DIR__ . '/../front/views/rules_list.php';
    }

    /**
     * Форма редактирования правила оповещения (вкладка Rules).
     */
    protected function showFormEventsEditRule($rule_id, $in_tab = null)
    {
        $rule = new NotificationRule();
        if (!$rule->getFromDB($rule_id)) {
            echo "<p class='alert alert-warning'>Rule not found.</p>";
            return;
        }
        $config = $this;
        include __DIR__ . '/../front/views/rule_editor_edit.php';
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['_glpi_tab']) && (int) $input['_glpi_tab'] === self::TAB_CONNECTION) {
            $connection_type_input = $input['connection_type'] ?? '';
            if ($connection_type_input === self::CONNECTION_LOGIN) {
                $input['connection_type'] = self::CONNECTION_LOGIN;
            } elseif ($connection_type_input === self::CONNECTION_PERSONAL_TOKEN) {
                $input['connection_type'] = self::CONNECTION_WEBHOOK;
            } else {
                $input['connection_type'] = self::CONNECTION_WEBHOOK;
            }
            if ($input['connection_type'] === self::CONNECTION_WEBHOOK) {
                $input['webhook_url']         = trim($input['webhook_url'] ?? '') !== '' ? trim($input['webhook_url']) : null;
                // Явно обрабатываем поля nickname и avatar, даже если они не переданы в POST
                $webhook_bot_nickname = isset($input['webhook_bot_nickname']) ? trim($input['webhook_bot_nickname']) : '';
                $webhook_bot_avatar   = isset($input['webhook_bot_avatar']) ? trim($input['webhook_bot_avatar']) : '';
                $input['webhook_bot_nickname'] = $webhook_bot_nickname !== '' ? $webhook_bot_nickname : null;
                $input['webhook_bot_avatar']   = $webhook_bot_avatar !== '' ? $webhook_bot_avatar : null;
                $input['mattermost_url']      = null;
                $input['mattermost_login']    = null;
                $input['mattermost_password'] = null;
            } else {
                $input['webhook_url']         = null;
                $input['webhook_bot_nickname'] = null;
                $input['webhook_bot_avatar']   = null;
                $input['mattermost_url'] = trim($input['mattermost_url'] ?? '') !== '' ? trim($input['mattermost_url']) : null;
                $input['mattermost_login'] = trim($input['mattermost_login'] ?? '') !== '' ? trim($input['mattermost_login']) : null;
                if (trim($input['mattermost_password'] ?? '') === '') {
                    unset($input['mattermost_password']);
                }
            }
            // Сохраняем статус последнего теста при обновлении конфига (не сбрасываем его)
            if (!isset($input['last_test_timestamp']) && array_key_exists('last_test_timestamp', $this->fields)) {
                $input['last_test_timestamp'] = $this->fields['last_test_timestamp'];
            }
            if (!isset($input['last_test_success']) && array_key_exists('last_test_success', $this->fields)) {
                $input['last_test_success'] = $this->fields['last_test_success'];
            }
            if (!isset($input['last_test_error']) && array_key_exists('last_test_error', $this->fields)) {
                $input['last_test_error'] = $this->fields['last_test_error'];
            }
        }
        return $input;
    }
}
