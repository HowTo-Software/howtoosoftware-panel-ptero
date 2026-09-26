import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    faClock,
    faCloudDownloadAlt,
    faCloudUploadAlt,
    faHdd,
    faMemory,
    faMicrochip,
    faWifi,
} from '@fortawesome/free-solid-svg-icons';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import UptimeDuration from '@/components/server/UptimeDuration';
import StatBlock from '@/components/server/console/StatBlock';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import classNames from 'classnames';
import { capitalize } from '@/lib/strings';
import getServerResourceUsage from '@/api/server/getServerResourceUsage';
import { parseResourceStats, ResourceStatKey, ResourceStats } from '@/components/server/console/resourceStats';

type Stats = ResourceStats;

const getBackgroundColor = (value: number, max: number | null): string | undefined => {
    const delta = !max ? 0 : value / max;

    if (delta > 0.8) {
        if (delta > 0.9) {
            return 'bg-red-500';
        }
        return 'bg-yellow-500';
    }

    return undefined;
};

const Limit = ({ limit, children }: { limit: string | null; children: React.ReactNode }) => (
    <>
        {children}
        <span className={'ml-1 text-gray-300 text-[70%] select-none'}>/ {limit || <>&infin;</>}</span>
    </>
);

const ServerDetailsBlock = ({ className }: { className?: string }) => {
    const [stats, setStats] = useState<Stats>({ memory: 0, cpu: 0, disk: 0, uptime: 0, tx: 0, rx: 0 });
    const lastSocketStatsAt = useRef<Partial<Record<ResourceStatKey, number>>>({});

    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const serverUuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);

    const textLimits = useMemo(
        () => ({
            cpu: limits?.cpu ? `${limits.cpu}%` : null,
            memory: limits?.memory ? bytesToString(mbToBytes(limits.memory)) : null,
            disk: limits?.disk ? bytesToString(mbToBytes(limits.disk)) : null,
        }),
        [limits]
    );

    const allocation = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((allocation) => allocation.isDefault);

        return !match ? 'n/a' : `${match.alias || ip(match.ip)}:${match.port}`;
    });

    useEffect(() => {
        let active = true;
        let hasLoggedRequestError = false;
        lastSocketStatsAt.current = {};

        const refreshStats = () => {
            getServerResourceUsage(serverUuid)
                .then((usage) => {
                    if (!active) return;
                    hasLoggedRequestError = false;
                    const patch = parseResourceStats(usage);
                    const receivedAt = Date.now();

                    setStats((current) => {
                        const next = { ...current };
                        (Object.keys(patch) as ResourceStatKey[]).forEach((key) => {
                            if (receivedAt - (lastSocketStatsAt.current[key] || 0) >= 20000) {
                                next[key] = patch[key]!;
                            }
                        });

                        return next;
                    });
                })
                .catch((error) => {
                    if (!active || hasLoggedRequestError) return;
                    hasLoggedRequestError = true;
                    console.error('Unable to load server resource usage from the Panel API.', error);
                });
        };

        refreshStats();
        const interval = setInterval(refreshStats, 15000);

        return () => {
            active = false;
            clearInterval(interval);
        };
    }, [serverUuid]);

    useEffect(() => {
        if (!connected || !instance) {
            return;
        }

        instance.send(SocketRequest.SEND_STATS);
    }, [instance, connected]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        const patch = parseResourceStats(data);
        const receivedAt = Date.now();
        const keys = Object.keys(patch) as ResourceStatKey[];
        if (!keys.length) return;

        keys.forEach((key) => {
            lastSocketStatsAt.current[key] = receivedAt;
        });
        setStats((current) => ({ ...current, ...patch }));
    });

    return (
        <div className={classNames('min-w-0', className)}>
            <StatBlock
                icon={faWifi}
                title={'Address'}
                copyOnClick={allocation}
                iconColor={'#93c5fd'}
                iconBackground={'rgba(59, 130, 246, 0.14)'}
            >
                {allocation}
            </StatBlock>
            <StatBlock
                icon={faClock}
                title={'Uptime'}
                color={getBackgroundColor(status === 'running' ? 0 : status !== 'offline' ? 9 : 10, 10)}
                iconColor={status === 'offline' ? '#fca5a5' : '#86efac'}
                iconBackground={status === 'offline' ? 'rgba(239, 68, 68, 0.14)' : 'rgba(34, 197, 94, 0.14)'}
            >
                {status === null ? (
                    'Offline'
                ) : stats.uptime > 0 ? (
                    <UptimeDuration uptime={stats.uptime / 1000} />
                ) : (
                    capitalize(status)
                )}
            </StatBlock>
            <StatBlock
                icon={faMicrochip}
                title={'CPU Load'}
                color={getBackgroundColor(stats.cpu, limits.cpu)}
                iconColor={'#c4b5fd'}
                iconBackground={'rgba(139, 92, 246, 0.14)'}
            >
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <Limit limit={textLimits.cpu}>{stats.cpu.toFixed(2)}%</Limit>
                )}
            </StatBlock>
            <StatBlock
                icon={faMemory}
                title={'Memory'}
                color={getBackgroundColor(stats.memory / 1024, limits.memory * 1024)}
                iconColor={'#67e8f9'}
                iconBackground={'rgba(6, 182, 212, 0.14)'}
            >
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <Limit limit={textLimits.memory}>{bytesToString(stats.memory)}</Limit>
                )}
            </StatBlock>
            <StatBlock
                icon={faHdd}
                title={'Disk'}
                color={getBackgroundColor(stats.disk / 1024, limits.disk * 1024)}
                iconColor={'#fcd34d'}
                iconBackground={'rgba(245, 158, 11, 0.14)'}
            >
                <Limit limit={textLimits.disk}>{bytesToString(stats.disk)}</Limit>
            </StatBlock>
            <StatBlock
                icon={faCloudDownloadAlt}
                title={'Network (Inbound)'}
                iconColor={'#6ee7b7'}
                iconBackground={'rgba(16, 185, 129, 0.14)'}
            >
                {status === 'offline' ? <span className={'text-gray-400'}>Offline</span> : bytesToString(stats.rx)}
            </StatBlock>
            <StatBlock
                icon={faCloudUploadAlt}
                title={'Network (Outbound)'}
                iconColor={'#d8b4fe'}
                iconBackground={'rgba(168, 85, 247, 0.14)'}
            >
                {status === 'offline' ? <span className={'text-gray-400'}>Offline</span> : bytesToString(stats.tx)}
            </StatBlock>
        </div>
    );
};

export default ServerDetailsBlock;
