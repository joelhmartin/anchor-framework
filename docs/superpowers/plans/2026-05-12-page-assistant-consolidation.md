# Page Assistant Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merge the standalone `anchor-page-assistant` plugin into the `anchor-framework` parent theme without functional loss, fix two pre-existing defects along the way, and decommission the plugin.

**Architecture:** Theme grows two new top-level `inc/` subtrees (`inc/editor/`, `inc/ai/`) plus an `assets/editor/` asset tree. A small `Anchor_Editor` helper class replaces the plugin's `APA_*` constants. Class names re-prefix to `Anchor_Editor_*` (editor surfaces) and `Anchor_AI_*` (AI module). REST namespace `anchor-assistant/v1` is preserved unchanged in this phase. Plugin is deactivated up front and ultimately stripped to a deprecation notice on its own feature branch.

**Tech Stack:** WordPress 6.4+, PHP 7.4+, no test framework, manual smoke verification per spec §15.

**Spec:** `docs/superpowers/specs/2026-05-12-page-assistant-consolidation.md` (commit `09d7448`).
**Recon (referenced by tasks):** `docs/page-assistant-recon.md` (commit `29774ed`).

---

## 0. Transformation Rules

Every "move" task in this plan applies these rules. Read this section once; tasks reference it by name.

### 0.1 Class rename rules

When the source file uses class symbol `APA_X`, the destination uses the symbol named in §5 of the spec.

**Find and replace inside the destination file:**

| Find | Replace with |
|---|---|
| `APA_Admin_Page` | `Anchor_Editor_Page` |
| `APA_Settings_Page` | `Anchor_Editor_Settings` |
| `APA_Config_Manager` | `Anchor_Editor_Config_Manager` |
| `APA_Frontend_Chat` | `Anchor_Editor_Frontend_Chat` |
| `APA_AI_Handler` | `Anchor_AI_Handler` |
| `APA_Prompt_Builder` | `Anchor_AI_Prompt_Builder` |
| `APA_File_Writer` | `Anchor_Editor_File_Writer` |
| `APA_Config_Writer` | `Anchor_Editor_Config_Writer` |
| `APA_REST_AI` | `Anchor_Editor_REST_AI` |
| `APA_REST_Config` | `Anchor_Editor_REST_Config` |
| `APA_REST_Files` | `Anchor_Editor_REST_Files` |
| `APA_REST_History` | `Anchor_Editor_REST_History` |
| `APA_REST_Media` | `Anchor_Editor_REST_Media` |
| `APA_REST_Menus` | `Anchor_Editor_REST_Menus` |
| `APA_Section_Registry` | (drop — section schema dies) |
| `APA_Section_Schema` | (drop — section schema dies) |
| `APA_REST_Sections` | (drop — section schema dies) |

### 0.2 Constant rename rules

| Find | Replace with |
|---|---|
| `APA_VERSION` | `Anchor_Editor::VERSION` |
| `APA_PLUGIN_DIR` | `Anchor_Editor::path()` |
| `APA_PLUGIN_URL` | `Anchor_Editor::url()` |
| `plugin_dir_path( __FILE__ )` | `Anchor_Editor::path()` |
| `plugin_dir_url( __FILE__ )` | `Anchor_Editor::url()` |
| `ANCHOR_PAGE_ASSISTANT_GH_TOKEN` | (drop — updater retired) |

`Anchor_Editor::path()` returns `get_template_directory() . '/'`.
`Anchor_Editor::url()` returns `get_template_directory_uri() . '/'`.
`Anchor_Editor::VERSION` is a class constant whose string value matches the theme's `style.css` `Version:` header at the time of the merge commit. It is bumped by hand when the theme version bumps. (Reading `style.css` at runtime is not worth the overhead.)

When a file uses `Anchor_Editor::path()`, append the trailing subpath needed by the call site:
- For inc/editor files needing relative includes: `Anchor_Editor::path() . 'inc/editor/...'`
- For asset URLs: `Anchor_Editor::url() . 'assets/editor/...'`

### 0.3 Asset handle rename rules

When the source file enqueues an asset with handle `apa-X`, the destination uses handle `anchor-editor-X`. The script/style filename inside the handle stays the same (e.g., `apa-admin-app` → `anchor-editor-admin-app`; the JS file is still `admin-app.js`).

**Do NOT rename the `wp_localize_script` localized object name.** Plugin currently localizes to a global like `window.APA` (recon §11). Keep that name unchanged so the JS files do not need editing. (Rename deferred to Phase 4 per spec §16.3.)

### 0.4 Section-schema decoupling

The destination file must **not** reference any `APA_Section_*` class or any function/method that depends on section schema. If the source file does reference them, drop the offending lines and replace any returned-value behavior with the safest no-op (e.g., return `[]` for a list-of-sections accessor, return `null` for a single-section lookup). Each task using this rule documents the exact lines to drop.

### 0.5 deka-* removal

The destination file must contain **zero** `deka-` strings. If the source file references `deka-home.css` or `deka-shop.css`, replace with a filter-driven allowlist: `apply_filters( 'anchor_editor/css_allowlist', [] )`. Each task using this rule shows the exact replacement.

### 0.6 Per-task verification

Every move task ends with three checks:

