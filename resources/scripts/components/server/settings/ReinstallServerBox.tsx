import { translateUiText } from '@/i18n/uiTranslations';
import React, { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSyncAlt } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import reinstallServer from '@/api/server/reinstallServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import styles from './settings.module.css';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const skipScripts = ServerContext.useStoreState((state) => state.server.data!.skipScripts);
    const [modalVisible, setModalVisible] = useState(false);
    const { addFlash, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const reinstall = () => {
        clearFlashes('settings');
        reinstallServer(uuid)
            .then(() => {
                addFlash({
                    key: 'settings',
                    type: 'success',
                    message: translateUiText('A reinstalação do servidor foi iniciada.'),
                });
            })
            .catch((error) => {
                console.error(error);

                addFlash({ key: 'settings', type: 'error', message: httpErrorToHuman(error) });
            })
            .then(() => setModalVisible(false));
    };

    useEffect(() => {
        clearFlashes();
    }, []);

    if (skipScripts) {
        return (
            <section className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.cardIcon}>
                        <FontAwesomeIcon icon={faSyncAlt} />
                    </div>
                    <div>
                        <h2>{translateUiText('Reinstalar Servidor')}</h2>
                        <p>{translateUiText('Reinstale completamente o seu servidor.')}</p>
                    </div>
                </div>
                <p css={tw`text-sm`}>
                    {translateUiText(
                        'A reinstalação está desativada porque este servidor ignora o script de instalação. Entre em contato com um administrador se precisar reinstalá-lo.'
                    )}
                </p>
            </section>
        );
    }

    return (
        <section className={`${styles.card} ${styles.reinstallCard}`}>
            <div className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <FontAwesomeIcon icon={faSyncAlt} />
                </div>
                <div>
                    <h2>{translateUiText('Reinstalar Servidor')}</h2>
                    <p>{translateUiText('Reinstale completamente o seu servidor.')}</p>
                </div>
            </div>
            <Dialog.Confirm
                open={modalVisible}
                title={translateUiText('Confirmar reinstalação do servidor')}
                confirm={translateUiText('Sim, reinstalar servidor')}
                onClose={() => setModalVisible(false)}
                onConfirmed={reinstall}
            >
                {translateUiText(
                    'O servidor será parado e alguns arquivos poderão ser excluídos ou alterados. Deseja continuar?'
                )}
            </Dialog.Confirm>
            <p css={tw`text-sm`}>
                {translateUiText(
                    'A reinstalação irá parar o servidor e executar novamente o script de instalação original.'
                )}
                <strong css={tw`font-medium`}>
                    {translateUiText(
                        'Alguns arquivos podem ser excluídos ou alterados durante esse processo. Faça backup dos seus dados antes de continuar.'
                    )}
                </strong>
            </p>
            <div css={tw`mt-6 text-right`}>
                <Button.Danger variant={Button.Variants.Secondary} onClick={() => setModalVisible(true)}>
                    {translateUiText('Reinstalar Servidor')}
                </Button.Danger>
            </div>
        </section>
    );
};
