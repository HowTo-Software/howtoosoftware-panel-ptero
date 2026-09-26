import { translateUiText } from '@/i18n/uiTranslations';
import { getBrowserLocale } from '@/i18n/locale';
import React, { useCallback, useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faFileAlt, faSave, faTimes } from '@fortawesome/free-solid-svg-icons';
import CodemirrorEditor from '@/components/elements/CodemirrorEditor';
import { usePermissions } from '@/plugins/usePermissions';
import { OpenFile, isDirty } from '@/components/server/files/useOpenFiles';
import modes from '@/modes';
import styles from './style.module.css';

interface Props {
    files: OpenFile[];
    activeFile: OpenFile | null;
    setActivePath: (path: string) => void;
    updateFile: (path: string, content: string) => void;
    saveFile: (path: string) => Promise<boolean>;
    closeFile: (path: string) => void;
    retryFile: (path: string) => void;
}

const MultiFileEditor = ({ files, activeFile, setActivePath, updateFile, saveFile, closeFile, retryFile }: Props) => {
    const [canUpdate] = usePermissions(['file.update']);
    const [mode, setMode] = useState('text/plain');
    const dirtyFiles = files.filter(isDirty);

    const saveActive = useCallback(() => {
        if (canUpdate && activeFile) void saveFile(activeFile.path);
    }, [activeFile, canUpdate, saveFile]);

    const saveAll = useCallback(() => {
        if (canUpdate) void Promise.all(dirtyFiles.map((file) => saveFile(file.path)));
    }, [canUpdate, dirtyFiles, saveFile]);

    const onContentChanged = useCallback(
        (content: string) => {
            if (activeFile) updateFile(activeFile.path, content);
        },
        [activeFile, updateFile]
    );

    const fetchContent = useCallback((_callback: () => Promise<string>) => undefined, []);
    const onContentSaved = useCallback(() => saveActive(), [saveActive]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                saveActive();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [saveActive]);

    return (
        <section className={styles.editor_panel} aria-label={translateUiText('File editor')}>
            {files.length > 0 ? (
                <div className={styles.editor_tabs} role={'tablist'} aria-label={translateUiText('Open files')}>
                    {files.map((file) => (
                        <div
                            key={file.path}
                            className={`${styles.editor_tab} ${
                                file.path === activeFile?.path ? styles.active_editor_tab : ''
                            }`}
                        >
                            <button
                                type={'button'}
                                className={styles.editor_tab_select}
                                role={'tab'}
                                aria-selected={file.path === activeFile?.path}
                                aria-controls={'multi-file-editor-panel'}
                                onClick={() => setActivePath(file.path)}
                                title={file.path}
                            >
                                <FontAwesomeIcon icon={faFileAlt} />
                                <span>{file.name}</span>
                                {isDirty(file) && (
                                    <i className={styles.dirty_dot} aria-label={translateUiText('Unsaved changes')} />
                                )}
                            </button>
                            <button
                                type={'button'}
                                className={styles.close_tab}
                                onClick={() => closeFile(file.path)}
                                aria-label={translateUiText('Close {{name}}', { name: file.name })}
                                title={translateUiText('Close file')}
                                disabled={file.status === 'loading' || file.status === 'saving'}
                            >
                                <FontAwesomeIcon icon={faTimes} />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <div className={styles.editor_tabs_empty}>{translateUiText('No files open')}</div>
            )}

            {activeFile ? (
                <>
                    <div className={styles.editor_toolbar}>
                        <div className={styles.editor_breadcrumb} title={activeFile.path}>
                            <span>{translateUiText('home')}</span>
                            <span className={styles.path_separator}>›</span>
                            <span>{translateUiText('container')}</span>
                            {activeFile.path
                                .split('/')
                                .filter(Boolean)
                                .map((part, index, parts) => (
                                    <React.Fragment key={`${index}-${part}`}>
                                        <span className={styles.path_separator}>›</span>
                                        <span className={index === parts.length - 1 ? styles.current_path : ''}>
                                            {part}
                                        </span>
                                    </React.Fragment>
                                ))}
                        </div>
                        <label className={styles.language_picker}>
                            <span className={'sr-only'}>{translateUiText('Editor language')}</span>
                            <select value={mode} onChange={(event) => setMode(event.currentTarget.value)}>
                                {modes.map((item) => (
                                    <option key={`${item.name}_${item.mime}`} value={item.mime}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <div className={styles.editor_body} id={'multi-file-editor-panel'} role={'tabpanel'}>
                        <CodemirrorEditor
                            key={'multi-file-codemirror'}
                            style={{ height: '100%', minHeight: 0 }}
                            documentKey={activeFile.path}
                            mode={mode}
                            filename={activeFile.name}
                            initialContent={activeFile.content}
                            onModeChanged={setMode}
                            fetchContent={fetchContent}
                            onContentSaved={onContentSaved}
                            onContentChanged={onContentChanged}
                            readOnly={!canUpdate || activeFile.status !== 'ready'}
                        />
                        {activeFile.status === 'loading' && (
                            <div className={styles.editor_overlay}>
                                {translateUiText('Loading')} {activeFile.name}…
                            </div>
                        )}
                        {activeFile.status === 'error' && (
                            <div className={styles.editor_overlay} role={'alert'}>
                                <p>{activeFile.error || translateUiText('Unable to load this file.')}</p>
                                <button type={'button'} onClick={() => retryFile(activeFile.path)}>
                                    {translateUiText('Try again')}
                                </button>
                            </div>
                        )}
                    </div>

                    <footer className={styles.editor_statusbar}>
                        <div className={styles.editor_status_left}>
                            <span>{translateUiText('UTF-8')}</span>
                            <span>{translateUiText('LF')}</span>
                            <span>{mode.toUpperCase().split('/').pop()}</span>
                        </div>
                        <div className={styles.editor_status_right}>
                            {activeFile.status === 'saving' ? (
                                <span>{translateUiText('Saving…')}</span>
                            ) : activeFile.error ? (
                                <span className={styles.error_status}>{activeFile.error}</span>
                            ) : isDirty(activeFile) ? (
                                <span className={styles.dirty_status}>{translateUiText('Unsaved changes')}</span>
                            ) : (
                                <span>{translateUiText('Saved')}</span>
                            )}
                            <span>
                                {translateUiText('Modified')}{' '}
                                {activeFile.modifiedAt.toLocaleString(getBrowserLocale() === 'pt' ? 'pt-BR' : 'en-US')}
                            </span>
                            {canUpdate && (
                                <button
                                    type={'button'}
                                    className={styles.save_button}
                                    onClick={saveActive}
                                    disabled={!isDirty(activeFile) || activeFile.status === 'saving'}
                                >
                                    <FontAwesomeIcon icon={faSave} />
                                    {translateUiText('Save File')}
                                </button>
                            )}
                        </div>
                    </footer>
                </>
            ) : (
                <div className={styles.editor_empty}>
                    <FontAwesomeIcon icon={faFileAlt} />
                    <strong>{translateUiText('Open a file to start editing')}</strong>
                    <span>{translateUiText('Select an editable file from the explorer.')}</span>
                </div>
            )}
            {canUpdate && dirtyFiles.length > 1 && (
                <button type={'button'} className={styles.save_all_button} onClick={saveAll}>
                    <FontAwesomeIcon icon={faSave} />
                    {translateUiText('Save all (')}
                    {dirtyFiles.length})
                </button>
            )}
        </section>
    );
};

export default MultiFileEditor;