```bash
php -l <new-file>                                                   # Expect: "No syntax errors detected"
grep -nE "APA_|plugin_dir_(path|url)|deka-" <new-file>               # Expect: no output
grep -nE "anchor-editor-|Anchor_Editor|Anchor_AI" <new-file>         # Expect: matches present (sanity)
```

If any check fails, do not commit. Stop and surface to the parent agent.

### 0.7 Original file disposition

**Do NOT delete the original plugin file during the move task.** Plugin files stay intact on disk (the plugin is just deactivated). They are removed wholesale in §K Decommission. This preserves rollback capacity per spec §14.

### 0.8 Commit conventions

Per master plan §10.1:
- Format: `<type>(<scope>): <subject>` — types: `feat`, `fix`, `refactor`, `docs`, `build`, `chore`. Scope: `editor` or `ai` or `assets` for this plan.
- One concern per commit.
- Co-author trailer: `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>`.

---

## Phase 1A: Scaffolding

### Task 1: Deactivate plugin in sandbox

**Files:** None (admin state change).

- [ ] **Step 1: Confirm plugin currently active**

```bash
wp plugin list --status=active --field=name | grep anchor-page-assistant
```
Expected: `anchor-page-assistant` listed.

- [ ] **Step 2: Deactivate**

```bash
wp plugin deactivate anchor-page-assistant
```
Expected: `Success: Deactivated 1 of 1 plugins.`

- [ ] **Step 3: Confirm WP admin and front end still load**

Surface to user: "Plugin deactivated. Please load `http://anchor-framework.local/wp-admin/` and `http://anchor-framework.local/` in your browser; confirm both load without PHP errors. Editor functionality is expected to be absent during the rest of Phase 1."

- [ ] **Step 4: No commit** — this is a runtime state change, not a code change.

---

### Task 2: Create `Anchor_Editor` helper class + bootstrap shell

**Files:**
- Create: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`
- Modify: `wp-content/themes/anchor-framework/functions.php` (add a single `require_once`)

- [ ] **Step 1: Read framework `functions.php`** to identify the right insertion point.

```bash
sed -n '1,60p' wp-content/themes/anchor-framework/functions.php
```
Expected: confirm there's an existing `require_once` block; new line goes immediately after the last such line.

- [ ] **Step 2: Create `inc/editor/bootstrap.php`** with this exact content:

```php
<?php
/**
 * Anchor Editor — bootstrap for the consolidated editor (formerly anchor-page-assistant plugin).
 *
 * Loads all editor and AI module classes and registers hooks. Source of truth: spec
 * docs/superpowers/specs/2026-05-12-page-assistant-consolidation.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for path / url / version. Replaces the plugin's APA_* constants.
 */
final class Anchor_Editor {

	const VERSION = '1.0.0';

	public static function path() {
		return trailingslashit( get_template_directory() );
	}

	public static function url() {
		return trailingslashit( get_template_directory_uri() );
	}
}

// Later tasks add require_once lines here for each class as it lands.
```

- [ ] **Step 3: Add `require_once` to `functions.php`**

Append at the end of the existing require_once block:

```php
require_once get_template_directory() . '/inc/editor/bootstrap.php';
```

- [ ] **Step 4: Verify PHP syntax**

```bash
php -l wp-content/themes/anchor-framework/inc/editor/bootstrap.php
php -l wp-content/themes/anchor-framework/functions.php
```
Both must report "No syntax errors detected".

- [ ] **Step 5: Verify front end still loads**

Surface to user: "Load `http://anchor-framework.local/` and `http://anchor-framework.local/wp-admin/`. Both should still render without errors. Paste any errors from `wp-content/debug.log` if present."

- [ ] **Step 6: Commit**

```bash
cd wp-content/themes/anchor-framework
git add inc/editor/bootstrap.php functions.php
git commit -m "$(cat <<'EOF'
feat(editor): add Anchor_Editor helper class and bootstrap shell

Introduces a minimal scaffold that subsequent tasks build on. The
helper class replaces the plugin's APA_VERSION / APA_PLUGIN_DIR /
APA_PLUGIN_URL constants. No editor functionality yet.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 1B: Leaf utilities

### Task 3: Move file writer → `Anchor_Editor_File_Writer`

**Files:**
- Source (read-only this task): `wp-content/plugins/anchor-page-assistant/includes/class-file-writer.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/class-file-writer.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source file in full** to understand its public API and any dependencies.

```bash
cat wp-content/plugins/anchor-page-assistant/includes/class-file-writer.php
```

Then re-read **recon §5 and §14** for documented allowed write paths, path-traversal protections, extension allowlist, and capability checks. The destination must preserve every safety check verbatim.

- [ ] **Step 2: Create the destination file** by copying the source content, then applying:
  - Class rename per §0.1 (`APA_File_Writer` → `Anchor_Editor_File_Writer`)
  - Constant/URL rename per §0.2
  - If the source contains any `APA_Section_*` references, apply §0.4 (none expected here, but verify)
  - Preserve every `current_user_can(...)` check verbatim
  - Preserve every path-validation step verbatim

- [ ] **Step 3: Add a `require_once` line in `bootstrap.php`** (immediately after the `Anchor_Editor` class definition):

```php
require_once Anchor_Editor::path() . 'inc/editor/class-file-writer.php';
```

