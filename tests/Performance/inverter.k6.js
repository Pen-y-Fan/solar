import { check, sleep } from 'k6';
import { DEFAULT_THRESHOLDS, bootstrapAuthIfNeeded, get } from './helpers.js';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '30s',
    thresholds: {
        http_req_failed: ['rate==0'],
      // Baseline p95 ~70.45ms; allow ~1.5x headroom
        http_req_duration: ['p(95)<110'],
    },
};

export default function () {
    if (__ITER === 0) {
        bootstrapAuthIfNeeded();
    }

  // Currently, inverter data is surfaced via dashboard widgets (Filament Livewire components)
  // which render as part of the dashboard page. We exercise the dashboard as a proxy
  // for inverter queries until direct JSON endpoints are exposed.
    const res = get('/');
    check(res, {
        'dashboard (inverter widgets) 200': (r) => r.status === 200,
    });

    sleep(1);
}
