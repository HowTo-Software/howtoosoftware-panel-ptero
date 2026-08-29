import {
    authenticatedStreamingFetch,
    SESSION_EXPIRED_MESSAGE,
    SessionExpiredError,
} from './authenticatedStreamingFetch';

describe('authenticatedStreamingFetch', () => {
    const originalFetch = global.fetch;

    afterEach(() => {
        global.fetch = originalFetch;
    });

    it('uses the panel session and decoded XSRF cookie for streaming requests', async () => {
        Object.defineProperty(global, 'document', {
            configurable: true,
            value: { cookie: 'pterodactyl_session=opaque; XSRF-TOKEN=abc%2B123%3D' },
        });
        const expected = { ok: true, status: 200 } as Response;
        global.fetch = jest.fn().mockResolvedValue(expected);

        await expect(
            authenticatedStreamingFetch('/stream', {
                method: 'POST',
                headers: { Accept: 'text/event-stream', 'Content-Type': 'application/json' },
                body: '{}',
            })
        ).resolves.toBe(expected);

        const [, init] = (global.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Headers;
        expect(init.credentials).toBe('same-origin');
        expect(headers.get('X-XSRF-TOKEN')).toBe('abc+123=');
        expect(headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(headers.get('Accept')).toBe('text/event-stream');
        expect(headers.get('Content-Type')).toBe('application/json');
    });

    it('converts HTTP 419 into a session-expired error without reading the stream', async () => {
        Object.defineProperty(global, 'document', { configurable: true, value: { cookie: '' } });
        global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 419 } as Response);

        await expect(authenticatedStreamingFetch('/stream')).rejects.toEqual(new SessionExpiredError());
        await expect(authenticatedStreamingFetch('/stream')).rejects.toThrow(SESSION_EXPIRED_MESSAGE);
    });

    it('does not forward a caller-provided XSRF header when the cookie is absent', async () => {
        Object.defineProperty(global, 'document', { configurable: true, value: { cookie: '' } });
        global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200 } as Response);

        await authenticatedStreamingFetch('/stream', { headers: { 'X-XSRF-TOKEN': 'stale-token' } });

        const [, init] = (global.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
        expect((init.headers as Headers).has('X-XSRF-TOKEN')).toBe(false);
    });
});
