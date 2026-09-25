import React, { useEffect, useState } from 'react';
import getServerSchedules from '@/api/server/schedules/getServerSchedules';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { ServerContext } from '@/state/server';
import Spinner from '@/components/elements/Spinner';
import { useHistory, useRouteMatch } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import { httpErrorToHuman } from '@/api/http';
import EditScheduleModal from '@/components/server/schedules/EditScheduleModal';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { frequencyLabel, fromCronFields, getScheduleAction } from '@/components/server/schedules/scheduleDraft';

const formatRunDate = (date: Date | null, timezone: string): string => {
    if (!date) return 'Not scheduled';
    try {
        return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(
            date
        );
    } catch {
        return date.toLocaleString();
    }
};

const ScheduleCard = ({ schedule, onOpen, onEdit }: { schedule: Schedule; onOpen: () => void; onEdit: () => void }) => {
    const { action } = getScheduleAction(schedule);
    const period = frequencyLabel(fromCronFields(schedule.cron));

    return (
        <article
            css={tw`rounded-xl border border-neutral-700 bg-neutral-800 p-4 transition hover:border-purple-500 hover:bg-neutral-800 sm:p-5`}
        >
            <div css={tw`flex items-start justify-between gap-4`}>
                <div css={tw`min-w-0`}>
                    <div css={tw`flex flex-wrap items-center gap-2`}>
                        <h3 css={tw`truncate text-lg font-semibold text-neutral-100`}>
                            <button type={'button'} onClick={onOpen} css={tw`text-left hover:text-purple-200`}>
                                {schedule.name}
                            </button>
                        </h3>
                        {schedule.isProcessing ? (
                            <span css={tw`rounded-full bg-blue-900 px-2 py-1 text-xs text-blue-200`}>Running</span>
                        ) : (
                            <span
                                css={[
                                    tw`rounded-full px-2 py-1 text-xs`,
                                    schedule.isActive
                                        ? tw`bg-green-900 text-green-200`
                                        : tw`bg-neutral-700 text-neutral-300`,
                                ]}
                            >
                                {schedule.isActive ? 'Enabled' : 'Disabled'}
                            </span>
                        )}
                    </div>
                    <p css={tw`mt-2 text-sm text-neutral-300`}>
                        {schedule.tasks.length === 0
                            ? 'No action configured'
                            : `${
                                  action === 'kill' ? 'Force stop' : action[0].toUpperCase() + action.slice(1)
                              } · ${period}`}
                    </p>
                    <p css={tw`mt-2 text-xs text-neutral-400`}>
                        Next run{' '}
                        <span css={tw`text-neutral-200`}>{formatRunDate(schedule.nextRunAt, schedule.timezone)}</span>
                        <span css={tw`mx-2 text-neutral-600`}>·</span>
                        {schedule.tasks.length} {schedule.tasks.length === 1 ? 'task' : 'tasks'}
                    </p>
                </div>
                <Can action={'schedule.update'}>
                    <button
                        type={'button'}
                        aria-label={`Edit ${schedule.name}`}
                        title={'Edit schedule'}
                        onClick={onEdit}
                        css={tw`flex-shrink-0 rounded-lg border border-neutral-600 bg-neutral-900 px-3 py-2 text-sm text-neutral-200 hover:border-purple-400 hover:text-white`}
                    >
                        Edit
                    </button>
                </Can>
            </div>
        </article>
    );
};

export default () => {
    const match = useRouteMatch();
    const history = useHistory();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, addError } = useFlash();
    const [loading, setLoading] = useState(true);
    const [visible, setVisible] = useState(false);
    const [editing, setEditing] = useState<Schedule | undefined>();
    const schedules = ServerContext.useStoreState((state) => state.schedules.data);
    const setSchedules = ServerContext.useStoreActions((actions) => actions.schedules.setSchedules);

    useEffect(() => {
        clearFlashes('schedules');
        getServerSchedules(uuid)
            .then((items) => setSchedules(items))
            .catch((error) => {
                addError({ message: httpErrorToHuman(error), key: 'schedules' });
                console.error(error);
            })
            .finally(() => setLoading(false));
    }, []);

    const create = () => {
        setEditing(undefined);
        setVisible(true);
    };
    const edit = (schedule: Schedule) => {
        setEditing(schedule);
        setVisible(true);
    };

    return (
        <ServerContentBlock title={'Schedules'}>
            <FlashMessageRender byKey={'schedules'} css={tw`mb-4`} />
            <div css={tw`mx-auto w-full`} style={{ maxWidth: 1320 }}>
                <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-4`}>
                    <div>
                        <p css={tw`text-xs uppercase tracking-widest text-purple-300`}>Automation</p>
                        <h2 css={tw`mt-1 text-2xl font-semibold text-neutral-100`}>Server schedules</h2>
                        <p css={tw`mt-1 text-sm text-neutral-400`}>
                            Automate power actions, console commands, and backups.
                        </p>
                    </div>
                    <Can action={'schedule.create'}>
                        <Button type={'button'} onClick={create}>
                            + Create schedule
                        </Button>
                    </Can>
                </div>
                <EditScheduleModal visible={visible} schedule={editing} onModalDismissed={() => setVisible(false)} />
                {loading && !schedules.length ? (
                    <Spinner size={'large'} centered />
                ) : schedules.length === 0 ? (
                    <div css={tw`rounded-xl border border-dashed border-neutral-600 bg-neutral-800 p-10 text-center`}>
                        <h3 css={tw`text-lg font-semibold text-neutral-100`}>No schedules yet</h3>
                        <p css={tw`mx-auto mt-2 max-w-lg text-sm text-neutral-400`}>
                            Create a schedule to run a server action automatically at a time that works for you.
                        </p>
                        <Can action={'schedule.create'}>
                            <Button type={'button'} className={'mt-5'} onClick={create}>
                                Create your first schedule
                            </Button>
                        </Can>
                    </div>
                ) : (
                    <div css={tw`grid grid-cols-1 gap-3 lg:grid-cols-2`}>
                        {schedules.map((schedule) => (
                            <ScheduleCard
                                key={schedule.id}
                                schedule={schedule}
                                onOpen={() => history.push(`${match.url}/${schedule.id}`)}
                                onEdit={() => edit(schedule)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </ServerContentBlock>
    );
};
