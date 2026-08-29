import { SESSION_EXPIRED_MESSAGE } from '@/api/authenticatedStreamingFetch';
import { streamAssistant } from './howtoo';
import fs from 'fs';
import path from 'path';

const encode = (value: string) => new TextEncoder().encode(value);

const streamingResponse = (...chunks: string[]): Response => {
    const reader = {
        read: jest.fn(),
        cancel: jest.fn().mockResolvedValue(undefined),
        releaseLock: jest.fn(),
    };

    chunks.forEach((chunk) => reader.read.mockResolvedValueOnce({ done: false, value: encode(chunk) }));
    reader.read.mockResolvedValueOnce({ done: true, value: undefined });

    return {
        ok: true,
        status: 200,
        body: { getReader: () => reader },
    } as unknown as Response;
};

describe('streamAssistant', () => {
    const originalFetch = global.fetch;
    let timeoutSpy: jest.SpyInstance;

    beforeEach(() => {
        Object.defineProperty(global, 'document', {
            configurable: true,
            value: { cookie: 'pterodactyl_session=session-value; XSRF-TOKEN=csrf%3Dtoken' },
        });
        Object.defineProperty(global, 'window', {
            configurable: true,
            value: { location: { pathname: '/server/server-id/assistant' } },
        });
    });

    afterEach(() => {
        global.fetch = originalFetch;
        timeoutSpy?.mockRestore();
    });

    it('authenticates the streaming POST and processes status, delta and done events', async () => {
        const onStatus = jest.fn();
        const onDelta = jest.fn();
        global.fetch = jest
            .fn()
            .mockResolvedValue(
                streamingResponse(
                    'event: status\ndata: {"state":"thinking"}\n\n',
                    'event: delta\ndata: {"content":"Hello"}\n\n',
                    'event: done\ndata: {"completed":true}\n\n'
                )
            );

        await expect(
            streamAssistant('server-id', 'hello', [], 'running', new AbortController().signal, onStatus, onDelta)
        ).resolves.toEqual({ role: 'assistant', content: 'Hello' });

        expect(onStatus).toHaveBeenCalledWith('thinking');
        expect(onDelta).toHaveBeenLastCalledWith('Hello');
        const [url, init] = (global.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Headers;
        expect(url).toBe('/api/client/servers/server-id/howtoo/assistant/stream');
        expect(init.method).toBe('POST');
        expect(init.credentials).toBe('same-origin');
        expect(headers.get('Accept')).toBe('text/event-stream');
        expect(headers.get('Content-Type')).toBe('application/json');
        expect(headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(headers.get('X-XSRF-TOKEN')).toBe('csrf=token');
    });

    it('keeps provider fallback in the same stream and resets partial output', async () => {
        const onDelta = jest.fn();
        global.fetch = jest
            .fn()
            .mockResolvedValue(
                streamingResponse(
                    'event: delta\ndata: {"content":"partial Gemini"}\n\n',
                    'event: reset\ndata: {"reason":"provider_fallback"}\n\n',
                    'event: status\ndata: {"state":"thinking"}\n\n',
                    'event: delta\ndata: {"content":"Groq answer"}\n\n',
                    'event: done\ndata: {"completed":true}\n\n'
                )
            );

        await expect(
            streamAssistant('server-id', 'help', [], 'running', new AbortController().signal, jest.fn(), onDelta)
        ).resolves.toEqual({ role: 'assistant', content: 'Groq answer' });
        expect(onDelta.mock.calls.map(([value]) => value)).toEqual(['partial Gemini', '', 'Groq answer']);
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('shows a friendly error when the session has expired', async () => {
        global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 419 } as Response);

        await expect(
            streamAssistant('server-id', 'hello', [], null, new AbortController().signal, jest.fn(), jest.fn())
        ).rejects.toThrow(SESSION_EXPIRED_MESSAGE);
    });

    it('does not expose provider credentials in the browser request', async () => {
        global.fetch = jest
            .fn()
            .mockResolvedValue(
                streamingResponse(
                    'event: delta\ndata: {"content":"Safe"}\n\n',
                    'event: done\ndata: {"completed":true}\n\n'
                )
            );

        await streamAssistant('server-id', 'hello', [], null, new AbortController().signal, jest.fn(), jest.fn());

        const [, init] = (global.fetch as jest.Mock).mock.calls[0] as [string, RequestInit];
        const requestHeaders: Record<string, string> = {};
        (init.headers as Headers).forEach((value, key) => {
            requestHeaders[key] = value;
        });
        const serializedRequest = JSON.stringify({
            headers: requestHeaders,
            body: init.body,
        });
        expect(serializedRequest).not.toMatch(/gemini|groq|api[_-]?key|AIza/i);

        const frontendSources = [
            path.join(__dirname, 'howtoo.ts'),
            path.join(__dirname, '..', 'authenticatedStreamingFetch.ts'),
        ]
            .map((file) => fs.readFileSync(file, 'utf8'))
            .join('\n');
        expect(frontendSources).not.toMatch(/GEMINI_API_KEY|GROQ_API_KEY|AIza[0-9A-Za-z_-]{20,}/);
    });

    it('does not add a fixed 20 second frontend timeout', async () => {
        timeoutSpy = jest.spyOn(global, 'setTimeout');
        global.fetch = jest
            .fn()
            .mockResolvedValue(
                streamingResponse(
                    'event: delta\ndata: {"content":"Hello"}\n\n',
                    'event: done\ndata: {"completed":true}\n\n'
                )
            );

        await streamAssistant('server-id', 'hello', [], null, new AbortController().signal, jest.fn(), jest.fn());

        expect(timeoutSpy.mock.calls.some((call) => call[1] === 20000)).toBe(false);
    });
});
