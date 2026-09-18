import React, { useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import styled from 'styled-components/macro';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
    serverPage?: boolean;
}

const ServerContentContainer = styled(ContentContainer)`
    max-width: 1200px;
    margin-right: 1rem;
    margin-left: 1rem;

    @media (min-width: 769px) {
        margin-right: 1rem;
        margin-left: 2rem;
    }
`;

const PageContentBlock: React.FC<PageContentBlockProps> = ({
    title,
    showFlashKey,
    className,
    serverPage = false,
    children,
}) => {
    useEffect(() => {
        if (title) {
            document.title = title;
        }
    }, [title]);

    const content = (
        <>
            {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
            {children}
        </>
    );

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                {serverPage ? (
                    <ServerContentContainer css={tw`my-4 sm:my-5`} className={className}>
                        {content}
                    </ServerContentContainer>
                ) : (
                    <ContentContainer css={tw`my-4 sm:my-10`} className={className}>
                        {content}
                    </ContentContainer>
                )}

                <ContentContainer css={tw`mb-4`}>
                    <p css={tw`text-center text-neutral-500 text-xs`}>
                        Copyright &copy; 2024 - 2026{' '}
                        <a
                            rel={'noopener nofollow noreferrer'}
                            href={'/'}
                            css={tw`no-underline text-neutral-500 hover:text-neutral-300`}
                        >
                            HowTo.Software
                        </a>
                        .
                    </p>
                </ContentContainer>
            </>
        </CSSTransition>
    );
};

export default PageContentBlock;
