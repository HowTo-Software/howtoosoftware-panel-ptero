import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faFileAlt, faFileArchive, faFileImport, faFolder } from '@fortawesome/free-solid-svg-icons';
import { encodePathSegments } from '@/helpers';
import { differenceInHours, format, formatDistanceToNow } from 'date-fns';
import React, { memo } from 'react';
import { FileObject } from '@/api/server/files/loadDirectory';
import FileDropdownMenu from '@/components/server/files/FileDropdownMenu';
import { ServerContext } from '@/state/server';
import { NavLink, useRouteMatch } from 'react-router-dom';
import isEqual from 'react-fast-compare';
import SelectFileCheckbox from '@/components/server/files/SelectFileCheckbox';
import { usePermissions } from '@/plugins/usePermissions';
import { join } from 'pathe';
import { bytesToString } from '@/lib/formatters';
import styles from './style.module.css';

const Clickable: React.FC<{ file: FileObject; onOpenFile?: (file: FileObject) => void }> = memo(
    ({ file, onOpenFile, children }) => {
        const [canRead] = usePermissions(['file.read']);
        const [canReadContents] = usePermissions(['file.read-content']);
        const directory = ServerContext.useStoreState((state) => state.files.directory);

        const match = useRouteMatch();

        if ((file.isFile && (!file.isEditable() || !canReadContents)) || (!file.isFile && !canRead)) {
            return <div className={styles.details}>{children}</div>;
        }

        if (file.isFile && onOpenFile) {
            return (
                <button type={'button'} className={styles.details} onClick={() => onOpenFile(file)} title={file.name}>
                    {children}
                </button>
            );
        }

        return (
            <NavLink
                className={styles.details}
                to={`${match.url}${file.isFile ? '/edit' : ''}#${encodePathSegments(join(directory, file.name))}`}
            >
                {children}
            </NavLink>
        );
    },
    isEqual
);

const FileObjectRow = ({ file, onOpenFile }: { file: FileObject; onOpenFile?: (file: FileObject) => void }) => (
    <div
        className={styles.file_row}
        key={file.name}
        onContextMenu={(e) => {
            e.preventDefault();
            window.dispatchEvent(
                new CustomEvent(`pterodactyl:files:ctx:${file.key}`, {
                    detail: { x: e.clientX, y: e.clientY },
                })
            );
        }}
    >
        <SelectFileCheckbox name={file.name} />
        <Clickable file={file} onOpenFile={onOpenFile}>
            <span className={styles.file_icon} aria-hidden={'true'}>
                {file.isFile ? (
                    <FontAwesomeIcon
                        icon={file.isSymlink ? faFileImport : file.isArchiveType() ? faFileArchive : faFileAlt}
                        style={{ color: file.isSymlink ? '#c4a0ff' : file.isArchiveType() ? '#fbbf77' : '#a7b4d3' }}
                    />
                ) : (
                    <FontAwesomeIcon icon={faFolder} style={{ color: '#8bb7ff' }} />
                )}
            </span>
            <span className={styles.file_info}>
                <span className={styles.file_name}>{file.name}</span>
                <span className={styles.file_meta}>
                    {file.isFile && <span>{bytesToString(file.size)}</span>}
                    <span title={file.modifiedAt.toString()}>
                        {Math.abs(differenceInHours(file.modifiedAt, new Date())) > 48
                            ? format(file.modifiedAt, 'MMM do, yyyy h:mma')
                            : formatDistanceToNow(file.modifiedAt, { addSuffix: true })}
                    </span>
                </span>
            </span>
        </Clickable>
        <FileDropdownMenu file={file} />
    </div>
);

export default memo(FileObjectRow, (prevProps, nextProps) => {
    if (prevProps.onOpenFile !== nextProps.onOpenFile) return false;

    /* eslint-disable @typescript-eslint/no-unused-vars */
    const { isArchiveType, isEditable, ...prevFile } = prevProps.file;
    const { isArchiveType: nextIsArchiveType, isEditable: nextIsEditable, ...nextFile } = nextProps.file;
    /* eslint-enable @typescript-eslint/no-unused-vars */

    return isEqual(prevFile, nextFile);
});
