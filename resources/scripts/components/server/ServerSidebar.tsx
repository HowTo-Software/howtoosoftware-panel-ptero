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
    top: 3.25rem;
    align-self: start;
    width: 13.25rem;
    height: calc(100vh - 3.25rem);
    overflow-y: auto;
    border-right: 1px solid rgba(111, 129, 182, 0.14);
    background: linear-gradient(180deg, rgba(20, 25, 44, 0.98), rgba(14, 18, 33, 0.98));
    padding: 0.7rem 0.55rem 1rem;

    @media (max-width: 768px) {
        position: static;
        width: 100%;
        height: auto;
        overflow-x: auto;
        scrollbar-width: none;
        border-right: 0;
        border-bottom: 1px solid var(--hts-border);
        padding: 0.6rem;

        &::-webkit-scrollbar {
            display: none;
        }
    }
`;

const ServerIdentity = styled.div`
    margin-bottom: 0.65rem;
    border: 1px solid rgba(127, 148, 211, 0.18);
    border-radius: 0.65rem;
    background: linear-gradient(145deg, rgba(31, 38, 64, 0.88), rgba(21, 27, 48, 0.95));
    padding: 0.65rem 0.7rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.025);

    strong,
    span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    strong {
        color: var(--hts-ink);
        font-size: 0.78rem;
        line-height: 1rem;
    }

    span {
        margin-top: 0.2rem;
        color: var(--hts-ink-muted);
        font-size: 0.66rem;
        line-height: 0.9rem;
    }

    @media (max-width: 768px) {
        display: none;
    }
`;

const Navigation = styled.nav`
    display: flex;
    flex-direction: column;
    gap: 0.16rem;

    a {
        display: flex;
        min-height: 2.15rem;
        align-items: center;
        gap: 0.58rem;
        border-left: 2px solid transparent;
        border-radius: 0.45rem;
        padding: 0.42rem 0.6rem;
        color: var(--hts-ink-soft);
        font-size: 0.74rem;
        line-height: 1rem;
        text-decoration: none;
        transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease, transform 150ms ease;
    }

    a:hover {
        background: rgba(131, 168, 243, 0.07);
        color: var(--hts-ink);
        transform: translateX(1px);
    }

    a.active {
        border-left-color: var(--hts-primary);
        background: linear-gradient(90deg, rgba(176, 105, 255, 0.15), rgba(82, 143, 255, 0.06));
        color: var(--hts-white);
        box-shadow: inset 0 0 0 1px rgba(176, 105, 255, 0.06);
    }

    svg {
        width: 0.9rem;
        font-size: 0.86rem;
        color: var(--hts-secondary);
    }

    @media (max-width: 768px) {
        flex-direction: row;
        width: max-content;

        a {
            min-height: 2.25rem;
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

const routeIconColors: Record<string, string> = {
    '/': '#67d5ff',
    '/files': '#8bb7ff',
    '/databases': '#b9a0ff',
    '/schedules': '#ffc46b',
    '/users': '#6ee7b7',
    '/backups': '#9aabff',
    '/network': '#5eead4',
    '/startup': '#fbbf77',
    '/settings': '#b7c2db',
    '/activity': '#7dd3fc',
    '/assistant': '#d0a2ff',
    '/workshop-mods': '#c4a0ff',
    '/curseforge-mods': '#89d3ff',
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
                                <FontAwesomeIcon
                                    icon={routeIcons[route.path] || faCog}
                                    fixedWidth
                                    style={{ color: routeIconColors[route.path] || 'var(--hts-secondary)' }}
                                />
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
