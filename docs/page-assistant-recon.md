# Anchor Page Assistant — Plugin Recon

Recon performed against `/Users/bif/DevKinsta/public/anchor-framework/wp-content/plugins/anchor-page-assistant/` at v1.0.0 in preparation for merging the plugin into the `anchor-framework` parent theme.

All citations use `file:line` against absolute paths within the plugin (or the theme where noted). Vendor sources under `vendor/plugin-update-checker/` were intentionally not read line-by-line.

---

## 1. Bootstrap (`anchor-page-assistant.php`)

### Plugin header fields (`anchor-page-assistant.php:2-15`)

| Field | Value |
|---|---|
| Plugin Name | Anchor Page Assistant |
| Plugin URI | https://github.com/joelhmartin/anchor-page-assistant |
| GitHub Plugin URI | joelhmartin/anchor-page-assistant |
| Description | AI-powered page builder and config manager for the Anchor Framework theme system. |
| Version | 1.0.0 |
| Author | Anchor |
| Author URI | https://github.com/joelhmartin |
| Requires PHP | 7.4 |
| Requires at least | 6.4 |
| License | GPL-2.0-or-later |
| Text Domain | (none declared) |

### Constants (`anchor-page-assistant.php:19-21`)

| Constant | Value |
|---|---|
| `APA_VERSION` | `'1.0.0'` |
| `APA_PLUGIN_DIR` | `plugin_dir_path( __FILE__ )` |
| `APA_PLUGIN_URL` | `plugin_dir_url( __FILE__ )` |

Plus `ANCHOR_PAGE_ASSISTANT_GH_TOKEN` — **expected to be defined by the user in `wp-config.php`** when a private repo token is needed (`includes/updater.php:39-41`). Not defined by the plugin itself.

### Activation / deactivation / uninstall hooks

**None observed.** Grep for `register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook` across the plugin returns no matches outside the vendor tree.

### Class loading

Manual `require_once` chain only — no autoloader. Order (`anchor-page-assistant.php:24-47`):

1. `includes/updater.php`
2. `includes/class-config-writer.php`
3. `includes/class-file-writer.php`
4. `includes/class-section-schema.php`
5. `includes/class-prompt-builder.php`
6. `admin/class-config-manager.php`
7. `admin/class-section-registry.php`
8. `admin/class-ai-handler.php`
9. `admin/class-admin-page.php`
10. `admin/class-settings-page.php`
11. `admin/class-frontend-chat.php`
12. `api/class-rest-config.php`
13. `api/class-rest-sections.php`
14. `api/class-rest-ai.php`
15. `api/class-rest-menus.php`
16. `api/class-rest-media.php`
17. `api/class-rest-history.php`
18. `api/class-rest-files.php`

The updater is included unconditionally at file load; everything else is required at file load but **only instantiates** inside `apa_init()`.

### Hooks registered at bootstrap

| Hook | Type | Priority | Callback | Source |
|---|---|---|---|---|
| `after_setup_theme` | action | 20 | `apa_init` | `anchor-page-assistant.php:76` |
| `admin_notices` | action (registered conditionally inside `apa_init` when the theme is missing) | default | anonymous fn rendering an error notice | `anchor-page-assistant.php:54-58` |

`apa_init()` gates everything behind `function_exists( 'anchor_get_template_data' )` — i.e., the Anchor Framework theme must be loaded and `inc/helpers.php` must have been included. The theme `inc/helpers.php:19-20` confirms this function exists.

### Plugin-specific lifecycle dependencies

- Constants `APA_PLUGIN_DIR` / `APA_PLUGIN_URL` are derived from `plugin_dir_path( __FILE__ )` / `plugin_dir_url( __FILE__ )` — flagged in §15.
- The updater is wired up with `PucFactory::buildUpdateChecker( $repo_url, APA_PLUGIN_DIR . 'anchor-page-assistant.php', 'anchor-page-assistant' )` — flagged in §9.
- No plugin row meta filters, no plugin action links, no `is_plugin_active()` checks.

---

## 2. Admin pages

### `class-admin-page.php` (APA_Admin_Page singleton)

| Field | Value |
|---|---|
| Top-level menu slug | `anchor-page-assistant` |
| Parent | (top level — `add_menu_page`) |
| Capability | `manage_options` (`admin/class-admin-page.php:31`) |
| Title / menu label | "Page Assistant" / "Page Assistant" |
| Icon | `dashicons-layout` |
| Position | `30` |
| Render callback | `render_page` → `include APA_PLUGIN_DIR . 'templates/admin-page.php'` |
| Screen ID | `toplevel_page_anchor-page-assistant` (used at line 49) |

Submenu (Live Editor) registered in the same `register_menu()` call (`admin/class-admin-page.php:38-45`):

| Field | Value |
|---|---|
| Parent slug | `anchor-page-assistant` |
| Submenu slug | `apa-live-editor` |
| Capability | `manage_options` |
| Title / label | "Live Editor" |
| Render callback | `render_live_editor` → `include APA_PLUGIN_DIR . 'templates/live-editor.php'` |
| Screen ID | `page-assistant_page_apa-live-editor` (used at line 50) |

#### Asset enqueue (`admin/class-admin-page.php:48-98`)

Hooked on `admin_enqueue_scripts`. Branches on `$hook`:

