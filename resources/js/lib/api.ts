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
