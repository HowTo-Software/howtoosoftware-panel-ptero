import { useEffect, useRef } from 'react';
import { ServerStats } from '@/api/server/getServerResourceUsage';
import { ServerResourcePoller } from '@/lib/ServerResourcePoller';

/** Poll server resources sequentially, pausing in background tabs and aborting on cleanup. */
export default (
    serverUuid: string,
    enabled: boolean,
    interval: number,
    onSuccess: (stats: ServerStats) => void,
    onError: (error: unknown) => void
): void => {
    const onSuccessRef = useRef(onSuccess);
    const onErrorRef = useRef(onError);
    onSuccessRef.current = onSuccess;
    onErrorRef.current = onError;

    useEffect(() => {
        if (!enabled) return;

        const poller = new ServerResourcePoller(
            serverUuid,
            interval,
            (stats) => onSuccessRef.current(stats),
            (error) => onErrorRef.current(error)
        );
        poller.start();

        return () => poller.stop();
    }, [serverUuid, enabled, interval]);
};
