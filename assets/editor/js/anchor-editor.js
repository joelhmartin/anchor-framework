/**
 * Anchor Editor — WP-native admin page entry.
 *
 * Phase 3.A: editor layout shell, tabs, Monaco PHP loader.
 * Phase 3.B: auto-save (blur + Cmd-S), dirty-tab indicator, CSS tab with
 *            "Create page CSS" empty state, preview hot-swap.
 * Phase 3.C: chat panel (agent loop), page-flag chips in toolbar, Cmd-K
 *            utility palette.
 *
 * Mounts on <div id="anchor-editor-app">. Reads window.anchorEditor for config.
 *
 * REST routes used:
 *   GET  anchor-assistant/v1/files/page/{slug}      → { contents, exists, path }
 *   POST anchor-assistant/v1/files/page/{slug}      → body { contents }
 *   GET  anchor-assistant/v1/files/page-css/{slug}  → { contents, exists, path } (always 200)
 *   POST anchor-assistant/v1/files/page-css/{slug}  → body { contents }
 *   GET  anchor-assistant/v1/editor/page-flags/{slug} → { no_header, no_footer }
 *   POST anchor-assistant/v1/editor/page-flags/{slug} → body { no_header, no_footer }
 *   POST anchor-assistant/v1/agent/plan             → { plan_id, plan }
 *   POST anchor-assistant/v1/agent/execute          → { results, halted, halt_reason }
 *   POST anchor-assistant/v1/ai/chat                → { reply }
 *   GET  anchor-assistant/v1/editor/utilities       → { classes: [] }
 *   GET  wp/v2/pages/{id}?context=edit              → WP page object (title, slug, featured_media)
 *   GET  wp/v2/media/{id}?context=edit              → media attachment (source_url)
 *   POST anchor-assistant/v1/editor/page-meta       → { post_id, title, slug, page_uri, ... }
 */

