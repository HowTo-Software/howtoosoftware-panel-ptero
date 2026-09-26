import { translateUiText } from '@/i18n/uiTranslations';
import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerCard from '@/components/dashboard/ServerCard';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import styles from '@/components/dashboard/serverCards.module.css';

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const [searchTerm, setSearchTerm] = useState('');
    const [query, setQuery] = useState('');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    useEffect(() => {
        const timer = setTimeout(() => setQuery(searchTerm.trim()), 250);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page, query],
        () =>
            getServers({
                page,
                query: query.trim() || undefined,
                type: showOnlyAdmin && rootAdmin ? 'admin' : undefined,
            })
    );

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin, query]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={translateUiText('Dashboard')} showFlashKey={'dashboard'}>
            <section css={tw`mx-auto w-full`} style={{ maxWidth: 1500 }}>
                <div css={tw`mb-6 flex flex-wrap items-end justify-between gap-4`}>
                    <div>
                        <h1 css={tw`text-3xl font-semibold text-neutral-100`}>{translateUiText('Seus servidores')}</h1>
                        <p css={tw`mt-2 text-sm text-neutral-400`}>
                            {translateUiText('Gerencie seus servidores de jogos.')}
                        </p>
                    </div>
                    <label
                        css={tw`flex w-full items-center gap-3 rounded-lg border border-neutral-700 bg-neutral-900 px-4 py-3 sm:max-w-md`}
                    >
                        <span aria-hidden={'true'} css={tw`text-neutral-400`}>
                            ⌕
                        </span>
                        <input
                            type={'search'}
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder={translateUiText('Buscar servidores, jogos, endereço ou node...')}
                            aria-label={translateUiText('Buscar servidores')}
                            css={tw`min-w-0 flex-1 bg-transparent text-sm text-neutral-100 placeholder-neutral-500 outline-none`}
                        />
                    </label>
                </div>
                {rootAdmin && (
                    <div css={tw`mb-4 flex items-center justify-end`}>
                        <p css={tw`mr-2 text-xs uppercase text-neutral-400`}>
                            {showOnlyAdmin
                                ? translateUiText('Exibindo servidores de outros usuários')
                                : translateUiText('Exibindo seus servidores')}
                        </p>
                        <Switch
                            name={'show_all_servers'}
                            defaultChecked={showOnlyAdmin}
                            onChange={() => setShowOnlyAdmin((s) => !s)}
                        />
                    </div>
                )}
                {!servers ? (
                    <Spinner centered size={'large'} />
                ) : (
                    <Pagination data={servers} onPageSelect={setPage}>
                        {({ items }) =>
                            items.length > 0 ? (
                                <div className={styles.grid}>
                                    {items.map((server) => (
                                        <ServerCard key={server.uuid} server={server} />
                                    ))}
                                </div>
                            ) : (
                                <p css={tw`text-center text-sm text-neutral-400`}>
                                    {searchTerm.trim()
                                        ? translateUiText('Nenhum servidor corresponde à sua busca.')
                                        : showOnlyAdmin
                                        ? translateUiText('Não há outros servidores para exibir.')
                                        : translateUiText('Não há servidores associados à sua conta.')}
                                </p>
                            )
                        }
                    </Pagination>
                )}
            </section>
        </PageContentBlock>
    );
};
