import React, { useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCopy,
    faDatabase,
    faExclamationTriangle,
    faInfoCircle,
    faServer,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import { ApplicationStore } from '@/state';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Can from '@/components/elements/Can';
import RenameServerBox from '@/components/server/settings/RenameServerBox';
import ReinstallServerBox from '@/components/server/settings/ReinstallServerBox';
import ServerCoverBox from '@/components/server/settings/ServerCoverBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { ip } from '@/lib/formatters';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { usePermissions } from '@/plugins/usePermissions';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import wipeProjectZomboidSave from '@/api/server/wipeProjectZomboidSave';
import styles from './settings.module.css';

function CardHeader({
    icon,
    title,
    subtitle,
    danger = false,
}: {
    icon: typeof faServer;
    title: string;
    subtitle: string;
    danger?: boolean;
}) {
    return (
        <header className={styles.cardHeader}>
            <div className={`${styles.cardIcon} ${danger ? styles.dangerIcon : ''}`}>
                <FontAwesomeIcon icon={icon} />
            </div>
            <div>
                <h2 className={danger ? styles.dangerTitle : ''}>{title}</h2>
                <p>{subtitle}</p>
            </div>
        </header>
    );
}

function DataRow({ label, value }: { label: string; value: string }) {
    return (
        <div className={styles.dataRow}>
            <span>{label}</span>
            <CopyOnClick text={value}>
                <div className={styles.copyValue}>
                    <code>{value}</code>
                    <button type='button' aria-label={`Copiar ${label}`}>
                        <FontAwesomeIcon icon={faCopy} />
                    </button>
                </div>
            </CopyOnClick>
        </div>
    );
}

const SettingsContainer = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const username = useStoreState((state: ApplicationStore) => state.user.data!.username);
    const [canWipe] = usePermissions('integration.workshop-wipe');
    const [confirmWipe, setConfirmWipe] = useState(false);
    const [wiping, setWiping] = useState(false);
    const wipeInFlight = useRef(false);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const isOffline = status === 'offline';
    const statusLabel =
        status === 'offline'
            ? 'PARADO'
            : status === 'running'
            ? 'ONLINE'
            : status === 'starting'
            ? 'INICIANDO'
            : status === 'stopping'
            ? 'DESLIGANDO'
            : 'DESCONHECIDO';

    const wipe = async () => {
        if (!server.howtoo.workshop.supported || !canWipe || !isOffline || wipeInFlight.current) return;

        wipeInFlight.current = true;
        setWiping(true);
        clearFlashes('settings');
        try {
            const { targets } = await wipeProjectZomboidSave(server.uuid);
            addFlash({
                key: 'settings',
                type: 'success',
                message:
                    targets.length > 0
                        ? 'Os dados de save do Project Zomboid foram removidos com sucesso.'
                        : 'Os diretórios de dados do Project Zomboid não foram encontrados; nenhum arquivo foi alterado.',
            });
            setConfirmWipe(false);
        } catch (error) {
            clearAndAddHttpError({ key: 'settings', error: httpErrorToHuman(error) });
        } finally {
            wipeInFlight.current = false;
            setWiping(false);
        }
    };

    return (
        <ServerContentBlock title={'Settings'}>
            <FlashMessageRender byKey={'settings'} />
            <div className={styles.heading}>
                <div>
                    <h1>Configurações</h1>
                    <p>Gerencie as configurações e ações do seu servidor.</p>
                </div>
                <div className={styles.breadcrumb}>
                    {server.name} <span>/</span> Configurações
                </div>
            </div>

            <div className={styles.grid}>
                <div className={styles.column}>
                    <section className={styles.card}>
                        <CardHeader
                            icon={faServer}
                            title='Informações do Servidor'
                            subtitle='Visualize informações básicas do seu servidor.'
                        />
                        <DataRow label='Node' value={server.node} />
                        <DataRow label='Server ID' value={server.uuid} />
                    </section>

                    <Can action={'file.sftp'}>
                        <section className={styles.card}>
                            <CardHeader
                                icon={faDatabase}
                                title='SFTP'
                                subtitle='Acesse seus arquivos usando as informações abaixo.'
                            />
                            <DataRow
                                label='Endereço do Servidor'
                                value={`sftp://${ip(server.sftpDetails.ip)}:${server.sftpDetails.port}`}
                            />
                            <DataRow label='Usuário' value={`${username}.${server.id}`} />
                            <div className={styles.notice}>
                                <span>
                                    <FontAwesomeIcon icon={faInfoCircle} /> Sua senha SFTP é a mesma usada para acessar
                                    este painel.
                                </span>
                                <a
                                    href={`sftp://${username}.${server.id}@${ip(server.sftpDetails.ip)}:${
                                        server.sftpDetails.port
                                    }`}
                                >
                                    Abrir SFTP ↗
                                </a>
                            </div>
                        </section>
                    </Can>

                    {server.howtoo.workshop.supported && (
                        <section className={`${styles.card} ${styles.wipeCard}`}>
                            <CardHeader
                                icon={faTrashAlt}
                                title='Wipe / Resetar Save (Project Zomboid)'
                                subtitle='Apaga os dados do mundo e mantém as configurações do servidor.'
                                danger
                            />
                            <p className={styles.wipeDescription}>
                                Wipar o save excluirá permanentemente o progresso salvo. Seus arquivos de configuração{' '}
                                <strong>não</strong> serão excluídos; o servidor poderá iniciar com as mesmas
                                configurações.
                            </p>
                            <div className={styles.warning}>
                                <FontAwesomeIcon icon={faExclamationTriangle} />
                                <span>
                                    O servidor precisa estar totalmente parado pelo painel. A ação fica bloqueada
                                    enquanto ele estiver iniciando, online ou desligando.
                                </span>
                            </div>
                            <div className={styles.targets}>
                                <span>Pastas removidas:</span>
                                <code>.cache/db</code>
                                <code>.cache/logs</code>
                                <code>.cache/saves</code>
                            </div>
                            <div className={styles.wipeFooter}>
                                <div className={styles.status}>
                                    Status do servidor: <strong data-offline={isOffline}>{statusLabel}</strong>
                                </div>
                                <Button.Danger
                                    className={styles.wipeButton}
                                    disabled={!isOffline || !canWipe || wiping}
                                    onClick={() => setConfirmWipe(true)}
                                >
                                    <FontAwesomeIcon icon={faTrashAlt} />{' '}
                                    {wiping ? 'Executando...' : 'Wipe / Resetar Save'}
                                </Button.Danger>
                            </div>
                            {!canWipe && (
                                <p className={styles.permissionNote}>
                                    Você não tem permissão para executar o wipe neste servidor.
                                </p>
                            )}
                        </section>
                    )}
                </div>

                <div className={styles.column}>
                    <Can action={'settings.rename'}>
                        <RenameServerBox />
                    </Can>
                    <Can action={'settings.reinstall'}>
                        <ReinstallServerBox />
                    </Can>
                </div>
            </div>

            <Can action={'settings.rename'}>
                <ServerCoverBox />
            </Can>

            <Dialog.Confirm
                open={confirmWipe}
                title={'Confirmar wipe do Project Zomboid?'}
                confirm={wiping ? 'Executando wipe...' : 'Confirmar Wipe'}
                onClose={() => !wiping && setConfirmWipe(false)}
                onConfirmed={wipe}
            >
                <div className={styles.confirmation}>
                    <p>
                        Esta ação removerá somente <code>.cache/db</code>, <code>.cache/logs</code> e{' '}
                        <code>.cache/saves</code>.
                    </p>
                    <p>
                        A pasta <code>.cache</code>, os arquivos de configuração e todos os outros arquivos serão
                        preservados.
                    </p>
                    <p className={styles.confirmWarning}>
                        O servidor precisa estar completamente parado. O estado será validado novamente pelo servidor
                        antes da exclusão.
                    </p>
                </div>
            </Dialog.Confirm>
        </ServerContentBlock>
    );
};

export default SettingsContainer;