- On `toplevel_page_anchor-page-assistant`:
  - Style `apa-admin` — `assets/css/admin.css`, no deps, ver `APA_VERSION`.
  - Script `apa-admin` — `assets/js/admin-app.js`, no deps, footer.
  - Script `apa-chat` — `assets/js/chat-widget.js`, depends on `apa-admin`, footer.
  - Style `dashicons` (re-enqueued).
  - `wp_localize_script( 'apa-admin', 'apaData', { restBase, nonce: wp_create_nonce('wp_rest'), pages, sections } )` — `admin/class-admin-page.php:92-97`.
- On `page-assistant_page_apa-live-editor`:
  - Style `apa-live-editor` — `assets/css/live-editor.css`.
  - Script `apa-live-editor` — `assets/js/live-editor.js`, no deps, footer.
  - `wp_localize_script( 'apa-live-editor', 'apaLive', { restBase, nonce, siteUrl, pages } )` — `admin/class-admin-page.php:58-63`.

#### Nonces / AJAX

No `wp_ajax_*` actions are registered anywhere in the plugin (verified by absence in the grep over `add_action`). Auth is exclusively via REST cookie + `X-WP-Nonce: wp_rest`.

#### `register_setting`

None in `class-admin-page.php`. See `class-settings-page.php` below.

### `class-settings-page.php` (APA_Settings_Page singleton)

| Field | Value |
|---|---|
| Parent slug | `anchor-page-assistant` |
| Submenu slug | `apa-settings` |
| Capability | `manage_options` (`admin/class-settings-page.php:32`) |
| Title / label | "Settings" |
| Render callback | inline (`render_page`, lines 44-148) |
| Screen ID | `page-assistant_page_apa-settings` (derived; not referenced in code) |

`register_setting` calls (all on `admin_init`, group `apa_settings`) — `admin/class-settings-page.php:39-41`:

| Group | Option | Sanitize callback |
|---|---|---|
| `apa_settings` | `apa_ai_provider` | (none — default behaviour) |
| `apa_settings` | `apa_ai_api_key` | (none — stored as plain text; sanitized in `APA_AI_Handler::set_api_key()` via `sanitize_text_field`) |
| `apa_settings` | `apa_ai_model` | (none) |

Settings form posts to `options.php` with `settings_fields( 'apa_settings' )` (line 68). Inline JS at lines 102-110 switches the displayed model dropdown when the provider changes. Inline test-API JS at lines 122-144 calls `POST /anchor-assistant/v1/ai/chat`.

### `class-config-manager.php` (APA_Config_Manager singleton)

Not an admin page. Pure data-access utility for `{stylesheet_directory}/config/*.php`. Operates on:

| Method | File path it touches |
|---|---|
| `get_config_dir()` | `get_stylesheet_directory() . '/config'` (line 25) |
| `list_pages()` / `get_page()` / `save_page()` | `config/pages/{slug}.php` (lines 41, 62, 77) |
| `get_site()` / `save_site()` | `config/site.php` |
| `get_data()` / `save_data()` | `config/data.php` |
| `get_globals()` / `save_globals()` | `config/globals.php` |
| `get_media()` / `save_media()` | `config/media.php` |

`save_page()` (lines 75-89) calls `anchor_validate_section()` per section if the function exists — implementation lives in the theme at `inc/validation.php:21-22`. All writes go through `APA_Config_Writer::write()`.

---

## 3. Frontend chat (`class-frontend-chat.php`)

### Injection (`admin/class-frontend-chat.php:25-28`)

- `wp_enqueue_scripts` → `maybe_enqueue`
- `wp_footer` → `maybe_render` (body is empty — JS builds the UI)

### Gate (`admin/class-frontend-chat.php:33-38`)

```php
is_user_logged_in()
&& current_user_can( 'manage_options' )
&& ! is_admin()
&& APA_AI_Handler::instance()->is_configured()
```

### JS / CSS dependency graph (`admin/class-frontend-chat.php:50-93`)

| Handle | Asset | Deps | In footer? |
|---|---|---|---|
| `apa-frontend-chat` (CSS) | `assets/css/frontend-chat.css` | (none) | n/a |
| `apa-code-editor` (CSS) | `assets/css/code-editor.css` | `apa-frontend-chat` | n/a |
| `dashicons` | (core) | (none) | n/a |
| (calls `wp_enqueue_media()`) | (core) | various | n/a |
| `apa-frontend-chat` (JS) | `assets/js/frontend-chat.js` | `jquery`, `media-views` | yes |
| `apa-code-editor` (JS) | `assets/js/code-editor.js` | `apa-frontend-chat` | yes |
| `apa-inline-edit` (JS) | `assets/js/inline-edit.js` | `apa-frontend-chat` | yes |

### REST/AJAX endpoints called from frontend JS

Endpoints (all under `anchor-assistant/v1`, called from `assets/js/frontend-chat.js`, `inline-edit.js`, `code-editor.js`):