- [ ] **Step 4: Apply §0.6 verification**

```bash
php -l wp-content/themes/anchor-framework/inc/editor/class-file-writer.php
grep -nE "APA_|plugin_dir_(path|url)|deka-" wp-content/themes/anchor-framework/inc/editor/class-file-writer.php
grep -nE "Anchor_Editor_File_Writer" wp-content/themes/anchor-framework/inc/editor/class-file-writer.php
```

- [ ] **Step 5: Verify front end still loads.** Surface to user: confirm `http://anchor-framework.local/` and `wp-admin/` still load.

- [ ] **Step 6: Commit**

```bash
cd wp-content/themes/anchor-framework
git add inc/editor/class-file-writer.php inc/editor/bootstrap.php
git commit -m "$(cat <<'EOF'
refactor(editor): move file writer into theme as Anchor_Editor_File_Writer

Verbatim move of the plugin's class-file-writer.php with APA_*
symbols renamed and plugin-dir constants replaced via Anchor_Editor.
All capability checks and path-traversal protections preserved.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Move config writer → `Anchor_Editor_Config_Writer`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/includes/class-config-writer.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/class-config-writer.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source file** and recon §6, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_Config_Writer` → `Anchor_Editor_Config_Writer`) and §0.2. If §0.4 applies, document the dropped lines in the commit body.
- [ ] **Step 3: Add `require_once` in `bootstrap.php`** after the file-writer line:
```php
require_once Anchor_Editor::path() . 'inc/editor/class-config-writer.php';
```
- [ ] **Step 4: Apply §0.6 verification** against the new file.
- [ ] **Step 5: Verify pages still load** (surface to user).
- [ ] **Step 6: Commit:** `refactor(editor): move config writer into theme as Anchor_Editor_Config_Writer`. Same commit-message structure as Task 3.

---

## Phase 1C: AI module

### Task 5: Move AI handler → `Anchor_AI_Handler` (with `MODEL` constant fix)

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/admin/class-ai-handler.php`
- Create: `wp-content/themes/anchor-framework/inc/ai/class-ai-handler.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source file** and recon §4. Note the provider, endpoint, and credential storage option name.

- [ ] **Step 2: Create destination** applying §0.1 (`APA_AI_Handler` → `Anchor_AI_Handler`) and §0.2.

- [ ] **Step 3: Define the missing `MODEL` constant** at the top of the class body:

```php
class Anchor_AI_Handler {
	const MODEL = 'claude-sonnet-4-6';
	// ... rest of the class
}
```

This fixes the latent fatal at `api/class-rest-ai.php:103` (recon §7 / spec §10.1).

- [ ] **Step 4: Add `require_once` in `bootstrap.php`** after the config-writer line:

```php
require_once Anchor_Editor::path() . 'inc/ai/class-ai-handler.php';
```

- [ ] **Step 5: Apply §0.6 verification.**

```bash
php -l wp-content/themes/anchor-framework/inc/ai/class-ai-handler.php
grep -n "const MODEL" wp-content/themes/anchor-framework/inc/ai/class-ai-handler.php
# Expect: at least one match
```

- [ ] **Step 6: Verify pages still load** (surface to user).

- [ ] **Step 7: Commit**

```bash
git add inc/ai/class-ai-handler.php inc/editor/bootstrap.php
git commit -m "$(cat <<'EOF'
fix(ai): move AI handler into theme as Anchor_AI_Handler and define MODEL constant

Fixes pre-existing latent fatal where APA_AI_Handler::MODEL was
referenced by api/class-rest-ai.php but never defined. Defaults to
claude-sonnet-4-6 (overridable via Settings page).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Move prompt builder → `Anchor_AI_Prompt_Builder` (with deka-* removal and section decoupling)

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/includes/class-prompt-builder.php`
- Create: `wp-content/themes/anchor-framework/inc/ai/class-prompt-builder.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source file** and grep it for both problem patterns:

```bash
grep -nE "deka-|APA_Section_" wp-content/plugins/anchor-page-assistant/includes/class-prompt-builder.php
```
Record every matched line number. These lines need targeted edits, not blind copies.

- [ ] **Step 2: Create destination** applying §0.1, §0.2.

- [ ] **Step 3: Apply §0.4** for each `APA_Section_*` reference found in Step 1.
  - If the source references `APA_Section_Schema::get_definitions()` (or similar accessor), replace with an empty array literal: `array()`. Document the line in the commit body.
  - If the source iterates section types into the prompt (e.g., "available sections: hero, cta-band, ..."), drop that prompt fragment entirely. The new prompt does not enumerate sections.

- [ ] **Step 4: Apply §0.5** for each `deka-` reference found in Step 1.
  - If the source has a `deka-home.css` / `deka-shop.css` literal in a string list / array (an allowlist), replace the entire list with: `apply_filters( 'anchor_editor/css_allowlist', array() )`.
  - If the source has them inside the prompt text (e.g., "Your site uses deka-home.css..."), drop the sentence; replace with a generic prompt sentence: `"The site's CSS file inventory is provided to you via the css_allowlist filter."`.

- [ ] **Step 5: Add `require_once`** in `bootstrap.php` after the AI handler line:
```php
require_once Anchor_Editor::path() . 'inc/ai/class-prompt-builder.php';
```

- [ ] **Step 6: Apply §0.6 verification** plus an extra grep for the dropped patterns:

```bash
php -l wp-content/themes/anchor-framework/inc/ai/class-prompt-builder.php
grep -nE "APA_|plugin_dir_(path|url)|deka-|APA_Section_" wp-content/themes/anchor-framework/inc/ai/class-prompt-builder.php
# Expect: no output
grep -n "anchor_editor/css_allowlist" wp-content/themes/anchor-framework/inc/ai/class-prompt-builder.php
# Expect: at least one match
```

- [ ] **Step 7: Surface to user** for visual confirmation pages still load.

- [ ] **Step 8: Commit**

```bash
git add inc/ai/class-prompt-builder.php inc/editor/bootstrap.php
git commit -m "$(cat <<'EOF'
refactor(ai): move prompt builder into theme; drop deka-* refs and section deps

