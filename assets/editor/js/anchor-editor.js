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

    function hotSwapPreviewCss() {
        var f = document.getElementById('ae-preview-frame');
        if (!f || !f.contentDocument) { reloadPreview(); return; }
        // Use the full hierarchical slug so services/web-design matches
        // the correct link rather than any page whose leaf is "web-design".
        var linkSelector = 'link[href*="/assets/css/pages/' + slug + '.css"]';
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

    // ── Save logic (per-panel sequencing) ────────────────────────────────────
    //
    // saving[panel] is false when idle; true while a request is in flight.
    // If a new save is requested while one is in flight we let the in-flight
    // request finish, then immediately retry once if dirty[panel] is still set.

    var saving = { php: false, css: false };

    function save(panel) {
        if (!dirty[panel] || !editors[panel] || saving[panel]) return Promise.resolve();
        saving[panel] = true;
        var contents = editors[panel].getValue();
        var url, doAfter;
        if (panel === 'php') {
            url     = restBase + 'files/page/' + encodeURIComponent(slug);
            doAfter = function () { reloadPreview(); };
        } else {
            // Use the full slug (including any path separators) so nested
            // pages don't collide on a shared CSS leaf name.
            url     = restBase + 'files/page-css/' + slug.split('/').map(encodeURIComponent).join('/');
            doAfter = function () { hotSwapPreviewCss(); };
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ contents: contents }),
        }).then(function (r) {
            if (!r.ok) throw new Error('Save ' + panel.toUpperCase() + ' failed: ' + r.status);
            return r.json();
        }).then(function () {
            // Only clear dirty if no new edits landed while the request was in flight.
            if (editors[panel].getValue() === contents) {
                setDirty(panel, false);
            }
            doAfter();
        }).catch(function (err) {
            console.error('[anchor-editor] save error (' + panel + '):', err);
        }).then(function () {
            saving[panel] = false;
            // Retry if content changed during the in-flight request.
            if (dirty[panel]) save(panel);
        });
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
        '        <p><small>Path: <code>child-theme/assets/css/pages/' + escapeHtml(slug) + '.css</code></small></p>',
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
        '        <label>Meta description</label>',
        '        <textarea id="ae-meta-desc" rows="3" maxlength="160"></textarea>',
        '        <small id="ae-meta-desc-count" class="ae-side-hint">0 / 160</small>',
        '        <label>Focus keyphrase</label>',
        '        <input type="text" id="ae-focus-kw" />',
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
            // Save every dirty panel, not just the active one, so the button
            // never silently no-ops when a non-active panel has unsaved edits.
            var ops = [];
            ['php', 'css'].forEach(function (panel) {
                if (dirty[panel]) ops.push(save(panel));
            });
            if (ops.length) {
                Promise.all(ops).catch(function (err) {
                    console.error('[anchor-editor] save error:', err);
                });
            }
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
    //
    // currentFlags is the authoritative in-memory copy of the server state.
    // Chip clicks derive the OTHER flag's value from here, not from DOM classes,
    // so stale DOM state can't accidentally clear a flag the user didn't touch.

    var currentFlags = { no_header: false, no_footer: false };

    function loadFlags() {
        if (!slug) return;
        fetch(restBase + 'editor/page-flags/' + encodeURIComponent(slug), {
            headers: { 'X-WP-Nonce': nonce },
        }).then(function (r) {
            if (!r.ok) return null;
            return r.json();
        }).then(function (flags) {
            if (!flags) return;
            currentFlags.no_header = !!flags.no_header;
            currentFlags.no_footer = !!flags.no_footer;
            updateChip('no-header', currentFlags.no_header);
            updateChip('no-footer', currentFlags.no_footer);
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
            // Derive the new value for the clicked flag by toggling currentFlags,
            // and keep the OTHER flag's value from currentFlags (not DOM classes).
            var newNoHeader = flag === 'no-header' ? !currentFlags.no_header : currentFlags.no_header;
            var newNoFooter = flag === 'no-footer' ? !currentFlags.no_footer : currentFlags.no_footer;
            var body = { no_header: newNoHeader, no_footer: newNoFooter };
            fetch(restBase + 'editor/page-flags/' + encodeURIComponent(slug), {
                method: 'POST',
                headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            }).then(function (r) {
                if (!r.ok) return;
                // Commit new values to in-memory state only after a successful save.
                currentFlags.no_header = newNoHeader;
                currentFlags.no_footer = newNoFooter;
                updateChip('no-header', currentFlags.no_header);
                updateChip('no-footer', currentFlags.no_footer);
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

        // Yoast meta — read current values back from the anchor page-meta endpoint.
        const seoResp = await fetch(restBase + 'editor/page-meta', {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId }),
        });
        if (seoResp.ok) {
            const seo = await seoResp.json();
            document.getElementById('ae-meta-desc').value = seo.meta_description || '';
            document.getElementById('ae-focus-kw').value  = seo.focus_keyphrase  || '';
            updateMetaDescCount();
        }

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

    // SEO fields — save on blur, update char count on input
    document.getElementById('ae-meta-desc').addEventListener('blur', function() {
        savePostMeta({ meta_description: document.getElementById('ae-meta-desc').value });
    });
    document.getElementById('ae-meta-desc').addEventListener('input', updateMetaDescCount);
    document.getElementById('ae-focus-kw').addEventListener('blur', function() {
        savePostMeta({ focus_keyphrase: document.getElementById('ae-focus-kw').value });
    });

    function updateMetaDescCount() {
        var v = document.getElementById('ae-meta-desc').value || '';
        var c = document.getElementById('ae-meta-desc-count');
        if (!c) return;
        c.textContent = v.length + ' / 160';
        c.classList.toggle('is-warn', v.length > 160 || v.length < 50);
    }

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
        var url = restBase + 'files/page-css/' + slug.split('/').map(encodeURIComponent).join('/');
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
            // Wrap checkbox + text in <label> so the control is accessible
            // without explicit id/htmlFor attributes.
            var labelEl = document.createElement('label');
            labelEl.className = 'ae-plan-step';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = true;
            cb.dataset.stepIndex = i;
            var span = document.createElement('span');
            span.textContent = step.tool + ' — ' + (step.rationale || '');
            labelEl.appendChild(cb);
            labelEl.appendChild(span);
            stepsEl.appendChild(labelEl);
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
        // Reload Monaco editors from disk so they reflect any agent writes,
        // then reload the preview.
        refreshEditorsAfterAgent().then(reloadPreview);
    });

    function refreshEditorsAfterAgent() {
        var ops = [];
        if (editors.php) {
            var phpUrl = restBase + 'files/page/' + encodeURIComponent(slug);
            ops.push(
                fetch(phpUrl, { headers: { 'X-WP-Nonce': nonce } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data) return;
                        var fresh = data.contents || '';
                        if (editors.php.getValue() !== fresh) {
                            editors.php.setValue(fresh);
                            setDirty('php', false);
                        }
                    })
                    .catch(function () {})
            );
        }
        if (editors.css) {
            var cssUrl = restBase + 'files/page-css/' + slug.split('/').map(encodeURIComponent).join('/');
            ops.push(
                fetch(cssUrl, { headers: { 'X-WP-Nonce': nonce } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data || !data.exists) return;
                        var fresh = data.contents || '';
                        if (editors.css.getValue() !== fresh) {
                            editors.css.setValue(fresh);
                            setDirty('css', false);
                        }
                    })
                    .catch(function () {})
            );
        }
        return Promise.all(ops);
    }

    function sendChat() {
        var ta = document.getElementById('ae-chat-text');
        if (!ta) return;
        var text = ta.value.trim();
        if (!text) return;
        ta.value = '';
        // Flush any dirty buffers before the agent reads files from disk.
        var flushOps = [];
        ['php', 'css'].forEach(function (panel) {
            if (dirty[panel]) flushOps.push(save(panel));
        });
        Promise.all(flushOps).then(function () {
            agent.send(text, {
                open_files: ['child-theme/page-content/' + slug + '.php'],
                current_page_slug: slug,
            });
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
