import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    width: min(100% - 2rem, 440px);
    margin: 0 auto;

    ${breakpoint('sm')`
        width: min(100% - 3rem, 440px);
    `};
`;

const Brand = styled.div`
    margin-bottom: 1.5rem;
    text-align: center;

    img {
        display: block;
        width: 3.25rem;
        height: 3.25rem;
        margin: 0 auto;
        padding: 0.25rem;
        border: 1px solid var(--hts-border-blue);
        border-radius: 0.5rem;
        background: rgba(176, 105, 255, 0.08);
        filter: drop-shadow(0 0 10px rgba(176, 105, 255, 0.4));
    }

    strong {
        display: block;
        margin-top: 0.75rem;
        color: var(--hts-ink);
        font-size: 1.125rem;
    }

    h2 {
        margin-top: 1.5rem;
        color: var(--hts-ink);
        font-size: 1.5rem;
        font-weight: 500;
    }
`;

const FormSurface = styled.div`
    width: 100%;
    border: 1px solid var(--hts-border-strong);
    border-radius: 0.5rem;
    background: var(--hts-surface);
    padding: 1.5rem;
    box-shadow: 0 24px 60px -38px rgba(0, 0, 0, 0.95);
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);

    return (
        <Container>
            <Brand>
                <img src={'/themes/howtoo/images/hts-logo.svg'} alt={''} aria-hidden={'true'} />
                <strong>{name}</strong>
                {title && <h2>{title}</h2>}
            </Brand>
            <FlashMessageRender css={tw`mb-3`} />
            <Form {...props} ref={ref}>
                <FormSurface>{props.children}</FormSurface>
            </Form>
            <p css={tw`text-center text-neutral-500 text-xs mt-4`}>&copy; {new Date().getFullYear()} HowToo Software</p>
        </Container>
    );
});
