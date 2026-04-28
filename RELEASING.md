# Releasing Anchor Framework

This document describes how to cut a new release. Releases are how versions ship to consuming sites — without a tagged release on GitHub, the WP-side updater will not see new code.

## When to bump the version

| Change type | Bump | Examples |
|---|---|---|
| Breaking API change | major | rename a class, remove a helper, change section prop shape |
| Backward-compatible addition | minor | new section template, new helper, new variant |
| Bug fix or internal refactor | patch | CSS layout fix, internal rename, doc update |

The "API" is what's listed in `docs/components.md` and `docs/hooks.md`. Anything outside those documents is private and can change without a major bump.

## Release steps

1. **Verify the work is on `main`** and the working tree is clean.
2. **Update `Version:` in `style.css`** to the new number.
3. **Update `CHANGELOG.md`** — move entries from `[Unreleased]` to a new dated heading. Document any breaking changes in the migration section.
4. **Update `Anchor Assistant Compatibility:`** in `CHANGELOG.md` if the supported `anchor-page-assistant` range changed.
5. **Commit:** `git commit -am "release: vX.Y.Z"`.
6. **Tag:** `git tag -a vX.Y.Z -m "Anchor Framework vX.Y.Z"`.
7. **Push:** `git push origin main --tags`.
8. **Create a GitHub release** from the tag. Paste the changelog entry into the release notes. Optionally attach a built `.zip` (the update-checker can consume tag archives directly, so this is only useful if you want to ship a different artifact than the repo contents).
9. **Verify on a consumer site** — visit *Appearance → Themes*. The site should show *Update available* within a few minutes (cache window). Click *Update Now* and confirm the version bumps.

## Coordinating with `anchor-page-assistant`

When a release of the framework needs a matching release of the assistant:

1. Ship the framework first.
2. In the assistant repo, update `Requires Anchor Framework:` in `anchor-page-assistant.php` and the changelog.
3. Tag and release the assistant with the matching version number.

If the version numbers do not need to match (independent fix on one side), they can drift — the compatibility headers govern what's allowed.

## Rolling back

WordPress core has no built-in theme rollback UI. To roll back:

1. On a consumer site, manually delete `wp-content/themes/anchor-framework/`.
2. Download the previous release zip from GitHub.
3. Upload via *Appearance → Themes → Add New → Upload Theme*.

The WP child theme is unaffected by parent rollbacks, so the site keeps rendering throughout (other than the parent's specific files).

## Hotfixing a bad release

If a release breaks consuming sites:

1. Revert the breaking commit on `main`.
2. Bump the patch version (e.g., `1.4.1` → `1.4.2`).
3. Repeat the release flow.

Sites that already auto-updated will receive the hotfix on the next check-in cycle.
