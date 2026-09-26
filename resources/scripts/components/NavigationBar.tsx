import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';
import { useTranslation } from 'react-i18next';

const Navigation = styled.header`
    width: 100%;
    overflow-x: hidden;
    border-bottom: 1px solid rgba(111, 129, 182, 0.14);
    background: linear-gradient(90deg, rgba(18, 23, 41, 0.99), rgba(22, 27, 48, 0.99));
    box-shadow: 0 10px 28px -24px rgba(0, 0, 0, 0.95);

    #logo > a {
        @media (max-width: 640px) {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }
`;

const Brand = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    min-width: 0;

    span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    img {
        width: 1.7rem;
        height: 1.7rem;
        flex: none;
        padding: 0.1rem;
        border: 1px solid rgba(176, 105, 255, 0.24);
        border-radius: 0.45rem;
        background: rgba(176, 105, 255, 0.07);
        filter: drop-shadow(0 0 6px rgba(176, 105, 255, 0.24));
    }
`;

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center h-full no-underline text-neutral-300 cursor-pointer transition-all duration-150`};
        padding-left: 1rem;
        padding-right: 1rem;
        font-size: 0.82rem;

        @media (max-width: 640px) {
            padding-left: 0.6rem;
            padding-right: 0.6rem;
        }

        &:active,
        &:hover {
            color: rgb(245 247 255);
            background: rgba(131, 168, 243, 0.06);
        }

        &:active,
        &:hover,
        &.active {
            box-shadow: inset 0 -2px ${theme`colors.cyan.400`.toString()};
        }
    }
`;

export default () => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const { t } = useTranslation('strings');

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <Navigation>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto flex min-w-0 w-full items-center h-[3.25rem] max-w-[1600px]'}>
                <div id={'logo'} className={'min-w-0 flex-1'}>
                    <Link
                        to={'/'}
                        className={
                            'text-2xl font-header font-medium px-4 no-underline text-neutral-200 hover:text-neutral-100 transition-colors duration-150'
                        }
                    >
                        <Brand>
                            <img src={'/themes/howtoo/images/hts-logo.svg'} alt={''} aria-hidden={'true'} />
                            <span>{name}</span>
                        </Brand>
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={t('home')}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={t('admin_control')}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={t('account')}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={t('sign_out')}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </Navigation>
    );
};
