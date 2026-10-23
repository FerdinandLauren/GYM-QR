import http from 'k6/http';
import { check, sleep } from 'k6';

// Jalankan:
//   php artisan load:prepare --members=50 --lockers=50
//   $env:TOKENS = (Get-Content storage/app/load-tokens.txt) -join ','
//   k6 run tests/load/scan.js
//
// Target PRD K1: p95 POST /attendance/scan-qr <= 1500 ms.

export const options = {
    scenarios: {
        checkin: {
            executor: 'ramping-vus',
            startVUs: 1,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '60s', target: 40 },
                { duration: '30s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<1500'],
        http_req_failed: ['rate<0.02'],
    },
};

const tokens = (__ENV.TOKENS || '').split(',').filter((token) => token !== '');
const qrPayload = __ENV.QR_PAYLOAD || 'GYM_LOAD';
const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    if (tokens.length === 0) {
        throw new Error('TOKENS kosong. Jalankan load:prepare lalu set env TOKENS.');
    }

    const token = tokens[(__VU - 1) % tokens.length];
    const idempotencyKey = `${__VU}-${__ITER}-${Date.now()}`;

    const response = http.post(
        `${baseUrl}/api/v1/attendance/scan-qr`,
        JSON.stringify({
            qr_payload: qrPayload,
            latitude: 0,
            longitude: 0,
            accuracy: 10,
            locker_intent: 'no_locker',
        }),
        {
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': idempotencyKey,
            },
        },
    );

    check(response, {
        'status 200 atau 409': (result) => result.status === 200 || result.status === 409,
    });

    sleep(1);
}
