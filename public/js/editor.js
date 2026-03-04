/**
 * Rule Editor tab — placeholder styling, field validation, save-button guard, raw payload toggle, macro autocomplete.
 */
window.mjlEditorInit = function () {
    'use strict';

    var root = document.querySelector('.mjl-editor-root');
    if (!root) return;

    var defaultRuleName   = root.dataset.defaultRuleName   || '';
    var defaultRecipient  = root.dataset.defaultRecipient  || '';
    var messagePlaceholder = root.dataset.messagePlaceholder || '';
    var macrosByTarget = {};
    var macrosExtraByEvent = {};
    var recipientOptionsByTarget = {};
    var recipientOptionsExtraByEvent = {};
    try { macrosByTarget = JSON.parse(root.dataset.macrosByTarget || '{}'); } catch (e) {}
    try { macrosExtraByEvent = JSON.parse(root.dataset.macrosExtraByEvent || '{}'); } catch (e) {}
    try { recipientOptionsByTarget = JSON.parse(root.dataset.recipientOptionsByTarget || '{}'); } catch (e) {}
    try { recipientOptionsExtraByEvent = JSON.parse(root.dataset.recipientOptionsExtraByEvent || '{}'); } catch (e) {}

    var ruleNameInput   = document.getElementById('mjl_rule_name');
    var recipientInput  = document.getElementById('mjl_rule_recipient');
    var messageInput    = document.getElementById('mjl_rule_message');
    var rawPayloadSel   = document.getElementById('mjl_use_raw_payload');
    var rawPayloadRow   = document.getElementById('mjl_raw_payload_row');
    var rawPayloadTa    = document.getElementById('mjl_raw_payload');
    var saveBtn         = document.getElementById('mjl_editor_save');
    var form            = document.getElementById('mjl_editor_form');

    /* ── Placeholder styling (pale italic → disappears on focus) ── */
    function bindPlaceholder(el) {
        if (!el) return;
        var ph = el.getAttribute('data-placeholder');
        if (!ph) return;
        var isTextarea = el.tagName === 'TEXTAREA';

        function applyStyle() {
            var val = isTextarea ? el.value : el.value;
            if (val === ph) {
                el.style.fontStyle = 'italic';
                el.style.fontSize  = '0.9em';
                el.style.color     = '#6c757d';
            } else {
                el.style.fontStyle = '';
                el.style.fontSize  = '';
                el.style.color     = '';
            }
        }
        el.addEventListener('focus', function () {
            if (el.value === ph) { el.value = ''; applyStyle(); }
        });
        el.addEventListener('blur', function () {
            if (el.value.trim() === '') { el.value = ph; }
            applyStyle();
        });
        el.addEventListener('input', applyStyle);
        applyStyle();
    }

    bindPlaceholder(ruleNameInput);
    bindPlaceholder(recipientInput);
    bindPlaceholder(messageInput);

    /* ── Validators ── */
    function validateRuleName() {
        if (!ruleNameInput) return;
        var v = ruleNameInput.value.trim();
        ruleNameInput.classList.toggle('mjl-field-invalid', v === '' || v === defaultRuleName);
    }

    function validateRecipient() {
        if (!recipientInput) return;
        var v = recipientInput.value.trim();
        if (v === '' || v === defaultRecipient) {
            recipientInput.classList.add('mjl-field-invalid');
            return;
        }
        var parts = v.split(',').map(function (r) { return r.trim(); });
        var bad = parts.some(function (r) { return r === '' || /\s/.test(r); });
        recipientInput.classList.toggle('mjl-field-invalid', bad);
    }

    function validateMessage() {
        if (!messageInput) return;
        var v = messageInput.value.trim();
        var wrap = messageInput.closest('.mjl-message-editor-wrap');
        if (wrap) wrap.classList.toggle('mjl-field-invalid', v === '' || v === messagePlaceholder);
    }

    function validateRawPayload() {
        if (!rawPayloadTa) return;
        var v = rawPayloadTa.value.trim();
        rawPayloadTa.classList.remove('mjl-raw-valid', 'mjl-raw-invalid');
        if (v === '') return;
        try { JSON.parse(v); rawPayloadTa.classList.add('mjl-raw-valid'); }
        catch (e) { rawPayloadTa.classList.add('mjl-raw-invalid'); }
    }

    /* ── Overall form validity ── */
    function isFormValid() {
        if (!ruleNameInput || !recipientInput || !messageInput) return false;

        var nameVal = ruleNameInput.value.trim();
        if (nameVal === '' || nameVal === defaultRuleName) return false;

        var recVal = recipientInput.value.trim();
        if (recVal === '' || recVal === defaultRecipient) return false;
        var parts = recVal.split(',').map(function (r) { return r.trim(); });
        if (parts.some(function (r) { return r === '' || /\s/.test(r); })) return false;

        var msgVal = messageInput.value.trim();
        if (msgVal === '' || msgVal === messagePlaceholder) return false;

        if (rawPayloadSel && rawPayloadSel.value === '1' && rawPayloadTa) {
            var raw = rawPayloadTa.value.trim();
            if (raw !== '') {
                try { JSON.parse(raw); } catch (e) { return false; }
            }
        }
        return true;
    }

    function updateSaveButton() {
        if (!saveBtn) return;
        var valid = isFormValid();
        saveBtn.disabled = !valid;
        saveBtn.classList.toggle('disabled', !valid);
    }

    /* ── Bind events ── */
    [ruleNameInput, recipientInput, messageInput].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', function () {
            if (el === ruleNameInput) validateRuleName();
            else if (el === recipientInput) validateRecipient();
            else if (el === messageInput) validateMessage();
            updateSaveButton();
        });
        el.addEventListener('blur', function () {
            if (el === ruleNameInput) validateRuleName();
            else if (el === recipientInput) validateRecipient();
            else if (el === messageInput) validateMessage();
            updateSaveButton();
        });
    });

    /* ── Raw payload toggle ── */
    if (rawPayloadSel && rawPayloadRow) {
        rawPayloadSel.addEventListener('change', function () {
            rawPayloadRow.style.display = rawPayloadSel.value === '1' ? '' : 'none';
            validateRawPayload();
            updateSaveButton();
        });
    }
    if (rawPayloadTa) {
        rawPayloadTa.addEventListener('input', function () { validateRawPayload(); updateSaveButton(); });
        rawPayloadTa.addEventListener('blur',  function () { validateRawPayload(); updateSaveButton(); });
    }

    /* ── Form submit guard ── */
    if (form) {
        form.addEventListener('submit', function (e) {
            // Clear placeholder values before submitting
            [ruleNameInput, recipientInput, messageInput].forEach(function (el) {
                if (!el) return;
                var ph = el.getAttribute('data-placeholder');
                if (ph && el.value === ph) el.value = '';
            });

            if (!isFormValid()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                updateSaveButton();
                alert('Please fill in all required fields correctly before saving.');
                return false;
            }
        }, true);
    }

    /* ── Cancel button navigates to Rules List ── */
    var rulesListUrl = root.dataset.rulesListUrl;
    document.querySelectorAll('.mjl-cancel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (rulesListUrl) {
                window.location.href = rulesListUrl;
            }
        });
    });

    /* ── Tooltips init ── */
    document.querySelectorAll('.form-help[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    /* ── Message toolbar (formatting) ── */
    var msgToolbar = document.getElementById('mjl_message_toolbar');
    var resizeGrip = document.getElementById('mjl_message_resize_grip');
    if (messageInput && msgToolbar) {
        function applyWrap(openTag, closeTag) {
            var start = messageInput.selectionStart, end = messageInput.selectionEnd;
            var val = messageInput.value;
            var sel = val.substring(start, end);
            var before = val.substring(0, start);
            var after = val.substring(end);
            messageInput.value = before + openTag + sel + closeTag + after;
            messageInput.selectionStart = messageInput.selectionEnd = end + openTag.length + closeTag.length;
            messageInput.focus();
        }
        function getLineStart(val, pos) { var i = val.lastIndexOf('\n', pos - 1); return i === -1 ? 0 : i + 1; }
        function getLineEnd(val, pos) { var i = val.indexOf('\n', pos); return i === -1 ? val.length : i; }
        function applyLink() {
            var start = messageInput.selectionStart, end = messageInput.selectionEnd;
            var val = messageInput.value;
            var sel = val.substring(start, end);
            var before = val.substring(0, start);
            var after = val.substring(end);
            var insert = sel ? '[' + sel + '](url)' : '[]()';
            messageInput.value = before + insert + after;
            messageInput.selectionStart = messageInput.selectionEnd = sel ? start + 1 + sel.length + 2 : start + 1;
            messageInput.focus();
        }
        function applyHeading() {
            var val = messageInput.value, start = messageInput.selectionStart;
            var lineStart = getLineStart(val, start);
            var insert = '### ';
            messageInput.value = val.slice(0, lineStart) + insert + val.slice(lineStart);
            messageInput.selectionStart = messageInput.selectionEnd = lineStart + insert.length;
            messageInput.focus();
        }
        function applyQuote() {
            var val = messageInput.value, start = messageInput.selectionStart, end = messageInput.selectionEnd;
            var lineStart = getLineStart(val, start);
            var lineEnd = getLineEnd(val, end);
            var lines = val.slice(lineStart, lineEnd).split('\n');
            var newLines = lines.map(function (l) { return '> ' + l; }).join('\n');
            messageInput.value = val.slice(0, lineStart) + newLines + val.slice(lineEnd);
            messageInput.selectionStart = messageInput.selectionEnd = lineStart + newLines.length;
            messageInput.focus();
        }
        msgToolbar.querySelectorAll('button[data-style]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                messageInput.focus();
                var style = btn.getAttribute('data-style');
                if (style === 'bold') applyWrap('**', '**');
                else if (style === 'italic') applyWrap('*', '*');
                else if (style === 'strike') applyWrap('~~', '~~');
                else if (style === 'heading') applyHeading();
                else if (style === 'code') {
                    var sel = messageInput.value.substring(messageInput.selectionStart, messageInput.selectionEnd);
                    if (/\n/.test(sel)) {
                        applyWrap('```\n', '\n```');
                    } else {
                        applyWrap('`', '`');
                    }
                }
                else if (style === 'link') applyLink();
                else if (style === 'quote') applyQuote();
                validateMessage();
                updateSaveButton();
            });
        });
    }
    if (resizeGrip && messageInput) {
        resizeGrip.addEventListener('mousedown', function (e) {
            e.preventDefault();
            var startY = e.clientY;
            var startHeight = messageInput.offsetHeight;
            var minH = 80, maxH = 400;
            function onMove(ev) {
                var dy = ev.clientY - startY;
                var newH = Math.min(maxH, Math.max(minH, startHeight + dy));
                messageInput.style.height = newH + 'px';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    /* ── Macro autocomplete ({ triggers dropdown) ── */
    function initMacroAutocomplete(inputEl, listEl, getSuggestionsFn, targetSelect, eventSelect) {
        if (!inputEl || !listEl) return;
        var selectedIdx = 0;
        var currentTarget = (targetSelect && targetSelect.value) || 'Ticket';
        var currentEvent  = (eventSelect && eventSelect.value) || '';

        function getAllSuggestions() {
            return getSuggestionsFn(currentTarget, currentEvent);
        }

        if (targetSelect) {
            targetSelect.addEventListener('change', function () {
                currentTarget = this.value || 'Ticket';
                if (listEl.style.display !== 'none') openAc();
            });
        }
        if (eventSelect) {
            eventSelect.addEventListener('change', function () {
                currentEvent = this.value || '';
                if (listEl.style.display !== 'none') openAc();
            });
        }

        function hideAc() { listEl.style.display = 'none'; listEl.setAttribute('aria-hidden', 'true'); }
        function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function getCursorCoords() {
            var style = window.getComputedStyle(inputEl);
            var padL = parseInt(style.paddingLeft, 10) || 0;
            var padT = parseInt(style.paddingTop, 10) || 0;
            var lineH = parseInt(style.lineHeight, 10) || parseInt(style.fontSize, 10) || 20;
            var textBefore = inputEl.value.substring(0, inputEl.selectionStart);
            var mirror = document.createElement('div');
            mirror.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:pre-wrap;word-wrap:break-word;padding:' + style.padding + ';font:' + style.font + ';line-height:' + style.lineHeight + ';width:' + (inputEl.clientWidth - padL - (parseInt(style.paddingRight, 10) || 0)) + 'px;box-sizing:border-box;';
            mirror.innerHTML = escapeHtml(textBefore) + '<span id="mjl-ac-caret"></span>';
            document.body.appendChild(mirror);
            var caret = mirror.querySelector('#mjl-ac-caret');
            var x = caret.offsetLeft;
            var y = caret.offsetTop;
            document.body.removeChild(mirror);
            var rect = inputEl.getBoundingClientRect();
            return { left: rect.left + padL + x - (inputEl.scrollLeft || 0), top: rect.top + padT + y + lineH - (inputEl.scrollTop || 0) + 2 };
        }

        function showAc() {
            var c = getCursorCoords();
            listEl.style.left = c.left + 'px';
            listEl.style.top = c.top + 'px';
            listEl.style.display = 'block';
            listEl.setAttribute('aria-hidden', 'false');
        }

        function getPrefix() {
            var val = inputEl.value;
            var pos = inputEl.selectionStart;
            var start = val.lastIndexOf('{', pos - 1);
            if (start === -1) return null;
            // If there's a closing '}' between '{' and cursor, the brace is already closed
            if (val.substring(start + 1, pos).indexOf('}') !== -1) return null;
            return val.substring(start, pos);
        }

        function filterSuggestions(prefix) {
            prefix = (prefix || '').toUpperCase();
            var all = getAllSuggestions();
            var filtered = all.filter(function (s) { return s.toUpperCase().indexOf(prefix) === 0; });
            return filtered.length ? filtered : all;
        }

        function renderAc(filtered) {
            listEl.innerHTML = '';
            filtered.forEach(function (val) {
                var div = document.createElement('div');
                div.className = 'mjl-ac-item';
                div.setAttribute('data-value', val);
                div.textContent = val;
                listEl.appendChild(div);
            });
        }

        function insertSuggestion(suggestion) {
            var val = inputEl.value;
            var pos = inputEl.selectionStart;
            var start = val.lastIndexOf('{', pos - 1);
            if (start === -1) return;
            inputEl.value = val.substring(0, start) + suggestion + val.substring(pos);
            inputEl.selectionStart = inputEl.selectionEnd = start + suggestion.length;
            inputEl.focus();
        }

        function openAc() {
            var prefix = getPrefix();
            if (prefix === null) { hideAc(); return; }
            var filtered = filterSuggestions(prefix);
            renderAc(filtered);
            selectedIdx = 0;
            listEl.querySelectorAll('.mjl-ac-item').forEach(function (el, i) {
                el.classList.toggle('active', i === 0);
                el.addEventListener('click', function () { insertSuggestion(el.getAttribute('data-value')); hideAc(); });
            });
            showAc();
        }

        inputEl.addEventListener('input', function () {
            if (inputEl.value.charAt(inputEl.selectionStart - 1) === '{' || (getPrefix() !== null && listEl.style.display !== 'none')) openAc();
            else hideAc();
        });

        inputEl.addEventListener('keydown', function (e) {
            if (listEl.style.display === 'none') return;
            var visible = listEl.querySelectorAll('.mjl-ac-item');
            if (e.key === 'Escape') { e.preventDefault(); hideAc(); return; }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIdx = (selectedIdx + 1) % visible.length;
                visible.forEach(function (el, i) { el.classList.toggle('active', i === selectedIdx); });
                var active = listEl.querySelector('.mjl-ac-item.active');
                if (active) active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIdx = (selectedIdx - 1 + visible.length) % visible.length;
                visible.forEach(function (el, i) { el.classList.toggle('active', i === selectedIdx); });
                var active = listEl.querySelector('.mjl-ac-item.active');
                if (active) active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                if (visible.length) {
                    e.preventDefault();
                    insertSuggestion(visible[selectedIdx].getAttribute('data-value'));
                    hideAc();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (!listEl.contains(e.target) && e.target !== inputEl) hideAc();
        });
    }

    var targetSelect   = document.getElementById('mjl_rule_target');
    var eventSelect    = document.getElementById('mjl_rule_event');
    var recipientAc    = document.getElementById('mjl_recipient_autocomplete');
    var messageAc      = document.getElementById('mjl_message_autocomplete');
    var rawPayloadAc   = document.getElementById('mjl_raw_payload_autocomplete');

    initMacroAutocomplete(
        recipientInput,
        recipientAc,
        function (target, event) {
            var base  = recipientOptionsByTarget[target] || recipientOptionsByTarget['Ticket'] || {};
            var extra = recipientOptionsExtraByEvent[event] || {};
            var merged = Object.assign({}, base, extra);
            return Object.keys(merged).map(function (k) { return '{' + k + '}'; });
        },
        targetSelect,
        eventSelect
    );

    initMacroAutocomplete(
        messageInput,
        messageAc,
        function (target, event) {
            var base  = macrosByTarget[target] || macrosByTarget['Ticket'] || [];
            var extra = macrosExtraByEvent[event] || [];
            var merged = base.concat(extra.filter(function (m) { return base.indexOf(m) === -1; }));
            return merged.map(function (m) { return '{' + m + '}'; });
        },
        targetSelect,
        eventSelect
    );

    initMacroAutocomplete(
        rawPayloadTa,
        rawPayloadAc,
        function (target, event) {
            var base  = macrosByTarget[target] || macrosByTarget['Ticket'] || [];
            var extra = macrosExtraByEvent[event] || [];
            var merged = base.concat(extra.filter(function (m) { return base.indexOf(m) === -1; }));
            return merged.map(function (m) { return '{' + m + '}'; });
        },
        targetSelect,
        eventSelect
    );

    /* ── Initial state ── */
    validateRuleName();
    validateRecipient();
    validateMessage();
    validateRawPayload();
    setTimeout(updateSaveButton, 50);
};
