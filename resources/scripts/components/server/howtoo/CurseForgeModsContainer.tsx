import React, { FormEvent, useEffect, useState } from 'react';
import styled from 'styled-components/macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import Can from '@/components/elements/Can';
import { httpErrorToHuman } from '@/api/http';
import {
    CurseForgeFile,
    CurseForgeMod,
    getCurseForgeFiles,
    getCurseForgeMod,
    getInstalledCurseForgeMods,
    installCurseForgeFile,
    searchCurseForge,
} from '@/api/server/howtoo';
import { ServerContext } from '@/state/server';
import { Badge, Card, Grid, Muted, Panel, Toolbar } from './IntegrationStyles';

const Columns = styled.div`
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(18rem, 1fr);
    gap: 1rem;
    margin-top: 1rem;

    @media (max-width: 900px) {
        grid-template-columns: 1fr;
    }
`;

const FileRow = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-top: 1px solid var(--hts-border);
    padding: 0.75rem 0;

    div {
        min-width: 0;
    }

    strong,
    small {
        display: block;
        overflow-wrap: anywhere;
    }

    small {
        margin-top: 0.2rem;
        color: var(--hts-ink-muted);
    }
`;

const ErrorText = styled.p`
    margin-top: 0.75rem;
    color: #fca5a5;
    font-size: 0.8125rem;
`;

const fileSize = (bytes: number) => `${Math.max(bytes / 1024 / 1024, 0.01).toFixed(2)} MB`;

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const compatibility = server.howtoo.curseForge;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<CurseForgeMod[]>([]);
    const [selected, setSelected] = useState<CurseForgeMod>();
    const [files, setFiles] = useState<CurseForgeFile[]>([]);
    const [installed, setInstalled] = useState<Array<{ name: string; size: number; modified_at: string | null }>>([]);
    const [loading, setLoading] = useState(true);
    const [searching, setSearching] = useState(false);
    const [filesLoading, setFilesLoading] = useState(false);
    const [installing, setInstalling] = useState<number>();
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const loadInstalled = async () => {
        setLoading(true);
        try {
            setInstalled(await getInstalledCurseForgeMods(server.uuid));
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadInstalled();
    }, [server.uuid]);

    const search = async (event: FormEvent) => {
        event.preventDefault();
        if (query.trim().length < 2) return;
        setSearching(true);
        setError('');
        try {
            setResults(await searchCurseForge(server.uuid, query.trim()));
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setSearching(false);
        }
    };

    const select = async (mod: CurseForgeMod) => {
        setSelected(mod);
        setFiles([]);
        setFilesLoading(true);
        setError('');
        try {
            const [details, compatibleFiles] = await Promise.all([
                getCurseForgeMod(server.uuid, mod.id),
                getCurseForgeFiles(server.uuid, mod.id),
            ]);
            setSelected(details);
            setFiles(compatibleFiles);
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setFilesLoading(false);
        }
    };

    const install = async (file: CurseForgeFile) => {
        if (!selected) return;
        setInstalling(file.id);
        setError('');
        setNotice('');
        try {
            const filename = await installCurseForgeFile(server.uuid, selected.id, file.id);
            setNotice(`${filename} installed in the mods directory.`);
            await loadInstalled();
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setInstalling(undefined);
        }
    };

    return (
        <ServerContentBlock title={'CurseForge Mods - BETA'}>
            <div>
                <Toolbar style={{ justifyContent: 'space-between' }}>
                    <div>
                        <strong>Compatible mod browser</strong>
                        <Muted>Only files matching this server are offered for installation.</Muted>
                    </div>
                    <Toolbar>
                        <Badge>{compatibility.gameVersion || 'Version not detected'}</Badge>
                        <Badge>{compatibility.modLoader || 'Loader not detected'}</Badge>
                    </Toolbar>
                </Toolbar>
                {notice && <Muted style={{ marginTop: '0.75rem', color: '#86efac' }}>{notice}</Muted>}
                {error && <ErrorText>{error}</ErrorText>}

                <form onSubmit={search} style={{ marginTop: '1rem' }}>
                    <Toolbar>
                        <Input
                            style={{ maxWidth: '32rem' }}
                            value={query}
                            onChange={(event) => setQuery(event.currentTarget.value)}
                            placeholder={'Search compatible CurseForge mods'}
                        />
                        <Button type={'submit'} isLoading={searching} disabled={query.trim().length < 2}>
                            Search
                        </Button>
                    </Toolbar>
                </form>

                <Columns>
                    <div>
                        {!results.length ? (
                            <Muted>Search for a mod to view compatible server files.</Muted>
                        ) : (
                            <Grid>
                                {results.map((mod) => (
                                    <Card key={mod.id}>
                                        {mod.image && <img src={mod.image} alt={''} loading={'lazy'} />}
                                        <h3>{mod.name}</h3>
                                        <p>{mod.summary}</p>
                                        <Muted>{mod.downloadCount.toLocaleString()} downloads</Muted>
                                        <Toolbar>
                                            <Button size={'xsmall'} onClick={() => select(mod)}>
                                                Select files
                                            </Button>
                                            {mod.website && (
                                                <a href={mod.website} target={'_blank'} rel={'noopener noreferrer'}>
                                                    Project page
                                                </a>
                                            )}
                                        </Toolbar>
                                    </Card>
                                ))}
                            </Grid>
                        )}
                    </div>

                    <Panel>
                        <h2>{selected ? selected.name : 'Compatible files'}</h2>
                        {selected?.description && <Muted style={{ marginTop: '0.5rem' }}>{selected.description}</Muted>}
                        {filesLoading ? (
                            <Spinner centered />
                        ) : !files.length ? (
                            <Muted style={{ marginTop: '0.75rem' }}>
                                {selected ? 'No compatible file was returned.' : 'Select a project to continue.'}
                            </Muted>
                        ) : (
                            files.map((file) => (
                                <FileRow key={file.id}>
                                    <div>
                                        <strong>{file.displayName}</strong>
                                        <small>
                                            {file.fileName} - {fileSize(file.fileLength)}
                                        </small>
                                    </div>
                                    <Can action={'integration.curseforge-install'}>
                                        <Button
                                            size={'xsmall'}
                                            isLoading={installing === file.id}
                                            disabled={installing !== undefined || !file.downloadUrl}
                                            onClick={() => install(file)}
                                        >
                                            Install
                                        </Button>
                                    </Can>
                                </FileRow>
                            ))
                        )}
                    </Panel>
                </Columns>

                <div style={{ marginTop: '1.25rem' }}>
                    <h2>Installed mod files</h2>
                    {loading ? (
                        <Spinner centered />
                    ) : !installed.length ? (
                        <Muted style={{ marginTop: '0.5rem' }}>No .jar or .zip mod files were found.</Muted>
                    ) : (
                        <Grid style={{ marginTop: '0.75rem' }}>
                            {installed.map((file) => (
                                <Card key={file.name}>
                                    <h3>{file.name}</h3>
                                    <Muted>{fileSize(file.size)}</Muted>
                                </Card>
                            ))}
                        </Grid>
                    )}
                </div>
            </div>
        </ServerContentBlock>
    );
};
