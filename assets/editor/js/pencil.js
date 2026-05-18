/**
 * Anchor Editor — front-end pencil overlay (Phase 4 rewrite).
 *
 * Renders only on Anchor-managed pages (gated server-side in should_load()).
 * Floating FA pen-to-square button → slide-over drawer with:
 *   - Page info header (title, slug, flag chips)
 *   - Simple chat (send → reply, no plan-execute UI)
 *   - "Open full editor" link
 *
 * No Files section. No agent-core import.
 */

(function () {
  const cfg = window.anchorPencil;
  if (!cfg || !cfg.postId) return;

  // ── 1. Floating pencil button ──────────────────────────────────────────────
  const btn = document.createElement('button');
  btn.className = 'anchor-pencil-btn';
  btn.type = 'button';
  btn.setAttribute('aria-label', 'Open Anchor editor');
  btn.innerHTML = '<i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>';
  document.body.appendChild(btn);

  // ── 2. Slide-over drawer ───────────────────────────────────────────────────
  const drawer = document.createElement('div');
  drawer.className = 'anchor-pencil-drawer';
  drawer.hidden = true;
  drawer.setAttribute('role', 'dialog');
  drawer.setAttribute('aria-label', 'Anchor Editor');
  drawer.innerHTML = `
    <header class="apd-header">
      <h2>Anchor Editor</h2>
      <button class="apd-close" type="button" aria-label="Close">&times;</button>
    </header>
    <section class="apd-page-info">
      <strong>${esc(cfg.title)}</strong>
      <code>/${esc(cfg.slug)}</code>
      <div class="apd-chips">
        <button class="apd-chip" type="button" data-flag="no_header"></button>
        <button class="apd-chip" type="button" data-flag="no_footer"></button>
      </div>
    </section>
    <section class="apd-chat" id="apd-chat"></section>
    <footer class="apd-footer">
      <a class="apd-link" href="${esc(cfg.editUrl)}">Open full editor &rarr;</a>
    </footer>
  `;
  document.body.appendChild(drawer);

  // ── 3. Flag chips ──────────────────────────────────────────────────────────
  function updateChip(chip, off) {
    chip.classList.toggle('is-off', !!off);
    chip.textContent =
      (chip.dataset.flag === 'no_header' ? 'Header' : 'Footer') +
      (off ? ' off' : ' on');
  }

  drawer.querySelectorAll('.apd-chip').forEach(function (chip) {
    var flag = chip.dataset.flag;
    updateChip(chip, !!(cfg.flags && cfg.flags[flag]));

    chip.addEventListener('click', async function () {
      var next = !chip.classList.contains('is-off'); // next state = toggled
      // Build the full flags object from current DOM state, then apply the toggle.
      var body = {
        no_header: drawer.querySelector('.apd-chip[data-flag="no_header"]').classList.contains('is-off'),
        no_footer: drawer.querySelector('.apd-chip[data-flag="no_footer"]').classList.contains('is-off'),
      };
      body[flag] = next;

      try {
        var r = await fetch(
          cfg.restBase + 'editor/page-flags/' + encodeURIComponent(cfg.slug),
          {
            method: 'POST',
            headers: {
              'X-WP-Nonce': cfg.nonce,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
          }
        );
        if (!r.ok) throw new Error('flags ' + r.status);
        cfg.flags = body;
        updateChip(chip, next);
        location.reload();
      } catch (err) {
        console.error('[anchor-pencil] flag toggle failed', err);
      }
    });
  });

  // ── 4. Open / close ────────────────────────────────────────────────────────
  btn.addEventListener('click', function () { drawer.hidden = false; });
  drawer.querySelector('.apd-close').addEventListener('click', function () { drawer.hidden = true; });

  // ── 5. Chat ────────────────────────────────────────────────────────────────
  (function initChat() {
    var root = drawer.querySelector('#apd-chat');
    root.innerHTML = [
      '<div class="apd-chat-messages" id="apd-chat-messages"></div>',
      '<form class="apd-chat-form" id="apd-chat-form">',
      '  <textarea placeholder="What should change on this page?" rows="2"></textarea>',
      '  <button type="submit">Send</button>',
      '</form>',
    ].join('');

    var list    = root.querySelector('#apd-chat-messages');
    var form    = root.querySelector('#apd-chat-form');
    var input   = form.querySelector('textarea');
    var history = [];

    function push(role, content) {
      history.push({ role: role, content: content });
      var el = document.createElement('div');
      el.className = 'apd-msg apd-msg-' + role;
      el.textContent = content;
      list.appendChild(el);
      list.scrollTop = list.scrollHeight;
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var msg = input.value.trim();
      if (!msg) return;
      input.value = '';
      push('user', msg);

      try {
        var r = await fetch(cfg.restBase + 'ai/chat', {
          method: 'POST',
          headers: {
            'X-WP-Nonce': cfg.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            message:   msg,
            history:   history,
            page_slug: cfg.slug,
            post_id:   cfg.postId,
          }),
        });
        var data  = await r.json();
        var reply = data.reply || data.assistant_text || data.message || data.text || JSON.stringify(data);
        push('assistant', reply);
      } catch (err) {
        push('assistant', 'Error: ' + err.message);
      }
    });

    input.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        form.requestSubmit();
      }
    });
  })();

  // ── Helpers ────────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
})();
