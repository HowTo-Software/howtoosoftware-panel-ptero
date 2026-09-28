import getServerResourceUsage, { ServerStats } from '@/api/server/getServerResourceUsage';
import { ServerResourcePoller } from './ServerResourcePoller';

jest.mock('@/api/server/getServerResourceUsage', () => ({
    __esModule: true,
    default: jest.fn(),
}));

const stats: ServerStats = {
    status: 'running',
    isSuspended: false,
    memoryUsageInBytes: 1024,
    cpuUsagePercent: 12,
    diskUsageInBytes: 2048,
    networkRxInBytes: 10,
    networkTxInBytes: 20,
    uptime: 1000,
};

class FakeVisibilityTarget extends EventTarget {
    visibilityState: DocumentVisibilityState = 'visible';

    setVisibility(state: DocumentVisibilityState): void {
        this.visibilityState = state;
        this.dispatchEvent(new Event('visibilitychange'));
    }
}

describe('ServerResourcePoller', () => {
    const request = getServerResourceUsage as jest.Mock;
    const originalRandom = Math.random;

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['performance'] });
        request.mockReset();
    });

    afterEach(() => {
        Math.random = originalRandom;
        jest.clearAllTimers();
        jest.useRealTimers();
    });

    it('allows one in-flight request, schedules sequential polling, and aborts on stop', async () => {
        const pending: Array<{ resolve: (stats: ServerStats) => void; signal: AbortSignal }> = [];
        request.mockImplementation(
            (_server: string, signal: AbortSignal) => new Promise((resolve) => pending.push({ resolve, signal }))
        );
        const onSuccess = jest.fn();
        const target = new FakeVisibilityTarget();
        const poller = new ServerResourcePoller('server-uuid', 15000, onSuccess, jest.fn(), target);

        poller.start();
        poller.start();
        expect(request).toHaveBeenCalledTimes(1);

        pending[0].resolve(stats);
        await Promise.resolve();
        await Promise.resolve();
        expect(onSuccess).toHaveBeenCalledWith(stats);
        expect(jest.getTimerCount()).toBe(1);

        jest.advanceTimersByTime(15000);
        expect(request).toHaveBeenCalledTimes(2);
        poller.stop();
        expect(pending[1].signal.aborted).toBe(true);

        jest.advanceTimersByTime(60000);
        expect(request).toHaveBeenCalledTimes(2);
    });

    it('pauses while hidden and resumes immediately when visible', async () => {
        const pending: Array<(stats: ServerStats) => void> = [];
        request.mockImplementation(() => new Promise((resolve) => pending.push(resolve)));
        const target = new FakeVisibilityTarget();
        target.visibilityState = 'hidden';
        const poller = new ServerResourcePoller('server-uuid', 15000, jest.fn(), jest.fn(), target);

        poller.start();
        expect(request).not.toHaveBeenCalled();

        target.setVisibility('visible');
        expect(request).toHaveBeenCalledTimes(1);
        target.setVisibility('hidden');
        pending[0](stats);
        await Promise.resolve();
        await Promise.resolve();
        expect(jest.getTimerCount()).toBe(0);

        target.setVisibility('visible');
        expect(request).toHaveBeenCalledTimes(2);
        poller.stop();
    });

    it('backs off a server-state 409 instead of retrying at the normal interval', async () => {
        Math.random = jest.fn(() => 0.5) as unknown as typeof Math.random;
        const conflict = { response: { status: 409 } };
        const onError = jest.fn();
        request.mockRejectedValueOnce(conflict).mockImplementation(() => new Promise(() => undefined));
        const poller = new ServerResourcePoller('server-uuid', 15000, jest.fn(), onError, new FakeVisibilityTarget());

        poller.start();
        expect(request).toHaveBeenCalledTimes(1);
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();
        expect(onError).toHaveBeenCalledWith(conflict);

        jest.advanceTimersByTime(59999);
        expect(request).toHaveBeenCalledTimes(1);
        jest.advanceTimersByTime(1);
        expect(request).toHaveBeenCalledTimes(2);
        poller.stop();
    });
});
