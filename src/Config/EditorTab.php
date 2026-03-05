<?php

namespace GlpiPlugin\Mattermostjetlag\Config;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\CriteriaFilter;
use GlpiPlugin\Mattermostjetlag\Config;
use GlpiPlugin\Mattermostjetlag\NotificationRule;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class EditorTab
{
    private const TEMPLATE = '@mattermostjetlag/config/editor.html.twig';

    public const RULE_TARGETS = [
        'Ticket' => 'Ticket',
    ];

    public const RULE_EVENTS = [
        'create'         => 'Ticket Created',
        'followup'       => 'Ticket Commented',
        'approval'       => 'Approval Requested',
        'approved'       => 'Ticket Approved',
        'rejected'       => 'Ticket Rejected',
        'status_changed' => 'Status Changed',
        'members_change' => 'Members Changed',
        'solution'          => 'Solution Proposed',
        'solution_approved' => 'Solution Approved',
        'solution_rejected' => 'Solution Rejected',
    ];

    public const RULE_ACTIVE_OPTIONS = [
        1 => 'Active',
        0 => 'Disable',
    ];

    /** Macros for message template by target (order defines autocomplete order) */
    public const RULE_MACROS_BY_TARGET = [
        'Ticket' => ['id', 'title', 'urgency', 'priority', 'type', 'category', 'assigned', 'requester', 'observer', 'event', 'status', 'link'],
    ];

    /** Extra macros available only for specific events (merged on top of base macros) */
    public const RULE_MACROS_EXTRA_BY_EVENT = [
        'approval' => ['approver'],
        'approved' => ['approver'],
        'rejected' => ['approver'],
    ];

    /** Recipient macros by target */
    public const RECIPIENT_OPTIONS_BY_TARGET = [
        'Ticket' => ['assigned' => 'assigned', 'requester' => 'requester', 'observer' => 'observer'],
    ];

    /** Extra recipient options available only for specific events */
    public const RECIPIENT_OPTIONS_EXTRA_BY_EVENT = [
        'approval' => ['approver' => 'approver'],
        'approved' => ['approver' => 'approver'],
        'rejected' => ['approver' => 'approver'],
    ];

    public const DEFAULT_RULE_NAME = '[Mattermost] -> Send notifications to all';
    public const DEFAULT_RECIPIENT = '@el.padre, general, {requester}';
    public const MESSAGE_PLACEHOLDER = 'Enter the message text to send to Mattermost. You can use templates, start typing { to see the list of available templates for this notification type. You can use Markdown manually or via the corresponding panel.';

    /**
     * Build default raw payload JSON with username and icon from Connectivity config.
     * Values come from DB: glpi_plugin_mattermostjetlag_configs (webhook_bot_nickname, webhook_bot_avatar).
     * If not set, empty string "" is used.
     */
    private static function buildDefaultRawPayload(Config $config): string
    {
        $fields   = $config->fields ?? [];
        $nickname = trim((string) ($fields['webhook_bot_nickname'] ?? ''));
        $avatar   = trim((string) ($fields['webhook_bot_avatar'] ?? ''));

        // Skip placeholder text (e.g. "e.g.: GLPI Notifications Bot")
        if (str_starts_with($nickname, 'e.g.:')) {
            $nickname = '';
        }
        if (str_starts_with($avatar, 'e.g.:')) {
            $avatar = '';
        }

        $useIconUrl = $avatar !== '' && preg_match('#^https?://#i', $avatar);

        $payload = [];
        if ($useIconUrl) {
            $payload['icon_url'] = $avatar;
        } elseif ($avatar !== '') {
            $payload['icon_emoji'] = $avatar;
        } else {
            $payload['icon_emoji'] = '';
        }
        $payload['username'] = $nickname !== '' ? $nickname : '';
        $payload['priority'] = [
            'priority'      => 'urgent',
            'requested_ack' => true,
        ];
        $payload['attachments'] = [
            [
                'pretext' => 'Attachment title',
                'text'    => 'Text in attach',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        $edit_id   = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editing   = ($edit_id > 0);
        $rule_id   = $editing ? $edit_id : null;
        $rule_data = [];
        $extended_filter_html = '';

        if ($editing) {
            $rule = new NotificationRule();
            if ($rule->getFromDB($edit_id) && $rule->canView()) {
                $rule_data = [
                    'name'            => $rule->getField('name'),
                    'target'          => $rule->getField('target') ?: 'Ticket',
                    'event'           => $rule->getField('event') ?: 'New',
                    'recipient'       => $rule->getField('recipient'),
                    'message'         => $rule->getField('message'),
                    'active'          => (int) $rule->getField('active'),
                    'use_raw_payload'  => (int) $rule->getField('use_raw_payload'),
                    'raw_payload'     => $rule->getField('raw_payload'),
                ];
                ob_start();
                CriteriaFilter::displayTabContentForItem($rule, 1, 0);
                $extended_filter_html = ob_get_clean();
            } else {
                $editing   = false;
                $rule_id   = null;
                $rule_data = [];
            }
        }

        $tab_rules      = Config::getType() . '$2';
        $rules_list_url = Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rules);
        $save_filter_url = Toolbox::getItemTypeFormURL(Config::getType()) . '?id=1';

        $targets = [];
        foreach (self::RULE_TARGETS as $k => $v) {
            $targets[$k] = __($v, 'mattermostjetlag');
        }
        $events = [];
        foreach (self::RULE_EVENTS as $k => $v) {
            $events[$k] = __($v, 'mattermostjetlag');
        }
        $active_options = [];
        foreach (self::RULE_ACTIVE_OPTIONS as $k => $v) {
            $active_options[$k] = __($v, 'mattermostjetlag');
        }

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'editing'               => $editing,
            'rule_id'               => $rule_id,
            'rule'                  => $rule_data,
            'extended_filter_html'  => $extended_filter_html,
            'rules_list_url'        => $rules_list_url,
            'save_filter_url'       => $save_filter_url,
            'targets'               => $targets,
            'events'                => $events,
            'active_options'        => $active_options,
            'default_rule_name'     => self::DEFAULT_RULE_NAME,
            'default_recipient'     => self::DEFAULT_RECIPIENT,
            'message_placeholder'  => self::MESSAGE_PLACEHOLDER,
            'default_raw_payload'   => self::buildDefaultRawPayload($config),
            'macros_by_target'               => self::RULE_MACROS_BY_TARGET,
            'macros_extra_by_event'          => self::RULE_MACROS_EXTRA_BY_EVENT,
            'recipient_options_by_target'    => self::RECIPIENT_OPTIONS_BY_TARGET,
            'recipient_options_extra_by_event' => self::RECIPIENT_OPTIONS_EXTRA_BY_EVENT,
        ]);
    }
}