Renames APA_Prompt_Builder to Anchor_AI_Prompt_Builder. Removes the
hard-coded deka-home.css / deka-shop.css references (hard-rule §5
violation) in favor of a filterable allowlist. Drops section-schema
dependency lines per spec §10.2 and §11.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 1D: Assets

### Task 7: Move CSS assets to `assets/editor/css/`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/assets/css/{admin,code-editor,frontend-chat,live-editor}.css`
- Create: `wp-content/themes/anchor-framework/assets/editor/css/{admin,code-editor,frontend-chat,live-editor}.css`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/assets/editor/css
```

- [ ] **Step 2: Copy each file** (do not move — leave originals in plugin per §0.7).

```bash
cp wp-content/plugins/anchor-page-assistant/assets/css/admin.css \
   wp-content/themes/anchor-framework/assets/editor/css/admin.css
cp wp-content/plugins/anchor-page-assistant/assets/css/code-editor.css \
   wp-content/themes/anchor-framework/assets/editor/css/code-editor.css
cp wp-content/plugins/anchor-page-assistant/assets/css/frontend-chat.css \
   wp-content/themes/anchor-framework/assets/editor/css/frontend-chat.css
cp wp-content/plugins/anchor-page-assistant/assets/css/live-editor.css \
   wp-content/themes/anchor-framework/assets/editor/css/live-editor.css
```

- [ ] **Step 3: Grep destinations for any `deka-` references**:
```bash
grep -rn "deka-" wp-content/themes/anchor-framework/assets/editor/css/
```
Expected: no output. If present, surface to user — spec §10.2 assumed the deka-* references were in the prompt builder + allowlist only; this would be a new finding.

- [ ] **Step 4: Commit**

```bash
git add assets/editor/css/
git commit -m "$(cat <<'EOF'
build(assets): copy editor CSS into theme at assets/editor/css/

Verbatim copies of the plugin's CSS files. Originals stay in plugin
until decommission (per spec §14 rollback). No content changes.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Move JS assets to `assets/editor/js/`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/assets/js/{admin-app,chat-widget,code-editor,frontend-chat,inline-edit,live-editor}.js`
- Create: `wp-content/themes/anchor-framework/assets/editor/js/{admin-app,chat-widget,code-editor,frontend-chat,inline-edit,live-editor}.js`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/assets/editor/js
```

- [ ] **Step 2: Copy each file.**

```bash
for f in admin-app chat-widget code-editor frontend-chat inline-edit live-editor; do
  cp "wp-content/plugins/anchor-page-assistant/assets/js/${f}.js" \
     "wp-content/themes/anchor-framework/assets/editor/js/${f}.js"
done
```

- [ ] **Step 3: Verify all 6 files copied.**
```bash
ls wp-content/themes/anchor-framework/assets/editor/js/ | wc -l
```
Expected: `6`.

- [ ] **Step 4: Grep destinations for `deka-` references.**
```bash
grep -rn "deka-" wp-content/themes/anchor-framework/assets/editor/js/
```
Expected: no output. If present, surface — same caveat as Task 7 Step 3.

- [ ] **Step 5: Commit**

```bash
git add assets/editor/js/
git commit -m "$(cat <<'EOF'
build(assets): copy editor JS into theme at assets/editor/js/

Verbatim copies of the plugin's JS files. The wp_localize_script
object name (currently 'APA') stays unchanged in JS code per spec
§16.3; PHP-side handles are renamed when each enqueuing class moves.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 1E: REST controllers (6 of 7 — sections dropped)

Pattern for all six tasks below is identical: read source + recon §7/§14, apply §0.1–§0.6 plus the controller-specific class rename, add a `require_once` line in `bootstrap.php` immediately after the AI handler / prompt-builder lines, verify, commit.

The destination REST namespace stays `anchor-assistant/v1` (spec §8). Do not rename the namespace or route paths.

### Task 9: Move REST/AI controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-ai.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-ai.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/inc/editor/api
```

- [ ] **Step 2: Read source file** and recon §7, §14.

- [ ] **Step 3: Create destination** applying §0.1 (`APA_REST_AI` → `Anchor_Editor_REST_AI`, `APA_AI_Handler` → `Anchor_AI_Handler`) and §0.2.
  - The line `return APA_AI_Handler::MODEL;` becomes `return Anchor_AI_Handler::MODEL;` — and now compiles because Task 5 defined the constant.

