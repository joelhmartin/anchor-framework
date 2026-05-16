/**
 * Anchor Editor — Paste-HTML-as-section modal.
 *
 * createPasteSection({ restBase, nonce, getOpenFile, onSuccess }).
 * getOpenFile: callback returning { path, slug } or null.
 * onSuccess({ path, target, slug }): called after successful paste.
 */

export function createPasteSection({ restBase, nonce, getOpenFile, onSuccess }) {
    let overlay = null;

    function build() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.className = 'anchor-modal';
        overlay.hidden = true;
        overlay.innerHTML = `
            <div class="anchor-modal-backdrop"></div>
            <div class="anchor-modal-panel anchor-paste-modal">
                <h2 class="anchor-modal-title">Paste section HTML</h2>
                <label>HTML
                    <textarea class="anchor-paste-html" rows="12" placeholder="<section class=&quot;...&quot;>…</section>"></textarea>
                </label>
                <fieldset class="anchor-paste-target">
                    <legend>Target</legend>
                    <label><input type="radio" name="anchor-paste-target" value="append" checked /> Append to current file</label>
                    <label><input type="radio" name="anchor-paste-target" value="new-page" /> Create new page</label>
                </fieldset>
                <label class="anchor-paste-slug" hidden>Slug
                    <input type="text" class="anchor-paste-slug-input" placeholder="pricing" />
                </label>
                <p class="anchor-modal-error" hidden></p>
                <div class="anchor-modal-actions">
                    <button type="button" class="button" data-action="cancel">Cancel</button>
                    <button type="button" class="button button-primary" data-action="apply">Apply</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.anchor-modal-backdrop').addEventListener('click', close);
        overlay.querySelector('[data-action="cancel"]').addEventListener('click', close);
        overlay.querySelector('[data-action="apply"]').addEventListener('click', apply);
        overlay.querySelectorAll('input[name="anchor-paste-target"]').forEach(r => {
            r.addEventListener('change', e => {
                overlay.querySelector('.anchor-paste-slug').hidden = e.target.value !== 'new-page';
            });
        });
    }

    function open() {
        build();
        overlay.querySelector('.anchor-paste-html').value = '';
        overlay.querySelector('.anchor-paste-slug-input').value = '';
        overlay.querySelectorAll('input[name="anchor-paste-target"]').forEach(r => {
            r.checked = r.value === 'append';
        });
        overlay.querySelector('.anchor-paste-slug').hidden = true;
        overlay.querySelector('.anchor-modal-error').hidden = true;
        overlay.hidden = false;
        setTimeout(() => overlay.querySelector('.anchor-paste-html').focus(), 10);
    }
    function close() { if (overlay) overlay.hidden = true; }

    async function apply() {
        const html = overlay.querySelector('.anchor-paste-html').value;
        const target = overlay.querySelector('input[name="anchor-paste-target"]:checked').value;
        const errEl = overlay.querySelector('.anchor-modal-error');
        if (!html.trim()) {
            errEl.hidden = false;
            errEl.textContent = 'Paste some HTML first.';
            return;
        }
        let slug = '';
        if (target === 'append') {
            const f = getOpenFile && getOpenFile();
            if (!f || !f.slug) {
                errEl.hidden = false;
                errEl.textContent = 'No page-content file is open to append to.';
                return;
            }
            slug = f.slug;
        } else {
            slug = overlay.querySelector('.anchor-paste-slug-input').value.trim();
            if (!/^[a-z0-9_\-/]+$/.test(slug)) {
                errEl.hidden = false;
                errEl.textContent = 'Slug must be lowercase letters, digits, dash, underscore, slash.';
                return;
            }
        }
        try {
            const r = await fetch(restBase + 'editor/paste-section', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ html, target, slug }),
            });
            const data = await r.json();
            if (!r.ok || data.error) {
                errEl.hidden = false;
                errEl.textContent = data.error || ('HTTP ' + r.status);
                return;
            }
            close();
            if (typeof onSuccess === 'function') onSuccess({ path: data.path, target, slug });
        } catch (err) {
            errEl.hidden = false;
            errEl.textContent = 'Error: ' + err.message;
        }
    }

    return { open, close };
}
