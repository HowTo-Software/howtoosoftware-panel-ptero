import React, { useContext, useEffect, useState } from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import createOrUpdateSchedule from '@/api/server/schedules/createOrUpdateSchedule';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import { usePermissions } from '@/plugins/usePermissions';
import {
    DEFAULT_CRON,
    fromCronFields,
    frequencyLabel,
    getScheduleAction,
    ScheduleAction,
    ScheduleFrequency,
    toCronFields,
    toggleWeekday,
} from '@/components/server/schedules/scheduleDraft';

interface Props {
    schedule?: Schedule;
}

const ACTIONS: { id: ScheduleAction; label: string; detail: string; permission: string }[] = [
    { id: 'restart', label: 'Restart', detail: 'Restart the server', permission: 'control.restart' },
    { id: 'start', label: 'Start', detail: 'Start the server', permission: 'control.start' },
    { id: 'stop', label: 'Stop', detail: 'Stop the server', permission: 'control.stop' },
    { id: 'command', label: 'Command', detail: 'Send a console command', permission: 'control.console' },
    { id: 'backup', label: 'Backup', detail: 'Create a server backup', permission: 'backup.create' },
    { id: 'kill', label: 'Force stop', detail: 'Terminate the server process', permission: 'control.stop' },
];
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const HOURS = Array.from({ length: 24 }, (_, index) => index);
const MINUTES = Array.from({ length: 60 }, (_, index) => index);

const actionFromSchedule = (schedule?: Schedule) => {
    const current = getScheduleAction(schedule);
    return {
        ...current,
        existingAction: schedule?.tasks.slice().sort((a, b) => a.sequenceId - b.sequenceId)[0]?.action,
    };
};

