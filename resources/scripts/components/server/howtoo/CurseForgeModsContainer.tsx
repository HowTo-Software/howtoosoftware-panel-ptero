import { translateUiText } from '@/i18n/uiTranslations';
import { getBrowserLocale } from '@/i18n/locale';
import React, { FormEvent, useCallback, useEffect, useState } from 'react';
import styled from 'styled-components/macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import Can from '@/components/elements/Can';
import { Dialog } from '@/components/elements/dialog';
import { httpErrorToHuman } from '@/api/http';
import {
    CurseForgeFile,
    CurseForgeMod,
    CurseForgeSort,
    getCurseForgeFiles,
    getCurseForgeMod,
    getCurseForgeServerPackFiles,
    getInstalledCurseForgeMods,
    installCurseForgeFile,
    installCurseForgeModpack,
    searchCurseForge,
    searchCurseForgeModpacks,
} from '@/api/server/howtoo';
import { ServerContext } from '@/state/server';
import { Badge, Card, Grid, Muted, Panel, Toolbar } from './IntegrationStyles';

type CatalogTab = 'mods' | 'modpacks' | 'installed';

const Layout = styled.div`
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) 21rem;
    gap: 1rem;
    margin-top: 1rem;

    @media (max-width: 1050px) {
        grid-template-columns: minmax(0, 1fr);
    }
`;

const MainColumn = styled.main`
    min-width: 0;
`;

const CatalogHeader = styled.div`
    display: flex;
    min-width: 0;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid var(--hts-border);
    padding-bottom: 0.9rem;

    h1 {
        color: var(--hts-ink);
        font-size: 1.2rem;
        font-weight: 650;
    }

    p {
        margin-top: 0.2rem;
        color: var(--hts-ink-muted);
        font-size: 0.8rem;
    }

    @media (max-width: 700px) {
        flex-direction: column;
    }
`;

const Badges = styled(Toolbar)`
    justify-content: flex-end;
`;

const TabBar = styled.nav`
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    border-bottom: 1px solid var(--hts-border);
    padding: 0.75rem 0;
`;

const TabButton = styled.button<{ $active: boolean }>`
    border: 1px solid ${({ $active }) => ($active ? '#8b5cf6' : 'var(--hts-border)')};
    border-radius: 0.5rem;
    background: ${({ $active }) => ($active ? 'rgba(124, 58, 237, 0.2)' : 'var(--hts-surface-soft)')};
    padding: 0.5rem 0.75rem;
    color: ${({ $active }) => ($active ? '#e9d5ff' : 'var(--hts-ink-soft)')};
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
`;

const ToolbarPanel = styled.div`
    display: grid;
    gap: 0.7rem;
    border: 1px solid var(--hts-border);
    border-radius: 0.65rem;
    background: var(--hts-surface);
    padding: 0.85rem;
`;

const SearchForm = styled.form`
    display: flex;
    min-width: 0;
    gap: 0.55rem;

    & > div {
        min-width: 0;
        flex: 1;
    }

    @media (max-width: 600px) {
        flex-direction: column;

        button {
            width: 100%;
        }
    }
`;

const SortBar = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;

    span {
        margin-right: 0.2rem;
        color: var(--hts-ink-muted);
        font-size: 0.72rem;
    }
`;

const SortButton = styled.button<{ $active: boolean }>`
    border: 1px solid ${({ $active }) => ($active ? 'var(--hts-border-blue)' : 'var(--hts-border)')};
    border-radius: 999px;
    background: ${({ $active }) => ($active ? 'rgba(99, 102, 241, 0.18)' : 'transparent')};
    padding: 0.3rem 0.6rem;
    color: ${({ $active }) => ($active ? '#c7d2fe' : 'var(--hts-ink-soft)')};
    font-size: 0.7rem;
    cursor: pointer;
`;

const ProjectGrid = styled(Grid)`
    grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
