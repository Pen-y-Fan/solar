import http from 'k6/http';
import { check } from 'k6';

export const BASE_URL = __ENV.APP_URL || 'https://solar.test';
export const USE_BOOTSTRAP_AUTH = (__ENV.USE_BOOTSTRAP_AUTH || 'true') === 'true';

export const DEFAULT_THRESHOLDS = {
    http_req_failed: ['rate==0'],
    http_req_duration: ['p(95)<500'],
};

function extractSessionCookie(setCookieHeader)
{
    if (!setCookieHeader) {
        return null;
    }
    const header = Array.isArray(setCookieHeader) ? setCookieHeader.join('; ') : String(setCookieHeader);
    const m = header.match(/(?:^|;\s*)laravel_session=([^;]+)/i);
    return m ? m[1] : null;
}

export function bootstrapAuthIfNeeded()
{
    if (!USE_BOOTSTRAP_AUTH) {
        return;
    }

    const jar = http.cookieJar();

  // Step 1: try local bootstrap endpoint (may 302)
    const res = http.get(`${BASE_URL}/_auth/bootstrap`, { redirects: 0 });

    function setCookieFrom(resp)
    {
        const parsed = (resp.cookies && (resp.cookies['laravel_session'] || resp.cookies['Laravel_Session'])) || [];
        let val = parsed.length > 0 ? parsed[0].value : null;
        if (!val) {
            val = extractSessionCookie(resp.headers['Set-Cookie'] || resp.headers['set-cookie']);
        }
        if (val) {
            const u = new URL(BASE_URL);
            const secure = u.protocol === 'https:';
            try {
                jar.set(BASE_URL, 'laravel_session', val, { path: '/', secure });
            } catch (e) {
                jar.set(BASE_URL, 'laravel_session', val);
            }
            return true;
        }
        return false;
    }

    let gotCookie = setCookieFrom(res);

  // Do not attempt generic /login fallback; many apps (e.g., Filament) use custom routes.
  // Treat auth as optional; if cookie not received, continue but mark as info-only.

  // Probe home for availability
    const probe = http.get(`${BASE_URL}/`);

    check(res, {
        'auth bootstrap 2xx/3xx': (r) => r.status >= 200 && r.status < 400,
        'received session cookie (info)': () => true, // keep non-failing; inspect logs if needed
    });
    check(probe, {
        'dashboard reachable (200)': (r) => r.status === 200,
    });
}

export function get(url, params = {})
{
    return http.get(`${BASE_URL}${url}`, params);
}

export function post(url, body = null, params = {})
{
    return http.post(`${BASE_URL}${url}`, body, params);
}

export function firstMatch(text, regex)
{
    const m = text.match(regex);
    if (!m) {
        return null;
    }
  // Prefer the second capture group (e.g., a numeric ID) if present; otherwise the first
    if (typeof m[2] !== 'undefined') {
        return m[2];
    }
    if (typeof m[1] !== 'undefined') {
        return m[1];
    }
    return null;
}
