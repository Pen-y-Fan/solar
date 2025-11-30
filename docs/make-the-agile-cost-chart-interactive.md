# User Story: Make the Agile cost chart interactive

Last updated: 2025-11-29 12:18 (local)

## Context

The Dashboard includes an Agile cost chart implemented as a Filament `ChartWidget` at:

- `app/Filament/Widgets/AgileChart.php`

This widget renders two datasets using Chart.js:

- Import value (inc. VAT)
- Export value (inc. VAT)

Labels are half-hourly time slots derived from `valid_from`, and average import/export lines are drawn. Data is read via
`AgileImportExportSeriesQuery` and auto-refreshed; missing/old data triggers import/export commands.

Reference tests:

- `tests/Unit/Filament/Widgets/AgileChartTestWidget.php` — provides a testable subclass exposing `getData()` and
  `getOptions()` for assertions.

Source of request: `docs/user-requests.md` → Dashboard → "Make the Agile cost chart interactive".

Once the Agile cost chart is interactive, the cost chart, in strategy, will need to be updated or copied from the Agile
chart.

- `app/Filament/Resources/StrategyResource/Widgets/CostChart.php`

## User request (as provided)

- Add a label for each period. Currently, each item in a period is a label value.
    - Instead, when the cursor is over the period, display a label box with all the values of the items in that period.
- Add a current period indicator, e.g. a line or a different background colour
- Enable zooming and panning

## Clarifications and scope

- Period: For Octopus Agile, a period equals a 30-minute slot. A “period” therefore corresponds to a single x-axis
  index (label), while “items in that period” map to multiple datasets (Import, Export, possibly more in future, e.g.,
  averages). The tooltip should aggregate values across all datasets at the hovered period and present them clearly.
- Labeling: The x-axis should show one label per 30-minute period rather than per dataset value. We will maintain
  concise tick formatting and move detailed values into a custom tooltip.
- Interactivity: Add zoom and pan via `chartjs-plugin-zoom` (pinch, wheel, drag), and add a vertical line for the
  current period via `chartjs-plugin-annotation` or a lightweight scriptable background highlight.

## Technical approach

### Files and components

- Widget: `app/Filament/Widgets/AgileChart.php` (extends `Filament\Widgets\ChartWidget`)
    - `getData()` constructs Chart.js datasets and labels
    - `getOptions()` returns Chart.js options; we will extend this to include interactivity

### Chart.js integration in Filament

Filament v3 `ChartWidget` wraps Chart.js. You configure Chart.js by returning:

- `getData(): array` — `{ datasets: [...], labels: [...] }`
- `getOptions(): array` — Chart.js options object, including `plugins`, `interaction`, `scales`, etc.

To add extra Chart.js plugins (zoom, annotation), Filament supports registering them globally in the Filament panel
service provider or per widget by returning proper `plugins` options. Where bundling is required, we will add the
plugins via `resources/js` and Vite, then register with Filament’s charts. Detailed implementation will be handled in
the development task.

### Planned enhancements

1. Tooltip aggregation for a period
    - Configure `interaction: { mode: 'index', intersect: false }` to display values of all datasets at the hovered
      x-index.
    - Use `plugins.tooltip.callbacks` to render a richer tooltip with per-dataset values, currency formatting, and the
      period time range.
    - Keep x-axis labels concise (e.g., `H:i`), show full date/time in tooltip header.

2. Current period indicator
    - Add a vertical annotation line at the current time’s nearest 30-minute index using `chartjs-plugin-annotation`.
    - Alternative (if avoiding the annotation plugin): implement a custom plugin that draws a background highlight for
      the active x-index based on `now()` mapped to the labels.
    - The indicator should update on chart polling refresh (the widget already polls) and on timezone-aware
      calculations (UTC storage, Europe/London display).

3. Zooming and panning
    - Integrate `chartjs-plugin-zoom` and enable:
        - Wheel and pinch zoom on x-axis
        - Drag-to-pan on x-axis
    - Provide a “Reset Zoom” control in the widget header (Filament action) that triggers a Livewire event to reset the
      chart zoom (via `chart.resetZoom()`).

