# WP Tables Agent Instructions

Read this file at the start of every WP Tables session.

## Linear Context

- Linear project: `WP Tables`
- Linear team: `Ianthompson`
- Linear project workflow document: `Engineering workflow and PR standard`
- Current released version must stay prominent in the Linear project summary and description.
- Keep the Linear project version aligned with the released plugin version, not an unreleased PR branch version.

Before editing:

1. Read the Linear issue and any linked project document or spec.
2. Identify the issue acceptance criteria and non-goals.
3. Inspect the relevant plugin files and current patterns.
4. Run `git status` before making changes.

Do not implement feature work without a Linear issue unless Ian explicitly asks for an exploratory local change.

## Branch Workflow

- Work from a branch off `main`.
- Never commit feature work directly to `main`.
- Use one branch and one PR per Linear issue.
- Prefer the Linear branch name for the issue when available.
- Keep unrelated work out of the issue branch.
- Do not revert user changes or unrelated work in a dirty worktree.

## Implementation Standard

- Implement only the stated acceptance criteria.
- Preserve existing behavior unless the issue explicitly changes it.
- Do not refactor opportunistically.
- Follow existing WordPress APIs, plugin structure, naming, escaping, and sanitization patterns.
- Keep CSV-backed tables safe. Treat CSV content and editor settings as untrusted input.
- Keep preview and frontend rendering behavior aligned where the issue expects it.
- Prefer small allowlisted presentation controls over open-ended unsafe CSS or markup input.

## Current Verification Gates

Use the narrowest useful verification for the change.

Run as relevant:

- `php -l wp-tables.php` after PHP changes.
- `node --check assets/admin.js` after admin JavaScript changes.
- `sh -n scripts/build-local-zip.sh` after packaging script changes.
- `git diff --check` before pushing a PR branch.
- `scripts/build-local-zip.sh` for releasable plugin changes.
- Inspect `wp-tables.zip` contents after rebuilding the local upload package.

When available, verify user-visible changes in a real WordPress admin/frontend context.

`Reviewer-manual` is a valid verification category. If an agent cannot verify WordPress admin screens, uploaded CSV behavior, frontend theme interactions, update-screen behavior, or other environment-specific behavior, say so plainly and list exact human test steps.

There is no established automated plugin test harness yet. Add or update tests when a test harness exists and the issue touches logic, data flow, permissions, update flow, CSV handling, integrations, or user-visible behavior.

## Release And Version Bookkeeping

For a releasable plugin change:

- Bump the plugin header version in `wp-tables.php`.
- Bump `Stable tag` in `readme.txt`.
- Update asset enqueue versions when changed assets need cache busting.
- Rebuild the local ignored upload package with `scripts/build-local-zip.sh`.
- Keep `wp-tables.zip` ignored and local.

For a released version:

- Publish via the tagged GitHub release workflow.
- Confirm the GitHub release asset `wp-tables.zip` exists.
- Update the Linear project summary and description so the current released version is prominent.
- Do not move Linear's current version forward for an unreleased PR branch.

## Pull Requests

Before opening a PR:

1. Run the relevant verification gates.
2. Review the diff for scope creep and unrelated files.
3. Refresh the local upload zip for releasable plugin changes.
4. Link the PR back to the Linear issue.

Each PR should explain:

- What changed
- Why
- The Linear issue
- Acceptance criteria checked
- Verification commands and results
- Screenshots, preview, or Loom when relevant
- Reviewer-manual steps when needed
- Risk
- What was intentionally not done
- Agent involvement
- Follow-up issues created

Open draft PRs by default unless Ian asks otherwise.

## Review Standard

Review against the linked Linear issue only.

Look for:

- Acceptance-criteria gaps
- Bugs and data-flow regressions
- Security, escaping, and sanitization problems
- WordPress admin or frontend update/package regressions
- Unnecessary scope expansion
- Missing loading or error states
- Code that will be hard for future agents to modify

Group review feedback as:

1. Must fix before merge
2. Should fix soon
3. Safe to merge

Capture unrelated problems as Linear follow-up issues instead of fixing them in the same PR.

## Project Notes

- Plugin repository: `https://github.com/ianthompson/wp-tables`
- Main plugin entry point: `wp-tables.php`
- Admin assets: `assets/admin.js` and `assets/admin.css`
- Frontend stylesheet: `assets/frontend.css`
- Local upload package builder: `scripts/build-local-zip.sh`
- GitHub release workflow: `.github/workflows/release.yml`
