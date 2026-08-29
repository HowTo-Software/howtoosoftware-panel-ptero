import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import styled from 'styled-components/macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import Can from '@/components/elements/Can';
import { httpErrorToHuman } from '@/api/http';
import {
    getWorkshopConfiguration,
    resolveWorkshopItem,
    saveWorkshop,
    searchWorkshop,
    WorkshopConfiguration,
    WorkshopItem,
} from '@/api/server/howtoo';
import { ServerContext } from '@/state/server';
import { Badge, Card, Grid, Muted, Toolbar } from './IntegrationStyles';

const Section = styled.div`
    margin-top: 1.25rem;
`;

const Chip = styled.span`
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--hts-border);
    border-radius: 999px;
    padding: 0.25rem 0.55rem;
    color: var(--hts-ink-soft);
    font-size: 0.75rem;

    button {
        color: #fca5a5;
    }
`;

const ErrorText = styled.p`
    margin-top: 0.75rem;
    color: #fca5a5;
    font-size: 0.8125rem;
`;

const unique = (values: string[]) => Array.from(new Set(values.map((value) => value.trim()).filter(Boolean)));
const manualModIds = (value: string) => unique(value.split(/[;,]/)).filter((id) => /^[A-Za-z0-9_.-]{1,128}$/.test(id));

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const [configuration, setConfiguration] = useState<WorkshopConfiguration>();
    const [workshopItems, setWorkshopItems] = useState<string[]>([]);
    const [mods, setMods] = useState<string[]>([]);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<WorkshopItem[]>([]);
    const [manualIds, setManualIds] = useState<Record<string, string>>({});
    const [manualFallback, setManualFallback] = useState<Record<string, boolean>>({});
    const [manualMod, setManualMod] = useState('');
    const [showManualEditor, setShowManualEditor] = useState(false);
    const [loading, setLoading] = useState(true);
    const [searching, setSearching] = useState(false);
    const [resolving, setResolving] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const load = async () => {
        setLoading(true);
        setError('');
        try {
            const data = await getWorkshopConfiguration(server.uuid);
            setConfiguration(data);
            setWorkshopItems(data.workshopItems);
            setMods(data.mods);
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [server.uuid]);

    const details = useMemo(() => {
        const map = new Map<string, WorkshopItem>();
        [...(configuration?.details || []), ...results].forEach((item) => map.set(item.workshopId, item));
        return map;
    }, [configuration?.details, results]);

    const changed =
        !!configuration &&
        (JSON.stringify(workshopItems) !== JSON.stringify(configuration.workshopItems) ||
            JSON.stringify(mods) !== JSON.stringify(configuration.mods));

    const search = async (event: FormEvent) => {
        event.preventDefault();
        if (query.trim().length < 2) return;
        setSearching(true);
        setError('');
        try {
            setResults(await searchWorkshop(server.uuid, query.trim()));
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setSearching(false);
        }
    };

    const add = async (item: WorkshopItem) => {
        let resolved = item;
        if (!resolved.modIds.length) {
            setResolving(item.workshopId);
            setError('');
            try {
                resolved = await resolveWorkshopItem(server.uuid, item.workshopId);
                setResults((current) =>
                    current.map((entry) => (entry.workshopId === resolved.workshopId ? resolved : entry))
                );
            } catch (error) {
                setError(httpErrorToHuman(error));
                setResolving(null);
                return;
            }
            setResolving(null);
        }

        const ids = resolved.modIds.length ? resolved.modIds : manualModIds(manualIds[item.workshopId] || '');
        if (!ids.length) {
            setManualFallback((current) => ({ ...current, [item.workshopId]: true }));
            setError(
                'No Mod ID was found in the installed mod.info or Steam metadata. Use the advanced fallback below.'
            );
            return;
        }
        setError('');
        if (!resolved.modIds.length) {
            resolved = { ...resolved, modIds: ids };
            setResults((current) =>
                current.map((entry) => (entry.workshopId === resolved.workshopId ? resolved : entry))
            );
        }
        setWorkshopItems((current) => unique([...current, resolved.workshopId]));
        setMods((current) => unique([...current, ...ids]));
    };

    const remove = (workshopId: string) => {
        const item = details.get(workshopId);
        const remainingItems = workshopItems.filter((id) => id !== workshopId);
        setWorkshopItems(remainingItems);
        if (item?.modIds.length) {
            const stillUsed = new Set(remainingItems.flatMap((id) => details.get(id)?.modIds || []));
            setMods((current) => current.filter((id) => !item.modIds.includes(id) || stillUsed.has(id)));
        }
    };

    const addManualMod = () => {
        const values = manualModIds(manualMod);
        if (!values.length) return;
        setMods((current) => unique([...current, ...values]));
        setManualMod('');
    };

    const save = async (restart: boolean) => {
        if (!configuration || !changed) return;
        setSaving(true);
        setError('');
        setNotice('');
        try {
            const result = await saveWorkshop(
                server.uuid,
                {
                    workshopItems,
                    mods,
                    revision: configuration.revision,
                    workshopMods: Object.fromEntries(
                        workshopItems.map((workshopId) => [workshopId, details.get(workshopId)?.modIds || []])
                    ),
                },
                restart
            );
            setConfiguration({ ...result, details: Array.from(details.values()), detailsError: null });
            setWorkshopItems(result.workshopItems);
            setMods(result.mods);
            setNotice(
                result.restartError ||
                    (result.restarted ? 'Changes saved and restart requested.' : 'Pending changes saved.')
            );
        } catch (error) {
            setError(httpErrorToHuman(error));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Spinner size={'large'} centered />;

    return (
        <ServerContentBlock title={'Workshop Mods - BETA'}>
            <div>
                <Toolbar style={{ justifyContent: 'space-between' }}>
                    <div>
                        <strong>Project Zomboid Workshop Manager</strong>
                        <Muted>{configuration?.path}</Muted>
                    </div>
                    <Badge>{changed ? 'Pending changes' : 'Configuration synchronized'}</Badge>
                </Toolbar>
                {configuration?.detailsError && <ErrorText>{configuration.detailsError}</ErrorText>}
                {notice && <Muted style={{ marginTop: '0.75rem', color: '#86efac' }}>{notice}</Muted>}
                {error && <ErrorText>{error}</ErrorText>}

                <Section>
                    <h2>Configured Workshop items</h2>
                    {!workshopItems.length ? (
                        <Muted style={{ marginTop: '0.5rem' }}>No Workshop items are configured.</Muted>
                    ) : (
                        <Grid style={{ marginTop: '0.75rem' }}>
                            {workshopItems.map((id) => {
                                const item = details.get(id);
                                return (
                                    <Card key={id}>
                                        {item?.image && <img src={item.image} alt={''} loading={'lazy'} />}
                                        <h3>{item?.name || `Workshop item ${id}`}</h3>
                                        <Badge>Workshop ID {id}</Badge>
                                        {!!item?.modIds.length && <Muted>Mod IDs: {item.modIds.join('; ')}</Muted>}
                                        {item?.description && <p>{item.description}</p>}
                                        <Can action={'integration.workshop-update'}>
                                            <Button
                                                size={'xsmall'}
                                                color={'red'}
                                                isSecondary
                                                onClick={() => remove(id)}
                                            >
                                                Remove
                                            </Button>
                                        </Can>
                                    </Card>
                                );
                            })}
                        </Grid>
                    )}
                </Section>

                <Section>
                    <h2>Configured PZ Mod IDs</h2>
                    <Toolbar style={{ marginTop: '0.75rem' }}>
                        {mods.map((id) => (
                            <Chip key={id}>
                                {id}
                                <Can action={'integration.workshop-update'}>
                                    <button
                                        type={'button'}
                                        aria-label={`Remove ${id}`}
                                        onClick={() => setMods(mods.filter((v) => v !== id))}
                                    >
                                        x
                                    </button>
                                </Can>
                            </Chip>
                        ))}
                    </Toolbar>
                    <Can action={'integration.workshop-update'}>
                        <div style={{ marginTop: '0.75rem' }}>
                            <Button
                                size={'xsmall'}
                                color={'grey'}
                                isSecondary
                                type={'button'}
                                onClick={() => setShowManualEditor((value) => !value)}
                            >
                                Advanced Mod ID editor
                            </Button>
                            {showManualEditor && (
                                <Toolbar style={{ marginTop: '0.75rem' }}>
                                    <Input
                                        style={{ maxWidth: '24rem' }}
                                        value={manualMod}
                                        onChange={(event) => setManualMod(event.currentTarget.value)}
                                        placeholder={'Manual Mod ID'}
                                    />
                                    <Button size={'small'} isSecondary type={'button'} onClick={addManualMod}>
                                        Add Mod ID
                                    </Button>
                                </Toolbar>
                            )}
                        </div>
                    </Can>
                </Section>

                <Section>
                    <form onSubmit={search}>
                        <Toolbar>
                            <Input
                                style={{ maxWidth: '32rem' }}
                                value={query}
                                onChange={(event) => setQuery(event.currentTarget.value)}
                                placeholder={'Search Project Zomboid Workshop'}
                            />
                            <Button type={'submit'} isLoading={searching} disabled={query.trim().length < 2}>
                                Search
                            </Button>
                        </Toolbar>
                    </form>
                    {!!results.length && (
                        <Grid style={{ marginTop: '1rem' }}>
                            {results.map((item) => (
                                <Card key={item.workshopId}>
                                    {item.image && <img src={item.image} alt={''} loading={'lazy'} />}
                                    <h3>{item.name}</h3>
                                    <Badge>Workshop ID {item.workshopId}</Badge>
                                    <p>{item.description}</p>
                                    {item.modIds.length ? (
                                        <Muted>
                                            Mod IDs: {item.modIds.join('; ')}
                                            {['mod_info', 'remote_mod_info'].includes(item.modIdSource || '')
                                                ? ' / verified from mod.info'
                                                : ''}
                                        </Muted>
                                    ) : manualFallback[item.workshopId] ? (
                                        <div>
                                            <Muted>Advanced fallback: enter the exact ID from mod.info.</Muted>
                                            <Input
                                                value={manualIds[item.workshopId] || ''}
                                                onChange={(event) =>
                                                    setManualIds((current) => ({
                                                        ...current,
                                                        [item.workshopId]: event.currentTarget.value,
                                                    }))
                                                }
                                                placeholder={'Manual PZ Mod ID (advanced)'}
                                            />
                                        </div>
                                    ) : (
                                        <Muted>Mod IDs will be resolved automatically when added.</Muted>
                                    )}
                                    <Can action={'integration.workshop-update'}>
                                        <Button
                                            size={'xsmall'}
                                            onClick={() => add(item)}
                                            isLoading={resolving === item.workshopId}
                                            disabled={workshopItems.includes(item.workshopId) || resolving !== null}
                                        >
                                            {workshopItems.includes(item.workshopId) ? 'Added' : 'Add'}
                                        </Button>
                                    </Can>
                                </Card>
                            ))}
                        </Grid>
                    )}
                </Section>

                <Can action={'integration.workshop-update'}>
                    <Toolbar style={{ justifyContent: 'flex-end', marginTop: '1.25rem' }}>
                        <Button
                            isSecondary
                            disabled={!changed || saving}
                            onClick={() => save(false)}
                            isLoading={saving}
                        >
                            Save
                        </Button>
                        <Can action={'control.restart'}>
                            <Button disabled={!changed || saving} onClick={() => save(true)} isLoading={saving}>
                                Save &amp; Restart
                            </Button>
                        </Can>
                    </Toolbar>
                </Can>
            </div>
        </ServerContentBlock>
    );
};
