// Registers Chart.js plugins for Filament ChartWidget instances following
// Filament's recommended API: push plugins to window.filamentChartJsPlugins.
// Also wires up helper behaviors (tooltip formatting) and a global reset zoom listener.

// IMPORTANT: Do NOT import Chart from 'chart.js/auto' here. Filament bundles
// Chart.js and handles plugin registration. We only export plugins via
// window.filamentChartJsPlugins.
// Register zoom plugin (annotation remains replaced by our custom line plugin)
import zoomPlugin from 'chartjs-plugin-zoom';
// import annotationPlugin from 'chartjs-plugin-annotation';

// Simple formatter for p/kWh values (e.g., 8.24 => "8.24p")
function formatPence(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value ?? '');
  return `${n.toFixed(2)}p`;
}

// Helpers for Europe/London time formatting from ISO strings
const londonTime = (d, opts) => new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Europe/London',
  hour12: false,
  ...opts,
}).format(d);

function formatLondonHHmm(d) {
  return londonTime(d, { hour: '2-digit', minute: '2-digit' });
}

function isLondonMidnight(d) {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/London', hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(d);
  const hh = parts.find(p => p.type === 'hour')?.value;
  const mm = parts.find(p => p.type === 'minute')?.value;
  return hh === '00' && mm === '00';
}

function formatLondonDayMonHHmm(d) {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/London', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(d);
  const day = String(parseInt(parts.find(p => p.type === 'day')?.value || '0', 10));
  const mon = parts.find(p => p.type === 'month')?.value || '';
  const hh = parts.find(p => p.type === 'hour')?.value || '';
  const mm = parts.find(p => p.type === 'minute')?.value || '';
  return `${day} ${mon} ${hh}:${mm}`;
}

// Compute a 30-min range title from an x label (now ISO or time-like)
function computePeriodTitle(label) {
  try {
    let base;
    if (/^\d{2}:\d{2}$/.test(label)) {
      // legacy fallback; assume today at that time in London
      const now = new Date();
      const [h, m] = label.split(':').map((n) => parseInt(n, 10));
      // Treat as UTC equivalent for simplicity, then format in London below
      base = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), h, m, 0, 0));
    } else {
      base = new Date(label);
    }
    if (isNaN(base.getTime())) return label;
    const end = new Date(base.getTime() + 30 * 60 * 1000);
    return `${formatLondonHHmm(base)}–${formatLondonHHmm(end)}`;
  } catch (e) {
    return label;
  }
}

// A lightweight helper plugin that replaces placeholder strings ('function')
// in PHP-provided options with real JS callbacks for tooltip formatting.
const solarTooltipHelper = {
  id: 'solarTooltipHelper',
  beforeInit(chart) {
    const t = chart?.options?.plugins?.tooltip;
    if (!t || !t.callbacks) return;

    if (t.callbacks.title === 'function') {
      t.callbacks.title = (items) => {
        const label = items?.[0]?.label ?? '';
        return computePeriodTitle(label);
      };
    }
    if (t.callbacks.label === 'function') {
      t.callbacks.label = (ctx) => {
        const label = ctx?.dataset?.label ?? '';
        const raw = typeof ctx.parsed?.y === 'number' ? ctx.parsed.y : Number(ctx.formattedValue);
        const value = Number.isFinite(raw) ? formatPence(raw) : String(ctx.formattedValue ?? '');
        return `${label}: ${value}`;
      };
    }
  },
};

