import { Websocket } from './Websocket';

class FakeWebSocket {
    static readonly CONNECTING = 0;
    static readonly OPEN = 1;
    static readonly CLOSING = 2;
    static readonly CLOSED = 3;

    readyState = FakeWebSocket.CONNECTING;
    onopen: ((event: Event) => void) | null = null;
    onclose: ((event: CloseEvent) => void) | null = null;
    onmessage: ((event: MessageEvent) => void) | null = null;
    onerror: ((event: Event) => void) | null = null;
    send = jest.fn();
    close = jest.fn((code = 1000) => {
        this.readyState = FakeWebSocket.CLOSED;
        this.onclose?.({ code } as CloseEvent);
    });

    readonly url: string;

    constructor(url: string) {
        this.url = url;
    }

    open(): void {
        this.readyState = FakeWebSocket.OPEN;
        this.onopen?.({} as Event);
    }

    disconnect(code: number): void {
        this.readyState = FakeWebSocket.CLOSED;
        this.onclose?.({ code } as CloseEvent);
    }
}

describe('Websocket', () => {
    const originalWebSocket = global.WebSocket;
    let sockets: FakeWebSocket[];
    let randomSpy: jest.SpyInstance | undefined;

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['performance'] });
        sockets = [];
        randomSpy = jest.spyOn(Math, 'random').mockReturnValue(0.5);
        Object.defineProperty(global, 'WebSocket', {
            configurable: true,
            value: class extends FakeWebSocket {
                constructor(url: string) {
                    super(url);
                    sockets.push(this);
                }
            },
        });
    });

    afterEach(() => {
        jest.clearAllTimers();
        jest.useRealTimers();
        randomSpy?.mockRestore();
        Object.defineProperty(global, 'WebSocket', { configurable: true, value: originalWebSocket });
    });

    it('authenticates after opening and reconnects once with a bounded delay', () => {
        const websocket = new Websocket();
        const reconnect = jest.fn();
        websocket.on('SOCKET_RECONNECT', reconnect);
        websocket.setToken('secret-token').connect('wss://node.example.test/socket');
        expect(sockets).toHaveLength(1);

        sockets[0].open();
        expect(JSON.parse(sockets[0].send.mock.calls[0][0])).toEqual({ event: 'auth', args: ['secret-token'] });

        sockets[0].disconnect(1006);
        expect(reconnect).toHaveBeenCalledWith({ attempt: 1, delay: 1000 });
        websocket.connect('wss://node.example.test/socket');
        expect(sockets).toHaveLength(1);
        jest.advanceTimersByTime(999);
        expect(sockets).toHaveLength(1);
        jest.advanceTimersByTime(1);
        expect(sockets).toHaveLength(2);

        websocket.close();
        expect(jest.getTimerCount()).toBe(0);
    });

    it('does not reconnect after Wings reports a terminal 4409 close', () => {
        const websocket = new Websocket();
        const reconnect = jest.fn();
        websocket.on('SOCKET_RECONNECT', reconnect);
        websocket.connect('wss://node.example.test/socket');

        sockets[0].disconnect(4409);
        jest.advanceTimersByTime(120000);

        expect(reconnect).not.toHaveBeenCalled();
        expect(sockets).toHaveLength(1);
    });

    it('closes an existing connection when explicitly reopened', () => {
        const websocket = new Websocket();
        websocket.connect('wss://node.example.test/socket');
        sockets[0].open();

        websocket.reconnect();

        expect(sockets[0].close).toHaveBeenCalledWith(1000, 'replaced');
        expect(sockets).toHaveLength(2);
    });

    it('stops after the configured reconnect limit', () => {
        const websocket = new Websocket();
        const failed = jest.fn();
        websocket.on('SOCKET_CONNECT_ERROR', failed);
        websocket.connect('wss://node.example.test/socket');

        for (let attempt = 0; attempt < 10; attempt++) {
            sockets[sockets.length - 1].disconnect(1006);
            jest.advanceTimersByTime(30000);
        }

        expect(sockets).toHaveLength(11);
        sockets[10].disconnect(1006);
        expect(failed).toHaveBeenCalledTimes(1);
        expect(jest.getTimerCount()).toBe(0);
    });
});
