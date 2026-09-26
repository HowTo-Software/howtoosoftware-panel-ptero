import { useCallback, useEffect, useRef, useState } from 'react';
import { join } from 'pathe';
import { httpErrorToHuman } from '@/api/http';
import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';
import { FileObject } from '@/api/server/files/loadDirectory';
import { ServerContext } from '@/state/server';

export interface OpenFile {
    path: string;
    name: string;
    content: string;
    originalContent: string;
    modifiedAt: Date;
    status: 'loading' | 'ready' | 'saving' | 'error';
    error?: string;
}

const isDirty = (file: OpenFile): boolean => file.content !== file.originalContent;

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [files, setFiles] = useState<OpenFile[]>([]);
    const [activePath, setActivePath] = useState<string | null>(null);
    const pendingReads = useRef(new Set<string>());
    const pendingSaves = useRef(new Set<string>());
    const filesRef = useRef(files);

    filesRef.current = files;

    const openFile = useCallback(
        (file: FileObject, directory: string) => {
            const path = join(directory || '/', file.name);
            setActivePath(path);

            if (filesRef.current.some((openFile) => openFile.path === path) || pendingReads.current.has(path)) {
                return;
            }

            pendingReads.current.add(path);
            setFiles((current) => [
                ...current,
                {
                    path,
                    name: file.name,
                    content: '',
                    originalContent: '',
                    modifiedAt: file.modifiedAt,
                    status: 'loading',
                },
            ]);

            getFileContents(uuid, path)
                .then((content) => {
                    setFiles((current) =>
                        current.map((openFile) =>
                            openFile.path === path
                                ? { ...openFile, content, originalContent: content, status: 'ready', error: undefined }
                                : openFile
                        )
                    );
                })
                .catch((error: unknown) => {
                    setFiles((current) =>
                        current.map((openFile) =>
                            openFile.path === path
                                ? {
                                      ...openFile,
                                      status: 'error',
                                      error: httpErrorToHuman(error),
                                  }
                                : openFile
                        )
                    );
                })
                .finally(() => pendingReads.current.delete(path));
        },
        [uuid]
    );

    const retryFile = useCallback(
        (path: string) => {
            const current = filesRef.current.find((file) => file.path === path);
            if (!current || pendingReads.current.has(path)) return;

            pendingReads.current.add(path);
            setFiles((files) =>
                files.map((file) => (file.path === path ? { ...file, status: 'loading', error: undefined } : file))
            );
            getFileContents(uuid, path)
                .then((content) =>
                    setFiles((files) =>
                        files.map((file) =>
                            file.path === path
                                ? { ...file, content, originalContent: content, status: 'ready', error: undefined }
                                : file
                        )
                    )
                )
                .catch((error: unknown) =>
                    setFiles((files) =>
                        files.map((file) =>
                            file.path === path
                                ? {
                                      ...file,
                                      status: 'error',
                                      error: httpErrorToHuman(error),
                                  }
                                : file
                        )
                    )
                )
                .finally(() => pendingReads.current.delete(path));
        },
        [uuid]
    );

    const updateFile = useCallback((path: string, content: string) => {
        setFiles((current) =>
            current.map((file) => (file.path === path ? { ...file, content, error: undefined } : file))
        );
    }, []);

    const saveFile = useCallback(
        async (path: string): Promise<boolean> => {
            const file = filesRef.current.find((openFile) => openFile.path === path);
            if (
                !file ||
                !isDirty(file) ||
                file.status === 'loading' ||
                file.status === 'error' ||
                pendingSaves.current.has(path)
            ) {
                return false;
            }

            const content = file.content;
            pendingSaves.current.add(path);
            setFiles((current) =>
                current.map((openFile) => (openFile.path === path ? { ...openFile, status: 'saving' } : openFile))
            );

            try {
                await saveFileContents(uuid, path, content);
                setFiles((current) =>
                    current.map((openFile) =>
                        openFile.path === path
                            ? {
                                  ...openFile,
                                  originalContent: content,
                                  modifiedAt: new Date(),
                                  status: 'ready',
                              }
                            : openFile
                    )
                );
                return true;
            } catch (error) {
                setFiles((current) =>
                    current.map((openFile) =>
                        openFile.path === path
                            ? {
                                  ...openFile,
                                  status: 'ready',
                                  error: httpErrorToHuman(error),
                              }
                            : openFile
                    )
                );
                return false;
            } finally {
                pendingSaves.current.delete(path);
            }
        },
        [uuid]
    );

    const closeFile = useCallback(
        (path: string) => {
            const file = filesRef.current.find((openFile) => openFile.path === path);
            if (!file || pendingReads.current.has(path) || pendingSaves.current.has(path)) return;
            if (isDirty(file) && !window.confirm(`Discard unsaved changes in ${file.name}?`)) return;

            const index = filesRef.current.findIndex((openFile) => openFile.path === path);
            const remaining = filesRef.current.filter((openFile) => openFile.path !== path);
            setFiles(remaining);
            if (activePath === path) {
                setActivePath(remaining[index]?.path || remaining[index - 1]?.path || null);
            }
        },
        [activePath]
    );

    useEffect(() => {
        const warnBeforeLeave = (event: BeforeUnloadEvent) => {
            if (filesRef.current.some(isDirty)) {
                event.preventDefault();
                event.returnValue = '';
            }
        };

        window.addEventListener('beforeunload', warnBeforeLeave);
        return () => window.removeEventListener('beforeunload', warnBeforeLeave);
    }, []);

    return {
        files,
        activeFile: files.find((file) => file.path === activePath) || null,
        activePath,
        setActivePath,
        openFile,
        retryFile,
        updateFile,
        saveFile,
        closeFile,
    };
};

export { isDirty };
