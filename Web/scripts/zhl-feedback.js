/**
 * ZHL Medienausleihe — "Seite in Entwicklung"-Badge + Problem-Melder (Übergangszeit).
 *
 * Selbst-einbettend: injiziert einen Badge unten rechts ("Diese Seite ist in
 * Entwicklung …") und ein Melde-Fenster. Beim Absenden geht eine Mail ans
 * Medien-Team. Mitgeschickt werden automatisch: aktuelle Seite (URL + Titel)
 * und — serverseitig aus der Session ermittelt — der eingeloggte Nutzer.
 *
 * Bewusst abhängigkeitsfrei (kein jQuery/Bootstrap nötig), damit es auf den
 * In-App-Seiten (globalfooter.tpl) UND den eigenständigen ZHL-Seiten
 * (zhl-start/login/register) identisch funktioniert.
 *
 * Konfiguration via data-Attribute am <script>-Tag (optional):
 *   data-endpoint="zhl-feedback-submit.php"   Pfad zum POST-Endpoint
 */
(function () {
    'use strict';

    if (window.__zhlFeedbackLoaded) { return; }
    window.__zhlFeedbackLoaded = true;

    var script = document.currentScript;
    var ENDPOINT = (script && script.getAttribute('data-endpoint')) || 'zhl-feedback-submit.php';

    // Sprache: <html lang> oder vom ZHL-Umschalter gesetztes localStorage (zhlLang).
    function isEnglish() {
        try {
            var stored = localStorage.getItem('zhlLang');
            if (stored === 'en') { return true; }
            if (stored === 'de') { return false; }
        } catch (e) { /* localStorage evtl. blockiert */ }
        var htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
        return htmlLang.indexOf('en') === 0;
    }

    var T = {
        de: {
            badgeTitle: 'Seite in Entwicklung',
            badgeSub: 'Etwas kaputt? Hier melden',
            close: 'Schließen',
            modalTitle: 'Problem melden',
            intro: 'Diese Seite ist noch in Entwicklung. Wenn etwas nicht funktioniert, melden Sie es uns gerne — wir kümmern uns darum.',
            pageLabel: 'Aktuelle Seite',
            autoNote: 'Seite und (falls angemeldet) Ihr Benutzerkonto werden automatisch mitgeschickt.',
            msgLabel: 'Was funktioniert nicht?',
            msgPh: 'Beschreiben Sie kurz, was Sie gemacht haben und was schiefging …',
            emailLabel: 'Ihre E-Mail für Rückfragen (optional)',
            emailPh: 'name@uni-bayreuth.de',
            send: 'Absenden',
            sending: 'Wird gesendet …',
            ok: 'Vielen Dank! Ihre Meldung ist bei uns angekommen.',
            errShort: 'Bitte beschreiben Sie das Problem etwas genauer (mindestens ein Satz).',
            errSend: 'Senden fehlgeschlagen. Bitte später erneut versuchen oder eine Mail an paul.doelle@uni-bayreuth.de.'
        },
        en: {
            badgeTitle: 'Site under development',
            badgeSub: 'Something broken? Report it',
            close: 'Close',
            modalTitle: 'Report a problem',
            intro: 'This site is still under development. If something does not work, please let us know — we will take care of it.',
            pageLabel: 'Current page',
            autoNote: 'The page and (if signed in) your account are sent along automatically.',
            msgLabel: 'What is not working?',
            msgPh: 'Briefly describe what you did and what went wrong …',
            emailLabel: 'Your e-mail for follow-up questions (optional)',
            emailPh: 'name@uni-bayreuth.de',
            send: 'Send',
            sending: 'Sending …',
            ok: 'Thank you! Your report has reached us.',
            errShort: 'Please describe the problem in a little more detail (at least one sentence).',
            errSend: 'Sending failed. Please try again later or email paul.doelle@uni-bayreuth.de.'
        }
    };

    function t(key) { return (isEnglish() ? T.en : T.de)[key]; }

    var CSS =
        '.zhl-fb-badge{position:fixed;right:18px;bottom:18px;z-index:2147483000;display:flex;align-items:center;gap:10px;' +
        'max-width:280px;padding:11px 14px;border-radius:12px;cursor:pointer;border:none;text-align:left;' +
        'background:linear-gradient(135deg,#009260 0%,#00744c 100%);color:#fff;' +
        'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
        'box-shadow:0 8px 24px rgba(0,80,52,.32);transition:transform .14s ease,box-shadow .14s ease;}' +
        '.zhl-fb-badge:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(0,80,52,.42);}' +
        '.zhl-fb-badge .zhl-fb-ic{flex:none;width:24px;height:24px;}' +
        '.zhl-fb-badge .zhl-fb-tt{display:block;font-weight:700;font-size:13px;line-height:1.25;}' +
        '.zhl-fb-badge .zhl-fb-st{display:block;font-size:12px;opacity:.9;line-height:1.25;margin-top:2px;}' +
        '@media (max-width:520px){.zhl-fb-badge{max-width:none;right:12px;bottom:12px;}}' +
        '.zhl-fb-overlay{position:fixed;inset:0;z-index:2147483001;display:none;align-items:center;justify-content:center;' +
        'background:rgba(15,30,24,.55);padding:18px;}' +
        '.zhl-fb-overlay.open{display:flex;}' +
        '.zhl-fb-modal{background:#fff;color:#1f2a25;width:100%;max-width:480px;max-height:90vh;overflow:auto;border-radius:16px;' +
        'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-shadow:0 24px 60px rgba(0,0,0,.3);}' +
        '.zhl-fb-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;' +
        'background:linear-gradient(135deg,#009260 0%,#00744c 100%);color:#fff;border-radius:16px 16px 0 0;}' +
        '.zhl-fb-head h2{margin:0;font-size:18px;font-weight:650;}' +
        '.zhl-fb-x{background:rgba(255,255,255,.18);border:none;color:#fff;width:32px;height:32px;border-radius:8px;' +
        'cursor:pointer;font-size:18px;line-height:1;flex:none;}' +
        '.zhl-fb-x:hover{background:rgba(255,255,255,.32);}' +
        '.zhl-fb-body{padding:20px;}' +
        '.zhl-fb-intro{margin:0 0 16px;font-size:14.5px;color:#3f4d46;line-height:1.5;}' +
        '.zhl-fb-meta{background:#f1f6f3;border:1px solid #dceae3;border-radius:10px;padding:11px 13px;margin:0 0 16px;font-size:12.5px;color:#3f4d46;}' +
        '.zhl-fb-meta b{color:#00744c;}' +
        '.zhl-fb-meta .zhl-fb-url{word-break:break-all;color:#1f2a25;}' +
        '.zhl-fb-meta .zhl-fb-auto{margin-top:6px;color:#6b7a72;}' +
        '.zhl-fb-label{display:block;font-size:13px;font-weight:600;color:#1f2a25;margin:0 0 6px;}' +
        '.zhl-fb-modal textarea,.zhl-fb-modal input[type=email]{width:100%;box-sizing:border-box;border:1px solid #cdd8d2;' +
        'border-radius:10px;padding:10px 12px;font:inherit;font-size:14.5px;color:#1f2a25;background:#fff;}' +
        '.zhl-fb-modal textarea{min-height:120px;resize:vertical;margin-bottom:14px;}' +
        '.zhl-fb-modal input[type=email]{margin-bottom:14px;}' +
        '.zhl-fb-modal textarea:focus,.zhl-fb-modal input[type=email]:focus{outline:none;border-color:#009260;box-shadow:0 0 0 3px rgba(0,146,96,.15);}' +
        '.zhl-fb-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;}' +
        '.zhl-fb-send{width:100%;border:none;cursor:pointer;background:linear-gradient(135deg,#009260 0%,#00744c 100%);' +
        'color:#fff;font:inherit;font-weight:650;font-size:15px;padding:12px;border-radius:10px;box-shadow:0 6px 16px rgba(0,146,96,.28);}' +
        '.zhl-fb-send:hover{box-shadow:0 10px 22px rgba(0,146,96,.36);}' +
        '.zhl-fb-send:disabled{opacity:.6;cursor:default;box-shadow:none;}' +
        '.zhl-fb-status{margin:12px 0 0;font-size:13.5px;line-height:1.45;}' +
        '.zhl-fb-status.err{color:#b3261e;}' +
        '.zhl-fb-status.ok{color:#067a4b;}';

    var WRENCH = '<svg class="zhl-fb-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2-2 2.6-2.6z"/></svg>';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function injectStyles() {
        var style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    function build() {
        injectStyles();

        var badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'zhl-fb-badge';
        badge.setAttribute('aria-haspopup', 'dialog');
        badge.innerHTML = WRENCH +
            '<span><span class="zhl-fb-tt"></span><span class="zhl-fb-st"></span></span>';
        badge.querySelector('.zhl-fb-tt').textContent = t('badgeTitle');
        badge.querySelector('.zhl-fb-st').textContent = t('badgeSub');

        var overlay = document.createElement('div');
        overlay.className = 'zhl-fb-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML =
            '<div class="zhl-fb-modal">' +
                '<div class="zhl-fb-head"><h2>' + esc(t('modalTitle')) + '</h2>' +
                    '<button type="button" class="zhl-fb-x" aria-label="' + esc(t('close')) + '">&times;</button></div>' +
                '<div class="zhl-fb-body">' +
                    '<p class="zhl-fb-intro">' + esc(t('intro')) + '</p>' +
                    '<div class="zhl-fb-meta"><b>' + esc(t('pageLabel')) + ':</b> ' +
                        '<span class="zhl-fb-url">' + esc(location.href) + '</span>' +
                        '<div class="zhl-fb-auto">' + esc(t('autoNote')) + '</div></div>' +
                    '<form class="zhl-fb-form" novalidate>' +
                        '<label class="zhl-fb-label" for="zhl-fb-msg">' + esc(t('msgLabel')) + '</label>' +
                        '<textarea id="zhl-fb-msg" required placeholder="' + esc(t('msgPh')) + '"></textarea>' +
                        '<label class="zhl-fb-label" for="zhl-fb-email">' + esc(t('emailLabel')) + '</label>' +
                        '<input type="email" id="zhl-fb-email" placeholder="' + esc(t('emailPh')) + '" autocomplete="email">' +
                        '<div class="zhl-fb-hp"><label>Bitte leer lassen<input type="text" id="zhl-fb-hp" tabindex="-1" autocomplete="off"></label></div>' +
                        '<button type="submit" class="zhl-fb-send">' + esc(t('send')) + '</button>' +
                        '<p class="zhl-fb-status" role="status"></p>' +
                    '</form>' +
                '</div>' +
            '</div>';

        document.body.appendChild(badge);
        document.body.appendChild(overlay);

        var modal = overlay.querySelector('.zhl-fb-modal');
        var status = overlay.querySelector('.zhl-fb-status');
        var msg = overlay.querySelector('#zhl-fb-msg');

        function open() {
            overlay.classList.add('open');
            setTimeout(function () { msg.focus(); }, 50);
        }
        function close() { overlay.classList.remove('open'); }

        badge.addEventListener('click', open);
        overlay.querySelector('.zhl-fb-x').addEventListener('click', close);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });

        overlay.querySelector('.zhl-fb-form').addEventListener('submit', function (e) {
            e.preventDefault();
            status.className = 'zhl-fb-status';
            status.textContent = '';

            var text = msg.value.trim();
            if (text.length < 10) {
                status.className = 'zhl-fb-status err';
                status.textContent = t('errShort');
                msg.focus();
                return;
            }

            var btn = overlay.querySelector('.zhl-fb-send');
            btn.disabled = true;
            btn.textContent = t('sending');

            var body = new URLSearchParams();
            body.set('message', text);
            body.set('href', location.href);
            body.set('page_title', document.title || '');
            body.set('contact_email', overlay.querySelector('#zhl-fb-email').value.trim());
            body.set('lang', isEnglish() ? 'en' : 'de');
            body.set('hp', overlay.querySelector('#zhl-fb-hp').value);

            fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body: body.toString()
            }).then(function (r) {
                return r.json().catch(function () { return { ok: r.ok }; });
            }).then(function (data) {
                if (data && data.ok) {
                    status.className = 'zhl-fb-status ok';
                    status.textContent = t('ok');
                    msg.value = '';
                    overlay.querySelector('#zhl-fb-email').value = '';
                    btn.disabled = false;
                    btn.textContent = t('send');
                    setTimeout(close, 2200);
                } else {
                    throw new Error('server');
                }
            }).catch(function () {
                status.className = 'zhl-fb-status err';
                status.textContent = t('errSend');
                btn.disabled = false;
                btn.textContent = t('send');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();