- `POST ai/chat` (`frontend-chat.js:149`)
- `POST ai/apply` (`frontend-chat.js:202`, `:252`, `:286`, `:329`; `chat-widget.js:151`; `inline-edit.js:354`)
- `GET history/pages/{slug}` (`frontend-chat.js:400`)
- `POST history/restore` (`frontend-chat.js:424`)
- `POST media/upload` (`frontend-chat.js:468`)
- `GET pages/{slug}` and `POST pages/{slug}` (`inline-edit.js:372`, `:419`)
- `GET files/page/{slug}` and `POST files/page/{slug}` (`code-editor.js:192`, `:267`)
- `GET files/css/{filename}` and `POST files/css/{filename}` (`code-editor.js:203`, `:273`)
- `GET files/page-css/{slug}` and `POST files/page-css/{slug}` (`code-editor.js:214`, `:270`)

### Localized data (`admin/class-frontend-chat.php:132-143`)

`apaFrontend = { restBase, wpRestBase, nonce, pageSlug, pageTitle, adminUrl, sections, postContext, hasPageContentFile, pageContentPath }`.

Notably, `postContext` includes `id`, `type`, `title`, `content`, `excerpt`, `featured_image`, `featured_image_id`, `edit_url`, and an optional `seo` block (lines 102-123) sourced from Yoast `_yoast_wpseo_*` post meta when `WPSEO_VERSION` is defined.

---

## 4. AI handler (`class-ai-handler.php` + `class-prompt-builder.php`)

### Providers (`admin/class-ai-handler.php:64-68`)

Two: `anthropic` (default) and `openai`. Selected via the `apa_ai_provider` option.

### Credentials

- API key stored in plain text in the `apa_ai_api_key` WP option (`admin/class-ai-handler.php:26-31`). No encryption, no key wrapping.
- Setter uses `sanitize_text_field()`; getter returns raw.
- Model selection in `apa_ai_model`, with provider-specific defaults `claude-sonnet-4-20250514` or `gpt-4o` (lines 33-41).

### Endpoint URLs

- Anthropic: `POST https://api.anthropic.com/v1/messages` (`admin/class-ai-handler.php:80`).
- OpenAI: `POST https://api.openai.com/v1/chat/completions` (`admin/class-ai-handler.php:127`).

### Request shape

Anthropic (`admin/class-ai-handler.php:80-93`):
- `timeout: 90`
- Headers: `x-api-key`, `anthropic-version: 2023-06-01`, JSON.
- Body: `{ model, max_tokens: 4096, system, messages: [...history, { role: 'user', content: $message }] }`.

OpenAI (`admin/class-ai-handler.php:127-138`):
- `timeout: 90`
- Headers: `Authorization: Bearer <key>`.
- Body: `{ model, max_tokens: 4096, messages: [{ role: 'system', content: $system }, ...history, { role: 'user', content }] }`.

### System prompt source

`APA_Prompt_Builder::build()` (`includes/class-prompt-builder.php:20-210`). Builds one of two prompts:

- **Direct-PHP mode** (`build_direct_php`, lines 223-404) when the current page has a `page-content/{slug}.php` file under the child theme. Embeds the current file contents, available theme components/partials, applicable CSS files (per-page CSS plus a small allowlist `client-overrides.css`, `client-tokens.css`, plus `deka-home.css` for home / `deka-shop.css` for shop — **note: `deka-*` references; flagged in §15**), and the Anchor Tools shortcode catalog.
- **Config mode** (lines 34-210) when there's no `page-content/{slug}.php`. Renders the full section schema, media keys, site config, services/team summary, nav menus, optional post context for blog/event singles.

Selector text `[DOM Context]` is documented as a UI convention so the AI can read selectors copied from the DOM (lines 377-385).

### Streaming?

Non-streaming. Both providers use `wp_remote_post` and read the full body before returning. The reply is parsed via `extract_json()` (looks for ` ```json ` fence) and `extract_php_file()` (looks for ` ```php ` fence) — `admin/class-ai-handler.php:160-184`.

### Known latent bug

`api/class-rest-ai.php:103` returns `'model' => APA_AI_Handler::MODEL`, but `APA_AI_Handler` does **not** define a `MODEL` constant anywhere in `admin/class-ai-handler.php` (grep confirms). The `GET /ai/settings` endpoint will fatal on every call. unclear — needs follow-up whether this endpoint is actually exercised by any UI; the JS files don't appear to read `model` off the settings response, only `configured`. Worth fixing during the merge.

---

## 5. File writer (`includes/class-file-writer.php`)

### Allowed write roots (`includes/class-file-writer.php:23-25`)

- Single root: `trailingslashit( get_stylesheet_directory() ) . 'page-content'`.

### Path-traversal protection (`includes/class-file-writer.php:45-78`)

`resolve_page_path()`:
1. Validates slug via `preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug )` (line 35) — slashes allowed for nested pages.
2. Creates the root dir with `wp_mkdir_p` if missing.
3. Resolves the root via `realpath()`.
4. Computes `$target = $root . '/' . $slug . '.php'`, computes `$parent_dir = dirname( $target )`, creates it if needed.
5. Resolves the parent via `realpath()` and asserts it equals `$root_real` or begins with `$root_real . DIRECTORY_SEPARATOR`.
6. Returns `$parent_real . DIRECTORY_SEPARATOR . basename( $target )`.

This is a proper jail: traversal via `..` segments is defeated by the post-resolution containment check.

### Extension allowlist

Implicit — `.php` is appended to the slug; no other extension is reachable through this writer. No allowlist beyond that.

### Auth & syntax check

