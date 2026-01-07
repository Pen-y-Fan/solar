import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '30s',
    thresholds: {
        http_req_failed: ['rate==0'],
      // Baseline p95 ~63.9ms; allow 1.5x headroom
        http_req_duration: ['p(95)<100'],
    },
};

const BASE_URL = __ENV.APP_URL || 'https://solar.test';

function bootstrapAuth()
{
  // Fetch a session cookie from the local auth bootstrap if running locally
    if ((__ENV.USE_BOOTSTRAP_AUTH || 'true') === 'true') {
        const res = http.get(`${BASE_URL}/_auth/bootstrap`, { redirects: 0 });
        check(res, {
            'auth bootstrap 200': (r) => r.status === 200,
            'received session cookie': (r) => !!r.cookies['laravel_session'],
        });
    }
}

export default function () {
  // Attempt auth on first iteration for a given VU
    if (__ITER === 0) {
        bootstrapAuth();
    }

  // Load main dashboard with small warmup retry/backoff to stabilize first-iteration flakiness
    let res = http.get(`${BASE_URL}/`);
    if (res.status !== 200) {
      // brief backoff and retry once
        sleep(0.5);
        res = http.get(`${BASE_URL}/`);
    }
    check(res, {
        'dashboard status 200': (r) => r.status === 200,
    });

  // Optionally hit known widget/data endpoints here as they are introduced
  // Example (adjust to real routes):
  // const widget = http.get(`${BASE_URL}/widgets/strategy-summary`);
  // check(widget, { 'widget 200': (r) => r.status === 200 });

    sleep(1);
}
