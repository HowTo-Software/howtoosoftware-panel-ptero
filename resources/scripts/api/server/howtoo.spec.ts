import { streamAssistant } from './howtoo';

describe('streamAssistant', () => {
    const originalFetch = global.fetch;
    const originalWindow = (global as any).window;
    let timeoutSpy: jest.SpyInstance;

    afterEach(() => {
        global.fetch = originalFetch;
        Object.defineProperty(global, 'window', { configurable: true, value: originalWindow });
        timeoutSpy?.mockRestore();
    });

    it('does not abort an active stream after 20000ms', async () => {
        timeoutSpy = jest.spyOn(global, 'setTimeout');
        let resolveFirstRead: (value: { done: boolean; value: Uint8Array }) => void = () => undefined;
        let markReadStarted: () => void = () => undefined;
        const readStarted = new Promise<void>((resolve) => {
            markReadStarted = resolve;
        });
        const reader = {
            read: jest
                .fn()
                .mockImplementationOnce(
                    () =>
                        new Promise<{ done: boolean; value: Uint8Array }>((resolve) => {
                            resolveFirstRead = resolve;
                            markReadStarted();
                        })
                )
                .mockResolvedValueOnce({ done: true, value: undefined }),
            cancel: jest.fn().mockResolvedValue(undefined),
            releaseLock: jest.fn(),
        };
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            body: { getReader: () => reader },
        } as unknown as Response);
        Object.defineProperty(global, 'window', {
            configurable: true,
            value: { location: { pathname: '/server/server-id/assistant' } },
        });
        const controller = new AbortController();

        const request = streamAssistant('server-id', 'ola', [], 'running', controller.signal, jest.fn(), jest.fn());
        await readStarted;

        expect(controller.signal.aborted).toBe(false);
        expect(timeoutSpy.mock.calls.some((call) => call[1] === 20000)).toBe(false);

        resolveFirstRead({
            done: false,
            value: new TextEncoder().encode('event: delta\ndata: {"content":"Olá!"}\n\n'),
        });

        await expect(request).resolves.toEqual({ role: 'assistant', content: 'Olá!' });
    });
});