- Auth: imposed at the REST layer (`current_user_can( 'manage_options' )`); the writer class itself has no permission check.
- `lint_php()` (`includes/class-file-writer.php:170-192`) shells out to `php -l` via `shell_exec` when available; falls back silently when `shell_exec` is disabled. If a syntax error is detected, returns a `WP_Error` with code `syntax_error`. **PHP-FPM workaround**: if `PHP_BINARY` contains `'fpm'`, falls back to bare `php` on `PATH` (line 177-179) — because `php-fpm` rejects `-l`.

### Backups

`cleanup_backups()` at the bottom of the file: keeps the most recent 10 `.{Y-m-d-His}.bak` files in the same directory.

---

## 6. Config writer (`includes/class-config-writer.php`)

### Targets

`APA_Config_Writer::write()` writes any **absolute** path it's handed. The caller (always `APA_Config_Manager`) restricts targets to `{stylesheet_directory}/config/*.php` — there is **no internal jail** on the writer itself.

### Safety

- Backup-before-write with `.{Y-m-d-His}.bak` extension (lines 22-28), pruned to 10 by `cleanup_backups()` (lines 114-126).
- Atomic write via `file_put_contents($path . '.tmp')` then `rename()` (lines 38-48).
- No syntax check (this writer produces its own well-formed `<?php return [...];` output via `export_array`/`export_value`, lines 63-109).
- No permission/capability check inside the writer — relies entirely on the calling REST controller's `permission_callback`.
- No realpath / containment check. If a caller hands in a path outside `config/`, it writes there.

### What "config" means

- `config/pages/{slug}.php` — per-page section list (legacy "config-driven page" paradigm).
- `config/site.php` — site-wide business info.
- `config/data.php` — shared entity data (services, team, products).
- `config/globals.php` — global reusable sections.
- `config/media.php` — media key → URL/attachment ID map.

---

## 7. REST endpoints

All endpoints live under namespace **`anchor-assistant/v1`** and require `current_user_can( 'manage_options' )` except where noted.

| File | Route | Methods | Capability | Body / params | What it does |
|---|---|---|---|---|---|
| `api/class-rest-config.php:29` | `/pages` | GET | `manage_options` | — | List page configs from `config/pages/*.php`. |
| `api/class-rest-config.php:36` | `/pages/{slug}` | GET, POST | `manage_options` | JSON body = full page config (POST) | Read/write `config/pages/{slug}.php`. |
| `api/class-rest-config.php:50` | `/site` | GET, POST | `manage_options` | JSON body = site config | Read/write `config/site.php`. |
| `api/class-rest-config.php:64` | `/data` | GET, POST | `manage_options` | JSON body = data array | Read/write `config/data.php`. |
| `api/class-rest-config.php:78` | `/globals` | GET, POST | `manage_options` | JSON body = globals | Read/write `config/globals.php`. |
| `api/class-rest-config.php:92` | `/media` | GET, POST | `manage_options` | JSON body = media map | Read/write `config/media.php`. |
| `api/class-rest-sections.php:27` | `/sections` | GET | `manage_options` | — | Return full schema registry. |
| `api/class-rest-sections.php:35` | `/sections/{type}` | GET | `manage_options` | — | Return one section schema. |
| `api/class-rest-ai.php:28` | `/ai/chat` | POST | `manage_options` | `{ message, history, page_slug?, config_key?, post_context?, selected_section_type? }` | Forward to Anthropic/OpenAI; return `{ reply, config, file_contents }`. |
| `api/class-rest-ai.php:37` | `/ai/settings` | GET, POST | `manage_options` | POST: `{ api_key }` | Get configured-status / save key. **Bug: GET references undefined `APA_AI_Handler::MODEL`.** |
| `api/class-rest-ai.php:55` | `/ai/apply` | POST | `manage_options` | `{ config | file_write | file_contents | css_write }` plus `page_slug` / `config_key` | Apply AI-generated change — config write, file write, CSS write, menu action, or post action. |
| `api/class-rest-menus.php:32` | `/menus` | GET | `manage_options` | — | Dump all menu locations + items. |
| `api/class-rest-menus.php:39` | `/menus/{location}` | GET | `manage_options` | — | Single menu with `items` + nested `tree`. |
| `api/class-rest-menus.php:46` | `/menus/{location}` | POST | `manage_options` | JSON body = array of items (tree) | Replace entire menu. |
| `api/class-rest-menus.php:53` | `/menus/{location}/items` | POST | `manage_options` | `{ title, url, parent_id?, position?, page_id? }` | Add one menu item. |
| `api/class-rest-menus.php:60` | `/menus/items/{id}` | DELETE | `manage_options` | — | Delete one menu item. |
| `api/class-rest-media.php:30` | `/media/library` | GET | `upload_files` | query: `per_page`, `page`, `search`, `type` | List attachments. |
| `api/class-rest-media.php:37` | `/media/upload` | POST | `upload_files` | multipart `file`, optional `media_key` | Upload to library, optionally write into `config/media.php`. |
| `api/class-rest-media.php:44` | `/media/library/{id}` | GET | `upload_files` | — | Single attachment details. |
| `api/class-rest-history.php:33` | `/history/{type}(/{slug})?` | GET | `manage_options` | `type ∈ {pages, site, data, globals, media}` | List `.bak` files for that config target. |
| `api/class-rest-history.php:40` | `/history/restore` | POST | `manage_options` | `{ backup_file, type, slug? }` | Copy backup back over the live file (with pre-restore backup). |
| `api/class-rest-files.php:39` | `/files/page/{slug}` | GET, POST | `manage_options` | POST: `{ contents }` | Read/write `page-content/{slug}.php` via `APA_File_Writer`. Slug regex allows `/` for nested. |
| `api/class-rest-files.php:57` | `/files/partials` | GET | `manage_options` | — | List `template-parts/partials/*.php` in the parent theme. |
| `api/class-rest-files.php:69` | `/files/section/{type}` | GET, POST | `manage_options` | POST: `{ contents }` | Read/write `template-parts/sections/{type-with-dash}.php` in the parent theme. Validates `type` is a known section. |
| `api/class-rest-files.php:88` | `/files/css/{filename}` | GET, POST | `manage_options` | POST: `{ contents }` | Read/write a CSS file from a fixed allowlist (`deka-home.css`, `client-overrides.css`, `client-tokens.css`, `deka-shop.css`) under `{stylesheet_directory}/assets/css/`. |
| `api/class-rest-files.php:105` | `/files/page-css/{slug}` | GET (`READABLE`), POST (`CREATABLE`) | `manage_options` | POST: `{ slug, contents }` | Read/write `{stylesheet_directory}/assets/css/pages/{slug}.css`. |

