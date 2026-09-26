import React, { ChangeEvent, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faImage, faTrashAlt, faUpload } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { deleteServerCover, uploadServerCover } from '@/api/server/serverCover';
import styles from './settings.module.css';

const ServerCoverBox = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const setServer = ServerContext.useStoreActions((actions) => actions.server.setServer);
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const upload = async (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.currentTarget.files?.[0];
        event.currentTarget.value = '';
        if (!image) return;

        setError('');
        setNotice('');
        setBusy(true);
        try {
            const coverImageUrl = await uploadServerCover(server.uuid, image);
            setServer({ ...server, coverImageUrl });
            setNotice('Foto de fundo atualizada.');
        } catch (requestError) {
            setError(httpErrorToHuman(requestError));
        } finally {
            setBusy(false);
        }
    };

    const remove = async () => {
        setError('');
        setNotice('');
        setBusy(true);
        try {
            await deleteServerCover(server.uuid);
            setServer({ ...server, coverImageUrl: null });
            setNotice('Foto personalizada removida. A imagem padr\u00e3o do jogo ser\u00e1 usada.');
        } catch (requestError) {
            setError(httpErrorToHuman(requestError));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className={`${styles.card} ${styles.coverCard}`}>
            <header className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <FontAwesomeIcon icon={faImage} />
                </div>
                <div>
                    <h2>Foto de Fundo do Servidor</h2>
                    <p>{'Personalize a imagem grande do card. O \u00edcone do jogo n\u00e3o ser\u00e1 alterado.'}</p>
                </div>
            </header>

            <div className={styles.coverPreview}>
                {server.coverImageUrl ? (
                    <img src={server.coverImageUrl} alt={`Foto de fundo de ${server.name}`} />
                ) : (
                    <span>{'Imagem padr\u00e3o do jogo'}</span>
                )}
            </div>

            <p className={styles.coverHelp}>
                {'Use JPG, PNG ou WebP com exatamente 1200 x 700 pixels (m\u00e1ximo 5 MB).'}
            </p>
            <input
                ref={input}
                className={styles.coverInput}
                type={'file'}
                accept={'image/jpeg,image/png,image/webp'}
                onChange={upload}
                disabled={busy}
                aria-label={'Selecionar foto de fundo do servidor'}
            />
            <div className={styles.coverActions}>
                <Button
                    type={'button'}
                    variant={Button.Variants.Secondary}
                    onClick={() => input.current?.click()}
                    disabled={busy}
                >
                    <FontAwesomeIcon icon={faUpload} /> {busy ? 'Enviando...' : 'Escolher foto'}
                </Button>
                {server.coverImageUrl && (
                    <button type={'button'} className={styles.removeCoverButton} onClick={remove} disabled={busy}>
                        <FontAwesomeIcon icon={faTrashAlt} /> Remover foto
                    </button>
                )}
            </div>
            {error && (
                <p className={styles.coverError} role={'alert'}>
                    {error}
                </p>
            )}
            {notice && (
                <p className={styles.coverNotice} role={'status'}>
                    {notice}
                </p>
            )}
        </section>
    );
};

export default ServerCoverBox;
