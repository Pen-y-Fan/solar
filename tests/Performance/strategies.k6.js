import { check, sleep } from 'k6';
import { DEFAULT_THRESHOLDS, bootstrapAuthIfNeeded, get, firstMatch } from './helpers.js';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '30s',
    thresholds: {
        http_req_failed: ['rate==0'],
      // Baseline p95 ~70.2ms; allow ~1.5x headroom
        http_req_duration: ['p(95)<110'],
    },
};

function findFirstEditId(html)
{
  // Filament resource edit links often look like /strategies/{id}/edit in HTML
    return firstMatch(html, /\/(strategies)\/(\d+)\/edit/);
}

export default function () {
    if (__ITER === 0) {
        bootstrapAuthIfNeeded();
    }

  // Index page
    const indexRes = get('/strategies');
    check(indexRes, {
        'strategies index 200': (r) => r.status === 200,
    });

  // Attempt to load first edit page if we can detect an id
    const editId = findFirstEditId(indexRes.body);
    if (editId) {
        const res = get(` / strategies / ${editId} / edit`);
        check(res, {
            'strategies edit 200': (r) => r.status === 200,
        });
    }

    sleep(1);
}
