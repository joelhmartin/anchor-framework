/**
 * Anchor Editor — front-end pencil overlay.
 *
 * Slide-over panel with files-for-this-page list + chat (plan-execute).
 * No Monaco. Activated by a pencil-icon toggle button.
 */

import { createAgentSession } from './agent-core.js';

(function () {
    if (typeof window.anchorPencil === 'undefined') return;
    const cfg = window.anchorPencil;

    // Build the toggle button and slide-over panel.
    const toggle = document.createElement('button');
    toggle.className = 'anchor-pencil-toggle';
    toggle.innerHTML = '✏️';
    toggle.title = 'Anchor Editor';
    document.body.appendChild(toggle);

    const panel = document.createElement('div');
    panel.className = 'anchor-pencil-panel';
    panel.innerHTML = `
        <div class="anchor-pencil-header">
            <strong>Anchor Editor</strong>
            <button class="anchor-pencil-close" title="Close">×</button>
        </div>
        <div class="anchor-pencil-files" id="anchor-pencil-files">
            <h4>Files</h4>
            <ul></ul>
        </div>
        <div class="anchor-pencil-viewer" id="anchor-pencil-viewer" hidden></div>
        <div class="anchor-pencil-chat" id="anchor-pencil-chat">
            <div class="anchor-pencil-messages" id="anchor-pencil-msgs"></div>
            <div class="anchor-pencil-input">
                <textarea id="anchor-pencil-text" rows="2" placeholder="Tell the agent what to do…"></textarea>
                <button class="button button-primary" id="anchor-pencil-send">Send</button>
            </div>
        </div>
    `;
    document.body.appendChild(panel);

    toggle.addEventListener('click', () => panel.classList.toggle('is-open'));
    panel.querySelector('.anchor-pencil-close').addEventListener('click', () => panel.classList.remove('is-open'));

    // Populate files-for-this-page list from cfg.files (localized server-side).
    const filesUl = panel.querySelector('#anchor-pencil-files ul');
    (cfg.files || []).forEach(f => {
        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.textContent = f.label || f.path;
        btn.addEventListener('click', () => viewFile(f));
        li.appendChild(btn);
        filesUl.appendChild(li);
    });

    async function viewFile(f) {
        const v = document.getElementById('anchor-pencil-viewer');
        v.hidden = false;
        v.innerHTML = `<div class="anchor-pencil-viewer-path">${escapeHtml(f.path)}</div><pre class="anchor-pencil-viewer-pre">Loading…</pre>`;
        // Use the same /files/page/{slug} endpoint when path is page-content.
        const slug = (f.path.match(/page-content\/(.+)\.php$/) || [])[1];
        if (!slug) {
            v.querySelector('pre').textContent = '(read endpoint not yet available for this file type)';
            return;
        }
        try {
            const r = await fetch(cfg.restBase + 'files/page/' + encodeURIComponent(slug), {
                headers: { 'X-WP-Nonce': cfg.nonce },
            });
            const data = await r.json();
            if (!r.ok || data.error) {
                v.querySelector('pre').textContent = 'Error: ' + (data.error || r.status);
                return;
            }
            v.querySelector('pre').textContent = data.contents || '(empty)';
        } catch (err) {
            v.querySelector('pre').textContent = 'Error: ' + err.message;
        }
    }

    // Chat — same plan-execute UX as IDE, just no Monaco.
    const agent = createAgentSession({ restBase: cfg.restBase, nonce: cfg.nonce });
    const msgList = document.getElementById('anchor-pencil-msgs');

    agent.addEventListener('message', e => {
        const d = document.createElement('div');
        d.className = 'anchor-msg anchor-msg-' + e.detail.role;
        d.textContent = e.detail.text;
        msgList.appendChild(d);
        msgList.scrollTop = msgList.scrollHeight;
    });

    agent.addEventListener('plan', e => {
        const box = document.createElement('div');
        box.className = 'anchor-plan';
        box.innerHTML = '<div class="anchor-plan-summary"></div><div class="anchor-plan-steps"></div><button class="button button-primary anchor-plan-approve">Approve</button>';
        box.querySelector('.anchor-plan-summary').textContent = e.detail.plan.summary;
        const stepsEl = box.querySelector('.anchor-plan-steps');
        e.detail.plan.steps.forEach((step, i) => {
            const r = document.createElement('label');
            r.className = 'anchor-plan-step';
            r.innerHTML = `<input type="checkbox" data-step-index="${i}" checked> <span class="anchor-step-tool">${step.tool}</span> <span class="anchor-step-rationale">${escapeHtml(step.rationale)}</span>`;
            stepsEl.appendChild(r);
        });
        box.querySelector('.anchor-plan-approve').addEventListener('click', () => {
            const keep = Array.from(stepsEl.querySelectorAll('input[type="checkbox"]')).map(cb => cb.checked);
            const modified = { ...e.detail.plan, steps: e.detail.plan.steps.filter((_, i) => keep[i]) };
            if (modified.steps.length === 0 || modified.steps[modified.steps.length - 1].tool !== 'done') {
                alert('Plan must end with the "done" step.'); return;
            }
            box.querySelector('.anchor-plan-approve').disabled = true;
            agent.approve(modified);
        });
        msgList.appendChild(box);
        msgList.scrollTop = msgList.scrollHeight;
    });

    agent.addEventListener('step-result', e => {
        const d = document.createElement('div');
        d.className = 'anchor-step-result ' + (e.detail.skipped ? 'is-skipped' : e.detail.success ? 'is-success' : 'is-failure');
        d.textContent = (e.detail.skipped ? '⊘ ' : e.detail.success ? '✓ ' : '✗ ') + e.detail.tool + (e.detail.error ? ' — ' + e.detail.error : '');
        msgList.appendChild(d);
        msgList.scrollTop = msgList.scrollHeight;
    });

    agent.addEventListener('done', e => {
        if (e.detail.halted) {
            const d = document.createElement('div');
            d.className = 'anchor-msg anchor-msg-agent';
            d.textContent = 'Halted: ' + (e.detail.halt_reason || 'unknown');
            msgList.appendChild(d);
        }
    });

    document.getElementById('anchor-pencil-send').addEventListener('click', send);
    document.getElementById('anchor-pencil-text').addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); send(); }
    });
    function send() {
        const ta = document.getElementById('anchor-pencil-text');
        const text = ta.value.trim();
        if (!text) return;
        ta.value = '';
        agent.send(text, {
            open_files: (cfg.files || []).map(f => f.path),
            current_page_slug: cfg.currentPageSlug || '',
        });
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
})();