- [ ] **Step 4: Add `require_once` to `bootstrap.php`** after the prompt-builder line. Use a single block for all REST controllers (added incrementally per task):
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-ai.php';
```

- [ ] **Step 5: Apply §0.6 verification.**

- [ ] **Step 6: Surface to user** for page-load smoke check.

- [ ] **Step 7: Commit** with message:
```
refactor(editor): move REST/AI controller into theme as Anchor_Editor_REST_AI

Latent MODEL fatal is now resolved (Task 5 introduced the constant).
REST namespace 'anchor-assistant/v1' preserved per spec §8.
```

---

### Task 10: Move REST/Config controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-config.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-config.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §7, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_REST_Config` → `Anchor_Editor_REST_Config`, plus rename any internal references to `APA_Config_Writer` → `Anchor_Editor_Config_Writer`) and §0.2.
- [ ] **Step 3: Add `require_once`** in `bootstrap.php`:
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-config.php';
```
- [ ] **Step 4: §0.6 verification.**
- [ ] **Step 5: Page-load smoke check (surface to user).**
- [ ] **Step 6: Commit:** `refactor(editor): move REST/Config controller into theme as Anchor_Editor_REST_Config`

---

### Task 11: Move REST/Files controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-files.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-files.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §7, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_REST_Files` → `Anchor_Editor_REST_Files`, `APA_File_Writer` → `Anchor_Editor_File_Writer`) and §0.2.
- [ ] **Step 3: Add `require_once`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-files.php';
```
- [ ] **Step 4: §0.6 verification.**
- [ ] **Step 5: Page-load smoke check.**
- [ ] **Step 6: Commit:** `refactor(editor): move REST/Files controller into theme as Anchor_Editor_REST_Files`

---

### Task 12: Move REST/History controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-history.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-history.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §7, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_REST_History` → `Anchor_Editor_REST_History`) and §0.2.
- [ ] **Step 3: Add `require_once`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-history.php';
```
- [ ] **Step 4: §0.6 verification.**
- [ ] **Step 5: Page-load smoke check.**
- [ ] **Step 6: Commit:** `refactor(editor): move REST/History controller into theme as Anchor_Editor_REST_History`

---

### Task 13: Move REST/Media controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-media.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-media.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §7, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_REST_Media` → `Anchor_Editor_REST_Media`) and §0.2.
- [ ] **Step 3: Add `require_once`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-media.php';
```
- [ ] **Step 4: §0.6 verification.**
- [ ] **Step 5: Page-load smoke check.**
- [ ] **Step 6: Commit:** `refactor(editor): move REST/Media controller into theme as Anchor_Editor_REST_Media`

---

### Task 14: Move REST/Menus controller

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/api/class-rest-menus.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/api/class-rest-menus.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §7, §14.
- [ ] **Step 2: Create destination** applying §0.1 (`APA_REST_Menus` → `Anchor_Editor_REST_Menus`) and §0.2.
- [ ] **Step 3: Add `require_once`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-menus.php';
```
- [ ] **Step 4: §0.6 verification.**
- [ ] **Step 5: Page-load smoke check.**
- [ ] **Step 6: Commit:** `refactor(editor): move REST/Menus controller into theme as Anchor_Editor_REST_Menus`

**Note:** `class-rest-sections.php` is **not** moved — it dies with the section schema per spec §11.

---

## Phase 1F: Admin pages

### Task 15: Move admin page → `Anchor_Editor_Page` (with top-level Anchor menu)

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/admin/class-admin-page.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/admin/class-editor-page.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/inc/editor/admin
```

- [ ] **Step 2: Read source** and recon §2 (admin-page subsection), §14.

- [ ] **Step 3: Create destination** applying §0.1 (`APA_Admin_Page` → `Anchor_Editor_Page`) and §0.2 and §0.3 (asset handle rename for any `wp_enqueue_*` calls in this file).

- [ ] **Step 4: Replace the menu registration.** Wherever the source calls `add_menu_page(...)` or `add_submenu_page(...)`, replace the structure with:

```php
public function register_menu() {
	add_menu_page(
		__( 'Anchor', 'anchor' ),
		__( 'Anchor', 'anchor' ),
		'manage_options',
		'anchor',
		array( $this, 'render' ),
		'dashicons-anchor',
		25
	);
	add_submenu_page(
		'anchor',
		__( 'Editor', 'anchor' ),
		__( 'Editor', 'anchor' ),
		'manage_options',
		'anchor',
		array( $this, 'render' )
	);
}
```

Settings + Config submenus register from their own classes (Tasks 16, 17) under parent slug `anchor`.

- [ ] **Step 5: Re-key asset enqueues to the new screen IDs.** The new top-level page hook is `toplevel_page_anchor`. Wherever the source checks `$hook === 'something_apa_something'`, replace with `$hook === 'toplevel_page_anchor'`. The new slug for the editor page itself is `anchor`.

- [ ] **Step 6: Update asset URLs** — `$plugin_url . 'assets/...'` becomes `Anchor_Editor::url() . 'assets/editor/...'`. Apply §0.3 handle renames to every enqueue call.

- [ ] **Step 7: Add `require_once` to `bootstrap.php`** after the REST controllers:
```php
require_once Anchor_Editor::path() . 'inc/editor/admin/class-editor-page.php';
```

- [ ] **Step 8: Apply §0.6 verification.**

- [ ] **Step 9: Surface to user:** "Load `/wp-admin/`. The Anchor top-level menu should appear with an Editor submenu. Confirm and report any errors."

- [ ] **Step 10: Commit:**
```
feat(editor): move admin page into theme and register top-level Anchor menu