const EditScheduleModal = ({ schedule }: Props) => {
    const { addError, clearFlashes } = useFlash();
    const { dismiss } = useContext(ModalContext);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);
    const backupLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.backups);
    const actionPermissions = usePermissions(ACTIONS.map((item) => item.permission));
    const [isSubmitting, setSubmitting] = useState(false);
    const [name, setName] = useState('');
    const [action, setAction] = useState<ScheduleAction>('restart');
    const [payload, setPayload] = useState('');
    const [frequency, setFrequency] = useState<ScheduleFrequency>({ type: 'interval', minutes: 5 });
    const [cron, setCron] = useState(DEFAULT_CRON);
    const [enabled, setEnabled] = useState(true);
    const [onlyWhenOnline, setOnlyWhenOnline] = useState(false);
    const primaryTask = schedule?.tasks.slice().sort((a, b) => a.sequenceId - b.sequenceId)[0];
    const frequencyLabelText = frequencyLabel(frequency);

    useEffect(() => {
        const nextCron = schedule?.cron || DEFAULT_CRON;
        const existing = actionFromSchedule(schedule);
        setName(schedule?.name || '');
        setAction(existing.action);
        setPayload(existing.payload);
        setFrequency(fromCronFields(nextCron));
        setCron(nextCron);
        setEnabled(schedule?.isActive ?? true);
        setOnlyWhenOnline(schedule?.onlyWhenOnline ?? false);
    }, [schedule?.id]);

    useEffect(() => () => clearFlashes('schedule:edit'), []);

    const setFrequencyAndCron = (next: ScheduleFrequency) => {
        setFrequency(next);
        const generated = toCronFields(next);
        if (generated) setCron(generated);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        clearFlashes('schedule:edit');
        if (!name.trim()) {
            addError({ key: 'schedule:edit', message: 'Enter a name for this schedule.' });
            return;
        }
        if (action === 'backup' && backupLimit === 0) {
            addError({
                key: 'schedule:edit',
                message: "A backup task cannot be created when the server's backup limit is set to 0.",
            });
            return;
        }
        if (frequency.type !== 'custom' && !toCronFields(frequency)) {
            addError({ key: 'schedule:edit', message: 'Choose a valid schedule frequency.' });
            return;
        }
        if (action === 'command' && !payload.trim()) {
            addError({ key: 'schedule:edit', message: 'Enter the command to run.' });
            return;
        }

        const powerPayload = action === 'command' || action === 'backup' ? '' : action;
        const actionType = action === 'command' ? 'command' : action === 'backup' ? 'backup' : 'power';
        const taskPayload =
            action === 'command'
                ? payload
                : action === 'backup'
                ? primaryTask?.action === 'backup'
                    ? primaryTask.payload
                    : ''
                : powerPayload;
        const taskChanged =
            !schedule || !primaryTask || primaryTask.action !== actionType || primaryTask.payload !== taskPayload;
        const generatedCron = frequency.type === 'custom' ? cron : toCronFields(frequency)!;

        setSubmitting(true);
        createOrUpdateSchedule(uuid, {
            id: schedule?.id,
            name: name.trim(),
            cron: generatedCron,
            onlyWhenOnline,
            isActive: enabled,
            ...(taskChanged
                ? {
                      task: {
                          action: actionType,
                          payload: taskPayload,
                          timeOffset: primaryTask?.timeOffset || 0,
                          continueOnFailure: primaryTask?.continueOnFailure || false,
                      },
                  }
                : {}),
        })
            .then((updated) => {
                appendSchedule(updated);
                dismiss();
            })
            .catch((error) => {
                console.error(error);
                addError({ key: 'schedule:edit', message: httpErrorToHuman(error) });
            })
            .finally(() => setSubmitting(false));
    };

    const selectedFrequency = frequency.type;
    const weekdays = frequency.type === 'days' ? frequency.weekdays : 'all';
    const dayValue =
        frequency.type === 'days' ? frequency : { type: 'days' as const, weekdays: 'all' as const, hour: 8, minute: 0 };
    const cronText = `${cron.minute} ${cron.hour} ${cron.dayOfMonth} ${cron.month} ${cron.dayOfWeek}`;

    return (
        <form onSubmit={submit} css={tw`m-0`}>
            <div css={tw`mx-auto w-full`} style={{ maxWidth: 780 }}>
                <div css={tw`flex items-start justify-between mb-6`}>
                    <div>
                        <p css={tw`text-xs uppercase tracking-widest text-purple-300 mb-1`}>Server automation</p>
                        <h2 css={tw`text-2xl font-semibold text-neutral-100`}>
                            {schedule ? 'Edit schedule' : 'Create schedule'}
                        </h2>
                        <p css={tw`text-sm text-neutral-400 mt-1`}>Choose an action and when it should run.</p>
                    </div>
                </div>
                <FlashMessageRender byKey={'schedule:edit'} css={tw`mb-4`} />

                <label css={tw`block mb-5`}>
                    <span css={tw`block text-xs uppercase text-neutral-300 mb-2`}>Schedule name</span>
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        maxLength={191}
                        autoFocus
                        css={tw`w-full rounded border border-neutral-600 bg-neutral-900 px-3 py-2 text-neutral-100 focus:border-purple-400 focus:outline-none`}
                        placeholder={'Daily restart'}
                    />
                </label>

                <div css={tw`mb-6`}>
                    <div css={tw`flex items-center justify-between mb-2`}>
                        <h3 css={tw`text-sm font-semibold text-neutral-100`}>Action</h3>
                        {schedule && schedule.tasks.length > 1 && (
                            <span css={tw`text-xs text-neutral-400`}>Additional tasks will be preserved.</span>
                        )}
                    </div>
                    <div css={tw`grid grid-cols-2 md:grid-cols-3 gap-2`}>
                        {ACTIONS.map((item, index) => {
                            const selected = action === item.id;
                            const permitted = actionPermissions[index] || selected;
                            return (
                                <button
                                    key={item.id}
                                    type={'button'}
                                    disabled={!permitted}
                                    onClick={() => {
                                        setAction(item.id);
                                        if (item.id === 'command' && !payload) setPayload('');
                                    }}
                                    css={[
                                        tw`text-left rounded-lg border p-3 transition`,
                                        selected
                                            ? tw`border-purple-400 bg-purple-900 text-white`
                                            : tw`border-neutral-700 bg-neutral-900 text-neutral-200 hover:border-neutral-500`,
                                        !permitted && tw`opacity-40 cursor-not-allowed`,
                                    ]}
                                >
                                    <span css={tw`block font-semibold`}>{item.label}</span>
                                    <span css={tw`block text-xs text-neutral-400 mt-1`}>{item.detail}</span>
                                </button>
                            );
                        })}
                    </div>
                    {action === 'command' && (
                        <label css={tw`block mt-4`}>
                            <span css={tw`block text-xs uppercase text-neutral-300 mb-2`}>Command</span>
                            <textarea
                                rows={3}
                                value={payload}
                                onChange={(event) => setPayload(event.target.value)}
                                css={tw`w-full rounded border border-neutral-600 bg-neutral-900 px-3 py-2 text-neutral-100 focus:border-purple-400 focus:outline-none`}
                                placeholder={'save'}
                            />
                        </label>
                    )}
                    {action === 'backup' && (
                        <p css={tw`text-xs text-neutral-400 mt-3`}>
                            The server’s .pteroignore rules will be used for excluded files.
                        </p>
                    )}
                </div>

                <div css={tw`mb-5`}>
                    <h3 css={tw`text-sm font-semibold text-neutral-100 mb-2`}>Frequency</h3>
                    <div css={tw`flex flex-wrap gap-2 mb-4`}>
                        {(
                            [
                                'interval',
                                'hourly',
                                'days',
                                ...(frequency.type === 'custom' ? ['custom'] : []),
                            ] as string[]
                        ).map((type) => (
                            <button
                                key={type}
                                type={'button'}
                                onClick={() => {
                                    if (type === 'interval')
                                        setFrequencyAndCron({
                                            type,
                                            minutes: frequency.type === 'interval' ? frequency.minutes : 5,
                                        });
                                    if (type === 'hourly')
                                        setFrequencyAndCron({
                                            type,
                                            minute: frequency.type === 'hourly' ? frequency.minute : 0,
                                        });
                                    if (type === 'days')
                                        setFrequencyAndCron({ type, weekdays: 'all', hour: 8, minute: 0 });
                                }}
                                css={[
                                    tw`rounded px-3 py-2 text-sm border`,
                                    selectedFrequency === type
                                        ? tw`border-purple-400 bg-purple-900 text-white`
                                        : tw`border-neutral-700 bg-neutral-900 text-neutral-300`,
                                ]}
                            >
                                {type === 'interval'
                                    ? 'Every X minutes'
                                    : type === 'hourly'
                                    ? 'Hourly'
                                    : type === 'days'
                                    ? 'Days and time'
                                    : 'Custom'}
                            </button>
                        ))}
                    </div>
                    {frequency.type === 'interval' && (
                        <label css={tw`flex items-center gap-3 text-sm text-neutral-200`}>
                            Run every{' '}
                            <select
                                value={frequency.minutes}
                                onChange={(event) =>
                                    setFrequencyAndCron({ ...frequency, minutes: Number(event.target.value) })
                                }
                                css={tw`rounded border border-neutral-600 bg-neutral-900 px-3 py-2`}
                            >
                                {Array.from({ length: 59 }, (_, index) => index + 1).map((minute) => (
                                    <option key={minute} value={minute}>
                                        {String(minute).padStart(2, '0')}
                                    </option>
                                ))}
                            </select>{' '}
                            minutes
                        </label>
                    )}
                    {frequency.type === 'hourly' && (
                        <label css={tw`flex items-center gap-3 text-sm text-neutral-200`}>
                            Run at minute{' '}
                            <select
                                value={frequency.minute}
                                onChange={(event) =>
                                    setFrequencyAndCron({ ...frequency, minute: Number(event.target.value) })
                                }
                                css={tw`rounded border border-neutral-600 bg-neutral-900 px-3 py-2`}
                            >
                                {MINUTES.map((minute) => (
                                    <option key={minute} value={minute}>
                                        {String(minute).padStart(2, '0')}
                                    </option>
                                ))}
                            </select>{' '}
                            of every hour
                        </label>
                    )}
                    {frequency.type === 'days' && (
                        <>
                            <div css={tw`grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4`}>
                                <button
                                    type={'button'}
                                    onClick={() => setFrequencyAndCron({ ...dayValue, weekdays: 'all' })}
                                    css={[
                                        tw`rounded border px-3 py-2 text-sm`,
                                        weekdays === 'all'
                                            ? tw`border-purple-400 bg-purple-900 text-white`
                                            : tw`border-neutral-700 bg-neutral-900 text-neutral-300`,
                                    ]}
                                >
                                    Every day
                                </button>
                                {DAYS.map((day, index) => (
                                    <button
                                        key={day}
                                        type={'button'}
                                        onClick={() =>
                                            setFrequencyAndCron({
                                                ...dayValue,
                                                weekdays: toggleWeekday(weekdays, index),
                                            })
                                        }
                                        css={[
                                            tw`rounded border px-3 py-2 text-sm`,
                                            weekdays !== 'all' && weekdays.includes(index)
                                                ? tw`border-purple-400 bg-purple-900 text-white`
                                                : tw`border-neutral-700 bg-neutral-900 text-neutral-300`,
                                        ]}
                                    >
                                        {day}
                                    </button>
                                ))}
                            </div>
                            <div css={tw`flex items-center gap-3 text-sm text-neutral-200`}>
                                At{' '}
                                <select
                                    value={dayValue.hour}
                                    onChange={(event) =>
                                        setFrequencyAndCron({ ...dayValue, hour: Number(event.target.value) })
                                    }
                                    css={tw`rounded border border-neutral-600 bg-neutral-900 px-3 py-2`}
                                >
                                    {HOURS.map((hour) => (
                                        <option key={hour} value={hour}>
                                            {String(hour).padStart(2, '0')}
                                        </option>
                                    ))}
                                </select>{' '}
                                :{' '}
                                <select
                                    value={dayValue.minute}
                                    onChange={(event) =>
                                        setFrequencyAndCron({ ...dayValue, minute: Number(event.target.value) })
                                    }
                                    css={tw`rounded border border-neutral-600 bg-neutral-900 px-3 py-2`}
                                >
                                    {MINUTES.map((minute) => (
                                        <option key={minute} value={minute}>
                                            {String(minute).padStart(2, '0')}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </>
                    )}
                    {frequency.type === 'custom' && (
                        <div css={tw`rounded border border-neutral-700 bg-neutral-900 p-3 text-sm text-neutral-300`}>
                            This existing frequency is not changed by the simplified editor.{' '}
                            <code css={tw`block mt-2 text-xs text-purple-200`}>{cronText}</code>
                        </div>
                    )}
                </div>

                <div css={tw`rounded-lg border border-purple-800 bg-neutral-900 p-4 mb-5`}>
                    <p css={tw`text-xs uppercase tracking-wide text-neutral-400`}>Schedule preview</p>
                    <p css={tw`text-lg text-neutral-100 mt-1`}>
                        {action === 'command'
                            ? `Run “${payload || 'your command'}”`
                            : action === 'backup'
                            ? 'Create a server backup'
                            : `${
                                  action === 'kill' ? 'Force stop' : action[0].toUpperCase() + action.slice(1)
                              } the server`}{' '}
                        {frequencyLabelText.toLowerCase()}.
                    </p>
                    <p css={tw`text-xs text-neutral-400 mt-2`}>
                        Times use the panel timezone{schedule?.timezone ? ` (${schedule.timezone})` : ''}.{' '}
                        <code css={tw`ml-1 text-neutral-500`}>
                            {cron.minute} {cron.hour} {cron.dayOfMonth} {cron.month} {cron.dayOfWeek}
                        </code>
                    </p>
                </div>

                <div css={tw`grid sm:grid-cols-2 gap-3 mb-6`}>
                    <label
                        css={tw`flex items-center gap-3 rounded border border-neutral-700 bg-neutral-900 p-3 cursor-pointer`}
                    >
                        <input
                            type={'checkbox'}
                            checked={enabled}
                            onChange={(event) => setEnabled(event.target.checked)}
                            css={tw`text-purple-500`}
                        />
                        <span>
                            <strong css={tw`block text-sm text-neutral-100`}>Schedule enabled</strong>
                            <small css={tw`text-xs text-neutral-400`}>Run automatically at the selected time.</small>
                        </span>
                    </label>
                    <label
                        css={tw`flex items-center gap-3 rounded border border-neutral-700 bg-neutral-900 p-3 cursor-pointer`}
                    >
                        <input
                            type={'checkbox'}
                            checked={onlyWhenOnline}
                            onChange={(event) => setOnlyWhenOnline(event.target.checked)}
                            css={tw`text-purple-500`}
                        />
                        <span>
                            <strong css={tw`block text-sm text-neutral-100`}>Only when online</strong>
                            <small css={tw`text-xs text-neutral-400`}>
                                Skip the action if the server is offline when it runs.
                            </small>
                        </span>
                    </label>
                </div>

                <div css={tw`flex justify-end gap-3`}>
                    <Button.Text type={'button'} onClick={dismiss}>
                        Cancel
                    </Button.Text>
                    <Button type={'submit'} disabled={isSubmitting}>
                        {isSubmitting ? 'Saving…' : schedule ? 'Save changes' : 'Create schedule'}
                    </Button>
                </div>
            </div>
        </form>
    );
};

export default asModal<Props>({ top: false, size: 'large' })(EditScheduleModal);
