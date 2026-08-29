export const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page or sign in again.';

export class SessionExpiredError extends Error {
    constructor() {
        super(SESSION_EXPIRED_MESSAGE);
        this.name = 'SessionExpiredError';
    }
}

const readCookie = (name: string): string | null => {
    if (typeof document === 'undefined' || !document.cookie) return null;

    const prefix = `${name}=`;
    const cookie = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(prefix));

    if (!cookie) return null;

    try {
        return decodeURIComponent(cookie.slice(prefix.length));
    } catch {
        return null;
    }
};

/**
 * Performs a same-origin streaming request using the session and XSRF contract
 * used by the panel's Axios client. The response body remains available to a
 * ReadableStream consumer.
 */
export const authenticatedStreamingFetch = async (input: RequestInfo, init: RequestInit = {}): Promise<Response> => {
    const headers = new Headers(init.headers);
    const xsrfToken = readCookie('XSRF-TOKEN');

    headers.set('X-Requested-With', 'XMLHttpRequest');
    if (xsrfToken) {
        headers.set('X-XSRF-TOKEN', xsrfToken);
    } else {
        headers.delete('X-XSRF-TOKEN');
    }

    const response = await fetch(input, {
        ...init,
        credentials: 'same-origin',
        headers,
    });

    if (response.status === 419) throw new SessionExpiredError();

    return response;
};
