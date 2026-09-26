import { translateUiText } from '@/i18n/uiTranslations';
import React from 'react';
import { ServerContext } from '@/state/server';
import ScreenBlock from '@/components/elements/ScreenBlock';
import ServerInstallSvg from '@/assets/images/server_installing.svg';
import ServerErrorSvg from '@/assets/images/server_error.svg';
import ServerRestoreSvg from '@/assets/images/server_restore.svg';

export default () => {
    const status = ServerContext.useStoreState((state) => state.server.data?.status || null);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data?.isTransferring || false);
    const isNodeUnderMaintenance = ServerContext.useStoreState(
        (state) => state.server.data?.isNodeUnderMaintenance || false
    );

    return status === 'installing' || status === 'install_failed' || status === 'reinstall_failed' ? (
        <ScreenBlock
            title={translateUiText('Running Installer')}
            image={ServerInstallSvg}
            message={translateUiText('Your server should be ready soon, please try again in a few minutes.')}
        />
    ) : status === 'suspended' ? (
        <ScreenBlock
            title={translateUiText('Server Suspended')}
            image={ServerErrorSvg}
            message={translateUiText('This server is suspended and cannot be accessed.')}
        />
    ) : isNodeUnderMaintenance ? (
        <ScreenBlock
            title={translateUiText('Node under Maintenance')}
            image={ServerErrorSvg}
            message={translateUiText('The node of this server is currently under maintenance.')}
        />
    ) : (
        <ScreenBlock
            title={isTransferring ? translateUiText('Transferring') : translateUiText('Restoring from Backup')}
            image={ServerRestoreSvg}
            message={
                isTransferring
                    ? translateUiText('Your server is being transferred to a new node, please check back later.')
                    : translateUiText(
                          'Your server is currently being restored from a backup, please check back in a few minutes.'
                      )
            }
        />
    );
};
