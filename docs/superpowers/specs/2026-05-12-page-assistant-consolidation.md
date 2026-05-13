# Page Assistant Consolidation — Design Spec

**Status:** Draft for review
**Date:** 2026-05-12
**Author:** Implementation agent (per master plan §5.2)
**Source of truth for recon:** `docs/page-assistant-recon.md` (committed `29774ed`)
**Drives:** the implementation plan at `docs/superpowers/plans/2026-05-12-page-assistant-consolidation.md` (to be written next).

---

## 1. Goal

Merge the standalone `anchor-page-assistant` WordPress plugin into the `anchor-framework` parent theme without losing any user-visible functionality, then decommission the plugin. This is **Phase 1** of the master plan. No new features — only relocation, rename, and surgical cleanup of pre-existing defects.

## 2. Scope

### In scope

- Move every surviving plugin source file to a destination inside the framework theme.
- Rename every `APA_*` class to the new prefixed conventions (see §5).
- Rehome admin UI under a new top-level `Anchor` menu.
- Replace `plugin_dir_*` / `APA_PLUGIN_*` constants with theme-relative equivalents.
- Drop the plugin's bundled `plugin-update-checker` and `includes/updater.php`; rely on the framework theme's existing updater.
- Drop the section registry/schema/REST-sections code path entirely.
- Fix the latent `APA_AI_Handler::MODEL` fatal in `api/class-rest-ai.php`.
- Remove all hard-coded `deka-*` references from the prompt builder and any CSS allowlist.
- Deactivate the plugin in the sandbox install and verify the theme handles everything.
- Tag a final standalone plugin release and add a `DEPRECATED.md` to the plugin repo.

### Out of scope (deferred or off-limits)

