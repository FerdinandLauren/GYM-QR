import type { ApiError } from './api';

let accessToken = '';
let refreshToken = '';

export function setTokens(access: string, refresh: string): void {
    accessToken = access;
    refreshToken = refresh;
}

export function getAccessToken(): string {
    return accessToken;
}

export async function refreshAccessToken(): Promise<boolean> {
    if (refreshToken === '') {
        return false;
    }

    try {
        const response = await fetch('/api/v1/auth/refresh', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: refreshToken }),
        });

        const payload = await response.json();

        if (!response.ok) {
            return false;
        }

        accessToken = String(payload.data.access_token);
        refreshToken = String(payload.data.refresh_token);

        return true;
    } catch {
        return false;
    }
}

export function isExpired(payload: ApiError): boolean {
    return payload.code === 'TOKEN_EXPIRED';
}
