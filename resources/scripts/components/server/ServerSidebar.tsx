import React from 'react';
import { NavLink, useRouteMatch } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArchive,
    faCalendarAlt,
    faChartLine,
    faCog,
    faDatabase,
    faExternalLinkAlt,
    faFolderOpen,
    faNetworkWired,
    faRocket,
    faRobot,
    faPuzzlePiece,
    faTerminal,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { useStoreState } from 'easy-peasy';
import { useTranslation } from 'react-i18next';
import styled from 'styled-components/macro';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';

const Sidebar = styled.aside`
    position: sticky;
    top: 3.5rem;
    align-self: start;
    width: 15.5rem;
    height: calc(100vh - 3.5rem);
    overflow-y: auto;
    border-right: 1px solid var(--hts-border);
    background: var(--hts-surface-soft);
    padding: 1rem 0.75rem 1.5rem;

    @media (max-width: 768px) {
        position: static;
        width: 100%;
        height: auto;
        overflow-x: auto;
        border-right: 0;
        border-bottom: 1px solid var(--hts-border);
        padding: 0.75rem;
    }
`;

const ServerIdentity = styled.div`
    margin-bottom: 1rem;
    border: 1px solid var(--hts-border-blue);
    border-radius: 0.5rem;
    background: var(--hts-surface);
    padding: 0.875rem;

    strong,
    span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    strong {
        color: var(--hts-ink);
        font-size: 0.875rem;
    }

    span {
        margin-top: 0.25rem;
        color: var(--hts-ink-muted);
        font-size: 0.75rem;
    }

    @media (max-width: 768px) {
        display: none;
    }
`;

const Navigation = styled.nav`
    display: flex;
    flex-direction: column;
    gap: 0.25rem;

    a {
        display: flex;
        min-height: 2.5rem;
        align-items: center;
        gap: 0.75rem;
        border-left: 2px solid transparent;
        border-radius: 0.375rem;
        padding: 0.625rem 0.75rem;
        color: var(--hts-ink-soft);
        font-size: 0.8125rem;
        text-decoration: none;
        transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
    }

    a:hover {
        background: rgba(131, 168, 243, 0.08);
        color: var(--hts-ink);
    }

    a.active {
        border-left-color: var(--hts-primary);
        background: rgba(176, 105, 255, 0.12);
        color: var(--hts-white);
    }

    svg {
        width: 1rem;
        color: var(--hts-secondary);
    }

    @media (max-width: 768px) {
        flex-direction: row;
        width: max-content;

        a {
            border-bottom: 2px solid transparent;
            border-left: 0;
            white-space: nowrap;
        }

        a.active {
            border-bottom-color: var(--hts-primary);
        }
    }
`;

const routeIcons: Record<string, IconDefinition> = {
    '/': faTerminal,
    '/files': faFolderOpen,
    '/databases': faDatabase,
    '/schedules': faCalendarAlt,
    '/users': faUsers,
    '/backups': faArchive,
    '/network': faNetworkWired,
    '/startup': faRocket,
    '/settings': faCog,
    '/activity': faChartLine,
    '/assistant': faRobot,
    '/workshop-mods': faPuzzlePiece,
    '/curseforge-mods': faPuzzlePiece,
};

const translationKeys: Record<string, string> = {
    '/': 'server_navigation.console',
    '/files': 'server_navigation.files',
    '/databases': 'server_navigation.databases',
    '/schedules': 'server_navigation.schedules',
    '/users': 'server_navigation.users',
    '/backups': 'server_navigation.backups',
    '/network': 'server_navigation.network',
    '/startup': 'server_navigation.startup',
    '/settings': 'server_navigation.settings',
    '/activity': 'server_navigation.activity',
    '/assistant': 'server_navigation.ai_assistant',
    '/workshop-mods': 'server_navigation.workshop_mods',
    '/curseforge-mods': 'server_navigation.curseforge_mods',
};

interface Props {
    adminUrl?: string;
}

export default ({ adminUrl }: Props) => {
    const { t } = useTranslation('strings');
    const match = useRouteMatch<{ id: string }>();
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    const to = (path: string) =>
        path === '/' ? match.url : `${match.url.replace(/\/*$/, '')}/${path.replace(/^\/+/, '')}`;

    return (
        <Sidebar aria-label={t('server_navigation.label')}>
            <ServerIdentity>
                <strong>{server.name}</strong>
                <span>
                    {server.node} · {server.identifier}
                </span>
            </ServerIdentity>
            <Navigation>
                {routes.server
                    .filter((route) => {
                        if (!route.name) return false;
                        if (route.feature === 'workshop') return server.howtoo.workshop.supported;
                        if (route.feature === 'curseForge') return server.howtoo.curseForge.supported;
                        return true;
                    })
                    .map((route) => {
                        const link = (
                            <NavLink key={route.path} to={to(route.path)} exact={route.exact}>
                                <FontAwesomeIcon icon={routeIcons[route.path] || faCog} fixedWidth />
                                <span>{t(translationKeys[route.path] || route.name!)}</span>
                            </NavLink>
                        );

                        return route.permission ? (
                            <Can key={route.path} action={route.permission} matchAny>
                                {link}
                            </Can>
                        ) : (
                            link
                        );
                    })}
                {rootAdmin && adminUrl && (
                    <a href={adminUrl} target={'_blank'} rel={'noreferrer'}>
                        <FontAwesomeIcon icon={faExternalLinkAlt} fixedWidth />
                        <span>{t('server_navigation.admin')}</span>
                    </a>
                )}
            </Navigation>
        </Sidebar>
    );
};
