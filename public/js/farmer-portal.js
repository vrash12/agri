(function () {
    'use strict';
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        var field = document.getElementById(button.dataset.passwordToggle);
        if (!field) return;
        button.hidden = false;
        button.addEventListener('click', function () {
            var reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(reveal));
        });
    });
    document.querySelectorAll('[data-portal-submit]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting === 'true' || (form.dataset.confirm && !window.confirm(form.dataset.confirm))) {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.dataset.originalLabel = button.textContent;
                button.textContent = button.dataset.submittingLabel || 'Please wait…';
            });
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-portal-submit]').forEach(function (form) {
            form.dataset.submitting = 'false';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                if (button.dataset.originalLabel) button.textContent = button.dataset.originalLabel;
            });
        });
    });
    var session = document.querySelector('[data-portal-heartbeat]');
    if (!session) return;
    var lastActive = Date.now(), lastSent = Date.now(), sending = false, ending = false;
    var timeout = Number(session.dataset.idleSeconds) * 1000;
    function active() { if (Date.now() - lastActive > 1000) lastActive = Date.now(); }
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (event) {
        window.addEventListener(event, active, { passive: true });
    });
    window.setInterval(function () {
        if (Date.now() - lastActive >= timeout) {
            if (ending) return;
            ending = true;
            var logout = document.querySelector('form[data-portal-logout]');
            document.documentElement.style.visibility = 'hidden';
            var expiry = setTimeout(function () { window.location.replace(session.dataset.loginUrl); }, 5000);
            fetch(logout.action, { method: 'POST', credentials: 'same-origin', headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'
            }}).catch(function () { /* The server independently rejects stale sessions. */ })
              .finally(function () { clearTimeout(expiry); window.location.replace(session.dataset.loginUrl); });
            return;
        }
        if (document.hidden || sending || lastActive <= lastSent || Date.now() - lastSent < 60000) return;
        sending = true;
        fetch(session.dataset.portalHeartbeat, { method: 'POST', credentials: 'same-origin', headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'
        }}).then(function (response) {
            if (response.status === 401 || response.status === 419) window.location.replace(session.dataset.loginUrl);
            if (response.ok) lastSent = Date.now();
        }).catch(function () { /* A later active heartbeat retries; server expiry remains authoritative. */ })
          .finally(function () { sending = false; });
    }, 15000);
})();
