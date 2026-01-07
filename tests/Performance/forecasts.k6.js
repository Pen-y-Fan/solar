import { check, sleep } from 'k6';
import { DEFAULT_THRESHOLDS, bootstrapAuthIfNeeded, get, firstMatch } from './helpers.js';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '30s',
    thresholds: {
        http_req_failed: ['rate==0'],
      // Baseline p95 ~77.7ms; allow ~1.5x headroom
        http_req_duration: ['p(95)<120'],
    },
};

function findFirstEditId(html)
{
    return firstMatch(html, /\/(forecasts)\/(\d+)\/edit/);
}

export default function () {
    if (__ITER === 0) {
        bootstrapAuthIfNeeded();
    }

    const indexRes = get('/forecasts');
    check(indexRes, { 'forecasts index 200': (r) => r.status === 200 });

    const editId = findFirstEditId(indexRes.body);
    if (editId) {
        const res = get(`/forecasts/${editId}/edit`);
        check(res, { 'forecasts edit 200': (r) => r.status === 200 });
    }

    sleep(1);
}