### Accessibility and UX

- Ensure tooltip text is readable in light/dark themes.
- Provide keyboard-accessible reset control.
- Keep animation modest to retain responsiveness on slower devices.

## Acceptance criteria

- When hovering or tapping on the chart, the tooltip shows aggregated values for all datasets at the hovered period 
  (import, export, and any derived lines), with clear labels and currency formatting (e.g., `£0.12`).
- A visible current-period indicator is rendered at the correct 30-minute slot aligned to Europe/London local time,
  regardless of UTC storage (verified at day boundaries and DST transitions if applicable).
- Users can zoom in/out on the x-axis using mouse wheel (desktop) and pinch (touch devices), and pan horizontally when
  zoomed.
- A “Reset Zoom” control returns the chart to the default full-range view.
- No regression to existing datasets, averages, or axis scaling (`min` derived from data still respected).
- Feature configuration is covered by automated tests, and the widget remains performant.

## Testing and QA

### Unit tests (PHP)

Extend tests using `AgileChartTestWidget` to assert configuration returned by `getOptions()` and `getData()`:

- `interaction.mode === 'index'` and `intersect === false`.
- `plugins.tooltip.callbacks` presence reflected by expected serialized options (we can assert the shape/keys returned
  by PHP side; full JS callback behavior is smoke-tested in browser tests).
- `plugins.zoom` config present with `zoom.wheel.enabled`, `zoom.pinch.enabled`, and `pan.enabled` on x-axis.
- If using annotation: presence of `plugins.annotation.annotations.currentPeriod` with positioning aligned to labels.
- Scales keep `y.min` honoring existing logic based on dataset minimums.

Note: Because callbacks and plugin instances are executed client-side, PHP unit tests will assert the configuration
payload (keys/values) rather than runtime rendering.

### Browser tests (Laravel Dusk) - future

Future: Add Dusk tests (see project guideline’s Dusk section) to validate interactive behaviors:

- Tooltip aggregation: hover at a known x-index and verify the tooltip DOM contains both import and export values for
  that period.
- Current period indicator: freeze time with `Carbon::setTestNow()` to a known slot and assert the annotation element is
  present (or canvas pixel sampling heuristic if feasible) and positioned near the expected x pixel (tolerances
  applied).
- Zoom & pan: simulate wheel (or zoom API call via injected JS) to zoom, assert the x-axis ticks change count/range; pan
  and assert labels shift; click “Reset Zoom” and assert default range returns.

Artifacts on failure: leverage Dusk screenshots and console logs.

### Static analysis and code quality

- Ensure `composer phpstan` and `composer cs` pass.

### Existing tests possibly impacted

- `tests/Unit/Filament/Widgets/AgileChartTestWidget.php` — may be extended to cover new options/plugins. Existing
  assertions (if any elsewhere) referencing `getOptions()` structure may need to be updated to include `plugins`,
  `interaction`, and any new options we add.

## Dependencies and notes

- Front-end: add and register `chartjs-plugin-zoom` and `chartjs-plugin-annotation` via NPM and Vite if not already
  included. Ensure Filament chart facade or the widget script registers these plugins.
- Timezone handling: labels are produced in Europe/London; the current-period calculation must map `now()` to the
  closest label index reflecting BST/GMT transitions.
- Performance: dataset size is ~96 points/day; zoom/pan cost is minimal. Keep tooltip callbacks efficient.

## Out of scope (for this story)

- Changing data source logic or altering the polling interval.
- Adding new datasets beyond those already present (import/export and averages).

## Definition of Done

- Task added in `docs/tasks.md` under section 1.1.x and linked to this document.
- Implementation merged with unit tests and Dusk tests green locally and in CI.
- Static analysis and code style pass (`composer all`).
- Acceptance criteria satisfied and validated manually on the Dashboard.
