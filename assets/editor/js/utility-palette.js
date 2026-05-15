/**
 * Anchor Editor — utility-class search palette.
 *
 * Exports createUtilityPalette({ restBase, nonce, getMonacoEditor }).
 * getMonacoEditor is a callback that returns the current Monaco
 * editor instance or null. Allows Insert-at-cursor when a file is
 * open.
 */

export function createUtilityPalette({ restBase, nonce, getMonacoEditor }) {
    let classes = [];
    let overlay = null;
    let visible = false;

    async function load() {
        if (classes.length) return classes;
        try {
            const r = await fetch(restBase + 'editor/utilities', { headers: { 'X-WP-Nonce': nonce } });
            const data = await r.json();
            if (r.ok && Array.isArray(data.classes)) classes = data.classes;
        } catch (e) { /* leave empty */ }
        return classes;
    }

    function build() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'anchor-utility-palette';
        overlay.hidden = true;
        overlay.innerHTML = `
            <div class="anchor-utility-backdrop"></div>
            <div class="anchor-utility-panel">
                <input type="search" class="anchor-utility-search" placeholder="Search utility classes (e.g. grid, py, text)…" />
                <ul class="anchor-utility-list"></ul>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('.anchor-utility-backdrop').addEventListener('click', close);
        const search = overlay.querySelector('.anchor-utility-search');
        search.addEventListener('input', () => render(search.value));
        document.addEventListener('keydown', e => {
            if (visible && e.key === 'Escape') close();
        });
        return overlay;
    }

    function render(filter) {
        const list = overlay.querySelector('.anchor-utility-list');
        list.innerHTML = '';
        const needle = filter.trim().toLowerCase();
        const groups = {};
        classes.forEach(c => {
            const hay = (c.class + ' ' + (c.description || '') + ' ' + (c.family || '')).toLowerCase();
            if (needle && hay.indexOf(needle) === -1) return;
            const family = c.family || 'General';
            (groups[family] = groups[family] || []).push(c);
        });
        Object.keys(groups).sort().forEach(family => {
            const head = document.createElement('li');
            head.className = 'anchor-utility-family';
            head.textContent = family;
            list.appendChild(head);
            groups[family].forEach(c => {
                const li = document.createElement('li');
                li.className = 'anchor-utility-row';
                li.innerHTML = `
                    <code class="anchor-utility-class"></code>
                    <span class="anchor-utility-desc"></span>
                    <button type="button" class="anchor-utility-copy" title="Copy class name">Copy</button>
                    <button type="button" class="anchor-utility-insert" title="Insert at cursor">Insert</button>
                `;
                li.querySelector('.anchor-utility-class').textContent = c.class;
                li.querySelector('.anchor-utility-desc').textContent = c.description || '';
                li.querySelector('.anchor-utility-copy').addEventListener('click', () => copyClass(c.class));
                li.querySelector('.anchor-utility-insert').addEventListener('click', () => insertAtCursor(c.class));
                list.appendChild(li);
            });
        });
    }

    function copyClass(cls) {
        const snippet = cls;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(snippet);
        } else {
            const ta = document.createElement('textarea');
            ta.value = snippet;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (_) {}
            document.body.removeChild(ta);
        }
    }

    function insertAtCursor(cls) {
        const ed = getMonacoEditor && getMonacoEditor();
        if (!ed) return;
        const sel = ed.getSelection();
        ed.executeEdits('anchor-utility-insert', [{
            range: sel,
            text: cls,
            forceMoveMarkers: true,
        }]);
        ed.focus();
    }

    async function open() {
        build();
        await load();
        overlay.hidden = false;
        visible = true;
        const s = overlay.querySelector('.anchor-utility-search');
        s.value = '';
        render('');
        setTimeout(() => s.focus(), 10);
    }
    function close() { if (overlay) overlay.hidden = true; visible = false; }
    function toggle() { visible ? close() : open(); }

    return { open, close, toggle };
}