**Total REST endpoints: 23 routes across 7 controllers.** (Counting each registered `register_rest_route` call; some routes accept multiple HTTP methods.)

---

## 8. Section registry / schema

### What it does

- `APA_Section_Schema::get_all()` (`includes/class-section-schema.php:17-216`) returns a hard-coded array of 14 section types (`hero`, `split_content`, `text_block`, `card_grid`, `icon_list`, `testimonial_band`, `faq_list`, `cta_band`, `services_tabs`, `team_grid`, `contact_block`, `gallery_band`, `shortcode_block`). Each has `label`, `description`, `variants`, `props` (with type, label, optional `required`/`default`/`options`/`item_shape`).
- `APA_Section_Registry::get_all()` (`admin/class-section-registry.php:28-48`) merges these schemas with `anchor_get_all_section_defaults()` from the theme (`inc/validation.php:89-90`) to produce a single registry containing both the prompt-builder-facing schema and the renderer-facing defaults.
- `get_labels()` returns just `type => label` for dropdowns.

### Coupling to legacy section template system

This is the legacy section-template world that the master plan §2.3 deprecates. Specifically:

| Piece | Master-plan status | Recommendation |
|---|---|---|
| Hard-coded 14-type schema array | **Legacy.** Tightly coupled to `template-parts/sections/*.php` in the theme. | **(b) Drop** for new authoring. Keep as a read-only data source for *existing* config-driven pages until they're migrated. |
| `APA_Section_Registry` & `anchor_get_all_section_defaults` merge | **Legacy.** Only useful when authoring configs. | **(c) Discuss.** Likely drop with the schema, but the section renderer (`anchor_render_section`) is referenced from `header.php` / `footer.php` / `archive-anchor_event.php` / `front-page.php` / `page.php` and still ships. |
| `/sections` REST endpoints | Used by the legacy admin chat. The new direct-PHP flow doesn't need it. | **(b) Drop** if confirmed. unclear — needs follow-up whether the JS admin app still relies on section schemas to render the section list. |
| Prompt mode that lists every section schema | **Legacy.** Replaced by `build_direct_php()` for pages with `page-content/{slug}.php`. | **(b) Drop** once all sites are on direct-PHP. Until then, must survive the merge — the prompt branches at `class-prompt-builder.php:27-32`. |
| `apply_filters('anchor_framework_section_classes', …)` in the theme (per `docs/hooks.md`) | Documented theme hook. Plugin does NOT consume it. | **(a) Must survive the merge** (theme-side hook, unrelated to the plugin merge but called out for completeness). |

The schema file uses generic, non-domain names — fine under the "no domain-specific names" rule.

---

## 9. Updater (`includes/updater.php`)

### Mechanism

- Bundled `yahnis-elsts/plugin-update-checker` library (`vendor/plugin-update-checker/`).
- Wired up via `YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker` (`includes/updater.php:26-30`).
- Polls the GitHub repo for new releases.

### Library version

