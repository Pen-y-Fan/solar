import http from 'k6/http';
import { check, sleep } from 'k6';
import { b64decode } from 'k6/encoding';
import { DEFAULT_THRESHOLDS, bootstrapAuthIfNeeded, BASE_URL, get, post } from './helpers.js';

export const options = {
    vus: Number(__ENV.VUS || 2),
    duration: __ENV.DURATION || '20s',
    thresholds: {
        http_req_failed: ['rate==0'],
      // Baseline p95 ~74.1ms; allow ~1.5x headroom
        http_req_duration: ['p(95)<115'],
    },
};

function triggerLocalGeneration(period = 'today')
{
    const params = { headers: { 'Content-Type': 'application/json' } };
    const payload = JSON.stringify({ period });
    return http.post(`${BASE_URL}/_perf/generate-strategy`, payload, params);
}

// Optional Livewire POST flow (gated). Requires providing LIVEWIRE_ENDPOINT (e.g., /livewire/message/strategies.table)
// and LIVEWIRE_PAYLOAD_BASE64 (base64-encoded JSON body) or will attempt a best-effort CSRF-only form post.
function tryLivewireGeneration(indexHtml)
{
    const enabled = (__ENV.STRAT_GEN_LIVEWIRE || 'false') === 'true';
    if (!enabled) {
        return null;
    }

  // Attempt to extract CSRF token from meta tag
    let csrf = null;
    const m = String(indexHtml || '').match(/<meta[^>]*name=["']csrf-token["'][^>]*content=["']([^"']+)["'][^>]*>/i);
    if (m) {
        csrf = m[1];
    }

    const endpoint = __ENV.LIVEWIRE_ENDPOINT || '';
    if (!endpoint) {
        return { status: 0, body: 'skipped: no LIVEWIRE_ENDPOINT' };
    }
    const headers = {
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json, text/plain, */*',
    };
    if (csrf) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    let payload;
    if (__ENV.LIVEWIRE_PAYLOAD_BASE64) {
        try {
            const decoded = b64decode(String(__ENV.LIVEWIRE_PAYLOAD_BASE64), 'rawstd', 's');
            payload = JSON.parse(decoded);
        } catch (_) {
            payload = {};
        }
    } else {
      // Minimal stub; projects should set LIVEWIRE_PAYLOAD_BASE64 for accuracy
        payload = { fingerprint: {}, serverMemo: {}, updates: [] };
    }

    const res = http.post(`${BASE_URL}${endpoint}`, JSON.stringify(payload), { headers });
    return res;
}

function shouldPostThisVU()
{
  // Default: only a single VU performs the POST to avoid parallel duplicate generations causing validation/conflicts.
  // Can be overridden with STRAT_GEN_CONCURRENT_POSTS=true to allow all VUs to post.
    const allowConcurrent = (__ENV.STRAT_GEN_CONCURRENT_POSTS || 'false') === 'true';
    if (allowConcurrent) {
        return true;
    }
  // Pick one VU deterministically
    return __VU === 1;
}

export default function () {
    if (__ITER === 0) {
        bootstrapAuthIfNeeded();
    }

  // Navigate to strategies index (entry point in UI)
    const index = get('/strategies');
    check(index, { 'strategies index 200 (generation flow entry)': (r) => r.status === 200 });

    const period = __ENV.STRATEGY_PERIOD || 'today';

  // Small stagger to avoid thundering herd at t=0
    sleep((__VU % 5) * 0.05);

  // Only attempt generation once per test (first iteration) to avoid parallel/duplicate runs
    if (__ITER === 0) {
      // Optionally attempt the Livewire POST flow first (if configured)
        const lw = tryLivewireGeneration(index.body);
        if (lw) {
            check(lw, {
                'livewire generation 2xx': (r) => r.status >= 200 && r.status < 300,
                'livewire response not empty': (r) => String(r.body || '').length > 0,
            });
        } else if (shouldPostThisVU()) {
          // Fallback: Call local-only performance endpoint to trigger generation via CQRS
            const gen = triggerLocalGeneration(period);
            check(gen, {
                'generation request 200/ok': (r) => r.status === 200 && String(r.body || '').includes('"ok":true'),
            });
        }
    }

  // Optionally re-open strategies to simulate user verifying result
    const verify = get('/strategies');
    check(verify, { 'strategies verify 200': (r) => r.status === 200 });

    sleep(1);
}
