import React, { FormEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import styled, { keyframes } from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane, faStop, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Button from '@/components/elements/Button';
import { Textarea } from '@/components/elements/Input';
import { streamAssistant, AssistantMessage } from '@/api/server/howtoo';
import { ServerContext } from '@/state/server';
import SafeMarkdown from './SafeMarkdown';

const Chat = styled.section`
    display: flex;
    height: min(46rem, calc(100vh - 11rem));
    min-height: 34rem;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--hts-border);
    border-radius: 0.5rem;
    background: var(--hts-surface);
`;

const Header = styled.header`
    display: flex;
    min-height: 4.25rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-bottom: 1px solid var(--hts-border);
    padding: 0.875rem 1rem;

    h1 {
        color: var(--hts-ink);
        font-size: 1rem;
        font-weight: 600;
    }

    p {
        margin-top: 0.2rem;
        color: var(--hts-ink-muted);
        font-size: 0.75rem;
    }
`;

const StatusDot = styled.span<{ online: boolean }>`
    display: inline-block;
    width: 0.45rem;
    height: 0.45rem;
    margin: 0 0.35rem 0.05rem 0.25rem;
    border-radius: 50%;
    background: ${(props) => (props.online ? '#4ade80' : '#94a3b8')};
`;

const Conversation = styled.div`
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    gap: 1.1rem;
    overflow-y: auto;
    padding: 1.25rem clamp(0.875rem, 3vw, 2rem);
`;

const Empty = styled.div`
    display: grid;
    flex: 1;
    place-items: center;
    color: var(--hts-ink-muted);
    font-size: 0.875rem;
    text-align: center;
`;

const MessageRow = styled.article<{ customer: boolean }>`
    display: flex;
    width: 100%;
    max-width: 52rem;
    align-self: ${(props) => (props.customer ? 'flex-end' : 'flex-start')};
    flex-direction: column;
    align-items: ${(props) => (props.customer ? 'flex-end' : 'flex-start')};
    gap: 0.35rem;
`;

const Author = styled.span`
    color: var(--hts-ink-muted);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
`;

const MessageBody = styled.div<{ customer: boolean }>`
    max-width: min(46rem, 92%);
    border: 1px solid ${(props) => (props.customer ? 'var(--hts-border-blue)' : 'transparent')};
    border-radius: 0.5rem;
    background: ${(props) => (props.customer ? 'rgba(176, 105, 255, 0.09)' : 'transparent')};
    padding: ${(props) => (props.customer ? '0.7rem 0.85rem' : '0')};
    color: var(--hts-ink-soft);
    font-size: 0.875rem;
    line-height: 1.65;
    overflow-wrap: anywhere;

    p + p,
    p + ul,
    p + ol,
    ul + p,
    ol + p,
    pre + p {
        margin-top: 0.75rem;
    }

    ul,
    ol {
        margin-left: 1.25rem;
    }

    a {
        color: var(--hts-secondary);
        text-decoration: underline;
    }

    strong {
        color: var(--hts-ink);
    }
`;

const pulse = keyframes`
    0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
    40% { opacity: 1; transform: translateY(-0.18rem); }
`;

const Thinking = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hts-ink-muted);
    font-size: 0.78rem;

    i {
        width: 0.3rem;
        height: 0.3rem;
        border-radius: 50%;
        background: var(--hts-secondary);
        animation: ${pulse} 1.1s infinite ease-in-out;
    }

    i:nth-child(2) {
        animation-delay: 0.15s;
    }
    i:nth-child(3) {
        animation-delay: 0.3s;
    }
`;

const Composer = styled.form`
    position: sticky;
    bottom: 0;
    border-top: 1px solid var(--hts-border);
    background: var(--hts-surface);
    padding: 0.875rem 1rem;
`;

const ComposerActions = styled.div`
    display: flex;
    min-height: 2rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.6rem;

    span {
        color: var(--hts-ink-muted);
        font-size: 0.7rem;
    }
`;

const ErrorText = styled.p`
    color: #fca5a5;
    font-size: 0.78rem;
