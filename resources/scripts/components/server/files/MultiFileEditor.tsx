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
        <section className={styles.editor_panel} aria-label={'File editor'}>
            {files.length > 0 ? (
                <div className={styles.editor_tabs} role={'tablist'} aria-label={'Open files'}>
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
                                {isDirty(file) && <i className={styles.dirty_dot} aria-label={'Unsaved changes'} />}
                            </button>
                            <button
                                type={'button'}
                                className={styles.close_tab}
                                onClick={() => closeFile(file.path)}
                                aria-label={`Close ${file.name}`}
                                title={'Close file'}
                                disabled={file.status === 'loading' || file.status === 'saving'}
                            >
                                <FontAwesomeIcon icon={faTimes} />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <div className={styles.editor_tabs_empty}>No files open</div>
            )}

            {activeFile ? (
                <>
                    <div className={styles.editor_toolbar}>
                        <div className={styles.editor_breadcrumb} title={activeFile.path}>
                            <span>home</span>
                            <span className={styles.path_separator}>›</span>
                            <span>container</span>
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
                            <span className={'sr-only'}>Editor language</span>
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
                            <div className={styles.editor_overlay}>Loading {activeFile.name}…</div>
                        )}
                        {activeFile.status === 'error' && (
                            <div className={styles.editor_overlay} role={'alert'}>
                                <p>{activeFile.error || 'Unable to load this file.'}</p>
                                <button type={'button'} onClick={() => retryFile(activeFile.path)}>
                                    Try again
                                </button>
                            </div>
                        )}
                    </div>

                    <footer className={styles.editor_statusbar}>
                        <div className={styles.editor_status_left}>
                            <span>UTF-8</span>
                            <span>LF</span>
                            <span>{mode.toUpperCase().split('/').pop()}</span>
                        </div>
                        <div className={styles.editor_status_right}>
                            {activeFile.status === 'saving' ? (
                                <span>Saving…</span>
                            ) : activeFile.error ? (
                                <span className={styles.error_status}>{activeFile.error}</span>
                            ) : isDirty(activeFile) ? (
                                <span className={styles.dirty_status}>Unsaved changes</span>
                            ) : (
                                <span>Saved</span>
                            )}
                            <span>Modified {activeFile.modifiedAt.toLocaleString()}</span>
                            {canUpdate && (
                                <button
                                    type={'button'}
                                    className={styles.save_button}
                                    onClick={saveActive}
                                    disabled={!isDirty(activeFile) || activeFile.status === 'saving'}
                                >
                                    <FontAwesomeIcon icon={faSave} />
                                    Save File
                                </button>
                            )}
                        </div>
                    </footer>
                </>
            ) : (
                <div className={styles.editor_empty}>
                    <FontAwesomeIcon icon={faFileAlt} />
                    <strong>Open a file to start editing</strong>
                    <span>Select an editable file from the explorer.</span>
                </div>
            )}
            {canUpdate && dirtyFiles.length > 1 && (
                <button type={'button'} className={styles.save_all_button} onClick={saveAll}>
                    <FontAwesomeIcon icon={faSave} />
                    Save all ({dirtyFiles.length})
                </button>
            )}
        </section>
    );
};

export default MultiFileEditor;
