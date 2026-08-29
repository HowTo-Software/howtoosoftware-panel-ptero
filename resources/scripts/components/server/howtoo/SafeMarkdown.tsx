import React, { useState } from 'react';
import styled from 'styled-components/macro';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import copy from 'copy-to-clipboard';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCopy } from '@fortawesome/free-solid-svg-icons';

const InlineCode = styled.code`
    border: 1px solid var(--hts-border);
    border-radius: 0.25rem;
    background: var(--hts-surface-soft);
    padding: 0.08rem 0.3rem;
    color: var(--hts-secondary);
    font-size: 0.8em;
`;

const CodeFrame = styled.div`
    position: relative;
    margin: 0.75rem 0;
    overflow: auto;
    border: 1px solid var(--hts-border);
    border-radius: 0.4rem;
    background: #080b14;
    padding: 2.25rem 0.9rem 0.9rem;

    code {
        color: #dbeafe;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.78rem;
        white-space: pre;
    }
`;

const CopyCode = styled.button`
    position: absolute;
    top: 0.45rem;
    right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hts-ink-muted);
    font-size: 0.68rem;

    &:hover {
        color: var(--hts-ink);
    }
`;

const MarkdownCode = ({ inline, children, ...props }: any) =>
    inline ? <InlineCode {...props}>{children}</InlineCode> : <code {...props}>{children}</code>;

const CodeBlock = ({ children }: any) => {
    const [copied, setCopied] = useState(false);
    const content = String(children?.props?.children || '').replace(/\n$/, '');

    const copyCode = () => {
        copy(content);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    };

    return (
        <CodeFrame>
            <CopyCode type={'button'} onClick={copyCode} aria-label={'Copy code'}>
                <FontAwesomeIcon icon={copied ? faCheck : faCopy} />
                {copied ? 'Copied' : 'Copy'}
            </CopyCode>
            {children}
        </CodeFrame>
    );
};

export default ({ content }: { content: string }) => (
    <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={{
            code: MarkdownCode,
            pre: CodeBlock,
            a: ({ children, ...props }) => (
                <a {...props} target={'_blank'} rel={'noreferrer noopener'}>
                    {children}
                </a>
            ),
        }}
    >
        {content}
    </ReactMarkdown>
);