Per `vendor/plugin-update-checker/composer.json` (the file's autoload entry references `load-v5p6.php`) and `vendor/plugin-update-checker/load-v5p6.php:1-3`: **v5p6 (5.6.x)**. No semver pin in `composer.json`. README in the vendor directory not consulted further.

### Update endpoint

- Source URL: `https://github.com/joelhmartin/anchor-page-assistant/` (`includes/updater.php:27`).
- Plugin file argument: `APA_PLUGIN_DIR . 'anchor-page-assistant.php'` (line 28).
- Slug argument: `'anchor-page-assistant'` (line 29).
- Branch: `main` (set via `$updater->setBranch('main')`, line 32).
- Release assets enabled when the VCS API supports it (`enableReleaseAssets()`, line 36).
- Optional auth via `ANCHOR_PAGE_ASSISTANT_GH_TOKEN` constant (lines 39-41).

### Theme-migration risk (flagged)

The second argument to `PucFactory::buildUpdateChecker` is `$plugin_file` — `puc` uses this to derive the plugin slug and the directory it watches. **After the merge, this code path moves into the theme.** The factory has a separate code path for *themes* (`Theme/UpdateChecker.php` in the vendor tree), and the correct call would be along the lines of:

```php
PucFactory::buildUpdateChecker( $repo_url, get_template_directory() . '/style.css', $slug );
```

The framework theme already ships its own copy of plugin-update-checker at `wp-content/themes/anchor-framework/inc/vendor/plugin-update-checker/` (grep confirms). **Decision needed**: after the merge, do we want a second update-checker registration inside the theme for the new "AI" feature set, or do we fold everything into the existing theme updater? unclear — needs follow-up. Either way, the `APA_PLUGIN_DIR . 'anchor-page-assistant.php'` argument cannot survive verbatim.

---

## 10. Templates

### `templates/admin-page.php`

- Included by `APA_Admin_Page::render_page()` (`admin/class-admin-page.php:100-102`) within `add_menu_page()`'s rendering context.
- Variables in scope: implicit — none required. Reads `APA_VERSION` constant (line 5) and calls `APA_Config_Manager::instance()->is_writable()` (line 24).
- Output: static markup for sidebar (page list, config buttons), main editor pane, JSON editor pane. All dynamic data is rendered later by `admin-app.js` via `apaData`.
- Escaping: `esc_html( APA_VERSION )` is the only echoed value. No user-controllable values are output unescaped. Safe.

### `templates/live-editor.php`

- Included by `APA_Admin_Page::render_live_editor()` (`admin/class-admin-page.php:104-106`).
- Variables in scope: none required. Pure markup; data comes from `apaLive` localized object loaded by `live-editor.js`.
- No echoed dynamic values. Safe.

---

## 11. Assets — full inventory

| File | Loaded by | Where | Handle | Deps | Localized data |
|---|---|---|---|---|---|
| `assets/css/admin.css` (451 lines) | `APA_Admin_Page::enqueue_assets` | admin (`toplevel_page_anchor-page-assistant`) | `apa-admin` | — | — |
| `assets/css/code-editor.css` (149 lines) | `APA_Frontend_Chat::maybe_enqueue` | frontend (logged-in admins, gated) | `apa-code-editor` | `apa-frontend-chat` | — |
| `assets/css/frontend-chat.css` (494 lines) | `APA_Frontend_Chat::maybe_enqueue` | frontend (gated) | `apa-frontend-chat` | — | — |
| `assets/css/live-editor.css` (121 lines) | `APA_Admin_Page::enqueue_assets` | admin (`page-assistant_page_apa-live-editor`) | `apa-live-editor` | — | — |
| `assets/js/admin-app.js` (483 lines) | `APA_Admin_Page::enqueue_assets` | admin (main page) | `apa-admin` | — | `apaData = { restBase, nonce, pages, sections }` |
| `assets/js/chat-widget.js` (208 lines) | `APA_Admin_Page::enqueue_assets` | admin (main page) | `apa-chat` | `apa-admin` | — (reads `apaData` set on `apa-admin`) |
| `assets/js/code-editor.js` (311 lines) | `APA_Frontend_Chat::maybe_enqueue` | frontend (gated) | `apa-code-editor` | `apa-frontend-chat` | — (reads `apaFrontend`) |
| `assets/js/frontend-chat.js` (832 lines) | `APA_Frontend_Chat::maybe_enqueue` | frontend (gated) | `apa-frontend-chat` | `jquery`, `media-views` | `apaFrontend = { restBase, wpRestBase, nonce, pageSlug, pageTitle, adminUrl, sections, postContext, hasPageContentFile, pageContentPath }` |
| `assets/js/inline-edit.js` (460 lines) | `APA_Frontend_Chat::maybe_enqueue` | frontend (gated) | `apa-inline-edit` | `apa-frontend-chat` | — (reads `apaFrontend`) |
| `assets/js/live-editor.js` (177 lines) | `APA_Admin_Page::enqueue_assets` | admin (live editor sub-page) | `apa-live-editor` | — | `apaLive = { restBase, nonce, siteUrl, pages }` |

Total: ~3,686 lines of asset code (1,215 CSS + 2,471 JS).

`assets/js/frontend-chat.js` depends on jQuery + `media-views`, which forces the WP media modal to be loaded on every front-end page render for an admin user — significant performance hit, flagged in §15.

---

## 12. Hooks coupling with `anchor-framework` theme

### Theme-defined function calls the plugin depends on

| Plugin caller | Function | Theme definition |
|---|---|---|
| `anchor-page-assistant.php:54` | `anchor_get_template_data()` | `inc/helpers.php:19-20` |
| `admin/class-config-manager.php:80` | `anchor_validate_section()` | `inc/validation.php:21-22` |
| `admin/class-section-registry.php:34` | `anchor_get_all_section_defaults()` | `inc/validation.php:89-90` |
| `admin/class-frontend-chat.php:44`, `includes/class-prompt-builder.php:27 (implicit)` | `anchor_determine_page_slug()` | `inc/config-loader.php:148-149` |
| `admin/class-frontend-chat.php:128-129`, prompt-builder | `anchor_get_page_content_path()` | `inc/page-content-loader.php:27` |
| `includes/class-config-writer.php:51` (commented note) | `anchor_load_config()` | `inc/config-loader.php:21` |

These are **hard function-existence dependencies**. If any are renamed, the plugin breaks. Post-merge, they become same-package internal calls — flagged as "must survive the merge" but should be turned into method calls or namespaced helpers as part of the consolidation.

### Theme-defined filters/actions the plugin consumes

**None observed.** The plugin does not call `add_filter` or `add_action` on any `anchor_framework_*` hook documented in `docs/hooks.md`. The plugin uses `apply_filters` / `do_action` **nowhere** outside the vendor tree (grep confirms).

### Plugin-defined hooks the theme consumes

**None observed.** The plugin defines no `anchor_assistant_*` filter or action of its own.

### Theme-defined filters documented in `docs/hooks.md`

For completeness (the plugin doesn't touch them, but they're part of the same shipping unit post-merge):

- `anchor_framework_enqueue_assets` (action) — `docs/hooks.md:12-20`.
- `anchor_framework_section_classes` (filter) — `docs/hooks.md:24-39`.
- `anchor_framework_button_args` (filter) — `docs/hooks.md:41-52`.

Net: the plugin/theme coupling is via **plain function calls**, not WordPress hooks. The merge can collapse them without breaking any hook contract.

---

## 13. Option / transient inventory

### Options

| Option name | Where read | Where written | Autoload | Default |
|---|---|---|---|---|
| `apa_ai_provider` | `admin/class-ai-handler.php:22`, `admin/class-settings-page.php:45` | settings form → `options.php` via `register_setting` (`admin/class-settings-page.php:39`) | (default — yes) | `'anthropic'` |
| `apa_ai_api_key` | `admin/class-ai-handler.php:26`, `admin/class-settings-page.php:46` | settings form (`register_setting`, line 40) and `APA_AI_Handler::set_api_key()` (`admin/class-ai-handler.php:30`) | (default — yes) | `''` |
| `apa_ai_model` | `admin/class-ai-handler.php:34`, `admin/class-settings-page.php:47` | settings form (`register_setting`, line 41) | (default — yes) | `''` (handler falls back to provider-specific default) |

No `add_option`, `delete_option` calls. All three options autoload by default — a moderate but not severe overhead on every request.

### Transients

**None observed.** Grep for `get_transient` / `set_transient` / `delete_transient` returns nothing outside the vendor tree.

Plugin-update-checker stores its own `update_*` site transients internally but those are managed by the library, not by plugin code.

---

## 14. Capability inventory

Every `current_user_can()` call across the plugin (grep confirms 14 call sites):

| Capability | Where |
|---|---|
| `manage_options` | `admin/class-frontend-chat.php:35` |
| `manage_options` | `api/class-rest-sections.php:31` (chat permission) |
| `manage_options` | `api/class-rest-sections.php:39` (single section permission) |
| `manage_options` | `api/class-rest-ai.php:32` (chat) |
| `manage_options` | `api/class-rest-ai.php:42` (settings GET) |
| `manage_options` | `api/class-rest-ai.php:49` (settings POST) |
| `manage_options` | `api/class-rest-ai.php:59` (apply) |
| `manage_options` | `api/class-rest-config.php:107` (shared `check_permission`) |
| `manage_options` | `api/class-rest-menus.php:68` (shared `check_perm`) |
| `manage_options` | `api/class-rest-files.php:128` (shared `check_permission`) |
| `manage_options` | `api/class-rest-history.php:36` (list backups) |
| `manage_options` | `api/class-rest-history.php:43` (restore) |
| `upload_files` | `api/class-rest-media.php:52` (shared `check_perm`) |
| (implicit) `manage_options` via `add_menu_page` / `add_submenu_page` | `admin/class-admin-page.php:31`, `:42`; `admin/class-settings-page.php:32` |

Effectively one role: an Administrator. The media endpoints relax to `upload_files` for the upload/listing path, which covers Editors too.

---

## 15. Risk inventory — what breaks when this runs from a theme

Items that depend on plugin-specific WordPress behaviour:

- **`plugin_dir_path( __FILE__ )` / `plugin_dir_url( __FILE__ )`** in `anchor-page-assistant.php:20-21`. After the merge, `__FILE__` points inside the theme, and these helpers still resolve to a directory path/URL — but the result is **the theme's directory**, not a plugin's. All uses of `APA_PLUGIN_DIR` (in `require_once` calls throughout the bootstrap and in `templates/*.php` `include` calls in `admin/class-admin-page.php:101,105`) and `APA_PLUGIN_URL` (in `wp_enqueue_*` calls across `admin/class-admin-page.php` and `admin/class-frontend-chat.php`) need to be replaced with `get_template_directory() . '/<subdir>/'` and `get_template_directory_uri() . '/<subdir>/'`. About **15 call sites** affected.
- **`register_activation_hook` / `register_deactivation_hook` / `register_uninstall_hook`** — none observed, so no migration to `after_switch_theme` / `switch_theme` is required. No-op risk.
- **`plugin_action_links_*` / `plugin_row_meta` filters** — none observed in plugin code. The bundled plugin-update-checker hooks `plugin_row_meta` internally (`vendor/.../Plugin/Ui.php:26-27`), but post-merge those rows belong to a theme, so the library's plugin-mode UI hooks become dead code. Switching to the library's theme mode is the clean answer (`Theme/UpdateChecker.php`).
- **`is_plugin_active()`** — not used.
- **`WP_PLUGIN_DIR` / `WP_PLUGIN_URL`** — not used.
- **`PucFactory::buildUpdateChecker( $url, $plugin_file, $slug )`** at `includes/updater.php:26-30` — the second arg must change from a plugin file to a theme `style.css`, and the factory must dispatch to the theme update checker variant. Flagged in §9.
- **Boot hook timing** — `after_setup_theme:20`. The theme's own bootstrap runs at `after_setup_theme` too (themes load via `setup_theme`/`after_setup_theme`). Post-merge, this code can just run inline from `functions.php` (or one of its includes); the `function_exists('anchor_get_template_data')` gate becomes unnecessary because the theme always loads its own helpers.
- **Theme-coupling functions called by the plugin** (§12) — all become same-package calls; verify ordering of `require_once`s so `inc/validation.php`, `inc/helpers.php`, `inc/config-loader.php`, `inc/page-content-loader.php` load **before** the assistant code.
- **`stylesheet_directory` vs `template_directory`** — `APA_Config_Manager::get_config_dir()`, `APA_File_Writer::page_content_dir()`, and the CSS write paths all use `get_stylesheet_directory()`, which means they target the *child* theme. `APA_File_Writer::list_partials()` and `apply_css_write` use `get_template_directory()` for partials. Correct as-is; **must remain that way after the merge** because the parent theme is the consolidation target but page content / brand CSS still live in the child theme.
- **Bundled `vendor/plugin-update-checker/`** exists both in the plugin and at `wp-content/themes/anchor-framework/inc/vendor/plugin-update-checker/`. Two PHP-side copies of the same library risk version skew and "class already declared" errors when one autoloader runs after the other. The library guards against this via the factory's version registry, but **picking one canonical copy** during the merge is strongly recommended.
- **`deka-home.css`, `deka-shop.css`** hard-coded into the prompt builder (`includes/class-prompt-builder.php:307-310`) and the CSS allowlist (`api/class-rest-files.php:255`). This violates the master plan's "no domain-specific names" rule. Flag for replacement with a configurable per-site allowlist (probably an Anchor Tools site-config field).
- **`APA_AI_Handler::MODEL` undefined constant** referenced from `api/class-rest-ai.php:103`. Pre-existing latent bug; will fatal if the endpoint is hit. Fix in the same pass as the merge.
- **`wp_enqueue_media()` + `media-views` JS dep** on every gated front-end page (`admin/class-frontend-chat.php:69-77`). Heavy. Reconsider whether it needs to load until the user opens the media picker.
- **`shell_exec` for `php -l`** in `APA_File_Writer::lint_php()`. Many shared hosts disable `shell_exec`; the fallback is silent (just no lint check). On Kinsta this likely works; on other hosts it may not. Not introduced by the merge, but worth documenting.

---

## 16. End-user feature inventory

The plugin gives a WordPress Administrator the following capabilities. All must continue to work after the merge.

1. **Admin "Page Assistant" top-level menu** with three sub-pages: the main app, Live Editor, Settings. Lets the admin pick from the list of config-driven pages (read from `{child-theme}/config/pages/*.php`) and either edit the JSON config directly in a textarea or use the section list view, then save. The admin can also pick any of the four shared configs (site, data, globals, media) and edit them as raw JSON.
2. **Settings page** for choosing AI provider (Anthropic or OpenAI), entering an API key, and selecting a model. Includes an inline "Test API Connection" button that calls `/ai/chat`.
3. **Live Editor sub-page** with a left-pane page picker + JSON textarea and a right-pane iframe preview. Edits the same `config/pages/*.php` file as the main app and refreshes the iframe on save.
4. **Frontend chat widget** that appears in the footer for logged-in admins on every public page. Lets the admin describe a change in natural language; the plugin sends the request to the configured AI with full page/site/data context and applies the returned change (JSON config write, raw PHP file write, raw CSS file write, menu edit, or post update) via `/ai/apply`.
5. **Inline editing on the frontend** via `inline-edit.js` — admins can directly edit text in the rendered page and have changes flow back into the page config (for config-driven pages) or `wp_update_post` (for single posts/events).
6. **Frontend code-editor pane** powered by Monaco (loaded from CDN in `code-editor.js`) for direct editing of `page-content/{slug}.php`, the per-page CSS file `{stylesheet_directory}/assets/css/pages/{slug}.css`, and the small CSS allowlist (`deka-home.css`, `client-overrides.css`, `client-tokens.css`, `deka-shop.css`).
7. **History / undo** — every config write creates a timestamped `.bak` and the chat can list/restore them via `/history/*`. Backup ring keeps the last 10 per file.
8. **Media uploads** — the frontend chat can upload to the WP media library and optionally write the URL into `config/media.php` under a named key.
9. **Menu management** — read and edit nav menus by location through `/menus/*` (the chat constructs `menu_action` payloads).
10. **Single-post editing** — on `single.php` / `single-anchor_event.php`, the chat detects the post context, exposes it to the AI, and can update title/content/excerpt/featured image plus Yoast SEO meta via `/ai/apply` (`post_action: update`).
11. **GitHub auto-updates** — plugin-update-checker surfaces new releases on the plugin's GitHub repo as standard WP plugin update notices.

---

## End of recon
