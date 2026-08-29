import styled from 'styled-components/macro';

export const Panel = styled.section`
    border: 1px solid var(--hts-border);
    border-radius: 0.5rem;
    background: var(--hts-surface);
    padding: 1rem;
`;

export const Toolbar = styled.div`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
`;

export const Grid = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    gap: 0.875rem;
`;

export const Card = styled.article`
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.65rem;
    border: 1px solid var(--hts-border);
    border-radius: 0.5rem;
    background: var(--hts-surface-soft);
    padding: 0.875rem;

    h3 {
        color: var(--hts-ink);
        font-size: 0.95rem;
        font-weight: 600;
    }

    p {
        color: var(--hts-ink-muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    img {
        width: 100%;
        height: 8rem;
        border-radius: 0.375rem;
        object-fit: cover;
    }
`;

export const Badge = styled.span`
    display: inline-flex;
    width: fit-content;
    align-items: center;
    border: 1px solid var(--hts-border-blue);
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    color: var(--hts-secondary);
    font-size: 0.7rem;
`;

export const Muted = styled.p`
    color: var(--hts-ink-muted);
    font-size: 0.8125rem;
`;
