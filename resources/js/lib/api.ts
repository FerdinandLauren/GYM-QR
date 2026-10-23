export interface ApiError {
    status: 'error';
    code: string;
    message: string;
    meta?: { details?: Record<string, unknown> };
}

export async function apiGet<T>(path: string, token: string): Promise<T> {
    const response = await fetch(path, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });

    const payload = await response.json();

    if (!response.ok) {
        throw payload as ApiError;
    }

    return payload.data as T;
}

export async function apiPost<T>(
    path: string,
    body: Record<string, unknown>,
    token: string,
    extraHeaders: Record<string, string> = {},
): Promise<T> {
    const response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
            ...extraHeaders,
        },
        body: JSON.stringify(body),
    });

    const payload = await response.json();

    if (!response.ok) {
        throw payload as ApiError;
    }

    return payload.data as T;
}
