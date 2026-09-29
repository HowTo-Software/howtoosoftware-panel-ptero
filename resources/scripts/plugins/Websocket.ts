import { EventEmitter } from 'events';

const MAX_RECONNECT_ATTEMPTS = 10;
const BASE_RECONNECT_DELAY_MS = 1000;
const MAX_RECONNECT_DELAY_MS = 30000;
const STABLE_CONNECTION_MS = 30000;

export class Websocket extends EventEmitter {
    private socket: WebSocket | null = null;
    private url: string | null = null;
    private token = '';
    private manuallyClosed = false;
    private reconnectAttempts = 0;
    private generation = 0;
    private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
    private stableTimer: ReturnType<typeof setTimeout> | null = null;

    connect(url: string): this {
        if (
            this.url === url &&
            ((this.socket &&
                (this.socket.readyState === WebSocket.CONNECTING || this.socket.readyState === WebSocket.OPEN)) ||
                this.reconnectTimer)
        ) {
            return this;
        }

        if (this.url !== url) {
            this.clearTimers();
            this.reconnectAttempts = 0;
        }
        this.url = url;
        this.manuallyClosed = false;
        this.openSocket();

        return this;
    }

    setToken(token: string, isUpdate = false): this {
        this.token = token;

        if (isUpdate) this.authenticate();

        return this;
    }

    authenticate(): void {
        if (this.url && this.token) this.send('auth', this.token);
    }

    close(code = 1000, reason?: string): void {
        this.manuallyClosed = true;
        this.clearTimers();
        this.token = '';
        this.generation += 1;

        const socket = this.socket;
        this.socket = null;
        if (socket) {
            this.emit('SOCKET_CLOSE');
            if (socket.readyState < WebSocket.CLOSING) socket.close(code, reason);
        }
    }

    open(): void {
        if (!this.url) return;

        this.manuallyClosed = false;
        this.reconnectAttempts = 0;
        this.clearTimers();
        this.openSocket();
    }

    reconnect(): void {
        this.open();
    }

    send(event: string, payload?: string | string[]): void {
        if (this.socket?.readyState !== WebSocket.OPEN) return;

        this.socket.send(JSON.stringify({ event, args: Array.isArray(payload) ? payload : [payload] }));
    }

    private openSocket(): void {
        if (this.manuallyClosed || !this.url) return;

        const generation = ++this.generation;
        const previousSocket = this.socket;
        this.socket = null;
        if (this.stableTimer !== null) clearTimeout(this.stableTimer);
        this.stableTimer = null;
        if (previousSocket) {
            this.emit('SOCKET_CLOSE');
            if (previousSocket.readyState < WebSocket.CLOSING) previousSocket.close(1000, 'replaced');
        }

        let socket: WebSocket;
        try {
            socket = new WebSocket(this.url);
        } catch (error) {
            this.emit('SOCKET_ERROR', error);
            this.scheduleReconnect();
            return;
        }

        this.socket = socket;
        socket.onopen = () => {
            if (generation !== this.generation) return;

            this.emit('SOCKET_OPEN');
            this.authenticate();
            if (this.stableTimer !== null) clearTimeout(this.stableTimer);
            this.stableTimer = setTimeout(() => {
                this.reconnectAttempts = 0;
                this.stableTimer = null;
            }, STABLE_CONNECTION_MS);
        };

        socket.onmessage = (message) => {
            if (generation !== this.generation) return;

            try {
                const { event, args } = JSON.parse(message.data);
                if (typeof event !== 'string') return;
                Array.isArray(args) ? this.emit(event, ...args) : this.emit(event);
            } catch (error) {
                console.warn('Failed to parse incoming websocket message.', error);
            }
        };

        socket.onerror = (error) => {
            if (generation === this.generation) this.emit('SOCKET_ERROR', error);
        };

        socket.onclose = (event) => {
            if (generation !== this.generation) return;

            this.socket = null;
            if (this.stableTimer) clearTimeout(this.stableTimer);
            this.stableTimer = null;
            this.emit('SOCKET_CLOSE', event);

            if (
                this.manuallyClosed ||
                event.code === 1000 ||
                event.code === 1001 ||
                event.code === 1003 ||
                event.code === 4400 ||
                event.code === 4409
            )
                return;
            this.scheduleReconnect();
        };
    }

    private scheduleReconnect(): void {
        if (this.manuallyClosed || this.reconnectTimer) return;

        if (this.reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
            this.emit('SOCKET_CONNECT_ERROR');
            return;
        }

        this.reconnectAttempts += 1;
        const baseDelay = Math.min(BASE_RECONNECT_DELAY_MS * 2 ** (this.reconnectAttempts - 1), MAX_RECONNECT_DELAY_MS);
        const delay = Math.round(baseDelay * (0.8 + Math.random() * 0.4));
        this.emit('SOCKET_RECONNECT', { attempt: this.reconnectAttempts, delay });

        this.reconnectTimer = setTimeout(() => {
            this.reconnectTimer = null;
            this.openSocket();
        }, delay);
    }

    private clearTimers(): void {
        if (this.reconnectTimer !== null) clearTimeout(this.reconnectTimer);
        if (this.stableTimer !== null) clearTimeout(this.stableTimer);
        this.reconnectTimer = null;
        this.stableTimer = null;
    }
}
