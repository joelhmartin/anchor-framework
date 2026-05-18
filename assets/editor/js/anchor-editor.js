/**
 * Anchor Editor — WP-native admin page entry.
 *
 * Phase 3.A: editor layout shell, tabs, Monaco PHP loader.
 * Mounts on <div id="anchor-editor-app">. Reads window.anchorEditor for config.
 *
 * REST: GET anchor-assistant/v1/files/page/{slug} → { contents, exists, path }
 */

(function () {
    'use strict';

    var host = document.getElementById('anchor-editor-app');
    if (!host) return;

    var cfg = window.anchorEditor || {};
    var postId   = cfg.postId   || 0;
    var slug     = cfg.slug     || '';
    var restBase = cfg.restBase || '';
    var nonce    = cfg.nonce    || '';
    var monacoVs = cfg.monacoVs || 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs';
    var homeUrl  = cfg.homeUrl  || '/';

    if (!postId) {
        host.textContent = 'No post id; cannot start editor.';
        return;
    }

    // ── Layout ──────────────────────────────────────────────────────────────

    var previewSrc = homeUrl + (homeUrl.slice(-1) === '/' ? '' : '/') + slug;

    host.innerHTML = [
        '<div class="ae-shell">',
        '  <header class="ae-toolbar">',
        '    <div class="ae-toolbar-left">',
        '      <button class="ae-tab is-active" data-tab="php">PHP</button>',
        '      <button class="ae-tab" data-tab="css">CSS</button>',
        '      <button class="ae-tab" data-tab="chat">Chat</button>',
        '    </div>',
        '    <div class="ae-toolbar-right">',
        '      <button class="ae-btn ae-save" disabled>Save</button>',
        '      <a class="ae-btn" target="_blank" rel="noopener" href="' + escapeAttr(previewSrc) + '">Preview &#8599;</a>',
        '    </div>',
        '  </header>',
        '  <main class="ae-main">',
        '    <section class="ae-panel ae-panel-editor" data-panel="php">',
        '      <div class="ae-monaco-host" id="ae-monaco-php"></div>',
        '    </section>',
        '    <section class="ae-panel" data-panel="css" hidden>',
        '      <p class="ae-panel-placeholder">CSS tab lands in Phase 3.B.</p>',
        '    </section>',
        '    <section class="ae-panel" data-panel="chat" hidden>',
        '      <p class="ae-panel-placeholder">Chat lands in Phase 3.C.</p>',
        '    </section>',
        '    <aside class="ae-preview">',
        '      <iframe id="ae-preview-frame" src="' + escapeAttr(previewSrc) + '" title="Page preview"></iframe>',
        '    </aside>',
        '  </main>',
        '</div>',
    ].join('\n');

    host.removeAttribute('data-loading');

    // ── Tabs ────────────────────────────────────────────────────────────────

    var tabs   = host.querySelectorAll('.ae-tab');
    var panels = host.querySelectorAll('.ae-panel');

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabs.forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            var target = btn.getAttribute('data-tab');
            panels.forEach(function (p) {
                p.hidden = p.getAttribute('data-panel') !== target;
            });
        });
    });

    // ── Monaco loader ────────────────────────────────────────────────────────

    loadMonaco(monacoVs).then(function (monaco) {
        return bootPhp(monaco);
    }).catch(function (err) {
        console.error('[anchor-editor] Monaco load failed:', err);
    });

    function bootPhp(monaco) {
        var url = restBase + 'files/page/' + encodeURIComponent(slug);
        return fetch(url, {
            headers: { 'X-WP-Nonce': nonce },
        }).then(function (r) {
            if (!r.ok) {
                console.error('[anchor-editor] failed to load PHP file, status:', r.status);
                return { contents: '' };
            }
            return r.json();
        }).then(function (data) {
            // Response shape: { contents: string, exists: bool, path: string }
            var value = (data && typeof data.contents === 'string') ? data.contents : '';
            monaco.editor.create(document.getElementById('ae-monaco-php'), {
                value: value,
                language: 'php',
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 13,
                scrollBeyondLastLine: false,
            });
        });
    }

    function loadMonaco(vs) {
        if (window.monaco) return Promise.resolve(window.monaco);
        return loadScript(vs + '/loader.min.js').then(function () {
            return new Promise(function (resolve, reject) {
                /* global require */
                require.config({ paths: { vs: vs } });
                require(['vs/editor/editor.main'], function () {
                    resolve(window.monaco);
                }, function (err) {
                    reject(new Error('Monaco editor.main load failed: ' + (err && err.message ? err.message : 'unknown')));
                });
            });
        });
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('Script load failed: ' + src)); };
            document.head.appendChild(s);
        });
    }

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
})();
