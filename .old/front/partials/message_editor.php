<?php
/**
 * Поле Message с тулбаром markdown и автодополнением плейсхолдеров.
 * Ожидает $message_value (строка для textarea), опционально $message_placeholder.
 * Ожидает $rule_target (Ticket|Approve) и $macros_by_target (массив [target => [macro, ...]]) для списка макросов по цели.
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$msg_placeholder = isset($message_placeholder) ? $message_placeholder : '';
$msg_val = $message_value ?? '';
$display_value = ($msg_val === '' && $msg_placeholder !== '') ? $msg_placeholder : $msg_val;
$value_esc = htmlescape($display_value);
$macros_by_target = $macros_by_target ?? \GlpiPlugin\Mattermost\Config::RULE_MACROS_BY_TARGET;
$rule_target = $rule_target ?? 'Ticket';
$placeholders = $macros_by_target[$rule_target] ?? $macros_by_target['Ticket'] ?? [];
?>
<?php
$compass_bold = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z\"/></svg>";
$compass_italic = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z\"/></svg>";
$compass_strike = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M6.85,7.08C6.85,4.37,9.45,3,12.24,3c1.64,0,3,0.49,3.9,1.28c0.77,0.65,1.46,1.73,1.46,3.24h-3.01 c0-0.31-0.05-0.59-0.15-0.85c-0.29-0.86-1.2-1.28-2.25-1.28c-1.86,0-2.34,1.02-2.34,1.7c0,0.48,0.25,0.88,0.74,1.21 C10.97,8.55,11.36,8.78,12,9H7.39C7.18,8.66,6.85,8.11,6.85,7.08z M21,12v-2H3v2h9.62c1.15,0.45,1.96,0.75,1.96,1.97 c0,1-0.81,1.67-2.28,1.67c-1.54,0-2.93-0.54-2.93-2.51H6.4c0,0.55,0.08,1.13,0.24,1.58c0.81,2.29,3.29,3.3,5.67,3.3 c2.27,0,5.3-0.89,5.3-4.05c0-0.3-0.01-1.16-0.48-1.94H21V12z\"/></svg>";
$compass_header = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M5 4h3v16H5V4zm11 0h3v16h-3V4zm-8 7h8v3H8v-3z\"/></svg>";
$compass_code = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M14.6,16.6L19.2,12L14.6,7.4L16,6L22,12L16,18L14.6,16.6M9.4,16.6L4.8,12L9.4,7.4L8,6L2,12L8,18L9.4,16.6Z\"/></svg>";
$compass_link = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z\"/></svg>";
$compass_quote = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"18\" height=\"18\"><path fill=\"currentColor\" d=\"M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z\"/></svg>";
$resize_grip_svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"><path d=\"M11 19v-6H5M19 11h-6V5\"/></svg>";
?>
<div class="mattermost-message-autocomplete-wrap" style="position: relative; width: 100%; max-width: 486px;">
<textarea name="rule_message" id="rule_message" rows="6" style="width: 100%;" class="form-control"<?php if ($msg_placeholder !== '') { echo ' data-placeholder="' . htmlescape($msg_placeholder) . '"'; } ?>><?php echo $value_esc; ?></textarea>
<div id="rule_message_toolbar" class="mattermost-msg-toolbar" role="toolbar" aria-label="Formatting">
<button type="button" data-style="bold" title="Bold"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_bold; ?></span></button>
<button type="button" data-style="italic" title="Italic"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_italic; ?></span></button>
<button type="button" data-style="strike" title="Strikethrough"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_strike; ?></span></button>
<button type="button" data-style="heading" title="Heading"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_header; ?></span></button>
<span class="mattermost-msg-toolbar-sep" aria-hidden="true"></span>
<button type="button" data-style="link" title="Link"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_link; ?></span></button>
<button type="button" data-style="code" title="Code"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_code; ?></span></button>
<button type="button" data-style="quote" title="Quote"><span class="mattermost-msg-icon" aria-hidden="true"><?php echo $compass_quote; ?></span></button>
<span id="rule_message_resize_grip" class="mattermost-msg-resize-grip" role="button" tabindex="-1" title="Resize" aria-label="Resize"><?php echo $resize_grip_svg; ?></span>
</div>
<div id="rule_message_autocomplete">
<?php foreach ($placeholders as $p) { ?>
<div class="mattermost-msg-ac-item" data-value="{<?php echo $p; ?>}">{<?php echo $p; ?>}</div>
<?php } ?>
</div>
</div>
<?php
$wrapBold = json_encode('**');
$wrapItalic = json_encode('*');
$wrapStrike = json_encode('~~');
$wrapCode = json_encode('`');
$macros_by_target_js = json_encode($macros_by_target);
$rule_target_js = json_encode($rule_target);
?>
<script>
(function() {
    var ta = document.getElementById('rule_message');
    var list = document.getElementById('rule_message_autocomplete');
    var toolbar = document.getElementById('rule_message_toolbar');
    if (!ta || !list) return;
    var macrosByTarget = <?php echo $macros_by_target_js; ?>;
    var currentTarget = <?php echo $rule_target_js; ?>;
    function applyStyle(openTag, closeTag) {
        var start = ta.selectionStart, end = ta.selectionEnd;
        var val = ta.value;
        var sel = val.substring(start, end);
        var before = val.substring(0, start);
        var after = val.substring(end);
        ta.value = before + openTag + sel + closeTag + after;
        ta.selectionStart = ta.selectionEnd = end + openTag.length + closeTag.length;
        ta.focus();
    }
    function applyLink() {
        var start = ta.selectionStart, end = ta.selectionEnd;
        var val = ta.value;
        var sel = val.substring(start, end);
        var before = val.substring(0, start);
        var after = val.substring(end);
        var insert = sel ? '[' + sel + '](url)' : '[]()';
        ta.value = before + insert + after;
        if (sel) {
            ta.selectionStart = ta.selectionEnd = start + 1 + sel.length + 2;
        } else {
            ta.selectionStart = ta.selectionEnd = start + 1;
        }
        ta.focus();
    }
    function getLineStart(val, pos) { var i = val.lastIndexOf("\n", pos - 1); return i === -1 ? 0 : i + 1; }
    function getLineEnd(val, pos) { var i = val.indexOf("\n", pos); return i === -1 ? val.length : i; }
    function applyHeading() {
        var val = ta.value, start = ta.selectionStart;
        var lineStart = getLineStart(val, start);
        var insert = "### ";
        ta.value = val.slice(0, lineStart) + insert + val.slice(lineStart);
        ta.selectionStart = ta.selectionEnd = lineStart + insert.length;
        ta.focus();
    }
    function applyQuote() {
        var val = ta.value, start = ta.selectionStart, end = ta.selectionEnd;
        var lineStart = getLineStart(val, start);
        var lineEnd = getLineEnd(val, end);
        var lines = val.slice(lineStart, lineEnd).split("\n");
        var newLines = lines.map(function(l) { return "> " + l; }).join("\n");
        ta.value = val.slice(0, lineStart) + newLines + val.slice(lineEnd);
        var cursorPos = lineStart + newLines.length;
        ta.selectionStart = ta.selectionEnd = cursorPos;
        ta.focus();
    }
    if (toolbar) {
        toolbar.querySelectorAll('button[data-style]').forEach(function(btn) {
            btn.addEventListener('mousedown', function(e) {
                e.preventDefault();
                ta.focus();
                var style = btn.getAttribute('data-style');
                if (style === 'bold') applyStyle(<?php echo $wrapBold; ?>, <?php echo $wrapBold; ?>);
                else if (style === 'italic') applyStyle(<?php echo $wrapItalic; ?>, <?php echo $wrapItalic; ?>);
                else if (style === 'strike') applyStyle(<?php echo $wrapStrike; ?>, <?php echo $wrapStrike; ?>);
                else if (style === 'heading') applyHeading();
                else if (style === 'code') applyStyle(<?php echo $wrapCode; ?>, <?php echo $wrapCode; ?>);
                else if (style === 'link') applyLink();
                else if (style === 'quote') applyQuote();
            });
        });
    }
    var grip = document.getElementById('rule_message_resize_grip');
    if (grip) {
        var minH = 80, maxH = 400;
        grip.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var startY = e.clientY;
            var startHeight = ta.offsetHeight;
            function onMove(ev) {
                var dy = ev.clientY - startY;
                var newH = Math.min(maxH, Math.max(minH, startHeight + dy));
                ta.style.height = newH + 'px';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }
    function getAllSuggestions() {
        var macros = macrosByTarget[currentTarget] || macrosByTarget['Ticket'] || [];
        return macros.map(function(m) { return '{' + m + '}'; });
    }
    var allSuggestions = getAllSuggestions();
    var selectedIdx = 0;
    var targetSelect = document.getElementById('rule_target');
    if (targetSelect) {
        targetSelect.addEventListener('change', function() {
            currentTarget = this.value || 'Ticket';
            allSuggestions = getAllSuggestions();
            if (list.style.display !== 'none') openAutocomplete();
        });
    }
    function hide() { list.style.display = 'none'; }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function getCursorCoords() {
        var style = window.getComputedStyle(ta);
        var padL = parseInt(style.paddingLeft, 10) || 0;
        var padT = parseInt(style.paddingTop, 10) || 0;
        var lineH = parseInt(style.lineHeight, 10) || 20;
        var textBefore = ta.value.substring(0, ta.selectionStart);
        var mirror = document.createElement('div');
        mirror.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:pre-wrap;word-wrap:break-word;padding:' + style.padding + ';font:' + style.font + ';line-height:' + style.lineHeight + ';width:' + (ta.clientWidth - padL - (parseInt(style.paddingRight,10)||0)) + 'px;box-sizing:border-box;';
        mirror.innerHTML = escapeHtml(textBefore) + '<span id="ac-caret-msg"></span>';
        document.body.appendChild(mirror);
        var caret = mirror.querySelector('#ac-caret-msg');
        var x = caret.offsetLeft;
        var y = caret.offsetTop;
        document.body.removeChild(mirror);
        var rect = ta.getBoundingClientRect();
        return { left: rect.left + padL + x - ta.scrollLeft, top: rect.top + padT + y + lineH - ta.scrollTop + 2 };
    }
    function show() { var c = getCursorCoords(); list.style.left = c.left + 'px'; list.style.top = c.top + 'px'; list.style.display = 'block'; }
    function filterSuggestions(prefix) {
        prefix = (prefix || '').toUpperCase();
        var filtered = allSuggestions.filter(function(s) { return s.toUpperCase().indexOf(prefix) === 0; });
        return filtered.length ? filtered : allSuggestions;
    }
    function renderList(filtered) {
        list.innerHTML = '';
        filtered.forEach(function(val) {
            var div = document.createElement('div');
            div.className = 'mattermost-msg-ac-item';
            div.setAttribute('data-value', val);
            div.textContent = val;
            list.appendChild(div);
        });
    }
    function getPrefix() {
        var val = ta.value;
        var pos = ta.selectionStart;
        var start = val.lastIndexOf('{', pos - 1);
        if (start === -1) return null;
        return val.substring(start, pos);
    }
    function insertSuggestion(suggestion) {
        var val = ta.value;
        var pos = ta.selectionStart;
        var start = val.lastIndexOf('{', pos - 1);
        if (start === -1) return;
        var before = val.substring(0, start);
        var after = val.substring(pos);
        ta.value = before + suggestion + after;
        ta.selectionStart = ta.selectionEnd = before.length + suggestion.length;
        ta.focus();
    }
    function openAutocomplete() {
        var prefix = getPrefix();
        if (prefix === null) { hide(); return; }
        var filtered = filterSuggestions(prefix);
        renderList(filtered);
        selectedIdx = 0;
        list.querySelectorAll('.mattermost-msg-ac-item').forEach(function(el, i) {
            el.classList.toggle('active', i === 0);
            el.addEventListener('click', function() { insertSuggestion(el.getAttribute('data-value')); hide(); });
        });
        show();
    }
    ta.addEventListener('input', function() {
        if (ta.value.charAt(ta.selectionStart - 1) === '{' || (getPrefix() !== null && list.style.display !== 'none')) openAutocomplete();
        else hide();
    });
    function scrollActiveIntoView() {
        var active = list.querySelector('.mattermost-msg-ac-item.active');
        if (active) active.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }
    ta.addEventListener('keydown', function(e) {
        if (list.style.display === 'none') return;
        var visible = list.querySelectorAll('.mattermost-msg-ac-item');
        if (e.key === 'Escape') { e.preventDefault(); hide(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); selectedIdx = (selectedIdx + 1) % visible.length; visible.forEach(function(el, i) { el.classList.toggle('active', i === selectedIdx); }); scrollActiveIntoView(); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); selectedIdx = (selectedIdx - 1 + visible.length) % visible.length; visible.forEach(function(el, i) { el.classList.toggle('active', i === selectedIdx); }); scrollActiveIntoView(); return; }
        if (e.key === 'Enter' || e.key === 'Tab') { if (visible.length) { e.preventDefault(); insertSuggestion(visible[selectedIdx].getAttribute('data-value')); hide(); } }
    });
    document.addEventListener('click', function(e) { if (!list.contains(e.target) && e.target !== ta) hide(); });
    (function() {
        var placeholder = ta.getAttribute('data-placeholder');
        if (!placeholder) return;
        function updatePlaceholderStyle() {
            if (ta.value === placeholder) { ta.style.fontStyle = 'italic'; ta.style.fontSize = '0.9em'; ta.style.color = '#6c757d'; }
            else { ta.style.fontStyle = ''; ta.style.fontSize = ''; ta.style.color = ''; }
        }
        ta.addEventListener('focus', function() { if (ta.value === placeholder) { ta.value = ''; updatePlaceholderStyle(); } });
        ta.addEventListener('blur', function() { if (ta.value.trim() === '') { ta.value = placeholder; updatePlaceholderStyle(); } });
        ta.addEventListener('input', updatePlaceholderStyle);
        updatePlaceholderStyle();
    })();
})();
</script>
