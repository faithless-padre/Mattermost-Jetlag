/**
 * Connectivity config tab — logic/validation for this tab only.
 * Scope: .mattermostjetlag-config-connectivity
 *
 * Called from inline <script> in the Twig template (GLPI loads tabs via AJAX,
 * so DOMContentLoaded fires before the tab HTML exists).
 */
/* ── Fix page title (remove "- 1 -" inserted by GLPI) ── */
(function () {
    'use strict';
    function fixPageTitle() {
        document.title = 'GLPI - Mattermost';
    }
    fixPageTitle();
    window.addEventListener('load', function () {
        fixPageTitle();
        setTimeout(fixPageTitle, 100);
        setTimeout(fixPageTitle, 400);
    });
})();

window.mattermostjetlagConnectivityInit = function () {
    'use strict';

    var root = document.querySelector('.mattermostjetlag-config-connectivity');
    if (!root) return;

    var formAction   = root.dataset.formAction || '';
    var placeholders = JSON.parse(root.dataset.placeholders || '{}');
    var i18nStatus   = root.dataset.i18nLastStatus || 'Last connection test status';
    var i18nSuccess  = root.dataset.i18nSuccess || 'Success';
    var i18nFailed   = root.dataset.i18nFailed || 'Failed';

    /* ── Initialize Bootstrap tooltips on form-help icons ── */
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('.form-help[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }

    /* ── Connection type toggle ── */
    var sel          = document.getElementById('connection_type_select');
    var blockWebhook = document.getElementById('mattermostjetlag_block_webhook');
    var blockAuth    = document.getElementById('mattermostjetlag_block_auth');

    if (sel && blockWebhook && blockAuth) {
        var lastValidValue = sel.value;
        function toggleBlocks() {
            var opt = sel.options[sel.selectedIndex];
            if (opt && opt.disabled) {
                sel.value = lastValidValue;
                return;
            }
            lastValidValue = sel.value;
            var isWebhook = sel.value === 'webhook';
            blockWebhook.style.display = isWebhook ? '' : 'none';
            blockAuth.style.display    = isWebhook ? 'none' : '';
        }
        sel.addEventListener('change', toggleBlocks);
        toggleBlocks();
    }

    /* ── Placeholder styling helper ── */
    function bindPlaceholder(el, ph) {
        function style() {
            if (el.value === ph) {
                el.style.fontStyle = 'italic';
                el.style.fontSize  = '0.9em';
                el.style.color     = '#6c757d';
            } else {
                el.style.fontStyle = '';
                el.style.fontSize  = '';
                el.style.color     = '';
            }
        }
        el.addEventListener('focus', function () { if (el.value === ph) { el.value = ''; style(); } });
        el.addEventListener('blur',  function () { if (el.value.trim() === '') { el.value = ph; } style(); });
        el.addEventListener('input', style);
        style();
    }

    /* ── Inputs with data-placeholder attribute ── */
    document.querySelectorAll('input[data-placeholder]').forEach(function (el) {
        bindPlaceholder(el, el.getAttribute('data-placeholder'));
    });

    /* ── Test channel / message fields ── */
    ['field_test_channel', 'field_test_message'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        bindPlaceholder(el, el.value);
    });

    /* ── Clear placeholders before form submit ── */
    var form = document.getElementById('mattermostjetlag_config_form');
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('input[data-placeholder]').forEach(function (el) {
                if (el.value === el.getAttribute('data-placeholder')) {
                    el.value = '';
                }
            });
        });
    }

    /* ── Test connection button ── */
    var btn = document.querySelector('button[name="test_connection"]');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var webhookInput = document.getElementById('field_webhook_url');
        var nickInput    = document.getElementById('field_webhook_bot_nickname');
        var avatarInput  = document.getElementById('field_webhook_bot_avatar');
        var channelInput = document.getElementById('field_test_channel');
        var messageInput = document.getElementById('field_test_message');

        if (!webhookInput || !channelInput || !messageInput) return;

        function cleanVal(el, ph) {
            var v = (el ? el.value : '').trim();
            return v === ph ? '' : v;
        }

        var webhookUrl = cleanVal(webhookInput, placeholders.webhook_url);
        var channel    = cleanVal(channelInput, placeholders.test_channel);
        var msg        = cleanVal(messageInput, placeholders.test_message);
        var nickname   = cleanVal(nickInput, placeholders.bot_nickname);
        var avatar     = cleanVal(avatarInput, placeholders.bot_avatar);

        function toast(fn, text) {
            if (typeof fn === 'function') fn(text);
            else alert(text);
        }

        if (!webhookUrl) { toast(window.glpi_toast_error, 'Please specify Mattermost Webhook URL.'); return; }
        if (!channel)    { toast(window.glpi_toast_error, 'Please specify the channel name or @username.'); return; }
        if (!msg)        { toast(window.glpi_toast_error, 'Please specify the test message text.'); return; }

        var csrfInput = document.querySelector('#mattermostjetlag_config_form input[name="_glpi_csrf_token"]');
        var fd = new FormData();
        fd.append('mattermost_ajax', 'test_webhook');
        fd.append('test_webhook_url', webhookUrl);
        fd.append('test_sender_nickname', nickname);
        fd.append('test_sender_avatar', avatar);
        fd.append('test_channel', channel);
        fd.append('test_message', msg);
        if (csrfInput && csrfInput.value) fd.append('_glpi_csrf_token', csrfInput.value);

        btn.disabled = true;
        btn.classList.add('disabled');

        fetch(formAction, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (resp) {
                var ct = resp.headers.get('content-type');
                if (ct && ct.includes('application/json')) {
                    return resp.json().then(function (data) {
                        return { ok: resp.ok, status: resp.status, data: data };
                    });
                }
                return resp.text().then(function (text) {
                    return { ok: false, status: resp.status, data: null, error: 'Non-JSON response (HTTP ' + resp.status + ')', responseText: text };
                });
            })
            .then(function (result) {
                if (csrfInput && result.data && result.data.csrf_token) {
                    csrfInput.value = result.data.csrf_token;
                }

                var statusEl = document.getElementById('mattermostjetlag_last_test_status');
                var nowStr   = (new Date()).toLocaleString();

                function setStatus(success) {
                    if (!statusEl) return;
                    statusEl.className = 'mattermostjetlag-last-test me-2 ' + (success ? 'border-success text-success' : 'border-danger text-danger');
                    var dot = statusEl.querySelector('.mattermostjetlag-last-test-dot');
                    if (dot) dot.className = 'mattermostjetlag-last-test-dot ' + (success ? 'bg-success' : 'bg-danger');
                    var textEl = statusEl.querySelector('.mattermostjetlag-last-test-text');
                    if (textEl) textEl.textContent = i18nStatus + ': ' + (success ? i18nSuccess : i18nFailed) + ' \u00b7 ' + nowStr;
                }

                if (result.ok && result.data && result.data.ok) {
                    setStatus(true);
                    toast(window.glpi_toast_info, result.data.message || 'Test message sent.');
                } else {
                    setStatus(false);
                    var errMsg = (result.data && result.data.error) || result.error || 'HTTP ' + result.status;
                    console.error('Test webhook error:', result);
                    toast(window.glpi_toast_error, 'Failed: ' + errMsg);
                }
            })
            .catch(function (e) {
                console.error('Test webhook exception:', e);
                toast(window.glpi_toast_error, 'Network error: ' + (e.message || String(e)));
            })
            .finally(function () {
                btn.disabled = false;
                btn.classList.remove('disabled');
            });
    });
};
