<?php
/**
 * Вкладка Connection: форма подключения к Mattermost и тест соединения.
 * Ожидает $config (GlpiPlugin\Mattermost\Config).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
$id = (int) $config->fields['id'];
$connection_type = $config->fields['connection_type'] ?? \GlpiPlugin\Mattermost\Config::CONNECTION_WEBHOOK;
$webhook_url = trim($config->fields['webhook_url'] ?? '');
$webhook_bot_nickname = trim($config->fields['webhook_bot_nickname'] ?? '');
$webhook_bot_avatar = trim($config->fields['webhook_bot_avatar'] ?? '');
$mattermost_url = trim($config->fields['mattermost_url'] ?? '');
$mattermost_login = trim($config->fields['mattermost_login'] ?? '');
$last_test_success_val = isset($config->fields['last_test_success']) ? (int) $config->fields['last_test_success'] : null;
$last_test_timestamp = $config->fields['last_test_timestamp'] ?? null;
if ($last_test_success_val === null && $last_test_timestamp === null) {
    global $DB;
    $dbu = new \DbUtils();
    $config_table = $dbu->getTableForItemType(\GlpiPlugin\Mattermost\Config::class);
    if ($DB->fieldExists($config_table, 'last_test_success')) {
        $row = $DB->request([
            'FROM'  => $config_table,
            'WHERE' => ['id' => 1],
        ])->current();
        if ($row !== null && array_key_exists('last_test_success', $row)) {
            $last_test_success_val = $row['last_test_success'] !== null ? (int) $row['last_test_success'] : null;
            $last_test_timestamp = $row['last_test_timestamp'] ?? null;
        }
    }
}
$last_test_success = ($last_test_success_val === 1);
$last_test_label = $last_test_success ? __('Success') : ($last_test_success_val === 0 ? __('Failed') : __('Unknown'));
$last_test_css = $last_test_success ? 'border-success text-success' : 'border-danger text-danger';
$last_test_date_display = null;
if ($last_test_timestamp !== null && $last_test_timestamp !== '' && (int) $last_test_timestamp > 0) {
    $last_test_date_display = date('Y-m-d H:i:s', (int) $last_test_timestamp);
}
$placeholder_webhook     = \GlpiPlugin\Mattermost\Config::PLACEHOLDER_WEBHOOK_URL;
$placeholder_bot_nickname = \GlpiPlugin\Mattermost\Config::PLACEHOLDER_BOT_NICKNAME;
$placeholder_bot_avatar  = \GlpiPlugin\Mattermost\Config::PLACEHOLDER_BOT_AVATAR;
$placeholder_api_url     = \GlpiPlugin\Mattermost\Config::PLACEHOLDER_API_URL;
$placeholder_login       = \GlpiPlugin\Mattermost\Config::PLACEHOLDER_LOGIN;
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<div class="card border-0 shadow-none p-0 m-0 mt-2">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-plug-connected"></i></div>
<?php echo htmlescape('Enter connection details'); ?>
</h4>
</div>
</div>
<div class="card border mb-0">
<div class="card-body">
<div class="alert alert-success mb-3 d-flex align-items-center">
<i class="ti ti-book flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
<span>Choose the connection type and provide the required data to send messages to Mattermost.</span>
</div>
<form id="mattermost_config_form" name="form" method="post" action="<?php echo \Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()); ?>">
<?php echo \Html::hidden('id', ['value' => $id]); ?>
<?php echo \Html::hidden('_glpi_tab', ['value' => (string) \GlpiPlugin\Mattermost\Config::TAB_CONNECTION]); ?>
<?php echo \Html::hidden('_glpi_csrf_token', ['value' => \Session::getNewCSRFToken()]); ?>
<div class="mattermost-form">
<?php
$connection_help = 'Webhook: send messages via a Mattermost incoming webhook URL. Login/Password: use Mattermost API with your user credentials.';
$popover_title_attr = htmlspecialchars(htmlescape('Connection types'), ENT_QUOTES, 'UTF-8');
$popover_content_attr = htmlspecialchars(htmlescape($connection_help), ENT_QUOTES, 'UTF-8');
$webhook_style = $connection_type === \GlpiPlugin\Mattermost\Config::CONNECTION_WEBHOOK ? '' : 'display: none;';
$auth_style = $connection_type === \GlpiPlugin\Mattermost\Config::CONNECTION_LOGIN ? '' : 'display: none;';
?>
<div class="mattermost-form-row">
<span class="mattermost-form-label"><?php echo htmlescape('Connection Type'); ?>:</span>
<div class="mattermost-form-control-cell">
<select name="connection_type" id="connection_type_select" class="form-select">
<option value="<?php echo \GlpiPlugin\Mattermost\Config::CONNECTION_WEBHOOK; ?>"<?php echo $connection_type === \GlpiPlugin\Mattermost\Config::CONNECTION_WEBHOOK ? ' selected' : ''; ?>>Webhook URL request</option>
<option value="<?php echo \GlpiPlugin\Mattermost\Config::CONNECTION_LOGIN; ?>"<?php echo $connection_type === \GlpiPlugin\Mattermost\Config::CONNECTION_LOGIN ? ' selected' : ''; ?> disabled>Login / Password connection</option>
<option value="<?php echo \GlpiPlugin\Mattermost\Config::CONNECTION_PERSONAL_TOKEN; ?>"<?php echo $connection_type === \GlpiPlugin\Mattermost\Config::CONNECTION_PERSONAL_TOKEN ? ' selected' : ''; ?> disabled>Personal Token</option>
</select>
<span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 mattermost-connection-help" style="width: 2.25rem; height: 2.25rem; cursor: pointer;" role="button" tabindex="-1" data-bs-toggle="popover" data-bs-placement="right" data-bs-trigger="hover" data-bs-title="<?php echo $popover_title_attr; ?>" data-bs-content="<?php echo $popover_content_attr; ?>" data-bs-html="false"><i class="material-icons" style="font-size: 1.5rem; color: #72b676;">help</i></span>
</div></div>
<div id="mattermost_block_webhook" class="mattermost-form-row" style="<?php echo $webhook_style; ?>">
<?php
$webhook_help_t = htmlspecialchars('Mattermost Webhook URL', ENT_QUOTES, 'UTF-8');
$webhook_help_c = htmlspecialchars(htmlescape('Incoming webhook URL from your Mattermost server. Create it in a channel via Integrations → Incoming Webhooks.'), ENT_QUOTES, 'UTF-8');
$webhook_value = $webhook_url !== '' ? $webhook_url : $placeholder_webhook;
?>
<span class="mattermost-form-label"><?php echo htmlescape('Mattermost Webhook URL'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('webhook_url', ['value' => $webhook_value, 'id' => 'field_webhook_url', 'class' => 'form-control', 'data-placeholder' => $placeholder_webhook]); ?>
<span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 mattermost-connection-help" style="width: 2.25rem; height: 2.25rem; cursor: pointer;" role="button" tabindex="-1" data-bs-toggle="popover" data-bs-placement="right" data-bs-trigger="hover" data-bs-title="<?php echo $webhook_help_t; ?>" data-bs-content="<?php echo $webhook_help_c; ?>" data-bs-html="false"><i class="material-icons" style="font-size: 1.5rem; color: #72b676;">help</i></span>
</div></div>
<div class="mattermost-form-row" style="<?php echo $webhook_style; ?>">
<?php
$bot_nickname_value = $webhook_bot_nickname !== '' ? $webhook_bot_nickname : $placeholder_bot_nickname;
?>
<span class="mattermost-form-label"><?php echo htmlescape('Sender default nickname'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('webhook_bot_nickname', ['value' => $bot_nickname_value, 'id' => 'field_webhook_bot_nickname', 'class' => 'form-control', 'data-placeholder' => $placeholder_bot_nickname]); ?>
</div></div>
<div class="mattermost-form-row" style="<?php echo $webhook_style; ?>">
<?php
$bot_avatar_help_t = htmlspecialchars('Sender default avatar', ENT_QUOTES, 'UTF-8');
$bot_avatar_help_c = htmlspecialchars(htmlescape('Sender default avatar URL or emoji (e.g., :robot_face:). Leave empty to use default.'), ENT_QUOTES, 'UTF-8');
$bot_avatar_value = $webhook_bot_avatar !== '' ? $webhook_bot_avatar : $placeholder_bot_avatar;
?>
<span class="mattermost-form-label"><?php echo htmlescape('Sender default avatar (URL or emoji)'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('webhook_bot_avatar', ['value' => $bot_avatar_value, 'id' => 'field_webhook_bot_avatar', 'class' => 'form-control', 'data-placeholder' => $placeholder_bot_avatar]); ?>
<span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 mattermost-connection-help" style="width: 2.25rem; height: 2.25rem; cursor: pointer;" role="button" tabindex="-1" data-bs-toggle="popover" data-bs-placement="right" data-bs-trigger="hover" data-bs-title="<?php echo $bot_avatar_help_t; ?>" data-bs-content="<?php echo $bot_avatar_help_c; ?>" data-bs-html="false"><i class="material-icons" style="font-size: 1.5rem; color: #72b676;">help</i></span>
</div></div>
<div id="mattermost_block_auth" style="<?php echo $auth_style; ?>">
<?php
$api_url_help_t = htmlspecialchars('Mattermost API URL', ENT_QUOTES, 'UTF-8');
$api_url_help_c = htmlspecialchars(htmlescape('Base URL of your Mattermost API, for example https://mattermost.example.com/api/v4/'), ENT_QUOTES, 'UTF-8');
$api_url_value = $mattermost_url !== '' ? $mattermost_url : $placeholder_api_url;
$login_value = $mattermost_login !== '' ? $mattermost_login : $placeholder_login;
?>
<div class="mattermost-form-row">
<span class="mattermost-form-label"><?php echo htmlescape('Mattermost API URL'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('mattermost_url', ['value' => $api_url_value, 'id' => 'field_mattermost_url', 'class' => 'form-control', 'data-placeholder' => $placeholder_api_url]); ?>
<span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 mattermost-connection-help" style="width: 2.25rem; height: 2.25rem; cursor: pointer;" role="button" tabindex="-1" data-bs-toggle="popover" data-bs-placement="right" data-bs-trigger="hover" data-bs-title="<?php echo $api_url_help_t; ?>" data-bs-content="<?php echo $api_url_help_c; ?>" data-bs-html="false"><i class="material-icons" style="font-size: 1.5rem; color: #72b676;">help</i></span>
</div></div>
<div class="mattermost-form-row">
<span class="mattermost-form-label"><?php echo htmlescape('User login'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('mattermost_login', ['value' => $login_value, 'id' => 'field_mattermost_login', 'class' => 'form-control', 'data-placeholder' => $placeholder_login]); ?>
</div></div>
<div class="mattermost-form-row mattermost-form-row-hint">
<span class="mattermost-form-label"><?php echo htmlescape('User Password'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('mattermost_password', ['value' => '', 'type' => 'password', 'id' => 'field_mattermost_password', 'autocomplete' => 'new-password', 'class' => 'form-control']); ?>
<p class="mattermost-hint"><?php echo htmlescape('When you enter a new password, the previous one will be replaced.'); ?></p>
</div></div></div>
<script>
(function() {
    var sel = document.getElementById('connection_type_select');
    var blockWebhook = document.getElementById('mattermost_block_webhook');
    var blockAuth = document.getElementById('mattermost_block_auth');
    if (!sel || !blockWebhook || !blockAuth) return;
    var lastValidValue = sel.value;
    function toggle() {
        var selectedOption = sel.options[sel.selectedIndex];
        if (selectedOption.disabled) {
            sel.value = lastValidValue;
            return;
        }
        lastValidValue = sel.value;
        var isWebhook = sel.value === '<?php echo \GlpiPlugin\Mattermost\Config::CONNECTION_WEBHOOK; ?>';
        blockWebhook.style.display = isWebhook ? '' : 'none';
        blockAuth.style.display = isWebhook ? 'none' : '';
        var botNicknameInput = document.getElementById('field_webhook_bot_nickname');
        var botAvatarInput = document.getElementById('field_webhook_bot_avatar');
        if (botNicknameInput) {
            var botNicknameRow = botNicknameInput.closest('.mattermost-form-row');
            if (botNicknameRow) botNicknameRow.style.display = isWebhook ? '' : 'none';
        }
        if (botAvatarInput) {
            var botAvatarRow = botAvatarInput.closest('.mattermost-form-row');
            if (botAvatarRow) botAvatarRow.style.display = isWebhook ? '' : 'none';
        }
    }
    sel.addEventListener('change', toggle);
    toggle();
})();
(function() {
    document.querySelectorAll('input[data-placeholder]').forEach(function(el) {
        var placeholder = el.getAttribute('data-placeholder');
        function updateStyle() {
            if (el.value === placeholder) { el.style.fontStyle = 'italic'; el.style.fontSize = '0.9em'; el.style.color = '#6c757d'; }
            else { el.style.fontStyle = ''; el.style.fontSize = ''; el.style.color = ''; }
        }
        el.addEventListener('focus', function() { if (el.value === placeholder) { el.value = ''; updateStyle(); } });
        el.addEventListener('blur', function() { if (el.value.trim() === '') el.value = placeholder; updateStyle(); });
        el.addEventListener('input', updateStyle);
        updateStyle();
    });
})();
(function() {
    var form = document.getElementById('mattermost_config_form');
    if (form) {
        form.addEventListener('submit', function() {
            // Перед сохранением, как и для webhook_url, очищаем плейсхолдеры:
            document.querySelectorAll('input[data-placeholder]').forEach(function(el) {
                var placeholder = el.getAttribute('data-placeholder');
                if (el.value === placeholder) {
                    el.value = '';
                }
            });
        });
    }
})();
(function() {
    if (typeof bootstrap === 'undefined') return;
    document.querySelectorAll('.mattermost-connection-help[data-bs-toggle="popover"]').forEach(function(el) { new bootstrap.Popover(el, { sanitize: false }); });
})();
</script>
</div>
</div></div>
<?php \Html::closeForm(); ?>

<div class="card border-0 shadow-none p-0 m-0 mt-3">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-bolt"></i></div>
<?php echo htmlescape('Test connection'); ?>
</h4>
</div>
</div>
<div class="card border mb-0">
<div class="card-body">
<div class="alert alert-success mb-3 d-flex align-items-center">
<i class="ti ti-book flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
<span>To test the connection, specify the channel name or username with @ symbol and message text.</span>
</div>
<div class="mattermost-form">
<?php
$channel_help_t = htmlspecialchars('Channel or username', ENT_QUOTES, 'UTF-8');
$channel_help_c = htmlspecialchars(htmlescape('Channel name (e.g. general) for a channel message, or @username for a direct message.'), ENT_QUOTES, 'UTF-8');
?>
<div class="mattermost-form-row">
<span class="mattermost-form-label"><?php echo htmlescape('Channel or username'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('test_channel', ['value' => \GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_CHANNEL, 'id' => 'field_test_channel', 'class' => 'form-control']); ?>
<span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 mattermost-connection-help" style="width: 2.25rem; height: 2.25rem; cursor: pointer;" role="button" tabindex="-1" data-bs-toggle="popover" data-bs-placement="right" data-bs-trigger="hover" data-bs-title="<?php echo $channel_help_t; ?>" data-bs-content="<?php echo $channel_help_c; ?>" data-bs-html="false"><i class="material-icons" style="font-size: 1.5rem; color: #72b676;">help</i></span>
</div></div>
<div class="mattermost-form-row">
<span class="mattermost-form-label"><?php echo htmlescape('Message Text'); ?>:</span>
<div class="mattermost-form-control-cell">
<?php echo \Html::input('test_message', ['value' => \GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_MESSAGE, 'id' => 'field_test_message', 'class' => 'form-control']); ?>
</div></div>
</div>
<div class="mattermost-btn-row">
<span id="mattermost_last_test_status" class="mattermost-last-test me-2 <?php echo $last_test_css; ?>" role="status"><span class="mattermost-last-test-dot <?php echo $last_test_success ? 'bg-success' : 'bg-danger'; ?>"></span><span class="mattermost-last-test-text"><?php echo htmlescape(__('Last connection test status')); ?>: <?php echo htmlescape($last_test_label); ?><?php if ($last_test_date_display) { echo ' · ' . htmlescape(\Html::convDateTime($last_test_date_display)); } ?></span></span>
<button type="button" class="btn btn-outline-secondary me-2" name="test_connection">Check connection</button>
<?php echo \Html::submit('Save', ['name' => 'update_config', 'class' => 'btn btn-primary', 'form' => 'mattermost_config_form']); ?>
</div>
<script>
(function() {
    var placeholderTestChannel = <?php echo json_encode(\GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_CHANNEL); ?>;
    var placeholderTestMessage = <?php echo json_encode(\GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_MESSAGE); ?>;
    var fields = [
        { id: 'field_test_channel', defaultValue: placeholderTestChannel },
        { id: 'field_test_message', defaultValue: placeholderTestMessage }
    ];
    fields.forEach(function(f) {
        var el = document.getElementById(f.id);
        if (!el) return;
        function updateStyle() {
            if (el.value === f.defaultValue) { el.style.fontStyle = 'italic'; el.style.fontSize = '0.9em'; el.style.color = '#6c757d'; }
            else { el.style.fontStyle = ''; el.style.fontSize = ''; el.style.color = ''; }
        }
        el.addEventListener('focus', function() { if (el.value === f.defaultValue) { el.value = ''; updateStyle(); } });
        el.addEventListener('blur', function() { if (el.value.trim() === '') el.value = f.defaultValue; updateStyle(); });
        el.addEventListener('input', updateStyle);
        updateStyle();
    });
})();
</script>
<script>
(function() {
    var btn = document.querySelector('button[name="test_connection"]');
    if (!btn) return;

    btn.addEventListener('click', function() {
        var webhookInput = document.getElementById('field_webhook_url');
        var nickInput    = document.getElementById('field_webhook_bot_nickname');
        var avatarInput  = document.getElementById('field_webhook_bot_avatar');
        var channelInput = document.getElementById('field_test_channel');
        var messageInput = document.getElementById('field_test_message');

        if (!webhookInput || !channelInput || !messageInput) {
            alert('Required fields are missing on the page.');
            return;
        }

        var webhookUrl = (webhookInput.value || '').trim();
        var webhookPlaceholder = webhookInput.getAttribute('data-placeholder') || '';
        if (webhookUrl === webhookPlaceholder) {
            webhookUrl = '';
        }

        var channel = (channelInput.value || '').trim();
        var msg     = (messageInput.value || '').trim();

        // Обработка плейсхолдеров тестовых полей
        var placeholderChannel = <?php echo json_encode(\GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_CHANNEL); ?>;
        var placeholderMessage = <?php echo json_encode(\GlpiPlugin\Mattermost\Config::PLACEHOLDER_TEST_MESSAGE); ?>;
        if (channel === placeholderChannel) { channel = ''; }
        if (msg === placeholderMessage) { msg = ''; }

        if (!webhookUrl) {
            if (typeof glpi_toast_error === 'function') {
                glpi_toast_error('Please specify Mattermost Webhook URL in the Connection tab.');
            } else {
                alert('Please specify Mattermost Webhook URL in the Connection tab.');
            }
            return;
        }
        if (!channel) {
            if (typeof glpi_toast_error === 'function') {
                glpi_toast_error('Please specify the channel name or @username.');
            } else {
                alert('Please specify the channel name or @username.');
            }
            return;
        }
        if (!msg) {
            if (typeof glpi_toast_error === 'function') {
                glpi_toast_error('Please specify the test message text.');
            } else {
                alert('Please specify the test message text.');
            }
            return;
        }

        var nickname = nickInput ? (nickInput.value || '').trim() : '';
        var avatar   = avatarInput ? (avatarInput.value || '').trim() : '';
        
        // Проверяем плейсхолдеры для nickname и avatar
        var nicknamePlaceholder = nickInput ? (nickInput.getAttribute('data-placeholder') || '') : '';
        var avatarPlaceholder = avatarInput ? (avatarInput.getAttribute('data-placeholder') || '') : '';
        
        if (nickname === nicknamePlaceholder) {
            nickname = '';
        }
        if (avatar === avatarPlaceholder) {
            avatar = '';
        }
        
        var csrfInput = document.querySelector('#mattermost_config_form input[name="_glpi_csrf_token"]');

        var formData = new FormData();
        formData.append('mattermost_ajax', 'test_webhook');
        formData.append('test_webhook_url', webhookUrl);
        formData.append('test_sender_nickname', nickname);
        formData.append('test_sender_avatar', avatar);
        formData.append('test_channel', channel);
        formData.append('test_message', msg);
        if (csrfInput && csrfInput.value) {
            formData.append('_glpi_csrf_token', csrfInput.value);
        }

        btn.disabled = true;
        btn.classList.add('disabled');

        fetch('<?php echo htmlescape(\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType())); ?>', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function(response) {
            // Собираем все заголовки для отладки
            var headers = {};
            response.headers.forEach(function(value, key) {
                headers[key] = value;
            });
            
            var contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json().then(function(data) {
                    return { 
                        ok: response.ok, 
                        status: response.status, 
                        data: data, 
                        isJson: true,
                        headers: headers
                    };
                }).catch(function(jsonErr) {
                    return response.text().then(function(text) {
                        return { 
                            ok: false, 
                            status: response.status, 
                            data: null, 
                            error: 'Failed to parse JSON: ' + jsonErr + ', Response: ' + text,
                            responseText: text,
                            headers: headers,
                            isJson: false 
                        };
                    });
                });
            } else {
                return response.text().then(function(text) {
                    return { 
                        ok: false, 
                        status: response.status, 
                        data: null, 
                        error: 'Server returned non-JSON response (HTTP ' + response.status + ')',
                        responseText: text,
                        headers: headers,
                        isJson: false 
                    };
                });
            }
        }).then(function(result) {
            // Обновляем CSRF токен из ответа (если есть) для следующего запроса
            if (csrfInput && result.data && result.data.csrf_token) {
                csrfInput.value = result.data.csrf_token;
            }
            
            var statusEl = document.getElementById('mattermost_last_test_status');
            var nowStr = (new Date()).toLocaleString();
            function setLastTestStatus(success) {
                if (!statusEl) return;
                statusEl.className = 'mattermost-last-test me-2 ' + (success ? 'border-success text-success' : 'border-danger text-danger');
                var dot = statusEl.querySelector('.mattermost-last-test-dot');
                if (dot) {
                    dot.className = 'mattermost-last-test-dot ' + (success ? 'bg-success' : 'bg-danger');
                }
                var textEl = statusEl.querySelector('.mattermost-last-test-text');
                if (textEl) {
                    textEl.textContent = '<?php echo htmlescape(__("Last connection test status")); ?>: ' + (success ? '<?php echo htmlescape(__("Success")); ?>' : '<?php echo htmlescape(__("Failed")); ?>') + ' · ' + nowStr;
                }
            }
            if (result.ok && result.data && result.data.ok) {
                setLastTestStatus(true);
                if (typeof glpi_toast_info === 'function') {
                    glpi_toast_info(result.data.message || 'Test message successfully sent to Mattermost.');
                } else {
                    alert(result.data.message || 'Test message successfully sent to Mattermost.');
                }
            } else {
                setLastTestStatus(false);
                var errMsg = '';
                var debugInfo = [];
                
                // Добавляем базовую информацию
                debugInfo.push('HTTP Status: ' + result.status);
                debugInfo.push('Content-Type: ' + (result.headers && result.headers['content-type'] ? result.headers['content-type'] : 'unknown'));
                
                if (result.data && result.data.error) {
                    errMsg = result.data.error;
                    if (result.data.debug) {
                        debugInfo.push('Debug info: ' + JSON.stringify(result.data.debug));
                    }
                } else if (result.error) {
                    errMsg = result.error;
                } else {
                    errMsg = 'HTTP ' + result.status + ' error';
                }
                
                // Добавляем полный текст ответа
                if (result.responseText) {
                    var responsePreview = result.responseText.length > 2000 
                        ? result.responseText.substring(0, 2000) + '... (truncated, total length: ' + result.responseText.length + ' chars)'
                        : result.responseText;
                    debugInfo.push('Response body: ' + responsePreview);
                }
                
                // Добавляем информацию о заголовках для 403 ошибок
                if (result.status === 403 && result.headers) {
                    debugInfo.push('Response headers: ' + JSON.stringify(result.headers));
                }
                
                // Формируем финальное сообщение
                var fullErrorMsg = errMsg;
                if (debugInfo.length > 0) {
                    fullErrorMsg += '\n\nDebug details:\n' + debugInfo.join('\n');
                }
                
                // Показываем в консоли для детального анализа
                console.error('Test webhook error:', {
                    status: result.status,
                    error: errMsg,
                    responseText: result.responseText,
                    headers: result.headers,
                    data: result.data
                });
                
                if (typeof glpi_toast_error === 'function') {
                    glpi_toast_error('Failed to send test message: ' + errMsg + '\n\nCheck browser console (F12) for full details.');
                } else {
                    alert('Failed to send test message: ' + fullErrorMsg);
                }
            }
        }).catch(function(e) {
            var statusEl = document.getElementById('mattermost_last_test_status');
            if (statusEl) {
                var nowStr = (new Date()).toLocaleString();
                statusEl.className = 'mattermost-last-test me-2 border-danger text-danger';
                var dot = statusEl.querySelector('.mattermost-last-test-dot');
                if (dot) dot.className = 'mattermost-last-test-dot bg-danger';
                var textEl = statusEl.querySelector('.mattermost-last-test-text');
                if (textEl) textEl.textContent = '<?php echo htmlescape(__("Last connection test status")); ?>: <?php echo htmlescape(__("Failed")); ?> · ' + nowStr;
            }
            var errMsg = 'Network or parsing error: ' + (e.message || String(e));
            console.error('Test webhook exception:', e);
            if (typeof glpi_toast_error === 'function') {
                glpi_toast_error('Failed to send test message: ' + errMsg + '\n\nCheck browser console (F12) for details.');
            } else {
                alert('Failed to send test message: ' + errMsg);
            }
        }).finally(function() {
            btn.disabled = false;
            btn.classList.remove('disabled');
        });
    });
})();
</script>
</div></div>