`;

const ProjectCard = styled(Card)<{ $selected?: boolean }>`
    border-color: ${({ $selected }) => ($selected ? '#8b5cf6' : 'var(--hts-border)')};
    background: ${({ $selected }) => ($selected ? 'rgba(35, 29, 57, 0.98)' : 'var(--hts-surface-soft)')};

    .project-heading {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: 0.65rem;
    }

    img {
        width: 3.6rem;
        height: 3.6rem;
        flex: 0 0 auto;
        border: 1px solid var(--hts-border);
        border-radius: 0.45rem;
        object-fit: cover;
    }

    .project-title {
        min-width: 0;
    }

    h3 {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-height: 1.3;
    }

    p {
        display: -webkit-box;
        min-height: 3.3rem;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-height: 1.4;
    }
`;

const ResultsHeading = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin: 1rem 0 0.7rem;

    h2 {
        color: var(--hts-ink);
        font-size: 0.95rem;
        font-weight: 650;
    }
`;

const CatalogContent = styled.div`
    margin-top: 1rem;
`;

const SidePanel = styled(Panel)`
    align-self: start;
    position: sticky;
    top: 1rem;

    h2 {
        color: var(--hts-ink);
        font-size: 0.95rem;
        font-weight: 650;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1050px) {
        position: static;
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

    strong {
        color: var(--hts-ink);
        font-size: 0.78rem;
    }

    small {
        margin-top: 0.2rem;
        color: var(--hts-ink-muted);
        font-size: 0.68rem;
    }
`;

const Notice = styled.div<{ $tone?: 'info' | 'warning' }>`
    margin-top: 0.8rem;
    border: 1px solid ${({ $tone }) => ($tone === 'warning' ? 'rgba(245, 158, 11, 0.45)' : 'var(--hts-border-blue)')};
    border-radius: 0.55rem;
    background: ${({ $tone }) => ($tone === 'warning' ? 'rgba(120, 72, 12, 0.14)' : 'rgba(41, 70, 120, 0.14)')};
    padding: 0.7rem 0.8rem;
    color: ${({ $tone }) => ($tone === 'warning' ? '#fcd98b' : 'var(--hts-ink-soft)')};
    font-size: 0.78rem;
    line-height: 1.45;
`;

const ErrorText = styled.p`
    margin-top: 0.75rem;
    color: #fca5a5;
    font-size: 0.8125rem;
`;

const Pager = styled.div`
    display: flex;
    justify-content: space-between;
    margin-top: 0.9rem;
`;

const FileTags = styled.div`
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.35rem;
`;

