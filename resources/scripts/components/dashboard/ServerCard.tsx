import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowRight, faServer } from '@fortawesome/free-solid-svg-icons';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState } from '@/api/server/getServerResourceUsage';
import { ip } from '@/lib/formatters';
import { resolveGameVisuals } from '@/components/dashboard/gameVisual';
import styles from '@/components/dashboard/serverCards.module.css';

interface Props {
    server: Server;
}

const getAddress = (server: Server): string => {
    const allocation = server.allocations.find((item) => item.isDefault);
    return allocation ? `${allocation.alias || ip(allocation.ip)}:${allocation.port}` : 'Endereço indisponível';
};

const getProvisioningStatus = (server: Server): string | undefined => {
    if (server.status === 'suspended') return 'Suspenso';
    if (server.status === 'installing') return 'Instalando';
    if (server.status === 'install_failed') return 'Falha na instalação';
    if (server.status === 'reinstall_failed') return 'Falha na reinstalação';
    if (server.status === 'restoring_backup') return 'Restaurando backup';
    if (server.isTransferring) return 'Transferindo';
    if (server.isNodeUnderMaintenance) return 'Node em manutenção';

    return undefined;
};

export default ({ server }: Props) => {
    const [powerState, setPowerState] = useState<ServerPowerState | null>(null);
    const [checking, setChecking] = useState(true);
    const [connectionError, setConnectionError] = useState(false);
    const [iconMissing, setIconMissing] = useState(false);
    const [backgroundMissing, setBackgroundMissing] = useState(false);
    const [customCoverUnavailable, setCustomCoverUnavailable] = useState(false);
    const provisioningStatus = getProvisioningStatus(server);
    const visuals = resolveGameVisuals(server);

    useEffect(() => {
        if (provisioningStatus) {
            setChecking(false);
            return;
        }

        let mounted = true;
        const updateStatus = () => {
            getServerResourceUsage(server.uuid)
                .then((stats) => {
                    if (!mounted) return;
                    setPowerState(stats.status);
                    setConnectionError(false);
                    setChecking(false);
                })
                .catch(() => {
                    if (!mounted) return;
                    setConnectionError(true);
                    setChecking(false);
                });
        };

        updateStatus();
        const timer = setInterval(updateStatus, 30000);

        return () => {
            mounted = false;
            clearInterval(timer);
        };
    }, [server.uuid, provisioningStatus]);

    useEffect(() => {
        setIconMissing(false);
        setBackgroundMissing(false);
        setCustomCoverUnavailable(false);
    }, [server.coverImageUrl, visuals.icon, visuals.background]);

    const status = provisioningStatus
        ? { label: provisioningStatus, className: styles.statusNeutral }
        : powerState === 'running'
        ? { label: 'Online', className: styles.statusOnline }
        : powerState === 'starting'
        ? { label: 'Iniciando', className: styles.statusPending }
        : powerState === 'stopping'
        ? { label: 'Desligando', className: styles.statusPending }
        : checking
        ? { label: 'Verificando', className: styles.statusPending }
        : connectionError
        ? { label: 'Indisponível', className: styles.statusNeutral }
        : { label: 'Offline', className: styles.statusOffline };

    return (
        <Link className={styles.card} to={`/server/${server.id}`} aria-label={`Abrir ${server.name}`}>
            <div className={styles.artwork}>
                {!backgroundMissing ? (
                    <img
                        className={styles.backgroundImage}
                        src={
                            server.coverImageUrl && !customCoverUnavailable ? server.coverImageUrl : visuals.background
                        }
                        alt={''}
                        aria-hidden={'true'}
                        onError={() => {
                            if (server.coverImageUrl && !customCoverUnavailable) {
                                setCustomCoverUnavailable(true);
                            } else {
                                setBackgroundMissing(true);
                            }
                        }}
                    />
                ) : (
                    <div className={styles.backgroundPlaceholder}>NO Image</div>
                )}
                <div className={styles.artworkOverlay} />
                <div className={styles.topLine}>
                    <span className={`${styles.status} ${status.className}`}>
                        <span className={styles.statusDot} />
                        {status.label}
                    </span>
                    <span className={styles.gameType}>{visuals.label}</span>
                </div>
                <div className={styles.identity}>
                    <div className={styles.iconFrame}>
                        {!iconMissing ? (
                            <img
                                src={visuals.icon}
                                alt={''}
                                aria-hidden={'true'}
                                onError={() => setIconMissing(true)}
                            />
                        ) : (
                            <span>NO Image</span>
                        )}
                    </div>
                    <div className={styles.serverIdentity}>
                        <span className={styles.serverName}>{server.name}</span>
                        <span className={styles.serverAddress}>{getAddress(server)}</span>
                        <span className={styles.nodePill}>
                            <FontAwesomeIcon icon={faServer} />
                            {server.node || 'Node indisponível'}
                        </span>
                    </div>
                </div>
            </div>
            <div className={styles.footer}>
                <span>Abrir servidor</span>
                <FontAwesomeIcon icon={faArrowRight} />
            </div>
        </Link>
    );
};
