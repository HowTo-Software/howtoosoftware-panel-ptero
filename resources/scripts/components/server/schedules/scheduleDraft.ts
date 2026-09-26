import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { translateUiText } from '@/i18n/uiTranslations';

export type ScheduleAction = 'restart' | 'start' | 'stop' | 'command' | 'backup' | 'kill';

export type ScheduleFrequency =
    | { type: 'interval'; minutes: number }
    | { type: 'hourly'; minute: number }
    | { type: 'days'; weekdays: 'all' | number[]; hour: number; minute: number }
    | { type: 'custom' };

export interface CronFields {
    minute: string;
    hour: string;
    dayOfMonth: string;
    month: string;
    dayOfWeek: string;
}

export const DEFAULT_CRON: CronFields = {
    minute: '*/5',
    hour: '*',
    dayOfMonth: '*',
    month: '*',
    dayOfWeek: '*',
};

export const toCronFields = (frequency: ScheduleFrequency): CronFields | null => {
    if (frequency.type === 'interval') {
        if (!Number.isInteger(frequency.minutes) || frequency.minutes < 1 || frequency.minutes > 59) return null;
        return { minute: `*/${frequency.minutes}`, hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' };
    }
    if (frequency.type === 'hourly') {
        if (!Number.isInteger(frequency.minute) || frequency.minute < 0 || frequency.minute > 59) return null;
        return { minute: String(frequency.minute), hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' };
    }
    if (frequency.type === 'days') {
        if (
            !Number.isInteger(frequency.hour) ||
            frequency.hour < 0 ||
            frequency.hour > 23 ||
            !Number.isInteger(frequency.minute) ||
            frequency.minute < 0 ||
            frequency.minute > 59
        )
            return null;
        const weekdays =
            frequency.weekdays === 'all' || frequency.weekdays.length === 0
                ? '*'
                : [...new Set(frequency.weekdays)]
                      .filter((day) => Number.isInteger(day) && day >= 0 && day <= 6)
                      .sort((a, b) => a - b)
                      .join(',');
        return {
            minute: String(frequency.minute),
            hour: String(frequency.hour),
            dayOfMonth: '*',
            month: '*',
            dayOfWeek: weekdays || '*',
        };
    }
    return null;
};

export const fromCronFields = (cron: CronFields): ScheduleFrequency => {
    if (cron.hour === '*' && cron.dayOfMonth === '*' && cron.month === '*' && cron.dayOfWeek === '*') {
        const interval = cron.minute.match(/^\*\/(\d+)$/);
        if (interval && Number(interval[1]) >= 1 && Number(interval[1]) <= 59) {
            return { type: 'interval', minutes: Number(interval[1]) };
        }
        if (/^\d+$/.test(cron.minute) && Number(cron.minute) <= 59) {
            return { type: 'hourly', minute: Number(cron.minute) };
        }
    }
    if (cron.dayOfMonth === '*' && cron.month === '*' && /^\d+$/.test(cron.minute) && /^\d+$/.test(cron.hour)) {
        const minute = Number(cron.minute);
        const hour = Number(cron.hour);
        if (minute <= 59 && hour <= 23) {
            if (cron.dayOfWeek === '*') return { type: 'days', weekdays: 'all', hour, minute };
            const weekdays = cron.dayOfWeek.split(',').map(Number);
            if (weekdays.length && weekdays.every((day) => Number.isInteger(day) && day >= 0 && day <= 6)) {
                return { type: 'days', weekdays: [...new Set(weekdays)], hour, minute };
            }
        }
    }
    return { type: 'custom' };
};

export const toggleWeekday = (selected: 'all' | number[], day: number): 'all' | number[] => {
    if (day < 0 || day > 6 || !Number.isInteger(day)) return selected;
    const days = selected === 'all' ? [] : [...selected];
    const next = days.includes(day) ? days.filter((value) => value !== day) : [...days, day];
    return next.length ? next.sort((a, b) => a - b) : 'all';
};

const pad = (value: number) => String(value).padStart(2, '0');

export const frequencyLabel = (frequency: ScheduleFrequency): string => {
    if (frequency.type === 'interval')
        return `${translateUiText('Every')} ${frequency.minutes} ${translateUiText(
            frequency.minutes === 1 ? 'minute' : 'minutes'
        )}`;
    if (frequency.type === 'hourly') return `${translateUiText('Hourly at minute')} ${pad(frequency.minute)}`;
    if (frequency.type === 'custom') return translateUiText('Custom frequency (kept as-is)');
    const weekdays =
        frequency.weekdays === 'all'
            ? translateUiText('Every day')
            : frequency.weekdays
                  .map((day) =>
                      translateUiText(
                          ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][day]
                      )
                  )
                  .join(', ');
    return `${weekdays} ${translateUiText('at')} ${pad(frequency.hour)}:${pad(frequency.minute)}`;
};

export const getScheduleAction = (schedule?: Schedule): { action: ScheduleAction; payload: string } => {
    const task = schedule?.tasks.slice().sort((a, b) => a.sequenceId - b.sequenceId)[0];
    if (!task) return { action: 'restart', payload: '' };
    if (task.action === 'command') return { action: 'command', payload: task.payload };
    if (task.action === 'backup') return { action: 'backup', payload: task.payload };
    if (task.action === 'power' && ['start', 'stop', 'restart', 'kill'].includes(task.payload)) {
        return { action: task.payload as ScheduleAction, payload: '' };
    }
    return { action: 'command', payload: task.payload };
};