const fileSize = (bytes: number) => `${Math.max(bytes / 1024 / 1024, 0.01).toFixed(2)} MB`;

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const compatibility = server.howtoo.curseForge;
    const [tab, setTab] = useState<CatalogTab>('mods');
    const [sort, setSort] = useState<CurseForgeSort>('downloads');
    const [query, setQuery] = useState('');
    const [activeQuery, setActiveQuery] = useState('');
    const [index, setIndex] = useState(0);
    const [totalCount, setTotalCount] = useState(0);
    const [results, setResults] = useState<CurseForgeMod[]>([]);
    const [selected, setSelected] = useState<CurseForgeMod>();
    const [files, setFiles] = useState<CurseForgeFile[]>([]);
    const [installed, setInstalled] = useState<Array<{ name: string; size: number; modified_at: string | null }>>([]);
    const [loadingInstalled, setLoadingInstalled] = useState(false);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [filesLoading, setFilesLoading] = useState(false);
    const [installing, setInstalling] = useState<number>();
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [pendingServerPack, setPendingServerPack] = useState<CurseForgeFile>();

    const isBedrock = compatibility.edition === 'bedrock';
    const isVanilla = compatibility.modLoaderType === 0;
    const canBrowseMods = compatibility.modLoaderType !== null && compatibility.modLoaderType > 0;
    const hasNextPage = index + 20 < totalCount;

    const loadCatalog = useCallback(
        async (
            catalogTab: Exclude<CatalogTab, 'installed'>,
            searchTerm: string,
            nextIndex: number,
            nextSort: CurseForgeSort
        ) => {
            setCatalogLoading(true);
            setError('');
            setFiles([]);
            setSelected(undefined);

            try {
                const page =
                    catalogTab === 'mods'
                        ? await searchCurseForge(server.uuid, searchTerm, nextIndex, nextSort)
                        : await searchCurseForgeModpacks(server.uuid, searchTerm, nextIndex, nextSort);
                setResults(page.items);
                setIndex(page.pagination.index);
                setTotalCount(page.pagination.totalCount);
            } catch (error) {
                setResults([]);
                setTotalCount(0);
                setError(httpErrorToHuman(error));
            } finally {
                setCatalogLoading(false);
            }
        },
        [server.uuid]
    );

    const loadInstalled = useCallback(async () => {
        if (isBedrock) return;
        setLoadingInstalled(true);
        setError('');
        try {
            setInstalled(await getInstalledCurseForgeMods(server.uuid));
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setLoadingInstalled(false);
        }
    }, [isBedrock, server.uuid]);

    useEffect(() => {
        if (tab === 'installed') {
            void loadInstalled();
            return;
        }

        if (isBedrock || (tab === 'mods' && !canBrowseMods)) {
            setResults([]);
            setTotalCount(0);
            setSelected(undefined);
            setFiles([]);
            return;
        }

        setQuery('');
        setActiveQuery('');
        setIndex(0);
        void loadCatalog(tab, '', 0, sort);
    }, [tab, loadCatalog, loadInstalled, isBedrock, canBrowseMods, sort]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        if (isBedrock || (tab === 'mods' && !canBrowseMods)) return;
        setActiveQuery(query.trim());
        void loadCatalog(tab as Exclude<CatalogTab, 'installed'>, query.trim(), 0, sort);
    };

    const changeSort = (nextSort: CurseForgeSort) => {
        setSort(nextSort);
        setQuery('');
        setActiveQuery('');
        setIndex(0);
    };

    const selectProject = async (mod: CurseForgeMod) => {
        setSelected(mod);
        setFiles([]);
        setFilesLoading(true);
        setError('');
        try {
            if (tab === 'modpacks') {
                setFiles(await getCurseForgeServerPackFiles(server.uuid, mod.id));
            } else {
                const [details, compatibleFiles] = await Promise.all([
                    getCurseForgeMod(server.uuid, mod.id),
                    getCurseForgeFiles(server.uuid, mod.id),
                ]);
                setSelected(details);
                setFiles(compatibleFiles);
            }
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setFilesLoading(false);
        }
    };

    const installMod = async (file: CurseForgeFile) => {
        if (!selected || installing !== undefined) return;
        setInstalling(file.id);
        setError('');
        setNotice('');
        try {
            const filename = await installCurseForgeFile(server.uuid, selected.id, file.id);
            setNotice(translateUiText('{{filename}} installed in the mods directory.', { filename }));
            await loadInstalled();
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setInstalling(undefined);
        }
    };

    const installServerPack = async () => {
        if (!selected || !pendingServerPack || installing !== undefined) return;
        setInstalling(pendingServerPack.id);
        setError('');
        setNotice('');
        try {
            await installCurseForgeModpack(server.uuid, selected.id, pendingServerPack.id);
            setNotice(
                translateUiText(
                    'The server pack was extracted. Check the startup command and server files before starting.'
                )
            );
            setPendingServerPack(undefined);
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setInstalling(undefined);
        }
    };

    const sortOptions: Array<{ id: CurseForgeSort; label: string }> = [
        { id: 'downloads', label: 'Most downloaded' },
        { id: 'popular', label: 'Popular' },
        { id: 'updated', label: 'Recently updated' },
    ];

    const pageTitle =
        tab === 'mods'
            ? translateUiText('Mods')
            : tab === 'modpacks'
            ? translateUiText('Modpacks')
            : translateUiText('Installed');

    return (
        <ServerContentBlock title={translateUiText('CurseForge Mods')}>
            <CatalogHeader>
                <div>
                    <h1>{translateUiText('Minecraft mod browser')}</h1>
                    <p>{translateUiText('Find server-compatible mods and published server packs from CurseForge.')}</p>
                </div>
                <Badges>
                    <Badge>
                        {compatibility.edition === 'bedrock'
                            ? translateUiText('Bedrock Edition')
                            : translateUiText('Java Edition')}
                    </Badge>
                    <Badge>{compatibility.gameVersion || translateUiText('Version not detected')}</Badge>
                    <Badge>{compatibility.modLoader || translateUiText('Loader not detected')}</Badge>
                </Badges>
            </CatalogHeader>

            {isBedrock && (
                <Notice $tone={'warning'}>
                    {translateUiText(
                        'Bedrock was detected. This CurseForge integration manages Java mods and Java server packs. Bedrock add-ons use a different format and are not installed by this page.'
                    )}
                </Notice>
            )}
            {!isBedrock && !compatibility.gameVersion && (
                <Notice $tone={'warning'}>
                    {translateUiText(
                        'The egg does not expose an exact Minecraft version. Modpack results cannot be version-filtered; check the version tags before installing.'
                    )}
                </Notice>
            )}
            {tab === 'mods' && !isBedrock && isVanilla && (
                <Notice>
                    {translateUiText(
                        'Vanilla Java was detected. Individual mods need Forge, Fabric, Quilt, or NeoForge; use Modpacks to find a server pack that includes a mod loader.'
                    )}
                </Notice>
            )}
            {tab === 'mods' && !isBedrock && !isVanilla && !canBrowseMods && (
                <Notice $tone={'warning'}>
                    {translateUiText(
                        'Minecraft Java was detected, but this server egg does not identify a supported mod loader. Configure the egg with Forge, Fabric, Quilt, or NeoForge to filter compatible mods.'
                    )}
                </Notice>
            )}

            {notice && <Notice>{notice}</Notice>}
            {error && <ErrorText>{error}</ErrorText>}

            <TabBar aria-label={translateUiText('CurseForge catalogs')}>
                {(['mods', 'modpacks', 'installed'] as CatalogTab[]).map((item) => (
                    <TabButton key={item} type={'button'} $active={tab === item} onClick={() => setTab(item)}>
                        {item === 'mods'
                            ? translateUiText('Mods')
                            : item === 'modpacks'
                            ? translateUiText('Modpacks')
                            : translateUiText('Installed')}
                    </TabButton>
                ))}
            </TabBar>

            {tab === 'installed' ? (
                <CatalogContent>
                    <ResultsHeading>
                        <h2>{translateUiText('Installed mod files')}</h2>
                        <Badge>{installed.length}</Badge>
                    </ResultsHeading>
                    {isBedrock ? (
                        <Notice $tone={'warning'}>
                            {translateUiText('Bedrock add-ons do not use the Java mods directory.')}
                        </Notice>
                    ) : loadingInstalled ? (
                        <Spinner centered />
                    ) : installed.length === 0 ? (
                        <Muted>{translateUiText('No .jar or .zip mod files were found in the mods directory.')}</Muted>
                    ) : (
                        <ProjectGrid>
                            {installed.map((file) => (
                                <Card key={file.name}>
                                    <h3>{file.name}</h3>
                                    <Muted>{fileSize(file.size)}</Muted>
                                </Card>
                            ))}
                        </ProjectGrid>
                    )}
                </CatalogContent>
            ) : (
                <>
                    <ToolbarPanel>
                        <SearchForm onSubmit={submitSearch}>
                            <div>
                                <Input
                                    value={query}
                                    onChange={(event) => setQuery(event.currentTarget.value)}
                                    placeholder={translateUiText(
                                        tab === 'modpacks'
                                            ? 'Search modpacks by name or paste a CurseForge modpack link'
                                            : 'Search compatible mods by name'
                                    )}
                                    disabled={isBedrock || (tab === 'mods' && !canBrowseMods)}
                                />
                            </div>
                            <Button
                                type={'submit'}
                                isLoading={catalogLoading}
                                disabled={isBedrock || (tab === 'mods' && !canBrowseMods)}
                            >
                                {translateUiText('Search')}
                            </Button>
                        </SearchForm>
                        <SortBar>
                            <span>{translateUiText('Sort by')}</span>
                            {sortOptions.map((option) => (
                                <SortButton
                                    key={option.id}
                                    type={'button'}
                                    $active={sort === option.id}
                                    disabled={isBedrock || (tab === 'mods' && !canBrowseMods)}
                                    onClick={() => changeSort(option.id)}
                                >
                                    {translateUiText(option.label)}
                                </SortButton>
                            ))}
                        </SortBar>
                    </ToolbarPanel>

                    <Layout>
                        <MainColumn>
                            <ResultsHeading>
                                <div>
                                    <h2>
                                        {activeQuery
                                            ? translateUiText('Search results')
                                            : `${translateUiText(pageTitle)} · ${translateUiText(
                                                  sortOptions.find((option) => option.id === sort)!.label
                                              )}`}
                                    </h2>
                                    <Muted>
                                        {totalCount > 0
                                            ? translateUiText('{{count}} CurseForge projects', {
                                                  count: totalCount.toLocaleString(
                                                      getBrowserLocale() === 'pt' ? 'pt-BR' : 'en-US'
                                                  ),
                                              })
                                            : tab === 'modpacks'
                                            ? translateUiText('Server pack files are separated from client downloads.')
                                            : translateUiText(
                                                  'Results are filtered to this server version and mod loader.'
                                              )}
                                    </Muted>
                                </div>
                                {totalCount > 0 && (
                                    <Badge>
                                        {Math.floor(index / 20) + 1} / {Math.ceil(totalCount / 20)}
                                    </Badge>
                                )}
                            </ResultsHeading>

                            {isBedrock ? (
                                <Notice $tone={'warning'}>
                                    {translateUiText('Choose a Java server to browse these mods and server packs.')}
                                </Notice>
                            ) : catalogLoading ? (
                                <Spinner centered />
                            ) : results.length === 0 ? (
                                <Panel>
                                    <strong>{translateUiText('No projects found')}</strong>
                                    <Muted>
                                        {translateUiText('Try another search or select a different ranking filter.')}
                                    </Muted>
                                </Panel>
                            ) : (
                                <ProjectGrid>
                                    {results.map((mod) => (
                                        <ProjectCard key={mod.id} $selected={selected?.id === mod.id}>
                                            <div className={'project-heading'}>
                                                {mod.image ? (
                                                    <img src={mod.image} alt={''} loading={'lazy'} />
                                                ) : (
                                                    <div aria-hidden={'true'} />
                                                )}
                                                <div className={'project-title'}>
                                                    <h3>{mod.name}</h3>
                                                    <Muted>
                                                        {mod.downloadCount.toLocaleString(
                                                            getBrowserLocale() === 'pt' ? 'pt-BR' : 'en-US'
                                                        )}{' '}
                                                        {translateUiText('downloads')}
                                                    </Muted>
                                                </div>
                                            </div>
                                            <p>{mod.summary}</p>
                                            <Toolbar>
                                                <Button size={'xsmall'} onClick={() => selectProject(mod)}>
                                                    {translateUiText(
                                                        tab === 'modpacks'
                                                            ? 'View server files'
                                                            : 'View compatible files'
                                                    )}
                                                </Button>
                                                {mod.website && (
                                                    <a href={mod.website} target={'_blank'} rel={'noopener noreferrer'}>
                                                        {translateUiText('CurseForge project')}
                                                    </a>
                                                )}
                                            </Toolbar>
                                        </ProjectCard>
                                    ))}
                                </ProjectGrid>
                            )}

                            {!isBedrock && totalCount > 20 && (
                                <Pager>
                                    <Button
                                        color={'grey'}
                                        isSecondary
                                        disabled={index <= 0 || catalogLoading}
                                        onClick={() =>
                                            void loadCatalog(
                                                tab as Exclude<CatalogTab, 'installed'>,
                                                activeQuery,
                                                Math.max(0, index - 20),
                                                sort
                                            )
                                        }
                                    >
                                        {translateUiText('Previous')}
                                    </Button>
                                    <Button
                                        color={'grey'}
                                        isSecondary
                                        disabled={!hasNextPage || catalogLoading}
                                        onClick={() =>
                                            void loadCatalog(
                                                tab as Exclude<CatalogTab, 'installed'>,
                                                activeQuery,
                                                index + 20,
                                                sort
                                            )
                                        }
                                    >
                                        {translateUiText('Next')}
                                    </Button>
                                </Pager>
                            )}
                        </MainColumn>

                        <SidePanel>
                            <h2>
                                {selected
                                    ? selected.name
                                    : translateUiText(tab === 'modpacks' ? 'Server pack files' : 'Compatible files')}
                            </h2>
                            {selected?.description && (
                                <Muted style={{ marginTop: '0.5rem' }}>{selected.description}</Muted>
                            )}
                            {!selected && (
                                <Muted style={{ marginTop: '0.55rem' }}>
                                    {translateUiText(
                                        tab === 'modpacks'
                                            ? 'Select a modpack to check whether its creator published a server pack.'
                                            : 'Choose a mod to see files matching this server.'
                                    )}
                                </Muted>
                            )}
                            {filesLoading ? (
                                <Spinner centered />
                            ) : selected && files.length === 0 ? (
                                <Notice $tone={tab === 'modpacks' ? 'warning' : undefined}>
                                    {translateUiText(
                                        tab === 'modpacks'
                                            ? 'This author has not published a server pack for the selected modpack.'
                                            : 'No compatible mod file was returned for this project.'
                                    )}
                                </Notice>
                            ) : (
                                files.map((file) => (
                                    <FileRow key={file.id}>
                                        <div>
                                            <strong>{file.displayName}</strong>
                                            <small>
                                                {file.fileName} · {fileSize(file.fileLength)}
                                            </small>
                                            <FileTags>
                                                {file.gameVersions.slice(0, 4).map((version) => (
                                                    <Badge key={version}>{version}</Badge>
                                                ))}
                                                {file.gameVersions.length > 4 && (
                                                    <Badge>+{file.gameVersions.length - 4}</Badge>
                                                )}
                                                {tab === 'modpacks' && <Badge>{translateUiText('Server pack')}</Badge>}
                                            </FileTags>
                                        </div>
                                        {tab === 'modpacks' ? (
                                            <Can action={'integration.curseforge-install'}>
                                                <Button
                                                    size={'xsmall'}
                                                    isLoading={installing === file.id}
                                                    disabled={installing !== undefined}
                                                    onClick={() => setPendingServerPack(file)}
                                                >
                                                    {translateUiText('Install pack')}
                                                </Button>
                                            </Can>
                                        ) : (
                                            <Can action={'integration.curseforge-install'}>
                                                <Button
                                                    size={'xsmall'}
                                                    isLoading={installing === file.id}
                                                    disabled={installing !== undefined || !file.downloadUrl}
                                                    onClick={() => void installMod(file)}
                                                >
                                                    {translateUiText('Install')}
                                                </Button>
                                            </Can>
                                        )}
                                    </FileRow>
                                ))
                            )}
                        </SidePanel>
                    </Layout>
                </>
            )}

            <Dialog.Confirm
                open={Boolean(pendingServerPack)}
                title={translateUiText('Install this server pack?')}
                confirm={installing ? translateUiText('Installing...') : translateUiText('Download and extract')}
                onClose={() => installing === undefined && setPendingServerPack(undefined)}
                onConfirmed={() => void installServerPack()}
            >
                <div>
                    <p>{translateUiText('The server must be completely stopped before installing a server pack.')}</p>
                    <p style={{ marginTop: '0.65rem' }}>
                        {translateUiText(
                            'The archive will be extracted into the server root. Existing files with the same names can be overwritten, so make a backup before continuing.'
                        )}
                    </p>
                    <p style={{ marginTop: '0.65rem' }}>
                        {translateUiText(
                            'After extraction, review the startup command and server files before starting the server.'
                        )}
                    </p>
                </div>
            </Dialog.Confirm>
        </ServerContentBlock>
    );
};
