/**
 * Anchor Editor — WP-native admin page entry.
 *
 * Phase 3.A: editor layout shell, tabs, Monaco PHP loader.
 * Phase 3.B: auto-save (blur + Cmd-S), dirty-tab indicator, CSS tab with
 *            "Create page CSS" empty state, preview hot-swap.
 *
 * Mounts on <div id="anchor-editor-app">. Reads window.anchorEditor for config.
 *
 * REST routes used:
 *   GET  anchor-assistant/v1/files/page/{slug}      → { contents, exists, path }
 *   POST anchor-assistant/v1/files/page/{slug}      → body { contents }
 *   GET  anchor-assistant/v1/files/page-css/{slug}  → { contents, exists, path } (always 200)
 *   POST anchor-assistant/v1/files/page-css/{slug}  → body { contents }
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

    // ── Dirty / save state ──────────────────────────────────────────────────

    // Keyed by panel name ('php', 'css').
    var editors = { php: null, css: null };
    var dirty   = { php: false, css: false };

    function setDirty(panel, isDirty) {
        dirty[panel] = isDirty;
        var tab = host.querySelector('.ae-tab[data-tab="' + panel + '"]');
        if (tab) tab.classList.toggle('is-dirty', isDirty);
        // Enable Save button whenever any tab is dirty.
        var anyDirty = Object.keys(dirty).some(function (k) { return dirty[k]; });
        var saveBtn = host.querySelector('.ae-save');
        if (saveBtn) saveBtn.disabled = !anyDirty;
    }

    // ── Preview helpers ──────────────────────────────────────────────────────

    function reloadPreview() {
        var f = document.getElementById('ae-preview-frame');
        if (f) f.src = f.src;
    }

    // The CSS file lives at assets/css/pages/{leaf}.css — strip any leading
    // path segments (e.g. "services/web-design" → "web-design").
    function cssSlugleaf(s) {
        var str = String(s);
        var i = str.lastIndexOf('/');
        return i === -1 ? str : str.slice(i + 1);
    }

    function hotSwapPreviewCss() {
        var f = document.getElementById('ae-preview-frame');
        if (!f || !f.contentDocument) { reloadPreview(); return; }
        var leaf = cssSlugleaf(slug);
        var linkSelector = 'link[href*="/assets/css/pages/' + leaf + '.css"]';
        var link = f.contentDocument.querySelector(linkSelector);
        if (!link) { reloadPreview(); return; }
        try {
            var url = new URL(link.href);
            url.searchParams.set('_ae', Date.now());
            link.href = url.toString();
        } catch (e) {
            reloadPreview();
        }
    }

    // ── Save logic ───────────────────────────────────────────────────────────

    function save(panel) {
        if (!dirty[panel] || !editors[panel]) return Promise.resolve();
        var contents = editors[panel].getValue();
        if (panel === 'php') {
            return savePHP(contents);
        } else if (panel === 'css') {
            return saveCSS(contents);
        }
        return Promise.resolve();
    }

    function savePHP(contents) {
        var url = restBase + 'files/page/' + encodeURIComponent(slug);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ contents: contents }),
        }).then(function (r) {
            if (!r.ok) throw new Error('Save PHP failed: ' + r.status);
            return r.json();
        }).then(function () {
            setDirty('php', false);
            reloadPreview();
        });
    }

    function saveCSS(contents) {
        var leaf = cssSlugleaf(slug);
        var url  = restBase + 'files/page-css/' + encodeURIComponent(leaf);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ contents: contents }),
        }).then(function (r) {
            if (!r.ok) throw new Error('Save CSS failed: ' + r.status);
            return r.json();
        }).then(function () {
            setDirty('css', false);
            hotSwapPreviewCss();
        });
    }

    // ── Layout ──────────────────────────────────────────────────────────────

    var previewSrc = homeUrl + (homeUrl.slice(-1) === '/' ? '' : '/') + slug;
    var leaf = cssSlugleaf(slug);

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
        '      <div class="ae-empty" id="ae-css-empty">',
        '        <p>This page doesn\'t have a custom CSS file yet.</p>',
        '        <p><small>Path: <code>child-theme/assets/css/pages/' + escapeHtml(leaf) + '.css</code></small></p>',
        '        <button class="ae-btn" id="ae-create-css">Create page CSS</button>',
        '      </div>',
        '      <div class="ae-monaco-host" id="ae-monaco-css" hidden></div>',
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

    // ── Save button ──────────────────────────────────────────────────────────

    var saveBtn = host.querySelector('.ae-save');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var active = host.querySelector('.ae-tab.is-active');
            var panel = active ? active.getAttribute('data-tab') : 'php';
            save(panel).catch(function (err) {
                console.error('[anchor-editor] save error:', err);
            });
        });
    }

    // ── Cmd-S / Ctrl-S keyboard shortcut ────────────────────────────────────

    window.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 's') {
            e.preventDefault();
            var active = host.querySelector('.ae-tab.is-active');
            var panel  = active ? active.getAttribute('data-tab') : 'php';
            save(panel).catch(function (err) {
                console.error('[anchor-editor] cmd-s save error:', err);
            });
        }
    });

    // ── Monaco loader ────────────────────────────────────────────────────────

    loadMonaco(monacoVs).then(function (monaco) {
        return bootPhp(monaco).then(function () {
            return bootCssIfExists(monaco);
        });
    }).catch(function (err) {
        console.error('[anchor-editor] Monaco load failed:', err);
    });

    // ── PHP editor ───────────────────────────────────────────────────────────

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
            var value = (data && typeof data.contents === 'string') ? data.contents : '';
            var phpEditor = monaco.editor.create(document.getElementById('ae-monaco-php'), {
                value: value,
                language: 'php',
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 13,
                scrollBeyondLastLine: false,
            });
            editors.php = phpEditor;
            phpEditor.onDidChangeModelContent(function () { setDirty('php', true); });
            phpEditor.onDidBlurEditorWidget(function () {
                save('php').catch(function (err) { console.error('[anchor-editor] php blur save:', err); });
            });
        });
    }

    // ── CSS editor ───────────────────────────────────────────────────────────

    var monacoRef = null; // stored so "Create page CSS" button can init editor later

    function bootCssIfExists(monaco) {
        monacoRef = monaco;
        var cssLeaf = cssSlugleaf(slug);
        var url = restBase + 'files/page-css/' + encodeURIComponent(cssLeaf);
        return fetch(url, {
            headers: { 'X-WP-Nonce': nonce },
        }).then(function (r) {
            if (!r.ok) {
                // Unexpected error — leave empty state visible
                console.warn('[anchor-editor] page-css check failed, status:', r.status);
                return null;
            }
            return r.json();
        }).then(function (data) {
            if (data && data.exists) {
                return initCssEditor(data.contents || '');
            }
            // File doesn't exist → show empty state (default)
        });
    }

    function initCssEditor(initial) {
        if (!monacoRef) return Promise.resolve();
        var emptyEl = document.getElementById('ae-css-empty');
        var cssHost = document.getElementById('ae-monaco-css');
        if (emptyEl) emptyEl.hidden = true;
        if (cssHost) cssHost.hidden = false;
        var cssEditor = monacoRef.editor.create(cssHost, {
            value: initial,
            language: 'css',
            automaticLayout: true,
            minimap: { enabled: false },
            fontSize: 13,
            scrollBeyondLastLine: false,
        });
        editors.css = cssEditor;
        cssEditor.onDidChangeModelContent(function () { setDirty('css', true); });
        cssEditor.onDidBlurEditorWidget(function () {
            save('css').catch(function (err) { console.error('[anchor-editor] css blur save:', err); });
        });
        return Promise.resolve();
    }

    // "Create page CSS" button
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'ae-create-css') {
            initCssEditor('');
        }
    });

    // ── Utilities ────────────────────────────────────────────────────────────

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

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
})();
