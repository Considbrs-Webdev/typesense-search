---
name: release
description: Cut a new version of the typesense-search plugin - bump the version, update CHANGELOG.md, commit, merge dev into main, tag, and publish a GitHub release. Use when the user asks to "release", "cut a version", "bump the version", or "tag a release".
---

# Cutting a release

This plugin has no build/asset step tracked in git (`/assets/` is gitignored — see
"Known gap" at the end). A release here just means: bump the version everywhere it's
declared, write a changelog entry, tag it, and publish GitHub release notes.

Version lives in **four** places and must move together:
- `typesense-search.php` — `* Version: X.Y.Z` header
- `composer.json` — `"version": "X.Y.Z"`
- `package.json` — `"version": "X.Y.Z"`
- `package-lock.json` — two `"version"` fields (root + `packages[""]`)

## 1. Figure out the next version

```bash
git fetch --tags
git describe --tags --abbrev=0          # current version
git log --oneline <last-tag>..dev        # unreleased changes on dev
```

Pick the bump by semver, same precedent as this repo's history (check `CHANGELOG.md` for
examples): bug fixes only → patch, backward-compatible features → minor, breaking change
(renamed hook/option/REST route/CLI command — see CLAUDE.md's "Stable identifiers") →
major. When in doubt, ask the user rather than guessing.

## 2. Draft the changelog entry — confirm before writing

Summarize the unreleased commits into **user-facing** bullets (not implementation detail —
compare `git log` messages against existing `CHANGELOG.md` entries for tone: e.g. "Fixed X
happening when Y", not "changed the shouldIndex() check in class Z"). Group under
`### Added` / `### Changed` / `### Fixed` as needed, Keep-a-Changelog style. Show the user
the draft entry and the chosen version number before proceeding — this is the one judgment
call in the process worth a pause.

## 3. Apply the version bump

```bash
# typesense-search.php, composer.json: edit the Version/version fields directly
npm version <X.Y.Z> --no-git-tag-version --allow-same-version   # syncs package.json + package-lock.json together
```

Insert the confirmed changelog entry at the top of `CHANGELOG.md` (after the header, before
the previous latest entry), following the existing format.

## 4. Verify

```bash
php -l typesense-search.php
composer test
npm run build       # confirm the frontend still builds even though dist isn't committed
```

## 5. Commit

Mirror this repo's existing pattern: **actual behavior changes get their own commit(s)**
(commit those as part of the normal course of work, before starting a release — don't bundle
a feature/fix into the release commit). The release commit itself touches only the version
files and changelog:

```bash
git add typesense-search.php composer.json package.json package-lock.json CHANGELOG.md
git commit -m "Release X.Y.Z"
```

## 6. Push, merge to main, tag — confirm before this step

This pushes to the shared remote and publishes a public release; confirm with the user
before running it (unless they already explicitly asked for the full release including
merge + publish in this same request).

```bash
git push origin dev

git checkout main
git merge --ff-only dev     # dev and main should never have diverged; if this fails, stop and investigate rather than force
git push origin main

git tag -a X.Y.Z -m "Release X.Y.Z"
git push origin X.Y.Z

git checkout dev
```

## 7. Publish the GitHub release

Match the existing release notes style (see e.g.
`gh release view 1.4.1 --repo Considbrs-Webdev/typesense-search`): title `vX.Y.Z`, body is
the changelog entry verbatim (starting with `## [X.Y.Z] - date`).

```bash
gh release create X.Y.Z --repo Considbrs-Webdev/typesense-search \
  --title "vX.Y.Z" \
  --notes "$(cat <<'EOF'
## [X.Y.Z] - YYYY-MM-DD

### Fixed
- ...
EOF
)"
```

## Known gap: Composer installs ship no built assets

`.gitignore` excludes all of `/assets/`, including the Vite build output
(`assets/dist/`). A site requiring this plugin via Composer from GitHub gets no compiled
JS/CSS — nothing in this release process currently fixes that. If asked to address it, the
likely direction (confirm with the user first, don't just do it): keep `dev` source-only,
but have CI build and commit `assets/dist/` onto `main` specifically as part of the release,
before tagging — so a Composer install of the tag gets working assets without the consuming
site needing Node.