`;

const safeStoredMessages = (serverId: string): AssistantMessage[] => {
    try {
        const parsed = JSON.parse(sessionStorage.getItem(`howtoo:assistant:${serverId}`) || '[]');
        if (!Array.isArray(parsed)) return [];

        return parsed
            .filter(
                (item) =>
                    item &&
                    ['user', 'assistant'].includes(item.role) &&
                    typeof item.content === 'string' &&
                    item.content.length <= 12000
            )
            .slice(-50);
    } catch {
        return [];
    }
};

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const serverStatus = ServerContext.useStoreState((state) => state.status.value);
    const available = server.howtoo.aiAssistant.available;
    const [messages, setMessages] = useState<AssistantMessage[]>(() => safeStoredMessages(server.uuid));
    const [message, setMessage] = useState('');
    const [draft, setDraft] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const conversation = useRef<HTMLDivElement>(null);
    const controller = useRef<AbortController>();
    const keepAtBottom = useRef(true);

    useEffect(() => {
        sessionStorage.setItem(`howtoo:assistant:${server.uuid}`, JSON.stringify(messages.slice(-50)));
    }, [messages, server.uuid]);

    useEffect(() => {
        if (!keepAtBottom.current || !conversation.current) return;
        const frame = requestAnimationFrame(() => {
            if (conversation.current) conversation.current.scrollTop = conversation.current.scrollHeight;
        });

        return () => cancelAnimationFrame(frame);
    }, [messages, draft, loading]);

    useEffect(() => () => controller.current?.abort(), []);

    const send = async () => {
        const content = message.trim();
        if (!content || loading || !available) return;

        const history = messages.slice(-10);
        const abortController = new AbortController();
        controller.current = abortController;
        keepAtBottom.current = true;
        setMessages((current) => [...current, { role: 'user', content }]);
        setMessage('');
        setDraft('');
        setError('');
        setLoading(true);

        try {
            const answer = await streamAssistant(
                server.uuid,
                content,
                history,
                serverStatus,
                abortController.signal,
                () => undefined,
                setDraft
            );
            setMessages((current) => [...current, answer]);
            setDraft('');
        } catch (error) {
            if (!abortController.signal.aborted) {
                setError(error instanceof Error ? error.message : 'The assistant could not answer right now.');
            }
        } finally {
            if (controller.current === abortController) controller.current = undefined;
            setLoading(false);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        send();
    };

    const keyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    };

    const cancel = () => {
        controller.current?.abort();
        controller.current = undefined;
        setDraft('');
        setLoading(false);
    };

    const clear = () => {
        cancel();
        setMessages([]);
        setDraft('');
        setError('');
        sessionStorage.removeItem(`howtoo:assistant:${server.uuid}`);
    };

    return (
        <ServerContentBlock title={'AI Assistant'} showFlashKey={'howtoo:assistant'}>
            <Chat>
                <Header>
                    <div>
                        <h1>AI Assistant</h1>
                        <p>
                            Server: {server.name}
                            <StatusDot online={serverStatus === 'running'} />
                            {serverStatus || 'unknown'}
                        </p>
                        <p>
                            Ollama
                            <StatusDot online={server.howtoo.aiAssistant.providers.ollama} />
                            {server.howtoo.aiAssistant.providers.ollama ? 'Available' : 'Unavailable'}
                        </p>
                    </div>
                    {!!messages.length && (
                        <Button size={'xsmall'} color={'grey'} isSecondary onClick={clear} type={'button'}>
                            <FontAwesomeIcon icon={faTrashAlt} className={'mr-2'} />
                            Clear
                        </Button>
                    )}
                </Header>

                <Conversation
                    ref={conversation}
                    aria-live={'polite'}
                    onScroll={(event) => {
                        const element = event.currentTarget;
                        keepAtBottom.current = element.scrollHeight - element.scrollTop - element.clientHeight < 96;
                    }}
                >
                    {!messages.length && (
                        <Empty>
                            {available
                                ? 'Ask about an error, a configuration option or a panel function.'
                                : 'No assistant provider is currently available.'}
                        </Empty>
                    )}
                    {messages.map((item, index) => (
                        <MessageRow key={`${item.role}-${index}`} customer={item.role === 'user'}>
                            <Author>{item.role === 'user' ? 'You' : 'Assistant'}</Author>
                            <MessageBody customer={item.role === 'user'}>
                                {item.role === 'assistant' ? (
                                    <SafeMarkdown content={item.content} />
                                ) : (
                                    <p>{item.content}</p>
                                )}
                            </MessageBody>
                        </MessageRow>
                    ))}
                    {loading && (
                        <MessageRow customer={false}>
                            <Author>Assistant</Author>
                            {draft ? (
                                <MessageBody customer={false}>
                                    <SafeMarkdown content={draft} />
                                </MessageBody>
                            ) : (
                                <Thinking aria-label={'Assistant is thinking'}>
                                    Thinking <i /> <i /> <i />
                                </Thinking>
                            )}
                        </MessageRow>
                    )}
                </Conversation>

                <Composer onSubmit={submit}>
                    <Textarea
                        rows={2}
                        maxLength={3000}
                        value={message}
                        onChange={(event) => setMessage(event.currentTarget.value)}
                        onKeyDown={keyDown}
                        placeholder={'Ask something...'}
                        disabled={!available}
                    />
                    <ComposerActions>
                        <div>
                            {error ? (
                                <ErrorText>{error}</ErrorText>
                            ) : (
                                <span>Enter to send / Shift+Enter for a new line</span>
                            )}
                        </div>
                        {loading ? (
                            <Button type={'button'} color={'red'} isSecondary size={'small'} onClick={cancel}>
                                <FontAwesomeIcon icon={faStop} className={'mr-2'} />
                                Stop
                            </Button>
                        ) : (
                            <Button type={'submit'} size={'small'} disabled={!message.trim() || !available}>
                                <FontAwesomeIcon icon={faPaperPlane} className={'mr-2'} />
                                Send
                            </Button>
                        )}
                    </ComposerActions>
                </Composer>
            </Chat>
        </ServerContentBlock>
    );
};
