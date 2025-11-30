# Task List: Make the Agile cost chart interactive

Last updated: 2025-11-29 13:20 (local)

Purpose: Implement the interactivity described in `docs/make-the-agile-cost-chart-interactive.md` using a TDD/DDD
approach. Each step is a restartable checkpoint. Regularly run `composer all` to keep quality green.

Related files:

- Widget: `app/Filament/Widgets/AgileChart.php`
- Test helper widget: `tests/Unit/Filament/Widgets/AgileChartTestWidget.php`
- Strategy cost chart (to update or align later): `app/Filament/Resources/StrategyResource/Widgets/CostChart.php`
- Panel provider (for registering JS/plugins if required): `app/Providers/Filament/AppPanelProvider.php`
- User story: `docs/make-the-agile-cost-chart-interactive.md`

Notes:

- Follow `.junie/guidelines.md` for development, testing, and code quality.
- Keep changes minimal and focused; prefer configuration in `getOptions()` and `getData()`.
- Prefer UTC storage with Europe/London display for current-period indicator logic.

---

- Progress:
    - [x] Backend options and unit tests for interaction, tooltips, annotation, zoom/pan
    - [x] Filament asset registration for Chart.js plugins and tooltip helper
    - [x] Front-end NPM deps installed and assets built locally (zoom/annotation plugins)
    - [ ] Manual QA in Dashboard (hover aggregation, indicator, zoom/pan, reset)

## 0. Preparation and planning (DDD/TDD framing)

- [x] Read and confirm acceptance criteria in `docs/make-the-agile-cost-chart-interactive.md`.
- [x] Skim current implementations of `AgileChart` and `CostChart` to understand existing `getData()` and
  `getOptions()`.
- [x] Decide plugin loading strategy for Chart.js plugins:
    - [ ] If Filament/Charts already bundles plugins, use options only.
    - [x] Otherwise, plan to add `chartjs-plugin-zoom` and `chartjs-plugin-annotation` via NPM and register with
      Vite/Filament.
- [ ] Create a small design note in commit message describing: tooltip aggregation, current-period indicator, zoom/pan,
  reset action.

Quality gate

- [x] Run: `composer all` (ensure green before changes to have a clean baseline).

---

## 1. Add/verify front-end dependencies

Follow Filament v3 documentation https://filamentphp.com/docs/3.x/widgets/charts#using-custom-chartjs-plugins

- [x] Add packages: `npm i -D chartjs-plugin-zoom chartjs-plugin-annotation`.
- [x] Register plugins in the chart initialization layer used by Filament (e.g., a small JS entry in `resources/js` and
  import via Vite, or per-widget registration hook if available).
- [x] Ensure plugins are loaded in the panel where `AgileChart` renders (e.g., via `AppPanelProvider` assets or Filament
  config).
- [x] Build assets: `npm run build` or `npm run dev`.

Verification

- [x] Manual smoke check in browser devtools: plugins are present on `window.Chart.registry.plugins` (optional).

---

## 2. TDD: configure interaction mode and tooltip aggregation (PHP unit tests first)

- [x] Extend `tests/Unit/Filament/Widgets/AgileChartTestWidget.php` (or create a new unit test) to assert `getOptions()`
  contains:
    - [x] `interaction.mode === 'index'` and `interaction.intersect === false`.
    - [x] `plugins.tooltip.callbacks` keys for custom rendering (assert presence/shape, not JS bodies).
- [x] Run: `composer test` (expect failing tests).

Implementation

- [x] Update `AgileChart::getOptions()` to return `interaction: { mode: 'index', intersect: false }`.
- [x] Add tooltip callbacks structure under `plugins.tooltip` that presents:
    - [x] Header with period (full date and time range for the 30-minute slot).
    - [x] Body lines per dataset (Import, Export, Averages) with currency formatting (e.g., `£0.12`).
- [x] Keep x-axis labels concise (e.g., `H:i`).
    
Verification

- [x] Run: `composer test` → tests pass for this section.
- [x] Run: `composer phpstan` and `composer cs`.
- [x] Run: `composer all`.