Replaces the plugin's menu registration with a top-level Anchor menu
(dashicons-anchor, position 25). Subsequent tasks add Settings and
Config submenus under the 'anchor' parent slug.
```

---

### Task 16: Move settings page → `Anchor_Editor_Settings`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/admin/class-settings-page.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/admin/class-settings-page.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §2 (settings subsection), §14.

- [ ] **Step 2: Create destination** applying §0.1 (`APA_Settings_Page` → `Anchor_Editor_Settings`) and §0.2 and §0.3.

- [ ] **Step 3: Register submenu under Anchor parent.** Wherever the source uses `add_options_page` or `add_submenu_page( 'apa-something', ... )`, replace with:

```php
add_submenu_page(
	'anchor',
	__( 'Settings', 'anchor' ),
	__( 'Settings', 'anchor' ),
	'manage_options',
	'anchor-settings',
	array( $this, 'render' )
);
```

- [ ] **Step 4: Re-key any screen-ID asset checks** to `anchor_page_anchor-settings`.

- [ ] **Step 5: Preserve `register_setting` calls verbatim** — same option names (`apa_ai_*` keys stay; spec §10.1).

- [ ] **Step 6: Add `require_once` to `bootstrap.php`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/admin/class-settings-page.php';
```

- [ ] **Step 7: §0.6 verification.**

- [ ] **Step 8: Page-load smoke check (surface to user).**

- [ ] **Step 9: Commit:** `feat(editor): move settings page into theme as Anchor_Editor_Settings`

---

### Task 17: Move config manager → `Anchor_Editor_Config_Manager`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/admin/class-config-manager.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/admin/class-config-manager.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read source** and recon §2 (config-manager subsection), §14.

- [ ] **Step 2: Create destination** applying §0.1 (`APA_Config_Manager` → `Anchor_Editor_Config_Manager`, plus rename `APA_Config_Writer` → `Anchor_Editor_Config_Writer` if referenced) and §0.2 and §0.3.

- [ ] **Step 3: Register submenu under Anchor parent.**

```php
add_submenu_page(
	'anchor',
	__( 'Config', 'anchor' ),
	__( 'Config', 'anchor' ),
	'manage_options',
	'anchor-config',
	array( $this, 'render' )
);
```

- [ ] **Step 4: Re-key any screen-ID asset checks** to `anchor_page_anchor-config`.

- [ ] **Step 5: Apply §0.4** if the source references any section-schema classes (the config manager may iterate sections in the legacy paradigm; drop those code paths).

- [ ] **Step 6: Add `require_once`:**
```php
require_once Anchor_Editor::path() . 'inc/editor/admin/class-config-manager.php';
```

- [ ] **Step 7: §0.6 verification.**

- [ ] **Step 8: Surface to user:** "Load `/wp-admin/admin.php?page=anchor-config`. Confirm the page loads with no PHP errors."

- [ ] **Step 9: Commit:** `feat(editor): move config manager into theme as Anchor_Editor_Config_Manager`

---

## Phase 1G: Frontend & templates

### Task 18: Move frontend chat → `Anchor_Editor_Frontend_Chat`

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/admin/class-frontend-chat.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/frontend/class-frontend-chat.php`
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/inc/editor/frontend
```

- [ ] **Step 2: Read source** and recon §3, §14.

- [ ] **Step 3: Create destination** applying §0.1 (`APA_Frontend_Chat` → `Anchor_Editor_Frontend_Chat`) and §0.2 and §0.3.

- [ ] **Step 4: Update asset URLs** for the `wp_enqueue_*` calls to point at `Anchor_Editor::url() . 'assets/editor/...'`.

- [ ] **Step 5: Preserve the capability gate and `wp_footer`/`wp_head` hook target verbatim.**

- [ ] **Step 6: Add `require_once`** in `bootstrap.php`:
```php
require_once Anchor_Editor::path() . 'inc/editor/frontend/class-frontend-chat.php';
```

- [ ] **Step 7: §0.6 verification.**

- [ ] **Step 8: Surface to user:** "Load `http://anchor-framework.local/` while logged in as admin. The chat widget should appear in the footer."

- [ ] **Step 9: Commit:** `feat(editor): move frontend chat into theme as Anchor_Editor_Frontend_Chat`

---

### Task 19: Move templates

**Files:**
- Source (read-only): `wp-content/plugins/anchor-page-assistant/templates/{admin-page,live-editor}.php`
- Create: `wp-content/themes/anchor-framework/inc/editor/templates/{admin-page,live-editor}.php`

- [ ] **Step 1: Create destination directory.**
```bash
mkdir -p wp-content/themes/anchor-framework/inc/editor/templates
```

- [ ] **Step 2: Read both source templates** to identify the variable scope they assume (recon §10).

- [ ] **Step 3: Copy each template** applying §0.2 if any `plugin_dir_*` or `APA_*` references appear.