- Any new editor feature work — that is Phase 4.
- REST namespace rename (`anchor-assistant/v1` stays put; renamed in Phase 4 when the editor UI is touched).
- Any change to the `Anchor Tools` plugin (Phase 3 territory).
- Migration of the live Deka site (Phase 5).
- Performance work, refactors not necessary to the merge, or test-suite scaffolding (the plugin has none today; we don't add one in Phase 1).

## 3. Locked decisions

These were settled during brainstorming on 2026-05-12 and are not up for revision in the implementation plan:

| Question | Decision |
|---|---|
| Fate of section registry/schema/REST-sections | **Drop entirely.** All three files plus any prompt-builder references die. |
| Admin menu placement | **New top-level menu `Anchor`** (slug `anchor`, dashicon `dashicons-anchor`, position 25). Sub-pages: Editor, Settings, Config. |
| Updater strategy | **Drop plugin updater and its vendor copy.** Theme's existing `inc/vendor/plugin-update-checker/` handles updates for the merged code. |
| Cleanup of pre-existing defects | **Fix during merge** — both the `MODEL` constant fatal and the `deka-*` references. |
| Class prefix convention | **Split:** `Anchor_Editor_*` for editor/admin/REST surfaces; `Anchor_AI_*` for AI provider/handler/prompt-builder. |

## 4. Destination layout

The merge lands under three new directories inside the framework theme:

```
wp-content/themes/anchor-framework/
├── inc/
│   ├── editor/
│   │   ├── bootstrap.php
│   │   ├── admin/
│   │   │   ├── class-editor-page.php
│   │   │   ├── class-settings-page.php
│   │   │   └── class-config-manager.php
│   │   ├── frontend/
│   │   │   └── class-frontend-chat.php
│   │   ├── api/
│   │   │   ├── class-rest-ai.php
│   │   │   ├── class-rest-config.php
│   │   │   ├── class-rest-files.php
│   │   │   ├── class-rest-history.php
│   │   │   ├── class-rest-media.php
│   │   │   └── class-rest-menus.php
│   │   ├── class-file-writer.php
│   │   ├── class-config-writer.php
│   │   └── templates/
│   │       ├── admin-page.php
│   │       └── live-editor.php
│   └── ai/
│       ├── class-ai-handler.php
│       └── class-prompt-builder.php
└── assets/editor/
    ├── css/{admin,code-editor,frontend-chat,live-editor}.css
    └── js/{admin-app,chat-widget,code-editor,frontend-chat,inline-edit,live-editor}.js
```

`functions.php` in the theme gains a single `require_once` for `inc/editor/bootstrap.php`, which performs the same load order as the plugin's main file did (recon §1).

## 5. Class / file rename map

| Plugin source | Framework destination | Class rename |
|---|---|---|
| `anchor-page-assistant.php` (bootstrap body) | `inc/editor/bootstrap.php` | function-scope; no class |
| `admin/class-admin-page.php` | `inc/editor/admin/class-editor-page.php` | `APA_Admin_Page` → `Anchor_Editor_Page` |
| `admin/class-settings-page.php` | `inc/editor/admin/class-settings-page.php` | `APA_Settings_Page` → `Anchor_Editor_Settings` |
| `admin/class-config-manager.php` | `inc/editor/admin/class-config-manager.php` | `APA_Config_Manager` → `Anchor_Editor_Config_Manager` |
| `admin/class-frontend-chat.php` | `inc/editor/frontend/class-frontend-chat.php` | `APA_Frontend_Chat` → `Anchor_Editor_Frontend_Chat` |
| `admin/class-ai-handler.php` | `inc/ai/class-ai-handler.php` | `APA_AI_Handler` → `Anchor_AI_Handler` |
| `includes/class-prompt-builder.php` | `inc/ai/class-prompt-builder.php` | `APA_Prompt_Builder` → `Anchor_AI_Prompt_Builder` |
| `includes/class-file-writer.php` | `inc/editor/class-file-writer.php` | `APA_File_Writer` → `Anchor_Editor_File_Writer` |
| `includes/class-config-writer.php` | `inc/editor/class-config-writer.php` | `APA_Config_Writer` → `Anchor_Editor_Config_Writer` |
| `api/class-rest-ai.php` | `inc/editor/api/class-rest-ai.php` | `APA_REST_AI` → `Anchor_Editor_REST_AI` |
| `api/class-rest-config.php` | `inc/editor/api/class-rest-config.php` | `APA_REST_Config` → `Anchor_Editor_REST_Config` |
| `api/class-rest-files.php` | `inc/editor/api/class-rest-files.php` | `APA_REST_Files` → `Anchor_Editor_REST_Files` |
| `api/class-rest-history.php` | `inc/editor/api/class-rest-history.php` | `APA_REST_History` → `Anchor_Editor_REST_History` |
| `api/class-rest-media.php` | `inc/editor/api/class-rest-media.php` | `APA_REST_Media` → `Anchor_Editor_REST_Media` |
| `api/class-rest-menus.php` | `inc/editor/api/class-rest-menus.php` | `APA_REST_Menus` → `Anchor_Editor_REST_Menus` |
| `assets/css/*` | `assets/editor/css/*` (filename unchanged) | n/a |
| `assets/js/*` | `assets/editor/js/*` (filename unchanged) | n/a |
| `templates/admin-page.php` | `inc/editor/templates/admin-page.php` | n/a |
| `templates/live-editor.php` | `inc/editor/templates/live-editor.php` | n/a |

**Asset handles** also re-prefix: `apa-*` → `anchor-editor-*`. This requires matching changes in any `wp_localize_script` calls that JS code reads (full list comes out of recon §11).

## 6. Constant / URL migration

| Old reference | New reference |
|---|---|
| `APA_VERSION` | `Anchor_Editor::VERSION` (a small class constant in `bootstrap.php` set from theme `style.css` version) |
| `APA_PLUGIN_DIR` | `Anchor_Editor::path()` returning `get_template_directory() . '/inc/editor/'` |
| `APA_PLUGIN_URL` | `Anchor_Editor::url()` returning `get_template_directory_uri() . '/'` |
| `plugin_dir_url( __FILE__ )` inside the rehomed files | `get_template_directory_uri() . '/assets/editor/'` |
| `plugin_dir_path( __FILE__ )` inside the rehomed files | `get_template_directory() . '/inc/editor/'` |
| `ANCHOR_PAGE_ASSISTANT_GH_TOKEN` (private-repo token in updater) | n/a — updater dies |

`Anchor_Editor` is a small static helper class introduced for path/url/version access. It replaces the loose `APA_*` constants with a single namespace.

## 7. Admin UI registration

A new top-level `Anchor` menu replaces the plugin's current menu/submenu structure. Registration happens in `inc/editor/admin/class-editor-page.php` (the renamed `class-admin-page.php`).

- **Menu page**: `add_menu_page( 'Anchor', 'Anchor', 'manage_options', 'anchor', [ $this, 'render' ], 'dashicons-anchor', 25 )`
- **Submenus**: `Editor` (default — same page as parent), `Settings`, `Config`
- Hook screen IDs change. Asset enqueues currently keyed off the plugin's screen ID (recon §2) must be re-keyed off the new screen IDs: `toplevel_page_anchor`, `anchor_page_anchor-settings`, `anchor_page_anchor-config`.
- Capability: `manage_options` preserved unless recon §14 found per-page exceptions; the implementation plan re-verifies each `current_user_can()` call.

## 8. REST endpoint preservation

All 23 routes under `anchor-assistant/v1` survive the merge *minus* the 3 routes from `class-rest-sections.php` which die with the section schema. Final count: **20 routes across 6 controllers**.

- Namespace **stays** `anchor-assistant/v1` so frontend JS continues to work unchanged. Renamed in Phase 4.
- Each controller class registers its routes on `rest_api_init` — that hook fires in theme context exactly as in plugin context.
- Capability checks (`current_user_can`) preserved verbatim per controller per recon §14.
- `Anchor_Editor_REST_AI::MODEL` constant **is defined** as part of this merge (see §10), fixing the latent fatal on `GET /ai/settings`.

## 9. Updater & vendor consolidation

- `includes/updater.php` and `vendor/plugin-update-checker/` (the plugin copies) are not migrated. They are deleted from the plugin tree as part of the deprecation commit on `feat/migrate-to-theme`.
- The framework theme's existing `inc/vendor/plugin-update-checker/` and its existing theme-updater registration cover the merged code. No new updater code is added.
- The retired plugin's GitHub release feed (`joelhmartin/anchor-page-assistant`) is left alone; the deprecated plugin's final release notes will point users to the framework theme's update channel.

## 10. Cleanup of pre-existing defects

Both cleanups land inside the merge work, not as separate followups.

### 10.1 Latent `MODEL` fatal

`api/class-rest-ai.php:103` returns `APA_AI_Handler::MODEL`. The constant is not defined in the plugin (recon §4). The new `Anchor_AI_Handler` class defines:

```php
const MODEL = 'claude-sonnet-4-6';
```

(`claude-sonnet-4-6` is the current Claude Sonnet model ID. The user may override via the Settings page; this constant exists only so `GET /ai/settings` returns a sane default instead of fataling.) This is a default surfaced by the `GET /ai/settings` endpoint; users override via the existing settings page which writes to the existing `apa_ai_*` option keys. Option keys are **not** renamed in this phase to keep the merge safe — Phase 4 can rename them with a one-shot migration.

### 10.2 `deka-*` references

The recon agent reported `deka-home.css` and `deka-shop.css` are referenced from the prompt builder and a CSS allowlist (locations to be re-confirmed by the implementer subagent against recon §15).

Cleanup approach:

1. Remove the hard-coded `deka-*` filenames entirely. The sandbox install has no Deka files anyway.
2. Replace with a filter-driven allowlist: `apply_filters( 'anchor_editor/css_allowlist', [] )`. Default empty. Child themes / Anchor Tools can extend.
3. Update the prompt builder to read from the same filter so the AI's awareness of project CSS stays generic.

If the implementer subagent finds a *runtime dependency* on those exact filenames (e.g., a fallback that loads `deka-home.css` for the home page), it must stop and surface — the spec assumes the references are advisory (in the prompt) plus an allowlist (gating writes), not load-time required.

## 11. What dies

Files removed from the framework destination (never migrated):

- `admin/class-section-registry.php` (legacy section system)
- `includes/class-section-schema.php` (legacy section system)
- `api/class-rest-sections.php` (legacy section system)
- `includes/updater.php` (theme self-updates)
- `vendor/plugin-update-checker/` (plugin copy — theme already has this lib)

Symbols retired:

- All `APA_*` class names.
- All `APA_*` defined constants.
- All `apa-*` script/style handles.

Functions / hooks removed from the plugin during deprecation commit:

- The plugin's main file becomes a deprecation notice only (see §13).
- All `require_once` chains in the plugin are removed; remaining file is just a header + admin notice.

## 12. Hook coupling with the framework

Recon §12 lists the framework hooks the plugin attaches to and any plugin-side hooks the framework theme attaches to. The implementer subagent re-reads recon §12 at start and preserves every listed pairing during the rename. Pairings are not enumerated here to avoid drift — the recon doc is the source of truth.

If recon §12 listed no cross-package hook contracts (i.e., the plugin and theme communicate only through WP core hooks), this section is a no-op.

## 13. Decommission steps

Performed on `feat/migrate-to-theme` branch of the plugin repo, after the framework's merge work is verified working:

1. Replace `anchor-page-assistant.php` body with: plugin header preserved, a single admin notice "This plugin is deprecated; functionality has merged into the Anchor Framework theme. Please deactivate.", no class loading, no hook registration.
2. Delete every other file in the plugin repo (`admin/`, `api/`, `includes/`, `assets/`, `templates/`, `vendor/`, `templates/`).
3. Add `DEPRECATED.md` at the plugin root explaining the merge, pointing to the framework theme repo.
4. Tag the immediately-prior commit as `v1.0.0-final` on the plugin repo.
5. Push branch + tag to the plugin upstream.

In the sandbox: `wp plugin deactivate anchor-page-assistant`. Verify consolidated editor still works.

## 14. Rollback plan

The merge is reversible at every step before the plugin decommission commit lands:

- **During framework merge work**: `git reset --keep <pre-merge SHA>` on `feat/consolidation-prep` removes the framework changes. The plugin code on disk is unchanged. Reactivate plugin and resume.
- **After decommission commit but before pushing**: revert the decommission commit on `feat/migrate-to-theme`, and revert the framework merge work as above.
- **After push but before user merges to `main`**: rollback by reverting the feature branches; `main` on both repos is untouched.

The hard cutoff is when the user merges either feature branch to `main`. Past that, rollback is a new commit, not a reset.

## 15. Acceptance criteria

Master plan §5.2 baseline plus additions from the recon findings:

- [ ] Top-level `Anchor` menu appears in WP admin with Editor / Settings / Config submenus.
- [ ] Editor admin page loads without PHP error.
- [ ] Settings page loads and saves AI credentials (same option keys as before).
- [ ] Config page loads and persists changes.
- [ ] Frontend chat appears for users with `manage_options` (or whatever capability recon §3 records) on the front end.
- [ ] Chat can send a prompt and receive a non-error response.
- [ ] File writer can save a change to a theme file (with appropriate auth).
- [ ] Config writer can update a config file.
- [ ] `GET /wp-json/anchor-assistant/v1/ai/settings` returns 200 (latent fatal fixed).
- [ ] All 20 surviving REST routes respond (no 404s; auth required where it was required).
- [ ] No `deka-*` strings appear anywhere under `wp-content/themes/anchor-framework/`.
- [ ] No `APA_` symbols remain anywhere under `wp-content/themes/anchor-framework/`.
- [ ] Sandbox plugin `anchor-page-assistant` is deactivated; activating it shows the deprecation notice without registering any conflicting hooks.
- [ ] No new entries in `wp-content/debug.log` on a full admin + front-end smoke test.

## 16. Open items for the implementer subagent (not blockers)

These are spec-level "to be confirmed during implementation against recon" notes — they don't block writing the plan, but the implementer must reconfirm:

1. Exact line(s) in `class-prompt-builder.php` and the CSS allowlist where `deka-*` appear (recon §15 cited "hard-coded in the prompt builder and a CSS allowlist" without exact locations).
2. Per-controller capability strings (recon §14 is canonical).
3. The `wp_localize_script` data shape — if a localized object name is `apa*`, decide whether to rename to `anchorEditor*` now (preferred, since `assets/editor/js/*` already gets handle-renamed) or defer to Phase 4.
4. Whether the `apa_ai_*` option keys carry user-supplied data in the sandbox. If yes (user has set up credentials), the migration leaves the keys; if no, the implementer can rename to `anchor_ai_*` in this phase — adds one safe migration step.

The plan-writing step will collapse these into concrete tasks once the implementer reconfirms.

---

*End of spec.*
