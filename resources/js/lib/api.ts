import { getAccessToken, refreshAccessToken } from './auth';

export interface ApiError {
    status: 'error';
    code: string;
    message: string;
    meta?: { details?: Record<string, unknown> };
}

async function parse<T>(response: Response): Promise<T> {
    const payload = await response.json();

    if (!response.ok) {
        throw payload as ApiError;
    }

    return payload.data as T;
}

export async function apiGet<T>(path: string, token: string): Promise<T> {
    const bearer = getAccessToken() || token;
    let response = await fetch(path, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${bearer}` },
    });

    if (response.status === 401) {
        const payload = (await response.clone().json()) as ApiError;

        if (payload.code === 'TOKEN_EXPIRED' && (await refreshAccessToken())) {
            response = await fetch(path, {
                headers: { Accept: 'application/json', Authorization: `Bearer ${getAccessToken()}` },
            });
        }
    }

    return parse<T>(response);
}

export async function apiPost<T>(
    path: string,
    body: Record<string, unknown>,
    token: string,
    extraHeaders: Record<string, string> = {},
): Promise<T> {
    const bearer = getAccessToken() || token;
    let response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${bearer}`,
            ...extraHeaders,
        },
        body: JSON.stringify(body),
    });

    if (response.status === 401) {
        const payload = (await response.clone().json()) as ApiError;

        if (payload.code === 'TOKEN_EXPIRED' && (await refreshAccessToken())) {
            response = await fetch(path, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${getAccessToken()}`,
                    ...extraHeaders,
                },
                body: JSON.stringify(body),
            });
        }
    }

    return parse<T>(response);
}

export async function sessionGet<T>(path: string): Promise<T> {
    const response = await fetch(path, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    return parse<T>(response);
}

export async function sessionPatch<T>(path: string, body: Record<string, unknown> = {}): Promise<T> {
    const response = await fetch(path, {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    return parse<T>(response);
}

export async function sessionPost<T>(
    path: string,
    body: Record<string, unknown> = {},
    extraHeaders: Record<string, string> = {},
): Promise<T> {
    const response = await fetch(path, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...extraHeaders },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    return parse<T>(response);
}