// Draw a simple vertical line at a given x-axis label without using the annotation plugin
// Configure via chart.options.plugins.solarCurrentTimeLine = {
//   label: '10:00', color: 'rgba(99,102,241,0.8)', lineWidth: 1, dash: [2,2]
// }
const solarCurrentTimeLine = {
  id: 'solarCurrentTimeLine',
  afterDatasetsDraw(chart) {
    try {
      const cfg = chart?.options?.plugins?.solarCurrentTimeLine;
      if (!cfg) return;
      const xScale = chart.scales?.x;
      const ca = chart.chartArea;
      if (!xScale || !ca) return;
      let x = NaN;
      if (Number.isInteger(cfg.index)) {
        if (typeof xScale.getPixelForTick === 'function') {
          x = xScale.getPixelForTick(cfg.index);
        } else {
          const lbl = Array.isArray(chart.data?.labels) ? chart.data.labels[cfg.index] : undefined;
          x = xScale.getPixelForValue(lbl, cfg.index);
        }
      } else if (cfg.label) {
        x = xScale.getPixelForValue(cfg.label);
      }
      if (!Number.isFinite(x)) return;
      const ctx = chart.ctx;
      ctx.save();
      ctx.beginPath();
      if (Array.isArray(cfg.dash)) ctx.setLineDash(cfg.dash);
      ctx.strokeStyle = cfg.color || 'rgba(99,102,241,0.8)';
      ctx.lineWidth = cfg.lineWidth || 1;
      ctx.moveTo(x, ca.top);
      ctx.lineTo(x, ca.bottom);
      ctx.stroke();
      ctx.restore();
    } catch (_) {
      /* noop */
    }
  },
};

// Replace placeholder x-axis ticks callback to render ISO labels as Europe/London friendly strings
const solarXAxisLabelFormatter = {
  id: 'solarXAxisLabelFormatter',
  beforeInit(chart) {
    try {
      const ticks = chart?.options?.scales?.x?.ticks;
      if (!ticks) return;
      if (ticks.callback === 'function') {
        ticks.callback = (value /* tick value */, index, values) => {
          try {
            const xScale = chart.scales?.x;
            let label = null;
            // Prefer the tick value directly if it looks like an ISO datetime string
            if (typeof value === 'string') {
              label = value;
            } else if (typeof value === 'number' && xScale?.getLabelForValue) {
              // On category scales, resolve the label from the scale for numeric tick values
              label = xScale.getLabelForValue(value);
            }
            if (!label && Array.isArray(chart.data?.labels)) {
              // Final fallback to original labels array
              label = chart.data.labels[index];
            }

            const d = new Date(label);
            if (isNaN(d.getTime())) return String(label ?? value ?? '');
            // Always show full date on the first visible tick
            if (index === 0) {
              return formatLondonDayMonHHmm(d);
            }
            // Otherwise keep HH:mm, except show date at midnight boundaries
            return isLondonMidnight(d) ? formatLondonDayMonHHmm(d) : formatLondonHHmm(d);
          } catch (_) {
            return String(value ?? '');
          }
        };
      }
    } catch (_) {
      /* noop */
    }
  },
};


// Per-chart reset listener plugin: each Chart instance subscribes to the reset event
const solarResetListener = {
  id: 'solarResetListener',
  beforeInit(chart) {
    chart.__solarResetHandler = () => {
      try {
        if (typeof chart.resetZoom === 'function') {
          chart.resetZoom();
        }
      } catch (_) {
        /* noop */
      }
    };
    window.addEventListener('agile-chart:reset-zoom', chart.__solarResetHandler);
    document.addEventListener('agile-chart:reset-zoom', chart.__solarResetHandler, { capture: true });
  },
  beforeDestroy(chart) {
    if (chart.__solarResetHandler) {
      window.removeEventListener('agile-chart:reset-zoom', chart.__solarResetHandler);
      document.removeEventListener('agile-chart:reset-zoom', chart.__solarResetHandler, { capture: true });
      delete chart.__solarResetHandler;
    }
  },
};

// Expose plugins to Filament via window.filamentChartJsPlugins per docs.
if (typeof window !== 'undefined') {
  // Ensure the aggregator array exists and avoid duplicate pushes by identity.
  window.filamentChartJsPlugins ??= [];

  const addUnique = (plugin) => {
    // Check by plugin id when available, otherwise by object reference.
    const exists = window.filamentChartJsPlugins.some((p) => {
      if (p === plugin) return true;
      try {
        return p?.id && plugin?.id && p.id === plugin.id;
      } catch (_) {
        return false;
      }
    });
    if (!exists) window.filamentChartJsPlugins.push(plugin);
  };

  // Enable zoom, keep annotation disabled in favor of our lightweight plugin
  addUnique(zoomPlugin);
  // addUnique(annotationPlugin);
  addUnique(solarTooltipHelper);
  addUnique(solarResetListener);
  addUnique(solarCurrentTimeLine);
  addUnique(solarXAxisLabelFormatter);
}
