import getServerResourceUsage, { ServerStats } from '@/api/server/getServerResourceUsage';

type VisibilityTarget = Pick<Document, 'visibilityState' | 'addEventListener' | 'removeEventListener'>;

const retryDelay = (error: unknown, failures: number, interval: number): number => {
    const status = (error as { response?: { status?: number } })?.response?.status;
    const delay = status === 409 ? 60000 : Math.min(120000, interval * 2 ** Math.min(failures - 1, 4));

    return Math.round(delay * (0.8 + Math.random() * 0.4));
};

/** Poll resources sequentially, pausing in background tabs and aborting on cleanup. */
export class ServerResourcePoller {
    private active = false;
    private started = false;
    private requestInFlight = false;
    private failures = 0;
    private timer: ReturnType<typeof setTimeout> | undefined;
    private controller: AbortController | undefined;
    private readonly serverUuid: string;
    private readonly interval: number;
    private readonly onSuccess: (stats: ServerStats) => void;
    private readonly onError: (error: unknown) => void;
    private readonly visibility: VisibilityTarget;

    // Assigned by hand: babel-loader strips TypeScript parameter properties without
    // emitting the assignments, so they are silently undefined in the browser while
    // ts-jest compiles them correctly and the tests still pass.
    constructor(
        serverUuid: string,
        interval: number,
        onSuccess: (stats: ServerStats) => void,
        onError: (error: unknown) => void,
        visibility: VisibilityTarget = document
    ) {
        this.serverUuid = serverUuid;
        this.interval = interval;
        this.onSuccess = onSuccess;
        this.onError = onError;
        this.visibility = visibility;
    }

    start(): void {
        if (this.started) return;

        this.started = true;
        this.active = true;
        this.visibility.addEventListener('visibilitychange', this.onVisibilityChange);
        void this.poll();
    }

    stop(): void {
        if (!this.active) return;

        this.active = false;
        if (this.timer !== undefined) clearTimeout(this.timer);
        this.timer = undefined;
        this.controller?.abort();
        this.controller = undefined;
        this.visibility.removeEventListener('visibilitychange', this.onVisibilityChange);
    }

    private readonly onVisibilityChange = (): void => {
        if (this.visibility.visibilityState === 'hidden') {
            if (this.timer !== undefined) clearTimeout(this.timer);
            this.timer = undefined;

            return;
        }

        if (!this.requestInFlight) {
            if (this.timer !== undefined) clearTimeout(this.timer);
            this.timer = undefined;
            void this.poll();
        }
    };

    private schedule(delay: number): void {
        if (!this.active || this.visibility.visibilityState === 'hidden') return;

        this.timer = setTimeout(() => {
            this.timer = undefined;
            void this.poll();
        }, delay);
    }

    private async poll(): Promise<void> {
        if (!this.active || this.requestInFlight || this.visibility.visibilityState === 'hidden') return;

        this.requestInFlight = true;
        const controller = new AbortController();
        this.controller = controller;
        let nextDelay = this.interval;

        try {
            const stats = await getServerResourceUsage(this.serverUuid, controller.signal);
            if (!this.active) return;

            this.failures = 0;
            this.onSuccess(stats);
        } catch (error) {
            if (!this.active || controller.signal.aborted) return;

            this.failures = Math.min(this.failures + 1, 5);
            nextDelay = retryDelay(error, this.failures, this.interval);
            this.onError(error);
        } finally {
            this.requestInFlight = false;
            if (this.controller === controller) this.controller = undefined;
            this.schedule(nextDelay);
        }
    }
}
