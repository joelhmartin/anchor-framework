/**
 * Anchor Editor — live preview iframe module.
 *
 * Manages a single iframe element inside the IDE editor panel. Tracks
 * the currently-displayed URL so it can:
 *   - reload after a PHP save (full iframe reload)
 *   - hot-swap a <link rel="stylesheet"> on CSS save
 *   - show a placeholder when the current file has no derivable URL
 *
 * Usage:
 *   const preview = createPreview({ container, restBase, nonce });
 *   preview.loadFile(filePath);
 *   preview.reload();
 *   preview.hotSwapCss(relativeCssPath);
 *   preview.setVisible(bool);
 */

export function createPreview({ container, restBase, nonce, homeUrl }) {
    let iframe = null;
    let placeholder = null;
    let lastPageUrl = '';     // last page URL we navigated to (for CSS hot-swap context)
    let lastCssLoadKey = 0;   // monotonic cache-buster
    let visible = false;

    build();

    function build() {
        container.innerHTML = '';
        iframe = document.createElement('iframe');
        iframe.className = 'anchor-ide-preview-iframe';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = '0';
        iframe.hidden = true;
        container.appendChild(iframe);

        placeholder = document.createElement('div');
        placeholder.className = 'anchor-ide-preview-placeholder';
        placeholder.textContent = 'No preview available for this file.';
        placeholder.hidden = true;
        container.appendChild(placeholder);
    }

    function loadFile(filePath) {
        const url = previewUrlForFile(filePath);
        if (!url) {
            iframe.src = 'about:blank';
            lastPageUrl = '';
            iframe.hidden = true;
            placeholder.hidden = !visible;
            return;
        }
        if (url !== lastPageUrl) {
            lastPageUrl = url;
            iframe.src = url;
        }
        iframe.hidden = !visible;
        placeholder.hidden = visible;
    }

    function reload() {
        if (!iframe.src) return;
        // Cache-buster query to defeat any aggressive caching during dev.
        const u = new URL(iframe.src, window.location.origin);
        u.searchParams.set('_anchor_reload', String(++lastCssLoadKey));
        iframe.src = u.toString();
    }

    function hotSwapCss(relativeCssPath) {
        // Find a <link rel="stylesheet"> in the iframe whose href contains the relative path.
        // Same-origin iframe, so DOM access is allowed.
        try {
            const doc = iframe.contentDocument;
            if (!doc) return reload();
            const links = doc.querySelectorAll('link[rel="stylesheet"]');
            const needle = relativeCssPath.replace(/^\/+/, '');
            let found = null;
            links.forEach(l => {
                if (!found && l.href && l.href.indexOf(needle) !== -1) found = l;
            });
            if (!found) return reload();
            const stamp = ++lastCssLoadKey;
            const u = new URL(found.href);
            u.searchParams.set('_v', String(stamp));
            // Replace with new <link> so the browser fetches fresh.
            const fresh = doc.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = u.toString();
            fresh.onload = () => { try { found.remove(); } catch (_) {} };
            found.parentNode.insertBefore(fresh, found.nextSibling);
        } catch (err) {
            // Cross-origin or detached iframe — fall back to full reload.
            reload();
        }
    }

    function setVisible(v) {
        visible = !!v;
        const hasUrl = !!lastPageUrl;
        iframe.hidden = !visible || !hasUrl;
        placeholder.hidden = !visible || hasUrl;
        container.parentElement.classList.toggle('anchor-ide-preview-on', visible);
    }

    /**
     * Compute a preview URL for a logical file path.
     *   child-theme/page-content/{slug}.php → home_url/{slug}/ (or home_url/ for 'home')
     *   anything else → null (no preview)
     */
    function previewUrlForFile(filePath) {
        const m = String(filePath || '').match(/page-content\/(.+)\.php$/);
        if (!m) return null;
        const slug = m[1];
        const base = (homeUrl || window.location.origin + '/').replace(/\/$/, '/');
        return slug === 'home' ? base : base + slug + '/';
    }

    return { loadFile, reload, hotSwapCss, setVisible };
}
