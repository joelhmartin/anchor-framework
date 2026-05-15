/**
 * Anchor Editor — admin IDE entry.
 *
 * Three-panel layout: file tree | Monaco | chat+plan+diff.
 * Mounts on <div id="anchor-ide">. Reads window.anchorIDE for config.
 */

import { createAgentSession } from './agent-core.js';

(function () {
    if (typeof window.anchorIDE === 'undefined') return;

    const cfg = window.anchorIDE;
    const host = document.getElementById('anchor-ide');
    if (!host) return;

    // Build the static three-panel scaffold.
    host.innerHTML = `
        <div class="anchor-ide-root">
            <div class="anchor-ide-tree" id="anchor-ide-tree"><p>Loading tree…</p></div>
            <div class="anchor-ide-editor">
                <div class="anchor-ide-editor-bar">
                    <span class="anchor-ide-editor-path">— no file open —</span>
                    <span class="anchor-ide-editor-dirty" hidden>●</span>
                    <button class="button" id="anchor-ide-save" disabled>Save</button>
                </div>
                <div class="anchor-ide-editor-host" id="anchor-ide-editor-host"></div>
            </div>
            <div class="anchor-ide-chat">
                <div class="anchor-ide-chat-header">
                    <strong>Agent</strong>
                    <button class="button-link" id="anchor-ide-chat-new">New conversation</button>
                </div>
                <div class="anchor-ide-chat-messages" id="anchor-ide-chat-messages"></div>
                <div class="anchor-ide-chat-input">
                    <textarea id="anchor-ide-chat-text" rows="2" placeholder="Tell the agent what to do…"></textarea>
                    <button class="button button-primary" id="anchor-ide-chat-send">Send</button>
                </div>
            </div>
        </div>
    `;
    host.removeAttribute('data-loading');

    // ─── State ─────────────────────────────────────────────────────
    const agent = createAgentSession({ restBase: cfg.restBase, nonce: cfg.nonce });
    let monaco = null;
    let editor = null;
    let openFile = null;          // { path, contents, dirty }
    let monacoPromise = null;

    // ─── File tree ─────────────────────────────────────────────────
    async function loadTree() {
        const treeEl = document.getElementById('anchor-ide-tree');
        try {
            const r = await fetch(cfg.restBase + 'files/tree', {
                headers: { 'X-WP-Nonce': cfg.nonce },
            });
            const data = await r.json();
            if (!r.ok) {
                treeEl.innerHTML = '<p>Tree error.</p>';
                return;
            }
            treeEl.innerHTML = '';
            data.tree.forEach(node => treeEl.appendChild(renderNode(node)));
        } catch (err) {
            treeEl.innerHTML = '<p>Tree error: ' + err.message + '</p>';
        }
    }

    function renderNode(node) {
        const wrap = document.createElement('div');
        wrap.className = 'anchor-tree-node anchor-tree-' + node.type + (node.writable ? '' : ' anchor-tree-readonly');

        if (node.type === 'dir') {
            const head = document.createElement('button');
            head.className = 'anchor-tree-dir-head';
            head.textContent = '▶ ' + node.label;
            const children = document.createElement('div');
            children.className = 'anchor-tree-children';
            children.style.display = 'none';
            head.addEventListener('click', () => {
                const open = children.style.display === 'block';
                children.style.display = open ? 'none' : 'block';
                head.textContent = (open ? '▶ ' : '▼ ') + node.label;
            });
            wrap.appendChild(head);
            (node.children || []).forEach(c => children.appendChild(renderNode(c)));
            wrap.appendChild(children);
        } else {
            const btn = document.createElement('button');
            btn.className = 'anchor-tree-file';
            btn.textContent = node.label;
            btn.addEventListener('click', () => openFileInEditor(node));
            wrap.appendChild(btn);
        }
        return wrap;
    }

    // ─── Monaco lifecycle ──────────────────────────────────────────
    function ensureMonaco() {
        if (monaco) return Promise.resolve(monaco);
        if (monacoPromise) return monacoPromise;
        monacoPromise = new Promise((resolve, reject) => {
            const loader = document.createElement('script');
            loader.src = cfg.monacoVs + '/loader.min.js';
            loader.onload = () => {
                /* global require */
                require.config({ paths: { vs: cfg.monacoVs } });
                require(['vs/editor/editor.main'], () => {
                    monaco = window.monaco;
                    resolve(monaco);
                }, (err) => {
                    monacoPromise = null; // allow retry
                    reject(new Error('Monaco editor.main load failed: ' + (err?.message || 'unknown')));
                });
            };
            loader.onerror = () => {
                monacoPromise = null; // allow retry
                reject(new Error('Monaco loader failed'));
            };
            document.head.appendChild(loader);
        });
        return monacoPromise;
    }

    async function openFileInEditor(node) {
        if (openFile && openFile.dirty) {
            if (!confirm('Discard unsaved changes?')) return;
        }
        await ensureMonaco();
        const r = await fetch(cfg.restBase + 'files/page/' + encodeURIComponent(slugFromPath(node.path)), {
            headers: { 'X-WP-Nonce': cfg.nonce },
        }).catch(() => null);
        // fallback: use generic read via tool registry isn't exposed as REST; for now we only have file-read via the page-content/css endpoints from Phase 1.
        // For 4A: when node.path is under page-content/, use /files/page/{slug}. Otherwise stub a read via /agent/plan list_dir+read_file flow is overkill. Add a generic /files/read endpoint if needed.
        // For now: only support page-content/.php opens in 4A. Other file types open read-only with empty placeholder.
        const editorHost = document.getElementById('anchor-ide-editor-host');
        editorHost.innerHTML = '';
        if (editor) { editor.dispose(); editor = null; }
        let contents = '';
        if (r && r.ok) {
            const data = await r.json();
            contents = data.contents || '';
        }
        editor = monaco.editor.create(editorHost, {
            value: contents,
            language: node.ext === 'php' ? 'php' : node.ext === 'css' ? 'css' : 'html',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            readOnly: !node.writable,
        });
        openFile = { path: node.path, contents, dirty: false, ext: node.ext, writable: node.writable };
        document.querySelector('.anchor-ide-editor-path').textContent = node.path;
        document.getElementById('anchor-ide-save').disabled = !node.writable;
        editor.onDidChangeModelContent(() => {
            openFile.dirty = editor.getValue() !== openFile.contents;
            document.querySelector('.anchor-ide-editor-dirty').hidden = !openFile.dirty;
        });
    }

    function slugFromPath(p) {
        const m = p.match(/page-content\/(.+)\.php$/);
        return m ? m[1] : '';
    }

    document.getElementById('anchor-ide-save').addEventListener('click', async () => {
        // Save via existing /files/page/{slug} POST endpoint (Phase 1).
        if (!openFile || !openFile.writable) return;
        const slug = slugFromPath(openFile.path);
        if (!slug) { alert('Manual save only supports page-content/*.php in Phase 4A.'); return; }
        try {
            const r = await fetch(cfg.restBase + 'files/page/' + encodeURIComponent(slug), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                body: JSON.stringify({ contents: editor.getValue() }),
            });
            const d = await r.json();
            if (!r.ok || d.error || d.success === false) {
                alert('Save failed: ' + (d.error || r.status));
                return;
            }
            openFile.contents = editor.getValue();
            openFile.dirty = false;
            document.querySelector('.anchor-ide-editor-dirty').hidden = true;
        } catch (err) {
            alert('Save failed: ' + err.message);
        }
    });

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            document.getElementById('anchor-ide-save').click();
        }
    });

    // ─── Chat panel ────────────────────────────────────────────────
    const msgList = document.getElementById('anchor-ide-chat-messages');
    function renderMessage(detail) {
        const div = document.createElement('div');
        div.className = 'anchor-msg anchor-msg-' + detail.role;
        div.textContent = detail.text;
        msgList.appendChild(div);
        msgList.scrollTop = msgList.scrollHeight;
    }

    function renderPlan(detail) {
        const div = document.createElement('div');
        div.className = 'anchor-plan';
        div.innerHTML = '<div class="anchor-plan-summary"></div><div class="anchor-plan-steps"></div><button class="button button-primary anchor-plan-approve">Approve</button>';
        div.querySelector('.anchor-plan-summary').textContent = detail.plan.summary;
        const stepsEl = div.querySelector('.anchor-plan-steps');
        detail.plan.steps.forEach((step, i) => {
            const row = document.createElement('label');
            row.className = 'anchor-plan-step';
            row.innerHTML = `
                <input type="checkbox" data-step-index="${i}" checked>
                <span class="anchor-step-tool">${step.tool}</span>
                <span class="anchor-step-rationale" title="${escapeAttr(step.rationale)}">${escapeHtml(step.rationale)}</span>
            `;
            stepsEl.appendChild(row);
        });
        div.querySelector('.anchor-plan-approve').addEventListener('click', () => {
            const keep = Array.from(stepsEl.querySelectorAll('input[type="checkbox"]')).map(cb => cb.checked);
            const modifiedPlan = {
                ...detail.plan,
                steps: detail.plan.steps.filter((_, i) => keep[i]),
            };
            // Ensure done step is preserved
            if (modifiedPlan.steps.length === 0 || modifiedPlan.steps[modifiedPlan.steps.length - 1].tool !== 'done') {
                alert('Plan must end with the "done" step.');
                return;
            }
            div.querySelector('.anchor-plan-approve').disabled = true;
            agent.approve(modifiedPlan);
        });
        msgList.appendChild(div);
        msgList.scrollTop = msgList.scrollHeight;
    }

    function renderStepResult(detail) {
        const div = document.createElement('div');
        div.className = 'anchor-step-result ' + (detail.skipped ? 'is-skipped' : detail.success ? 'is-success' : 'is-failure');
        const glyph = detail.skipped ? '⊘' : detail.success ? '✓' : '✗';
        div.textContent = `${glyph} ${detail.tool}` + (detail.error ? ` — ${detail.error}` : '');
        msgList.appendChild(div);
        msgList.scrollTop = msgList.scrollHeight;
    }

    function renderDone(detail) {
        if (detail.halted) {
            renderMessage({ role: 'agent', text: 'Halted: ' + (detail.halt_reason || 'unknown') });
        }
        // Refresh tree so new files show up.
        loadTree();
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function escapeAttr(s) { return escapeHtml(s); }

    agent.addEventListener('message', e => renderMessage(e.detail));
    agent.addEventListener('plan', e => renderPlan(e.detail));
    agent.addEventListener('step-result', e => renderStepResult(e.detail));
    agent.addEventListener('done', e => renderDone(e.detail));

    document.getElementById('anchor-ide-chat-send').addEventListener('click', sendChat);
    document.getElementById('anchor-ide-chat-text').addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); sendChat(); }
    });
    function sendChat() {
        const ta = document.getElementById('anchor-ide-chat-text');
        const text = ta.value.trim();
        if (!text) return;
        ta.value = '';
        agent.send(text, {
            open_files: openFile ? [openFile.path] : [],
            current_page_slug: openFile ? slugFromPath(openFile.path) : '',
        });
    }

    document.getElementById('anchor-ide-chat-new').addEventListener('click', () => {
        msgList.innerHTML = '';
    });

    loadTree();
})();
