# CI Build Fixes — Task List

Purpose: Track and complete the two CI-related user stories so progress can be checked off and the work can be resumed at any time.

Scope of this checklist:
- CI code-quality check inconsistency between local and CI
- CI build for tests failing (likely missing frontend build causing Vite manifest error)

References:
- User requests: see `docs/user-requests.md` → “The CI code-quality check isn't consistent” and “The CI build for tests is failing”.
- Follow `.junie/guidelines.md` — regularly run `composer all` locally to validate code style, static analysis, and tests.

Assumptions:
- npm, git, and PHP are installed locally and can be run from the command line.
- Preferred local validation: `composer all` during/after each major change.

---

## Setup

- [x] use `git` to create and checkout a branch 'feature/fix-ci-pipelines'

## A. CI code-quality check isn't consistent

Problem summary (from user-requests):
- CI used `vendor/bin/phpcs --standard=PSR12 app tests` which behaves differently to local `composer cs` (`phpcs --standard=PSR12 --extensions=php app tests`).
- CI was failing due to `.js` files being checked by one path but not the other; make both consistent. Recommendation: both use `--extensions=php,js`.

Checklist:
- [x] Audit current CI code-quality command(s) (e.g., in `.github/workflows/*.yml` or CI scripts) and note differences with `composer cs`.
- [x] Update local Composer scripts to explicitly include JS for consistency:
  - `composer cs` → `phpcs --standard=PSR12 --extensions=php,js app tests`
  - `composer cs-fix` → `phpcbf --standard=PSR12 --extensions=php,js app tests`
- [x] Update CI to call `composer cs` (preferred) so CI and local are identical.
- [x] Run locally: `composer cs` and address any violations.
- [x] If PHPCS reports auto-fixables, run `composer cs-fix` locally, commit changes.
- [x] Run locally: `composer all` to ensure code style, static analysis, and tests still pass.
- [ ] Open PR and confirm CI code-quality step passes with the updated configuration.

Notes:
- PHPCBF can fix many violations automatically. Re-run `composer cs` after `composer cs-fix` to confirm a clean result.

---

## B. The CI build for tests is failing

Problem summary (from user-requests):
- CI fails to complete the build; likely missing `npm run build` causing `Illuminate\Foundation\ViteManifestNotFoundException` during `php artisan key:generate` or test bootstrap.

Checklist:
- [x] Ensure CI installs frontend dependencies: `npm ci` (or `npm install --frozen-lockfile` if using pnpm/yarn alternative; default is npm here).
- [x] Ensure CI builds assets before running Laravel commands/tests: `npm run build`.
- [x] Verify that `public/build/manifest.json` exists after the build (Vite default) — this should prevent `ViteManifestNotFoundException`.
- [x] Order of steps in CI should be:
  1) Checkout
  2) Setup PHP toolchain and cache Composer
  3) `composer install --no-interaction --prefer-dist --no-progress`
  4) Setup Node and cache npm
  5) `npm ci`
  6) `npm run build`
  7) Prepare Laravel app: copy `.env.example` → `.env`, `php artisan key:generate`
  8) Run migrations/seeding for tests (SQLite in-memory per phpunit.xml or a test DB if configured)
  9) Run quality and tests: `composer all` or `composer test`
- [ ] Confirm `php artisan key:generate` runs cleanly in CI after the build step.
- [ ] Confirm the PHPUnit run is green in CI.
- [x] Run locally: `composer all` to mirror the CI workflow and confirm parity.

Optional mitigations (use only if needed):
- [ ] If the application does not require built assets during tests, consider deferring view rendering or configuring a test-safe Vite setting. However, building in CI is the simpler and more robust approach.

---

## C. Verification and close-out
- [ ] Update `docs/tasks.md` item “1.1.5 Fix CI build (tests and code-quality)” to mark the two sub-items as complete `[x]` when done.
- [ ] Document any CI workflow file changes in the PR description.
- [x] Final local run: `composer all` and ensure no regressions.

---

## Commands reference (local)
- Run everything: `composer all`
- Code style check: `composer cs`
- Code style auto-fix: `composer cs-fix`
- Static analysis: `composer phpstan`
- Tests: `composer test`
- Frontend build: `npm ci && npm run build`
