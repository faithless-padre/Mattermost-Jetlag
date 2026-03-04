<?php
/**
 * Вкладка Editor: форма добавления нового правила.
 * Ожидает $config (GlpiPlugin\Mattermost\Config). URL-переменные: form_url, all_rules_url.
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
$tab_editor = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_RULE_EDITOR;
$tab_rules = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_ALL_RULES;
$form_url = isset($form_url) ? $form_url : (\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_editor));
$all_rules_url = isset($all_rules_url) ? $all_rules_url : (\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rules));
$message_value = isset($message_value) ? $message_value : '';
$default_rule_name = '[Mattermost] -> Send notifications to all';
$default_recipient = '@el.padre, general, {requester}';
$message_placeholder = 'Enter the message text to send to Mattermost. You can use templates, start typing { to see the list of available templates for this notification type. You can use Markdown manually or via the corresponding panel.';
?>
<!-- 1. Extended Filter hint -->
<div class="card border-0 shadow-none p-0 m-0 mt-2">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-adjustments-horizontal"></i></div>
<?php echo htmlescape('Extended Filter'); ?>
</h4>
</div>
</div>
<div class="card border">
<div class="card-body">
<p class="text-muted mb-0"><?php echo htmlescape('Save the rule below first. You will then be able to set an extended filter for this rule in the editor.'); ?></p>
</div></div>

<!-- 2. Add new rule details -->
<div class="card border-0 shadow-none p-0 m-0 mt-3">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-message"></i></div>
<?php echo htmlescape('Add new rule details'); ?>
</h4>
</div>
</div>
<form method="post" action="<?php echo htmlescape($form_url); ?>">
<?php echo \Html::hidden('_glpi_csrf_token', ['value' => \Session::getNewCSRFToken()]); ?>
<input type="hidden" name="add_notification_rule" value="1" />
<div class="card border">
<div class="card-body">
<div class="mattermost-form">
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Rule name'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::input('rule_name', ['value' => $default_rule_name, 'id' => 'rule_name', 'class' => 'form-control', 'data-placeholder' => $default_rule_name, 'autocomplete' => 'off']); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Target'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_target', \GlpiPlugin\Mattermost\Config::RULE_TARGETS, ['selected' => 'Ticket', 'id' => 'rule_target', 'class' => 'form-select']); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Event Type'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_event', \GlpiPlugin\Mattermost\Config::RULE_EVENTS, ['selected' => 'New', 'class' => 'form-select']); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label">Recipient list: <span class="mattermost-required-star">*</span></span><div class="mattermost-form-control-cell"><?php $recipient_value = ''; $recipient_placeholder = $default_recipient; $rule_target = 'Ticket'; $recipient_options_by_target = \GlpiPlugin\Mattermost\Config::RECIPIENT_OPTIONS_BY_TARGET; include __DIR__ . '/../partials/recipient_editor.php'; ?></div></div>
<?php $rule_target = 'Ticket'; $macros_by_target = \GlpiPlugin\Mattermost\Config::RULE_MACROS_BY_TARGET; ?>
<div class="mattermost-form-row"><span class="mattermost-form-label">Message template: <span class="mattermost-required-star">*</span></span><div class="mattermost-form-control-cell"><?php include __DIR__ . '/../partials/message_editor.php'; ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Rule status'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_active', \GlpiPlugin\Mattermost\Config::RULE_ACTIVE_OPTIONS, ['selected' => 1, 'class' => 'form-select']); ?></div></div>
</div>
</div></div>

<!-- 3. Override Mattermost Payload -->
<div class="card border-0 shadow-none p-0 m-0 mt-3">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-code"></i></div>
<?php echo htmlescape('Override Mattermost Payload'); ?>
</h4>
</div>
</div>
<div class="card border">
<div class="card-body">
<div class="mattermost-form">
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Override Mattermost Payload'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_use_raw_payload', [0 => 'Disabled', 1 => 'Enabled'], ['selected' => 0, 'id' => 'rule_use_raw_payload', 'class' => 'form-select']); ?></div></div>
<div id="rule_raw_payload_row" class="mattermost-form-row" style="display: none;"><span class="mattermost-form-label"><?php echo 'Override Payload (JSON)'; ?>:</span><div class="mattermost-form-control-cell"><textarea name="rule_raw_payload" id="rule_raw_payload" rows="18" style="width: 100%; max-width: 486px; font-family: ui-monospace, monospace; font-size: 0.875rem;" class="form-control"><?php echo htmlescape($config->getDefaultRawPayload()); ?></textarea></div></div>
</div>
<div class="alert alert-success mb-0 mt-3 d-flex align-items-center">
<i class="ti ti-book flex-shrink-0 me-2" style="font-size: 1.25rem;" aria-hidden="true"></i>
<span><?php echo htmlescape('To clarify the Mattermost Payload structure, refer to the developer documentation:'); ?> <a href="https://developers.mattermost.com/api-documentation/#/schemas/Post" target="_blank" rel="noopener noreferrer">https://developers.mattermost.com/api-documentation/#/schemas/Post</a></span>
</div>
</div></div>
<script>
(function(){
    var sel = document.getElementById('rule_use_raw_payload');
    var row = document.getElementById('rule_raw_payload_row');
    var ta = document.getElementById('rule_raw_payload');
    if (!sel || !row) return;
    function toggle(){ row.style.display = sel.value === '1' ? '' : 'none'; }
    sel.addEventListener('change', toggle);
    toggle();
    function validateRawPayload() {
        if (!ta) return;
        var val = ta.value.trim();
        ta.classList.remove('mattermost-raw-valid', 'mattermost-raw-invalid');
        if (val === '') return;
        try {
            JSON.parse(val);
            ta.classList.add('mattermost-raw-valid');
        } catch (e) {
            ta.classList.add('mattermost-raw-invalid');
        }
    }
    if (ta) {
        ta.addEventListener('blur', validateRawPayload);
        ta.addEventListener('input', validateRawPayload);
        validateRawPayload();
    }
})();
</script>

<!-- 4. Buttons -->
<div class="mt-3 mb-2 d-flex justify-content-end gap-2"><a href="<?php echo htmlescape($all_rules_url); ?>" class="btn btn-outline-secondary">Cancel</a><?php echo \Html::submit('Save', ['class' => 'btn btn-primary']); ?></div>
<?php \Html::closeForm(); ?>
<script>
(function() {
    document.querySelectorAll('input[data-placeholder]').forEach(function(el) {
        var placeholder = el.getAttribute('data-placeholder');
        if (!placeholder) return;
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
    var ta = document.getElementById('rule_message');
    var msgPlaceholder = <?php echo json_encode($message_placeholder); ?>;
    if (ta && msgPlaceholder) {
        var form = ta.closest('form');
        if (form) form.addEventListener('submit', function() {
            if (ta.value === msgPlaceholder) ta.value = '';
        });
    }
})();
(function() {
    var defaultRuleName = <?php echo json_encode($default_rule_name); ?>;
    var defaultRecipient = <?php echo json_encode($default_recipient); ?>;
    var messagePlaceholder = <?php echo json_encode($message_placeholder); ?>;
    var ruleNameInput = document.getElementById('rule_name');
    var recipientInput = document.getElementById('rule_recipient');
    var messageInput = document.getElementById('rule_message');
    function validateRuleName() {
        if (!ruleNameInput) return;
        var value = ruleNameInput.value.trim();
        ruleNameInput.classList.remove('mattermost-field-invalid');
        if (value === '' || value === defaultRuleName) {
            ruleNameInput.classList.add('mattermost-field-invalid');
        }
    }
    function validateRecipient() {
        if (!recipientInput) return;
        var value = recipientInput.value.trim();
        recipientInput.classList.remove('mattermost-field-invalid');
        if (value === '' || value === defaultRecipient) {
            recipientInput.classList.add('mattermost-field-invalid');
            return;
        }
        var recipients = value.split(',').map(function(r) { return r.trim(); });
        var hasInvalid = recipients.some(function(r) {
            return r === '' || /\s/.test(r);
        });
        if (hasInvalid) {
            recipientInput.classList.add('mattermost-field-invalid');
        }
    }
    function validateMessage() {
        if (!messageInput) return;
        var wrap = messageInput.closest('.mattermost-message-autocomplete-wrap');
        var value = messageInput.value.trim();
        if (wrap) wrap.classList.remove('mattermost-field-invalid');
        messageInput.classList.remove('mattermost-field-invalid');
        if (value === '' || value === messagePlaceholder) {
            if (wrap) wrap.classList.add('mattermost-field-invalid');
            messageInput.classList.add('mattermost-field-invalid');
        }
    }
    var rawPayloadSelect = document.getElementById('rule_use_raw_payload');
    var rawPayloadTextarea = document.getElementById('rule_raw_payload');
    var form = ruleNameInput ? ruleNameInput.closest('form') : document.querySelector('form[method="post"]');
    var saveButton = null;
    function findSaveButton() {
        if (!form) return null;
        return form.querySelector('button[type="submit"]') || form.querySelector('input[type="submit"]') || document.querySelector('button.btn-primary[type="submit"]');
    }
    saveButton = findSaveButton();
    function isFormValid() {
        if (!ruleNameInput || !recipientInput || !messageInput) return false;
        var ruleNameValue = ruleNameInput.value.trim();
        var ruleNameValid = ruleNameValue !== '' && ruleNameValue !== defaultRuleName;
        if (!ruleNameValid) return false;
        var recipientValue = recipientInput.value.trim();
        var recipientValid = false;
        if (recipientValue !== '' && recipientValue !== defaultRecipient) {
            var recipients = recipientValue.split(',').map(function(r) { return r.trim(); });
            recipientValid = recipients.length > 0 && recipients.every(function(r) {
                return r !== '' && !/\s/.test(r);
            });
        }
        if (!recipientValid) return false;
        var messageValue = messageInput.value.trim();
        var messageValid = messageValue !== '' && messageValue !== messagePlaceholder;
        if (!messageValid) return false;
        var rawPayloadEnabled = rawPayloadSelect && rawPayloadSelect.value === '1';
        if (rawPayloadEnabled && rawPayloadTextarea) {
            var rawValue = rawPayloadTextarea.value.trim();
            if (rawValue !== '') {
                try {
                    JSON.parse(rawValue);
                } catch (e) {
                    return false;
                }
            }
        }
        return true;
    }
    function updateSaveButton() {
        if (!saveButton) {
            saveButton = findSaveButton();
        }
        if (!saveButton) {
            console.warn('Save button not found');
            return;
        }
        var isValid = isFormValid();
        if (isValid) {
            saveButton.removeAttribute('disabled');
            saveButton.disabled = false;
        } else {
            saveButton.setAttribute('disabled', 'disabled');
            saveButton.disabled = true;
        }
        if (saveButton.classList) {
            saveButton.classList.toggle('disabled', !isValid);
        }
    }
    window.updateSaveButton = updateSaveButton;
    if (ruleNameInput) {
        ruleNameInput.addEventListener('blur', function() { validateRuleName(); updateSaveButton(); });
        ruleNameInput.addEventListener('input', function() { validateRuleName(); updateSaveButton(); });
        validateRuleName();
    }
    if (recipientInput) {
        recipientInput.addEventListener('blur', function() { validateRecipient(); updateSaveButton(); });
        recipientInput.addEventListener('input', function() { validateRecipient(); updateSaveButton(); });
        validateRecipient();
    }
    if (messageInput) {
        messageInput.addEventListener('blur', function() { validateMessage(); updateSaveButton(); });
        messageInput.addEventListener('input', function() { validateMessage(); updateSaveButton(); });
        validateMessage();
    }
    if (rawPayloadSelect) {
        rawPayloadSelect.addEventListener('change', function() {
            setTimeout(function() {
                if (rawPayloadTextarea) {
                    var validateRawPayload = function() {
                        if (!rawPayloadTextarea) return;
                        var val = rawPayloadTextarea.value.trim();
                        rawPayloadTextarea.classList.remove('mattermost-raw-valid', 'mattermost-raw-invalid');
                        if (val === '') {
                            updateSaveButton();
                            return;
                        }
                        try {
                            JSON.parse(val);
                            rawPayloadTextarea.classList.add('mattermost-raw-valid');
                        } catch (e) {
                            rawPayloadTextarea.classList.add('mattermost-raw-invalid');
                        }
                        updateSaveButton();
                    };
                    rawPayloadTextarea.addEventListener('input', validateRawPayload);
                    rawPayloadTextarea.addEventListener('blur', validateRawPayload);
                    validateRawPayload();
                }
                updateSaveButton();
            }, 10);
        });
    }
    if (rawPayloadTextarea) {
        rawPayloadTextarea.addEventListener('input', function() {
            setTimeout(function() {
                var val = rawPayloadTextarea.value.trim();
                rawPayloadTextarea.classList.remove('mattermost-raw-valid', 'mattermost-raw-invalid');
                if (val === '') {
                    updateSaveButton();
                    return;
                }
                try {
                    JSON.parse(val);
                    rawPayloadTextarea.classList.add('mattermost-raw-valid');
                } catch (e) {
                    rawPayloadTextarea.classList.add('mattermost-raw-invalid');
                }
                updateSaveButton();
            }, 10);
        });
        rawPayloadTextarea.addEventListener('blur', function() {
            var val = rawPayloadTextarea.value.trim();
            rawPayloadTextarea.classList.remove('mattermost-raw-valid', 'mattermost-raw-invalid');
            if (val === '') {
                updateSaveButton();
                return;
            }
            try {
                JSON.parse(val);
                rawPayloadTextarea.classList.add('mattermost-raw-valid');
            } catch (e) {
                rawPayloadTextarea.classList.add('mattermost-raw-invalid');
            }
            updateSaveButton();
        });
    }
    if (form) {
        form.addEventListener('submit', function(e) {
            var isValid = isFormValid();
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                updateSaveButton();
                alert('Please fill in all required fields correctly before saving.');
                return false;
            }
        }, true);
    }
    setTimeout(function() {
        updateSaveButton();
    }, 100);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(updateSaveButton, 100);
        });
    }
})();
</script>