```bash
cp wp-content/plugins/anchor-page-assistant/templates/admin-page.php \
   wp-content/themes/anchor-framework/inc/editor/templates/admin-page.php
cp wp-content/plugins/anchor-page-assistant/templates/live-editor.php \
   wp-content/themes/anchor-framework/inc/editor/templates/live-editor.php
```

Then edit each file to apply §0.2 (constant rename) only — class names typically do not appear in templates.

- [ ] **Step 4: Update include paths in the calling classes** to reference the new template locations. The admin page class (Task 15) and any class that includes `live-editor.php` need their `include` / `require` paths repointed:

```bash
grep -rn "templates/admin-page" wp-content/themes/anchor-framework/inc/editor/
grep -rn "templates/live-editor" wp-content/themes/anchor-framework/inc/editor/
```

Repoint each occurrence to `Anchor_Editor::path() . 'inc/editor/templates/...'`.

- [ ] **Step 5: §0.6 verification on both templates.**

- [ ] **Step 6: Surface to user** for admin-page + live-editor visual confirm.

- [ ] **Step 7: Commit:** `feat(editor): move admin and live-editor templates into theme`

---

## Phase 1H: Wire-up & cleanup pass

### Task 20: Instantiate registrars in `bootstrap.php`

**Files:**
- Modify: `wp-content/themes/anchor-framework/inc/editor/bootstrap.php`

- [ ] **Step 1: Read current `bootstrap.php`** — should have `Anchor_Editor` class + a stack of `require_once` lines from Tasks 3, 4, 5, 6, 9–14, 15, 16, 17, 18 (12 includes).

- [ ] **Step 2: Append instantiation block** at the end of the file (this is what actually registers admin pages, REST routes, frontend chat):

```php
add_action( 'plugins_loaded', function() {
	new Anchor_Editor_Page();
	new Anchor_Editor_Settings();
	new Anchor_Editor_Config_Manager();
	new Anchor_Editor_Frontend_Chat();
	new Anchor_Editor_REST_AI();
	new Anchor_Editor_REST_Config();
	new Anchor_Editor_REST_Files();
	new Anchor_Editor_REST_History();
	new Anchor_Editor_REST_Media();
	new Anchor_Editor_REST_Menus();
} );
```

