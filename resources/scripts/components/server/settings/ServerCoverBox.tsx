import React, { ChangeEvent, RefObject, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faImage, faTrashAlt, faUpload } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { deleteServerCover, deleteServerIcon, uploadServerCover, uploadServerIcon } from '@/api/server/serverCover';
import styles from './settings.module.css';

interface ImageOptionProps {
    title: string;
    description: string;
    label: string;
    input: RefObject<HTMLInputElement>;
    busy: boolean;
    hasCustomImage: boolean;
    onChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onRemove: () => void;
    error: string;
    notice: string;
}

const ImageOption = ({
    title,
    description,
    label,
    input,
    busy,
    hasCustomImage,
    onChange,
    onRemove,
    error,
    notice,
}: ImageOptionProps) => (
    <div className={styles.imageOption}>
        <div className={styles.imageOptionIcon}>
            <FontAwesomeIcon icon={faImage} />
        </div>
        <div className={styles.imageOptionText}>
            <h3>{title}</h3>
            <p>{description}</p>
            {(error || notice) && (
                <span className={error ? styles.coverError : styles.coverNotice} role={error ? 'alert' : 'status'}>
                    {error || notice}
                </span>
            )}
        </div>
        <input
            ref={input}
            className={styles.coverInput}
            type={'file'}
            accept={'image/jpeg,image/png,image/webp'}
            onChange={onChange}
            disabled={busy}
            aria-label={label}
        />
        <div className={styles.imageOptionActions}>
            {hasCustomImage && (
                <button type={'button'} className={styles.imageReset} onClick={onRemove} disabled={busy}>
                    <FontAwesomeIcon icon={faTrashAlt} /> Restaurar padrão
                </button>
            )}
            <Button
                type={'button'}
                variant={Button.Variants.Secondary}
                onClick={() => input.current?.click()}
                disabled={busy}
            >
                <FontAwesomeIcon icon={faUpload} /> {busy ? 'Enviando...' : 'Enviar foto'}
            </Button>
        </div>
    </div>
);

const ServerCoverBox = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const setServer = ServerContext.useStoreActions((actions) => actions.server.setServer);
    const coverInput = useRef<HTMLInputElement>(null);
    const iconInput = useRef<HTMLInputElement>(null);
    const [coverBusy, setCoverBusy] = useState(false);
    const [iconBusy, setIconBusy] = useState(false);
    const [coverError, setCoverError] = useState('');
    const [coverNotice, setCoverNotice] = useState('');
    const [iconError, setIconError] = useState('');
    const [iconNotice, setIconNotice] = useState('');

    const uploadCover = async (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.currentTarget.files?.[0];
        event.currentTarget.value = '';
        if (!image) return;

        setCoverError('');
        setCoverNotice('');
        setCoverBusy(true);
        try {
            const coverImageUrl = await uploadServerCover(server.uuid, image);
            setServer({ ...server, coverImageUrl });
            setCoverNotice('Foto de fundo atualizada.');
        } catch (requestError) {
            setCoverError(httpErrorToHuman(requestError));
        } finally {
            setCoverBusy(false);
        }
    };

    const uploadIcon = async (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.currentTarget.files?.[0];
        event.currentTarget.value = '';
        if (!image) return;

        setIconError('');
        setIconNotice('');
        setIconBusy(true);
        try {
            const iconImageUrl = await uploadServerIcon(server.uuid, image);
            setServer({ ...server, iconImageUrl });
            setIconNotice('Ícone personalizado atualizado.');
        } catch (requestError) {
            setIconError(httpErrorToHuman(requestError));
        } finally {
            setIconBusy(false);
        }
    };

    const removeCover = async () => {
        setCoverError('');
        setCoverNotice('');
        setCoverBusy(true);
        try {
            await deleteServerCover(server.uuid);
            setServer({ ...server, coverImageUrl: null });
            setCoverNotice('Foto padrão do jogo restaurada.');
        } catch (requestError) {
            setCoverError(httpErrorToHuman(requestError));
        } finally {
            setCoverBusy(false);
        }
    };

    const removeIcon = async () => {
        setIconError('');
        setIconNotice('');
        setIconBusy(true);
        try {
            await deleteServerIcon(server.uuid);
            setServer({ ...server, iconImageUrl: null });
            setIconNotice('Ícone padrão do jogo restaurado.');
        } catch (requestError) {
            setIconError(httpErrorToHuman(requestError));
        } finally {
            setIconBusy(false);
        }
    };

    return (
        <section className={styles.imageOptions} aria-labelledby={'image-options-title'}>
            <header className={styles.imageOptionsHeading}>
                <h2 id={'image-options-title'}>Imagens do servidor</h2>
                <p>Personalize as imagens exibidas no card da lista de servidores.</p>
            </header>
            <ImageOption
                title={'Foto de fundo'}
                description={'JPG, PNG ou WebP · 1200 x 700 px · até 5 MB'}
                label={'Selecionar foto de fundo do servidor'}
                input={coverInput}
                busy={coverBusy}
                hasCustomImage={!!server.coverImageUrl}
                onChange={uploadCover}
                onRemove={removeCover}
                error={coverError}
                notice={coverNotice}
            />
            <ImageOption
                title={'Ícone pequeno'}
                description={'JPG, PNG ou WebP · 256 x 256 px · até 5 MB'}
                label={'Selecionar ícone do servidor'}
                input={iconInput}
                busy={iconBusy}
                hasCustomImage={!!server.iconImageUrl}
                onChange={uploadIcon}
                onRemove={removeIcon}
                error={iconError}
                notice={iconNotice}
            />
        </section>
    );
};

export default ServerCoverBox;
