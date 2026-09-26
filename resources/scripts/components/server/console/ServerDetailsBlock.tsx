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

type Stats = Record<'memory' | 'cpu' | 'disk' | 'uptime' | 'rx' | 'tx', number>;

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
    const lastSocketStatsAt = useRef(0);

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
        lastSocketStatsAt.current = 0;

        const refreshStats = () => {
            getServerResourceUsage(serverUuid)
                .then((usage) => {
                    if (!active) return;
                    if (Date.now() - lastSocketStatsAt.current < 20000) return;

                    setStats({
                        memory: usage.memoryUsageInBytes,
                        cpu: usage.cpuUsagePercent,
                        disk: usage.diskUsageInBytes,
                        rx: usage.networkRxInBytes,
                        tx: usage.networkTxInBytes,
                        uptime: usage.uptime,
                    });
                })
                .catch(() => undefined);
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
        let incoming: any;
        try {
            incoming = JSON.parse(data);
        } catch (e) {
            return;
        }

        if (!incoming || typeof incoming !== 'object') return;
        const network = incoming.network || {};
        const coreValues = [incoming.memory_bytes, incoming.cpu_absolute, incoming.disk_bytes];
        if (!coreValues.every((value) => value !== null && value !== undefined && Number.isFinite(Number(value)))) {
            return;
        }

        lastSocketStatsAt.current = Date.now();

        setStats((current) => ({
            memory: Number(incoming.memory_bytes),
            cpu: Number(incoming.cpu_absolute),
            disk: Number(incoming.disk_bytes),
            tx: Number.isFinite(Number(network.tx_bytes)) ? Number(network.tx_bytes) : current.tx,
            rx: Number.isFinite(Number(network.rx_bytes)) ? Number(network.rx_bytes) : current.rx,
            uptime: Number(incoming.uptime) || 0,
        }));
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