(If any of these classes use a different bootstrap pattern — e.g., a static `init()` method — match the source. The implementer must re-check each class's constructor signature when reading it; this block assumes a no-arg `__construct` that registers hooks.)

- [ ] **Step 3: PHP-lint the bootstrap.**
```bash
php -l wp-content/themes/anchor-framework/inc/editor/bootstrap.php
```

- [ ] **Step 4: Surface to user:**
"Load `/wp-admin/`. You should see the Anchor top-level menu with Editor, Settings, Config submenus. Visit each. Then load the front end while logged in — chat widget should appear in footer. Then check `/wp-json/anchor-assistant/v1/ai/settings` (should return 200, not a fatal). Paste any errors from `debug.log`."

- [ ] **Step 5: Commit:** `feat(editor): wire up all editor and REST classes in bootstrap`

---

### Task 21: Full-codebase grep for residual `APA_`, `deka-`, `plugin_dir_*` in framework

**Files:** No file changes expected — this task verifies cleanliness. If it finds anything, fix and re-verify.

- [ ] **Step 1: Grep for `APA_` symbols.**
```bash
grep -rn "APA_" wp-content/themes/anchor-framework/ --include="*.php"
```
Expected: no output. If matches exist (likely in a missed include or a string), fix the file and re-grep.

- [ ] **Step 2: Grep for `deka-` strings.**
```bash
grep -rn "deka-" wp-content/themes/anchor-framework/
```
Expected: no output. If matches exist, surface to user with file and line — spec §10.2 lock said "zero `deka-` strings post-merge".

- [ ] **Step 3: Grep for `plugin_dir_*` calls.**
```bash
grep -rn "plugin_dir_\(path\|url\)" wp-content/themes/anchor-framework/ --include="*.php"
```
Expected: no output.

- [ ] **Step 4: Grep for legacy section-schema class names.**
```bash
grep -rn "APA_Section_\|Anchor_Section_Schema\|class-section-schema\|class-section-registry" wp-content/themes/anchor-framework/inc/editor/ wp-content/themes/anchor-framework/inc/ai/
```
Expected: no output (this code path was supposed to die per spec §11).

- [ ] **Step 5: If any of Steps 1–4 produced output, fix inline and re-run.** Otherwise this task is a no-op verification — no commit needed. Append a note to the parent agent's task log: "Task 21 verification passed — no residual symbols."

---

## Phase 1I: Acceptance gate

### Task 22: User acceptance verification

**Files:** None.

- [ ] **Step 1: Surface the full spec §15 acceptance checklist to the user** for manual smoke testing. Copy the list verbatim from the spec.

- [ ] **Step 2: Wait for user to either:**
  - **Confirm all items pass** → proceed to Phase 1J (decommission).
  - **Report failures** → stop, open a remediation task per failure, fix, re-verify.

- [ ] **Step 3: Do not proceed without explicit user confirmation.** The acceptance gate is a hard checkpoint per master plan §5.5.

---

## Phase 1J: Decommission plugin

These tasks operate on the `anchor-page-assistant` plugin repo (not the framework theme). They run on its `feat/migrate-to-theme` branch (already created in Phase 0 §4.4).

### Task 23: Deactivate plugin (confirm) and strip to deprecation notice

**Files:**
- Modify: `wp-content/plugins/anchor-page-assistant/anchor-page-assistant.php`
- Delete: every other file in the plugin (admin/, api/, assets/, includes/, templates/, vendor/, CHANGELOG.md, RELEASING.md, README.md)

- [ ] **Step 1: Confirm plugin is deactivated in WP.**
```bash
wp plugin list --status=active --field=name | grep anchor-page-assistant
```
Expected: no output.

- [ ] **Step 2: Confirm working tree of plugin is clean and on `feat/migrate-to-theme`.**
```bash
cd wp-content/plugins/anchor-page-assistant
git status --short
git branch --show-current
```
Expected: empty output for status, `feat/migrate-to-theme` for branch.

- [ ] **Step 3: Replace `anchor-page-assistant.php` with deprecation-notice-only content:**

```php
<?php
/**
 * Plugin Name:       Anchor Page Assistant
 * Plugin URI:        https://github.com/joelhmartin/anchor-page-assistant
 * Description:       DEPRECATED — functionality has moved into the Anchor Framework parent theme. Please deactivate and delete this plugin.
 * Version:           1.0.0-deprecated
 * Author:            Anchor
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Anchor Page Assistant</strong> is deprecated. Its functionality has merged into the Anchor Framework theme. Please deactivate and delete this plugin.</p></div>';
} );
```

- [ ] **Step 4: Delete every other file in the plugin tree.**

```bash
cd wp-content/plugins/anchor-page-assistant
rm -rf admin api assets includes templates vendor
rm -f CHANGELOG.md RELEASING.md README.md
```

(Keep: `.git/`, `.github/` (if present), `.gitignore` (if present), the new minimal main file.)

- [ ] **Step 5: Add `DEPRECATED.md`** at the plugin root:

```markdown
# Anchor Page Assistant — DEPRECATED

This plugin is deprecated as of v1.0.0-final.

All functionality has merged into the Anchor Framework parent theme:
https://github.com/joelhmartin/anchor-framework

To upgrade:
1. Pull the latest Anchor Framework theme.
2. Deactivate and delete this plugin.

No data migration is required. AI credentials and other settings stored under `apa_*` option keys are read by the framework theme as-is.
```

- [ ] **Step 6: Surface to user:** "Loading `/wp-admin/plugins.php` should show Anchor Page Assistant with a deprecation notice. The plugin folder is now stripped to just the main file and DEPRECATED.md. Confirm and report any errors."

- [ ] **Step 7: Commit:**
```bash
cd wp-content/plugins/anchor-page-assistant
git add -A
git commit -m "$(cat <<'EOF'
chore: deprecate plugin in favor of Anchor Framework theme merge

Strips all code; plugin now ships an admin notice only. All
functionality has merged into the anchor-framework parent theme.
See DEPRECATED.md.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 24: Tag final standalone release on plugin repo

**Files:** No code change — git operation only.

- [ ] **Step 1: Identify the immediately-prior commit on `main`** (the last fully-functional plugin commit).
```bash
cd wp-content/plugins/anchor-page-assistant
git log main -1 --format='%H %s'
```

- [ ] **Step 2: Tag it as `v1.0.0-final`.**

```bash
git tag -a v1.0.0-final <SHA from step 1> -m "Final standalone release before merge into anchor-framework"
```

- [ ] **Step 3: Verify the tag.**
```bash
git tag -l v1.0.0-final
git show v1.0.0-final --stat | head -5
```
Expected: tag exists and points at the last functional `main` SHA.

- [ ] **Step 4: Do NOT push the tag yet.** Master plan §10.1 says PRs/pushes are user-discretion. Surface to user: "Tag `v1.0.0-final` created locally on plugin repo. Branch `feat/migrate-to-theme` has the deprecation commit. Push when ready: `git push origin feat/migrate-to-theme` and `git push origin v1.0.0-final`."

---

## Phase 1K: Wrap-up

### Task 25: Surface Phase 1 done summary to user

**Files:** None.

- [ ] **Step 1: Compose summary** covering:
  - Framework branch `feat/consolidation-prep` — N commits, ready for user merge to `main`
  - Plugin branch `feat/migrate-to-theme` — 1 deprecation commit + 1 tag `v1.0.0-final`, ready for user push
  - All spec §15 acceptance checkboxes verified
  - Pre-existing defects fixed: `MODEL` fatal + `deka-*` references
  - Decommission complete; plugin is admin-notice-only on disk

- [ ] **Step 2: Per master plan §5.6, wait for user to greenlight Phase 2.** Do not start Phase 2 work.

---

## Risks & escape hatches

If during any task the implementer subagent finds something inconsistent with the recon doc (e.g., a class with a name not on the rename map; a hook chain the recon missed), STOP and surface to the parent agent. The parent agent decides whether to amend this plan in-place or to roll back. Per master plan §10.4: do not work around — surface and pause.

If a verification step (§0.6 or Task 21) fails:
1. Re-read the source plugin file in full.
2. Diff against the new theme file.
3. If the difference is unintentional, fix it. If it's a recon-omission, surface to the parent agent and pause.

---

*End of plan.*
