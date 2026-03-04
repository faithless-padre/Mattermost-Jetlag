<?php
/**
 * Поле Recipient: ввод через запятую, при вводе { — автодополнение макросов по Target.
 * Ожидает $recipient_value, опционально $recipient_placeholder, $rule_target, $recipient_options_by_target (из Config::RECIPIENT_OPTIONS_BY_TARGET).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$recipient_value = $recipient_value ?? '';
$recipient_placeholder = $recipient_placeholder ?? '';
$rule_target = $rule_target ?? 'Ticket';
$recipient_options_by_target = $recipient_options_by_target ?? \GlpiPlugin\Mattermost\Config::RECIPIENT_OPTIONS_BY_TARGET;
$recipient_display = ($recipient_value === '' && $recipient_placeholder !== '') ? $recipient_placeholder : $recipient_value;
$recipient_esc = htmlescape($recipient_display);
?>
<div class="mattermost-recipient-autocomplete-wrap" style="position: relative; width: 100%; max-width: 486px;">
<input type="text" name="rule_recipient" id="rule_recipient" value="<?php echo $recipient_esc; ?>" class="form-control" autocomplete="off"<?php if ($recipient_placeholder !== '') { echo ' data-placeholder="' . htmlescape($recipient_placeholder) . '"' . ($recipient_value === '' ? ' data-is-placeholder="1"' : ''); } ?> />
<div id="rule_recipient_autocomplete"></div>
</div>
<script>
(function() {
    var input = document.getElementById('rule_recipient');
    var list = document.getElementById('rule_recipient_autocomplete');
    if (!input || !list) return;
    var recipientOptionsByTarget = <?php echo json_encode($recipient_options_by_target); ?>;
    var currentTarget = <?php echo json_encode($rule_target); ?>;
    function getAllSuggestions() {
        var opts = recipientOptionsByTarget[currentTarget] || recipientOptionsByTarget['Ticket'] || {};
        return Object.keys(opts).map(function(k) { return '{' + k + '}'; });
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
        var style = window.getComputedStyle(input);
        var padL = parseInt(style.paddingLeft, 10) || 0;
        var padT = parseInt(style.paddingTop, 10) || 0;
        var lineH = parseInt(style.lineHeight, 10) || parseInt(style.fontSize, 10) || 20;
        var textBefore = input.value.substring(0, input.selectionStart);
        var mirror = document.createElement('div');
        mirror.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:pre;word-wrap:normal;padding:' + style.padding + ';font:' + style.font + ';line-height:' + style.lineHeight + ';box-sizing:border-box;';
        mirror.innerHTML = escapeHtml(textBefore) + '<span id="ac-caret-rec"></span>';
        document.body.appendChild(mirror);
        var caret = mirror.querySelector('#ac-caret-rec');
        var x = caret.offsetLeft;
        var y = caret.offsetTop;
        document.body.removeChild(mirror);
        var rect = input.getBoundingClientRect();
        return { left: rect.left + padL + x - input.scrollLeft, top: rect.top + padT + y + lineH - input.scrollTop + 2 };
    }
    function show() {
        var c = getCursorCoords();
        list.style.left = c.left + 'px';
        list.style.top = c.top + 'px';
        list.style.display = 'block';
    }
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
        var val = input.value;
        var pos = input.selectionStart;
        var start = val.lastIndexOf('{', pos - 1);
        if (start === -1) return null;
        return val.substring(start, pos);
    }
    function insertSuggestion(suggestion) {
        var val = input.value;
        var pos = input.selectionStart;
        var start = val.lastIndexOf('{', pos - 1);
        if (start === -1) return;
        var before = val.substring(0, start);
        var after = val.substring(pos);
        input.value = before + suggestion + after;
        input.selectionStart = input.selectionEnd = before.length + suggestion.length;
        input.focus();
    }
    function scrollActiveIntoView() {
        var active = list.querySelector('.mattermost-msg-ac-item.active');
        if (active) active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
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
    input.addEventListener('input', function() {
        if (input.value.charAt(input.selectionStart - 1) === '{' || (getPrefix() !== null && list.style.display !== 'none')) openAutocomplete();
        else hide();
    });
    input.addEventListener('keydown', function(e) {
        if (list.style.display === 'none') return;
        var visible = list.querySelectorAll('.mattermost-msg-ac-item');
        if (e.key === 'Escape') { e.preventDefault(); hide(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); selectedIdx = (selectedIdx + 1) % visible.length; visible.forEach(function(el, i) { el.classList.toggle('active', i === selectedIdx); }); scrollActiveIntoView(); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); selectedIdx = (selectedIdx - 1 + visible.length) % visible.length; visible.forEach(function(el, i) { el.classList.toggle('active', i === selectedIdx); }); scrollActiveIntoView(); return; }
        if (e.key === 'Enter' || e.key === 'Tab') { if (visible.length) { e.preventDefault(); insertSuggestion(visible[selectedIdx].getAttribute('data-value')); hide(); } }
    });
    document.addEventListener('click', function(e) { if (!list.contains(e.target) && e.target !== input) hide(); });
    (function() {
        var placeholder = input.getAttribute('data-placeholder');
        if (!placeholder) return;
        function updatePlaceholderStyle() {
            if (input.value === placeholder) {
                input.style.fontStyle = 'italic';
                input.style.fontSize = '0.9em';
                input.style.color = '#6c757d';
            } else {
                input.style.fontStyle = '';
                input.style.fontSize = '';
                input.style.color = '';
            }
        }
        input.addEventListener('focus', function() {
            if (input.value === placeholder) {
                input.value = '';
                updatePlaceholderStyle();
            }
        });
        input.addEventListener('blur', function() {
            if (input.value.trim() === '') {
                input.value = placeholder;
                updatePlaceholderStyle();
            }
        });
        input.addEventListener('input', updatePlaceholderStyle);
        updatePlaceholderStyle();
        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                if (input.value === placeholder) {
                    input.value = '';
                }
            });
        }
    })();
})();
</script>