import { createAgentSession } from './agent-core.js';
import { createUtilityPalette } from './utility-palette.js';

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
        '    <div class="ae-flags">',
        '      <button class="ae-chip" data-flag="no-header" type="button">Header …</button>',
        '      <button class="ae-chip" data-flag="no-footer" type="button">Footer …</button>',
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
        '    <section class="ae-panel ae-panel-chat" data-panel="chat" hidden>',
        '      <div class="ae-chat">',
        '        <div class="ae-chat-messages" id="ae-chat-messages"></div>',
        '        <div class="ae-chat-input">',
        '          <textarea id="ae-chat-text" placeholder="Ask the agent to make changes… (⌘↵ to send)"></textarea>',
        '          <button class="ae-btn" id="ae-chat-send">Send</button>',
        '        </div>',
        '      </div>',
        '    </section>',
        '    <aside class="ae-preview">',
        '      <iframe id="ae-preview-frame" src="' + escapeAttr(previewSrc) + '" title="Page preview"></iframe>',
        '    </aside>',
        '    <aside class="ae-sidebar">',
        '      <section class="ae-side-block">',
        '        <h3>Page</h3>',
        '        <label>Title</label>',
        '        <input type="text" id="ae-title" />',
        '        <label>Slug (leaf)</label>',
        '        <input type="text" id="ae-slug" />',
        '        <label>Featured image</label>',
        '        <button class="ae-btn ae-side-btn" id="ae-pick-featured">Choose</button>',
        '        <button class="ae-btn ae-side-btn ae-side-btn-secondary" id="ae-clear-featured" hidden>Remove</button>',
        '        <div id="ae-featured-preview"></div>',
        '      </section>',
        '      <section class="ae-side-block">',
        '        <h3>SEO</h3>',
        '        <a class="ae-btn ae-side-btn" id="ae-yoast-bypass" href="" target="_blank" rel="noopener">Edit SEO →</a>',
        '        <p class="ae-side-note">Opens the classic WP edit screen with our takeover bypassed so Yoast renders natively.</p>',
        '      </section>',
        '      <section class="ae-side-block">',
        '        <h3>Anchor</h3>',
        '        <a class="ae-btn ae-side-btn" id="ae-open-ide" href="">Open full IDE →</a>',
        '      </section>',
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

    // ── Cmd-K / Ctrl-K → utility palette ────────────────────────────────────

    var palette = createUtilityPalette({
        restBase: restBase,
        nonce: nonce,
        getMonacoEditor: function () {
            var active = host.querySelector('.ae-tab.is-active');
            var panel  = active ? active.getAttribute('data-tab') : 'php';
            return editors[panel] || null;
        },
    });

    window.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            palette.toggle();
        }
    });

    // ── Page-flag chips ──────────────────────────────────────────────────────

    function loadFlags() {
        if (!slug) return;
        fetch(restBase + 'editor/page-flags/' + encodeURIComponent(slug), {
            headers: { 'X-WP-Nonce': nonce },
        }).then(function (r) {
            if (!r.ok) return null;
            return r.json();
        }).then(function (flags) {
            if (!flags) return;
            updateChip('no-header', !!flags.no_header);
            updateChip('no-footer', !!flags.no_footer);
        }).catch(function () {});
    }

    function updateChip(flag, isOff) {
        var chip = host.querySelector('.ae-chip[data-flag="' + flag + '"]');
        if (!chip) return;
        chip.classList.toggle('is-off', isOff);
        var label = flag === 'no-header' ? 'Header' : 'Footer';
        chip.textContent = label + ' ' + (isOff ? 'off' : 'on');
    }

    host.querySelectorAll('.ae-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var flag  = chip.dataset.flag;
            var isOff = !chip.classList.contains('is-off'); // toggle
            // Build POST body: no_header / no_footer booleans, keep the other as-is
            var body = {
                no_header: flag === 'no-header' ? isOff : !!host.querySelector('.ae-chip[data-flag="no-header"]').classList.contains('is-off'),
                no_footer: flag === 'no-footer' ? isOff : !!host.querySelector('.ae-chip[data-flag="no-footer"]').classList.contains('is-off'),
            };
            fetch(restBase + 'editor/page-flags/' + encodeURIComponent(slug), {
                method: 'POST',
                headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            }).then(function (r) {
                if (!r.ok) return;
                updateChip(flag, isOff);
                reloadPreview();
            }).catch(function () {});
        });
    });

    loadFlags();

    // ── Right sidebar — page meta ────────────────────────────────────────────

    async function loadPostMeta() {
        // Read via WP core REST. We need an admin-context user, so include the nonce.
        const r = await fetch(`/wp-json/wp/v2/pages/${postId}?context=edit`, {
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin',
        });
        if (!r.ok) return;
        const p = await r.json();
        document.getElementById('ae-title').value = (p.title && (p.title.raw ?? p.title.rendered)) || '';
        // slug input shows the LEAF only (post_name is the leaf slug).
        document.getElementById('ae-slug').value = p.slug || '';

        // Featured image preview
        if (p.featured_media) {
            const m = await fetch(`/wp-json/wp/v2/media/${p.featured_media}?context=edit`, {
                headers: { 'X-WP-Nonce': nonce },
            }).then(function(res) { return res.json(); });
            setFeaturedPreview(m.source_url);
        }

        document.getElementById('ae-yoast-bypass').href = cfg.editUrlBypass || '#';
        document.getElementById('ae-open-ide').href = `admin.php?page=anchor-live-editor#open=child-theme/page-content/${slug}.php`;
    }

    function setFeaturedPreview(srcUrl) {
        var wrap = document.getElementById('ae-featured-preview');
        var removeBtn = document.getElementById('ae-clear-featured');
        if (srcUrl) {
            wrap.innerHTML = '<img src="' + escapeAttr(srcUrl) + '" alt="" />';
            removeBtn.hidden = false;
        } else {
            wrap.innerHTML = '';
            removeBtn.hidden = true;
        }
    }

    async function savePostMeta(patch) {
        var body = Object.assign({ post_id: postId }, patch);
        const r = await fetch(restBase + 'editor/page-meta', {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        if (!r.ok) {
            console.error('[anchor-editor] save meta failed', await r.text());
        }
        return r.ok ? r.json() : null;
    }

    // Wire sidebar inputs
    document.getElementById('ae-title').addEventListener('blur', function() {
        savePostMeta({ title: document.getElementById('ae-title').value });
    });
    document.getElementById('ae-slug').addEventListener('blur', function() {
        savePostMeta({ slug: document.getElementById('ae-slug').value });
    });

    // Featured image picker (wp.media)
    document.getElementById('ae-pick-featured').addEventListener('click', function(e) {
        e.preventDefault();
        if (!window.wp || !window.wp.media) {
            alert('Media library unavailable.');
            return;
        }
        var frame = wp.media({ title: 'Featured image', multiple: false });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            savePostMeta({ featured_id: att.id });
            setFeaturedPreview(att.url);
        });
        frame.open();
    });

    document.getElementById('ae-clear-featured').addEventListener('click', function() {
        savePostMeta({ featured_id: 0 });
        setFeaturedPreview('');
    });

    loadPostMeta();

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

    // ── Chat panel (agent loop) ──────────────────────────────────────────────

    var agent = createAgentSession({ restBase: restBase, nonce: nonce });
    var msgList = document.getElementById('ae-chat-messages');

    function appendMessage(role, text) {
        var div = document.createElement('div');
        div.className = 'ae-chat-message ' + role;
        div.textContent = text;
        msgList.appendChild(div);
        msgList.scrollTop = msgList.scrollHeight;
    }

    function renderPlan(planData, planId) {
        var wrapper = document.createElement('div');
        wrapper.className = 'ae-chat-message plan';

        var summary = document.createElement('div');
        summary.className = 'ae-plan-summary';
        summary.textContent = planData.summary || 'Proposed plan:';
        wrapper.appendChild(summary);

        var stepsEl = document.createElement('div');
        stepsEl.className = 'ae-plan-steps-list';

        (planData.steps || []).forEach(function (step, i) {
            var row = document.createElement('div');
            row.className = 'ae-plan-step';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = true;
            cb.dataset.stepIndex = i;
            var label = document.createElement('span');
            label.textContent = step.tool + ' — ' + (step.rationale || '');
            row.appendChild(cb);
            row.appendChild(label);
            stepsEl.appendChild(row);
        });
        wrapper.appendChild(stepsEl);

        var approveBtn = document.createElement('button');
        approveBtn.className = 'ae-btn ae-plan-approve';
        approveBtn.textContent = 'Approve & run';
        approveBtn.addEventListener('click', function () {
            var keep = Array.from(stepsEl.querySelectorAll('input[type="checkbox"]')).map(function (cb) { return cb.checked; });
            var filteredSteps = (planData.steps || []).filter(function (_, i) { return keep[i]; });
            // Ensure done step is still present
            if (!filteredSteps.length || filteredSteps[filteredSteps.length - 1].tool !== 'done') {
                alert('Plan must end with the "done" step.');
                return;
            }
            approveBtn.disabled = true;
            approveBtn.textContent = 'Running…';
            agent.approve({ summary: planData.summary, steps: filteredSteps });
        });
        wrapper.appendChild(approveBtn);

        msgList.appendChild(wrapper);
        msgList.scrollTop = msgList.scrollHeight;
    }

    function renderStepResult(detail) {
        var div = document.createElement('div');
        var status = detail.skipped ? 'skipped' : (detail.success ? 'ok' : 'fail');
        div.className = 'ae-chat-step-result ae-step-' + status;
        var glyph = detail.skipped ? '⊘' : (detail.success ? '✓' : '✗');
        div.textContent = glyph + ' ' + detail.tool + (detail.error ? ' — ' + detail.error : '');
        msgList.appendChild(div);
        msgList.scrollTop = msgList.scrollHeight;
    }

    agent.addEventListener('message', function (e) {
        appendMessage(e.detail.role === 'user' ? 'user' : 'assistant', e.detail.text);
    });

    agent.addEventListener('plan', function (e) {
        renderPlan(e.detail.plan, e.detail.planId);
    });

    agent.addEventListener('step-result', function (e) {
        renderStepResult(e.detail);
    });

    agent.addEventListener('done', function (e) {
        if (e.detail.halted) {
            appendMessage('assistant', 'Halted: ' + (e.detail.halt_reason || 'unknown error'));
        } else {
            appendMessage('assistant', 'Done.');
        }
        reloadPreview();
    });

    function sendChat() {
        var ta = document.getElementById('ae-chat-text');
        if (!ta) return;
        var text = ta.value.trim();
        if (!text) return;
        ta.value = '';
        agent.send(text, {
            open_files: ['child-theme/page-content/' + slug + '.php'],
            current_page_slug: slug,
        });
    }

    var sendBtn = document.getElementById('ae-chat-send');
    if (sendBtn) {
        sendBtn.addEventListener('click', sendChat);
    }

    var chatTextarea = document.getElementById('ae-chat-text');
    if (chatTextarea) {
        chatTextarea.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault();
                sendChat();
            }
        });
    }

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
