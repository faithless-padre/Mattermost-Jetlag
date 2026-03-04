<?php
/**
 * Вкладка Editor: форма редактирования правила (Extended Filter + форма + Raw Payload).
 * Ожидает $config (GlpiPlugin\Mattermost\Config), $rule (NotificationRule), $rule_id (int).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
if (!isset($rule) || !isset($rule_id)) {
    return;
}
$tab_editor = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_RULE_EDITOR;
$tab_rules = \GlpiPlugin\Mattermost\Config::getType() . '$' . \GlpiPlugin\Mattermost\Config::TAB_ALL_RULES;
$form_url = isset($form_url) ? $form_url : (\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_editor));
$list_url = isset($list_url) ? $list_url : (\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1&_glpi_tab=' . urlencode($tab_rules));
$message_value = $rule->getField('message') ?? '';
$default_rule_name = '[Mattermost] -> Send notifications to all';
$default_recipient = '@el.padre, general, {requester}';
$message_placeholder = 'Enter the message text to send to Mattermost. You can use templates, start typing { to see the list of available templates for this notification type. You can use Markdown manually or via the corresponding panel.';
$rule_name_val = trim($rule->getField('name') ?? '');
$rule_recipient_val = trim($rule->getField('recipient') ?? '');
$rule_name_display = $rule_name_val !== '' ? $rule_name_val : $default_rule_name;
$rule_recipient_val = is_string($rule_recipient_val) ? $rule_recipient_val : '';
?>
<!-- 1. Extended Filter -->
<?php
// Проверяем, есть ли сохраненный фильтр для этого правила
$cf = new \Glpi\Search\CriteriaFilter();
$saved_filters = $cf->find(['itemtype' => \GlpiPlugin\Mattermost\NotificationRule::class, 'items_id' => (int) $rule_id]);
$has_filter = !empty($saved_filters);
?>
<div class="card border-0 shadow-none p-0 m-0 mt-2">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-adjustments-horizontal"></i></div>
<?php echo htmlescape('Extended Filter'); ?>
</h4>
</div>
</div>
<div class="card border">
<div class="card-body" id="mattermost-extended-filter-card" data-mattermost-rule-id="<?php echo (int) $rule_id; ?>" data-mattermost-save-filter-url="<?php echo htmlescape(\Toolbox::getItemTypeFormURL(\GlpiPlugin\Mattermost\Config::getType()) . '?id=1'); ?>">
<?php \Glpi\Search\CriteriaFilter::displayTabContentForItem($rule, 1, 0); ?>
</div></div>
<script>
(function() {
    var card = document.getElementById('mattermost-extended-filter-card');
    if (!card) return;
    var ruleId = card.getAttribute('data-mattermost-rule-id');
    var saveFilterUrl = card.getAttribute('data-mattermost-save-filter-url');
    if (!ruleId || !saveFilterUrl) return;
    var saveBtn = card.querySelector('button[name="save_filters"]');
    if (!saveBtn) return;
    var form = saveBtn.closest('form');
    if (!form) return;
    // Unbind core handler and use our plugin endpoint so save works (core returns 400 for plugin itemtype)
    $(saveBtn).off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var btn = $(e.currentTarget);
        btn.find('i').addClass('d-none');
        btn.find('.spinner-border').removeClass('d-none');
        btn.prop('disabled', true);
        btn.siblings('button').prop('disabled', true);
        var formData = new FormData(form);
        formData.set('mattermost_ajax', 'save_rule_filter');
        formData.set('item_itemtype', '<?php echo htmlescape(\GlpiPlugin\Mattermost\NotificationRule::class); ?>');
        formData.set('item_items_id', ruleId);
        formData.set('action', 'save_filter');
        $.ajax({
            type: 'POST',
            url: saveFilterUrl,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(data) {
            if (data && data.ok) {
                if (typeof glpi_toast_info === 'function') glpi_toast_info('<?php echo htmlescape(__("Filter saved")); ?>');
                $(card).find('button[name="delete_filters"]').removeClass('d-none');
            }
            $('#criteria_filter_preview').addClass('d-none');
        }).fail(function(xhr) {
            var msg = '<?php echo htmlescape(__("Unable to save filter")); ?>';
            if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
            if (typeof glpi_toast_error === 'function') glpi_toast_error(msg); else alert(msg);
        }).always(function() {
            btn.find('i').removeClass('d-none');
            btn.find('.spinner-border').addClass('d-none');
            btn.prop('disabled', false);
            btn.siblings('button').prop('disabled', false);
        });
    });
    // Delete filter: same as save — use plugin endpoint so core does not return 400
    var deleteBtn = card.querySelector('button[name="delete_filters"]');
    if (deleteBtn) {
        $(deleteBtn).off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var btn = $(e.currentTarget);
            btn.find('i').addClass('d-none');
            btn.find('.spinner-border').removeClass('d-none');
            btn.prop('disabled', true);
            btn.siblings('button').prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: saveFilterUrl,
                data: {
                    mattermost_ajax: 'delete_rule_filter',
                    item_items_id: ruleId,
                    action: 'delete_filter'
                },
                dataType: 'json'
            }).done(function(data) {
                if (data && data.ok) location.reload();
            }).fail(function(xhr) {
                var msg = '<?php echo htmlescape(__("Failed to delete filter")); ?>';
                if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                if (typeof glpi_toast_error === 'function') glpi_toast_error(msg); else alert(msg);
            }).always(function() {
                btn.find('i').removeClass('d-none');
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
                btn.siblings('button').prop('disabled', false);
            });
        });
    }
})();
</script>
<!-- 2. Edit existing rule -->
<div class="card border-0 shadow-none p-0 m-0 mt-3">
<div class="card-header mb-3 pt-2 border-top rounded-0" style="background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);">
<h4 class="card-title ms-5 mb-0">
<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1"><i class="fs-2x ti ti-message"></i></div>
<?php echo htmlescape('Edit existing rule') . ' : ' . htmlescape($rule->getField('name') ?? ''); ?>
</h4>
</div>
</div>
<form method="post" action="<?php echo htmlescape($form_url); ?>">
<?php echo \Html::hidden('_glpi_csrf_token', ['value' => \Session::getNewCSRFToken()]); ?>
<input type="hidden" name="update_notification_rule" value="1" />
<input type="hidden" name="rule_id" value="<?php echo (int) $rule_id; ?>" />
<div class="card border">
<div class="card-body">
<div class="mattermost-form">
<?php
$rule_name_attr = ['value' => $rule_name_display, 'id' => 'rule_name', 'class' => 'form-control', 'data-placeholder' => $default_rule_name, 'autocomplete' => 'off'];
if ($rule_name_val === '') $rule_name_attr['data-is-placeholder'] = '1';
?>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Rule name'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::input('rule_name', $rule_name_attr); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Target'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_target', \GlpiPlugin\Mattermost\Config::RULE_TARGETS, ['selected' => $rule->getField('target') ?: 'Ticket', 'id' => 'rule_target', 'class' => 'form-select']); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Event Type'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_event', \GlpiPlugin\Mattermost\Config::RULE_EVENTS, ['selected' => $rule->getField('event') ?: 'New', 'class' => 'form-select']); ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label">Recipient list: <span class="mattermost-required-star">*</span></span><div class="mattermost-form-control-cell"><?php $recipient_value = $rule_recipient_val; $recipient_placeholder = $default_recipient; $rule_target = $rule->getField('target') ?: 'Ticket'; $recipient_options_by_target = \GlpiPlugin\Mattermost\Config::RECIPIENT_OPTIONS_BY_TARGET; include __DIR__ . '/../partials/recipient_editor.php'; ?></div></div>
<?php $rule_target = $rule->getField('target') ?: 'Ticket'; $macros_by_target = \GlpiPlugin\Mattermost\Config::RULE_MACROS_BY_TARGET; ?>
<div class="mattermost-form-row"><span class="mattermost-form-label">Message template: <span class="mattermost-required-star">*</span></span><div class="mattermost-form-control-cell"><?php include __DIR__ . '/../partials/message_editor.php'; ?></div></div>
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Rule status'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_active', \GlpiPlugin\Mattermost\Config::RULE_ACTIVE_OPTIONS, ['selected' => (int) $rule->getField('active'), 'class' => 'form-select']); ?></div></div>
</div>
</div></div>

<?php
$use_raw = (int) ($rule->getField('use_raw_payload') ?? 0);
$raw_row_style = $use_raw ? '' : 'display: none;';
$raw_payload_value = trim($rule->getField('raw_payload') ?? '');
if ($raw_payload_value === '') {
    $raw_payload_value = $config->getDefaultRawPayload();
}
?>
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
<div class="mattermost-form-row"><span class="mattermost-form-label"><?php echo 'Override Mattermost Payload'; ?>:</span><div class="mattermost-form-control-cell"><?php echo \Html::select('rule_use_raw_payload', [0 => 'Disabled', 1 => 'Enabled'], ['selected' => $use_raw, 'id' => 'rule_use_raw_payload', 'class' => 'form-select']); ?></div></div>
<div id="rule_raw_payload_row" class="mattermost-form-row" style="<?php echo $raw_row_style; ?>"><span class="mattermost-form-label"><?php echo 'Override Payload (JSON)'; ?>:</span><div class="mattermost-form-control-cell"><textarea name="rule_raw_payload" id="rule_raw_payload" rows="18" style="width: 100%; max-width: 486px; font-family: ui-monospace, monospace; font-size: 0.875rem;" class="form-control"><?php echo htmlescape($raw_payload_value); ?></textarea></div></div>
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
    var updateSaveButtonFromRawPayload = function() {
        var saveBtn = document.querySelector('form button[type="submit"], form input[type="submit"]');
        if (saveBtn && window.updateSaveButton) {
            window.updateSaveButton();
        }
    };
    if (ta) {
        ta.addEventListener('blur', updateSaveButtonFromRawPayload);
        ta.addEventListener('input', updateSaveButtonFromRawPayload);
    }
    if (sel) {
        sel.addEventListener('change', function() {
            setTimeout(updateSaveButtonFromRawPayload, 10);
        });
    }
})();
</script>

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
    var form = document.getElementById('rule_message') ? document.getElementById('rule_message').closest('form') : null;
    var msgPlaceholder = <?php echo json_encode($message_placeholder); ?>;
    var ta = document.getElementById('rule_message');
    if (form) {
        form.addEventListener('submit', function() {
            document.querySelectorAll('input[data-placeholder][data-is-placeholder="1"]').forEach(function(el) {
                if (el.value === el.getAttribute('data-placeholder')) el.value = '';
            });
            if (ta && msgPlaceholder && ta.value === msgPlaceholder) ta.value = '';
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

<!-- 4. Buttons -->
<div class="mt-3 mb-2 d-flex justify-content-end gap-2"><a href="<?php echo htmlescape($list_url); ?>" class="btn btn-outline-secondary">Cancel</a><?php echo \Html::submit('Save', ['class' => 'btn btn-primary']); ?></div>
<?php \Html::closeForm(); ?>
