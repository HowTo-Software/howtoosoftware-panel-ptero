import { translateUiText } from '@/i18n/uiTranslations';
import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import styles from './style.module.css';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    return (
        <ServerContentBlock title={translateUiText('Console')} className={'console-content-wide'}>
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isNodeUnderMaintenance
                        ? translateUiText(
                              'The node of this server is currently under maintenance and all actions are unavailable.'
                          )
                        : isInstalling
                        ? translateUiText(
                              'This server is currently running its installation process and most actions are unavailable.'
                          )
                        : translateUiText(
                              'This server is currently being transferred to another node and all actions are unavailable.'
                          )}
                </Alert>
            )}
            <div className={styles.console_page}>
                <div className={styles.console_header}>
                    <div className={styles.console_identity}>
                        <h1 className={'font-header font-semibold text-gray-50 line-clamp-1'}>{name}</h1>
                        <p className={'line-clamp-1'}>{description}</p>
                    </div>
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtons className={styles.power_buttons} />
                    </Can>
                </div>

                <div className={styles.console_workspace}>
                    <div className={styles.console_panel}>
                        <Spinner.Suspense>
                            <Console />
                        </Spinner.Suspense>
                    </div>
                    <ServerDetailsBlock className={styles.details_rail} />
                </div>

                <div className={styles.chart_grid}>
                    <Spinner.Suspense>
                        <StatGraphs />
                    </Spinner.Suspense>
                </div>
            </div>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