---

## 3. TDD: add current-period indicator configuration

Tests

- [x] Add/extend unit tests to assert presence of annotation configuration when enabled, e.g. under
  `plugins.annotation.annotations.currentPeriod` with properties referencing the current x-index/label.
- [x] Include timezone-aware mapping note in tests (assert shape; not runtime position).
- [x] Run: `composer test` (expect failing tests).

Implementation

- [x] In `AgileChart::getOptions()`, add annotation config for a vertical line or highlight at the current 30-minute
  period.
    - [x] Compute current label (Europe/London) that matches `now()` rounded/truncated to the active slot.
    - [x] Configure `chartjs-plugin-annotation` with a vertical line at that x-value (or a background box spanning the
      bar/point width).
- [x] Ensure the indicator recalculates on widget refresh (use `now()` at render time; Filament polling will re-render).

Verification

- [x] Run: `composer test` → tests pass for this section.
- [x] Run: `composer all`.

---

## 4. TDD: add zooming and panning + reset control

Tests

- [x] Extend unit tests to assert `plugins.zoom` presence with keys:
    - [x] `zoom.wheel.enabled === true` and `zoom.pinch.enabled === true`.
    - [x] `pan.enabled === true` on x-axis.
- [x] Run: `composer test` (expect failing tests).

Implementation

- [x] Configure `plugins.zoom` in `AgileChart::getOptions()` with wheel/pinch zoom on x, and panning on x.
- [x] Add a Filament action in the widget header: “Reset Zoom”.
    - [x] Emit a Livewire browser event that calls `chart.resetZoom()` on the Chart instance in the widget view script.
    - [x] Ensure action is keyboard accessible and visible in light/dark modes.

Verification

- [x] Run: `composer test` → tests pass for this section.
- [x] Run: `composer all`.

---

## 5. Guard rails and regression checks

- [x] Ensure existing y-axis min scaling logic is preserved; update or add a unit test to confirm `y.min` behavior
  remains intact.
- [x] Confirm dataset integrity (import/export/averages) — unit tests still pass.
- [x] Run: `composer all`.

---

## 6. Strategy cost chart alignment (copy/update after Agile chart is interactive)

- [x] Review `app/Filament/Resources/StrategyResource/Widgets/CostChart.php` for parallels.
- [x] Apply the same interaction/tooltip/zoom/pan/current indicator configurations where appropriate.
- [ ] If logic diverges, extract common option-building helpers to avoid duplication (optional, keep scope minimal).
- [x] Add/extend unit tests mirroring `AgileChart` assertions for `CostChart` if it exposes testable methods.
- [x] Run: `composer all`.

---

## 7. Manual QA and documentation

- [x] Manually verify in the Dashboard:
    - [x] Hover shows aggregated tooltip with all datasets and currency formatting.
    - [x] Current-period indicator aligns with local time slot, including near midnight and DST boundaries (sanity
      check).
    - [x] Zoom with mouse wheel and pinch works; pan works when zoomed.
    - [x] “Reset Zoom” returns full range.
- [x] Update `docs/tasks.md` to reference this task file and mark subtasks as appropriate.
- [x] Add brief notes to `README.md` or a developer doc if plugin registration needs local build steps.

---

## 8. Future: Browser tests with Laravel Dusk (optional in this story if agreed)

- [ ] Install and configure Dusk (see guidelines) if not present.
- [ ] Add Dusk tests for tooltip aggregation, current-period indicator presence, zoom/pan/reset (may use JS-injected API
  calls for determinism).
- [ ] Ensure `QUEUE_CONNECTION=sync` for Dusk runs if required.
- [ ] Run: `php artisan dusk` and confirm green locally.

---

## 9. Final quality gate and Definition of Done

- [x] Run: `composer all` (code style, static analysis, tests).
- [x] Ensure unit tests cover new configuration for `AgileChart` (and `CostChart` if updated).
- [x] Validate acceptance criteria in `docs/make-the-agile-cost-chart-interactive.md` are met.
- [x] Link this task file from `docs/tasks.md` and mark the parent story checklist when complete.
