<?php
/**
 * Вкладка Instructions: инструкции по настройке и использованию плагина.
 * Ожидает $config (GlpiPlugin\Mattermost\Config).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
?>

<!-- Mattermost Configuration -->
<div class="card border-0 shadow-none p-0 m-0 mt-2">
    <div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
        <h4 class="card-title ms-5">
            <div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-server"></i></div>
            <?php echo htmlescape('Mattermost Configuration'); ?>
        </h4>
    </div>
</div>
<div class="alert alert-info mb-3">
    <i class="ti ti-info-circle flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Webhook URL Setup'); ?></h5>
        <ol class="mb-0">
            <li class="mb-2"><?php echo htmlescape('Go to your Mattermost server and navigate to the channel where you want to receive notifications.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Click on the channel name → Integrations → Incoming Webhooks.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Click "Add Incoming Webhook" and configure the webhook:'); ?>
                <ul class="mt-2">
                    <li><?php echo htmlescape('Set a title (e.g., "GLPI Notifications")'); ?></li>
                    <li><?php echo htmlescape('Choose the channel where notifications will be posted'); ?></li>
                    <li><?php echo htmlescape('Optionally set a default username and icon'); ?></li>
                </ul>
            </li>
            <li class="mb-2"><?php echo htmlescape('Copy the webhook URL (it looks like: https://your-mattermost.com/hooks/xxxxx)'); ?></li>
            <li><?php echo htmlescape('Paste this URL into the "Mattermost Webhook URL" field in the Connection tab of this plugin.'); ?></li>
        </ol>
    </div>
</div>

<div class="alert alert-warning mb-3">
    <i class="ti ti-alert-triangle flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Login / Password Authentication'); ?></h5>
        <p class="mb-3"><?php echo htmlescape('This method uses Mattermost API with your user credentials. Currently disabled in this plugin version.'); ?></p>
        <p class="mb-3"><strong><?php echo htmlescape('Note'); ?>:</strong> <?php echo htmlescape('When using Login / Password authentication, you cannot override the sender nickname and avatar - messages will be sent from the authenticated Mattermost user account.'); ?></p>
        <ol class="mb-0">
            <li class="mb-2"><?php echo htmlescape('Ensure you have a Mattermost user account with appropriate permissions.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('The API URL format is: https://your-mattermost.com/api/v4/'); ?></li>
            <li><?php echo htmlescape('Enter your Mattermost login credentials in the Connection tab.'); ?></li>
        </ol>
    </div>
</div>

<div class="alert alert-info mb-3">
    <i class="ti ti-key flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Personal Token Authentication'); ?></h5>
        <p class="mb-3"><?php echo htmlescape('This method uses a personal access token for API authentication. Currently disabled in this plugin version.'); ?></p>
        <ol class="mb-0">
            <li class="mb-2"><?php echo htmlescape('Log in to your Mattermost account.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Go to Account Settings → Security → Personal Access Tokens.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Click "Create Token" and give it a description (e.g., "GLPI Plugin").'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Copy the generated token immediately (it will not be shown again).'); ?></li>
            <li><?php echo htmlescape('Use this token in the Connection tab when Personal Token option becomes available.'); ?></li>
        </ol>
    </div>
</div>

<!-- Plugin Configuration -->
<div class="card border-0 shadow-none p-0 m-0 mt-3">
    <div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
        <h4 class="card-title ms-5">
            <div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-settings"></i></div>
            <?php echo htmlescape('Plugin Configuration'); ?>
        </h4>
    </div>
</div>
<div class="alert alert-success mb-3">
    <i class="ti ti-plug-connected flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Connection Setup'); ?></h5>
        <ol class="mb-0">
            <li class="mb-2"><?php echo htmlescape('Go to the Connection tab.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Select "Webhook URL request" as the connection type.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Enter your Mattermost Webhook URL in the "Mattermost Webhook URL" field.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Optionally set "Sender default nickname" (e.g., "GLPI Bot") and "Sender default avatar" (emoji like :robot_face: or image URL).'); ?></li>
            <li><?php echo htmlescape('Click "Save" to store the connection settings.'); ?></li>
        </ol>
    </div>
</div>

<div class="alert alert-success mb-3">
    <i class="ti ti-message-plus flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Creating Notification Rules'); ?></h5>
        <ol class="mb-0">
            <li class="mb-2"><?php echo htmlescape('Go to the Editor tab.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Fill in the required fields:'); ?>
                <ul class="mt-2">
                    <li><strong><?php echo htmlescape('Rule name'); ?>:</strong> <?php echo htmlescape('A descriptive name for your rule (must differ from the default)'); ?></li>
                    <li><strong><?php echo htmlescape('Target'); ?>:</strong> <?php echo htmlescape('The entity type (Ticket or Approve)'); ?></li>
                    <li><strong><?php echo htmlescape('Event Type'); ?>:</strong> <?php echo htmlescape('When to trigger (New, Update, or Delete)'); ?></li>
                    <li><strong><?php echo htmlescape('Recipient list'); ?>:</strong> <?php echo htmlescape('Comma-separated list of channels/users (e.g., username for direct message, @channel-name for channel, {requester})'); ?></li>
                    <li><strong><?php echo htmlescape('Message template'); ?>:</strong> <?php echo htmlescape('The message to send. Use {macros} for dynamic values (type { to see available options)'); ?></li>
                </ul>
            </li>
            <li class="mb-2"><?php echo htmlescape('Set "Rule status" to Active or Disable.'); ?></li>
            <li class="mb-2"><?php echo htmlescape('Optionally enable "Override Mattermost Payload" to send custom JSON payload.'); ?></li>
            <li><?php echo htmlescape('Click "Save" to create the rule.'); ?></li>
        </ol>
    </div>
</div>

<div class="alert alert-success mb-3">
    <i class="ti ti-list flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Managing Rules'); ?></h5>
        <ul class="mb-0">
            <li class="mb-2"><strong><?php echo htmlescape('Rules List'); ?>:</strong> <?php echo htmlescape('View all your notification rules, sort by columns, search, and perform bulk actions (enable/disable/delete).'); ?></li>
            <li class="mb-2"><strong><?php echo htmlescape('Editing Rules'); ?>:</strong> <?php echo htmlescape('Click the pencil icon next to any rule to edit it. After saving a rule, you can set an Extended Filter to limit when the rule triggers.'); ?></li>
            <li class="mb-2"><strong><?php echo htmlescape('Extended Filter'); ?>:</strong> <?php echo htmlescape('Use the Extended Filter section to add conditions (e.g., only trigger for tickets with specific priority or status).'); ?></li>
        </ul>
    </div>
</div>

<div class="alert alert-info mb-0">
    <i class="ti ti-code flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
    <div>
        <h5 class="mb-3 mt-0"><?php echo htmlescape('Message Templates'); ?></h5>
        <p class="mb-2"><?php echo htmlescape('You can use the following macros in your message templates:'); ?></p>
        <ul class="mb-3">
            <li><code>{id}</code> - <?php echo htmlescape('Ticket/Item ID'); ?></li>
            <li><code>{title}</code> - <?php echo htmlescape('Ticket/Item title'); ?></li>
            <li><code>{status}</code> - <?php echo htmlescape('Current status'); ?></li>
            <li><code>{priority}</code> - <?php echo htmlescape('Priority level'); ?></li>
            <li><code>{requester}</code> - <?php echo htmlescape('Requester username'); ?></li>
            <li><code>{assigned}</code> - <?php echo htmlescape('Assigned user'); ?></li>
            <li><code>{link}</code> - <?php echo htmlescape('Direct link to the item'); ?></li>
            <li><?php echo htmlescape('And more... Type { in the message field to see all available macros for your selected Target.'); ?></li>
        </ul>
        <p class="mb-0"><strong><?php echo htmlescape('Tip'); ?>:</strong> <?php echo htmlescape('You can use Markdown formatting in your messages. The autocomplete feature (type {) helps you insert macros correctly.'); ?></p>
    </div>
</div>

