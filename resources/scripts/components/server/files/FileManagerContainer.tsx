import React, { useCallback, useEffect, useState } from 'react';
import { httpErrorToHuman } from '@/api/http';
import { CSSTransition } from 'react-transition-group';
import Spinner from '@/components/elements/Spinner';
import FileObjectRow from '@/components/server/files/FileObjectRow';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { FileObject } from '@/api/server/files/loadDirectory';
import NewDirectoryButton from '@/components/server/files/NewDirectoryButton';
import { NavLink, useLocation } from 'react-router-dom';
import Can from '@/components/elements/Can';
import { ServerError } from '@/components/elements/ScreenBlock';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import FileManagerStatus from '@/components/server/files/FileManagerStatus';
import MassActionsBar from '@/components/server/files/MassActionsBar';
import UploadButton from '@/components/server/files/UploadButton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useStoreActions } from '@/state/hooks';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FileActionCheckbox } from '@/components/server/files/SelectFileCheckbox';
import { hashToPath } from '@/helpers';
import MultiFileEditor from '@/components/server/files/MultiFileEditor';
import useOpenFiles from '@/components/server/files/useOpenFiles';
import styles from './style.module.css';

const sortFiles = (files: FileObject[]): FileObject[] => {
    const sortedFiles = [...files]
        .sort((a, b) => a.name.localeCompare(b.name))
        .sort((a, b) => (a.isFile === b.isFile ? 0 : a.isFile ? 1 : -1));
    return sortedFiles.filter((file, index) => index === 0 || file.name !== sortedFiles[index - 1].name);
};

export default () => {
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const { hash } = useLocation();
    const { data: files, error, mutate } = useFileManagerSwr();
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const clearFlashes = useStoreActions((actions) => actions.flashes.clearFlashes);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);
    const setSelectedFiles = ServerContext.useStoreActions((actions) => actions.files.setSelectedFiles);
    const selectedFilesLength = ServerContext.useStoreState((state) => state.files.selectedFiles.length);
    const openFiles = useOpenFiles();
    const [filter, setFilter] = useState('');

    useEffect(() => {
        clearFlashes('files');
        setSelectedFiles([]);
        setDirectory(hashToPath(hash));
    }, [hash]);

    useEffect(() => {
        mutate();
    }, [directory]);

    useEffect(() => {
        setFilter('');
    }, [directory]);

    const onSelectAllClick = (event: React.ChangeEvent<HTMLInputElement>) => {
        setSelectedFiles(event.currentTarget.checked ? files?.map((file) => file.name) || [] : []);
    };

    const onOpenFile = useCallback(
        (file: FileObject) => openFiles.openFile(file, directory),
        [directory, openFiles.openFile]
    );

    if (error) {
        return <ServerError message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    const sortedFiles = files ? sortFiles(files.slice(0, 250)) : [];
    const visibleFiles = sortedFiles.filter((file) => file.name.toLowerCase().includes(filter.trim().toLowerCase()));

    return (
        <ServerContentBlock title={'Server Files'} showFlashKey={'files'} className={'file-manager-content-wide'}>
            <ErrorBoundary>
                <div className={styles.file_page}>
                    <header className={styles.file_page_header}>
                        <div className={styles.file_page_title}>
                            <h1>Server Files</h1>
                            <p>Browse, manage, and edit your server files.</p>
                        </div>
                        <Can action={'file.create'}>
                            <div className={styles.file_toolbar}>
                                <FileManagerStatus />
                                <UploadButton />
                                <NewDirectoryButton />
                                <NavLink to={`/server/${id}/files/new${window.location.hash}`}>
                                    <Button>New File</Button>
                                </NavLink>
                            </div>
                        </Can>
                    </header>

                    <div className={styles.file_workspace}>
                        <section className={styles.explorer_panel} aria-label={'File explorer'}>
                            <div className={styles.explorer_header}>
                                <strong>File Explorer</strong>
                                <span className={styles.explorer_count}>
                                    {files ? `${files.length} items` : 'Loading'}
                                </span>
                            </div>
                            <div className={styles.explorer_path}>
                                <FileManagerBreadcrumbs
                                    renderLeft={
                                        <FileActionCheckbox
                                            type={'checkbox'}
                                            checked={selectedFilesLength === (files?.length === 0 ? -1 : files?.length)}
                                            onChange={onSelectAllClick}
                                        />
                                    }
                                />
                            </div>
                            <label className={styles.explorer_search}>
                                <span className={'sr-only'}>Filter files in this folder</span>
                                <span aria-hidden={'true'}>⌕</span>
                                <input
                                    type={'search'}
                                    value={filter}
                                    onChange={(event) => setFilter(event.currentTarget.value)}
                                    placeholder={'Filter this folder…'}
                                />
                            </label>
                            <div className={styles.file_list}>
                                {!files ? (
                                    <Spinner size={'large'} centered />
                                ) : (
                                    <>
                                        {files.length > 250 && (
                                            <div
                                                className={'mb-2 rounded bg-yellow-900/50 p-2 text-xs text-yellow-200'}
                                            >
                                                This folder has more than 250 items; only the first 250 are shown.
                                            </div>
                                        )}
                                        {visibleFiles.length > 0 ? (
                                            <CSSTransition classNames={'fade'} timeout={150} appear in>
                                                <div>
                                                    {visibleFiles.map((file) => (
                                                        <FileObjectRow
                                                            key={file.key}
                                                            file={file}
                                                            onOpenFile={onOpenFile}
                                                        />
                                                    ))}
                                                </div>
                                            </CSSTransition>
                                        ) : (
                                            <div className={styles.explorer_empty}>
                                                {files.length === 0 ? 'This folder is empty.' : 'No matching files.'}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                            <MassActionsBar />
                        </section>

                        <MultiFileEditor
                            files={openFiles.files}
                            activeFile={openFiles.activeFile}
                            setActivePath={openFiles.setActivePath}
                            updateFile={openFiles.updateFile}
                            saveFile={openFiles.saveFile}
                            closeFile={openFiles.closeFile}
                            retryFile={openFiles.retryFile}
                        />
                    </div>
                </div>
            </ErrorBoundary>
        </ServerContentBlock>
    );
};
