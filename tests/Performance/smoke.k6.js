import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: 1,
    duration: '10s',
    thresholds: {
        http_req_failed: ['rate==0'],
        http_req_duration: ['p(95)<800'],
    },
};

const BASE_URL = __ENV.APP_URL || 'https://solar.test';
const AUTH = __ENV.AUTH_HEADER || '';

export default function () {
    const headers = AUTH ? { Authorization : AUTH } : {};
    const res = http.get(`${BASE_URL}/`, { headers });
    check(res, {
        'status is 200': (r) => r.status === 200,
    });
    sleep(1);
}
