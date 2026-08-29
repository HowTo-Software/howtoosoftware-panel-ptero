import React, { FormEvent, useMemo, useState } from 'react';
import styled from 'styled-components/macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Button from '@/components/elements/Button';
import { Textarea } from '@/components/elements/Input';
import { httpErrorToHuman } from '@/api/http';
import { askAssistant, AssistantMessage, AssistantProvider } from '@/api/server/howtoo';
import { ServerContext } from '@/state/server';
import { Muted, Panel, Toolbar } from './IntegrationStyles';

const Conversation = styled.div`
    display: flex;
    min-height: 20rem;
    max-height: 34rem;
    flex-direction: column;
    gap: 0.75rem;
    overflow-y: auto;
    padding: 0.5rem 0;
`;

const Message = styled.div<{ customer: boolean }>`
    align-self: ${(props) => (props.customer ? 'flex-end' : 'flex-start')};
    max-width: min(48rem, 88%);
    border: 1px solid ${(props) => (props.customer ? 'var(--hts-border-blue)' : 'var(--hts-border)')};
    border-radius: 0.5rem;
    background: ${(props) => (props.customer ? 'rgba(176, 105, 255, 0.1)' : 'var(--hts-surface-soft)')};
    padding: 0.75rem 0.875rem;
    color: var(--hts-ink-soft);
    font-size: 0.875rem;
    line-height: 1.55;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
`;

const ErrorText = styled.p`
    margin-top: 0.75rem;
    color: #fca5a5;
    font-size: 0.8125rem;
`;

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const available = server.howtoo.aiAssistant.providers;
    const providers = useMemo(
        () => (['gemini', 'groq'] as AssistantProvider[]).filter((provider) => available[provider]),
        [available.gemini, available.groq]
    );
    const [provider, setProvider] = useState<AssistantProvider>(available.gemini ? 'gemini' : 'groq');
    const [messages, setMessages] = useState<AssistantMessage[]>([]);
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const content = message.trim();
        if (!content || loading || !providers.length) return;

        const history = messages.slice(-10);
        setMessages((current) => [...current, { role: 'user', content }]);
        setMessage('');
        setError('');
        setLoading(true);

        try {
            const answer = await askAssistant(server.uuid, provider, content, history);
            setMessages((current) => [...current, answer]);
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setLoading(false);
        }
    };

    return (
        <ServerContentBlock title={'AI Assistant'}>
            <Panel>
                <Toolbar>
                    <strong>Server help</strong>
                    {providers.map((item) => (
                        <Button
                            key={item}
                            size={'xsmall'}
                            isSecondary={provider !== item}
                            onClick={() => setProvider(item)}
                            type={'button'}
                        >
                            {item === 'gemini' ? 'Gemini' : 'Groq'}
                        </Button>
                    ))}
                    {!!messages.length && (
                        <Button
                            size={'xsmall'}
                            color={'grey'}
                            isSecondary
                            onClick={() => setMessages([])}
                            type={'button'}
                        >
                            Clear
                        </Button>
                    )}
                </Toolbar>
                <Muted style={{ marginTop: '0.75rem' }}>
                    Explanations only. The assistant cannot execute commands, edit files or restart this server.
                </Muted>
                {!providers.length ? (
                    <ErrorText>No assistant provider is currently enabled by the administrator.</ErrorText>
                ) : (
                    <>
                        <Conversation aria-live={'polite'}>
                            {!messages.length && (
                                <Muted>Ask about an error, configuration option or panel function.</Muted>
                            )}
                            {messages.map((item, index) => (
                                <Message key={`${item.role}-${index}`} customer={item.role === 'user'}>
                                    {item.content}
                                </Message>
                            ))}
                            {loading && <Muted>Preparing a contextual answer...</Muted>}
                        </Conversation>
                        <form onSubmit={submit}>
                            <Textarea
                                rows={3}
                                maxLength={3000}
                                value={message}
                                onChange={(event) => setMessage(event.currentTarget.value)}
                                placeholder={'Describe what you need help with'}
                                disabled={loading}
                            />
                            <Toolbar style={{ justifyContent: 'flex-end', marginTop: '0.75rem' }}>
                                <Button type={'submit'} isLoading={loading} disabled={!message.trim()}>
                                    Send
                                </Button>
                            </Toolbar>
                        </form>
                    </>
                )}
                {error && <ErrorText>{error}</ErrorText>}
            </Panel>
        </ServerContentBlock>
    );
};
