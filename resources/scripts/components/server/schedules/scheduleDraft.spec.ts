import { fromCronFields, toCronFields, toggleWeekday } from '@/components/server/schedules/scheduleDraft';

describe('schedule frequency conversion', () => {
    it('creates intervals, including the five-minute default', () => {
        expect(toCronFields({ type: 'interval', minutes: 5 })).toEqual({
            minute: '*/5',
            hour: '*',
            dayOfMonth: '*',
            month: '*',
            dayOfWeek: '*',
        });
        expect(toCronFields({ type: 'interval', minutes: 0 })).toBeNull();
        expect(fromCronFields({ minute: '*/5', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' })).toEqual({
            type: 'interval',
            minutes: 5,
        });
    });

    it('maps hourly command timing at minute zero', () => {
        expect(toCronFields({ type: 'hourly', minute: 0 })).toEqual({
            minute: '0',
            hour: '*',
            dayOfMonth: '*',
            month: '*',
            dayOfWeek: '*',
        });
    });

    it('maps daily and selected weekdays using cron Sunday zero', () => {
        expect(toCronFields({ type: 'days', weekdays: 'all', hour: 8, minute: 0 })?.dayOfWeek).toBe('*');
        expect(toCronFields({ type: 'days', weekdays: [1], hour: 8, minute: 0 })?.dayOfWeek).toBe('1');
        expect(toCronFields({ type: 'days', weekdays: [1, 3, 5], hour: 18, minute: 30 })).toEqual({
            minute: '30',
            hour: '18',
            dayOfMonth: '*',
            month: '*',
            dayOfWeek: '1,3,5',
        });
        expect(toCronFields({ type: 'days', weekdays: [0], hour: 4, minute: 15 })?.dayOfWeek).toBe('0');
    });

    it('never leaves weekday selection empty and keeps unsupported cron intact', () => {
        expect(toggleWeekday([2], 2)).toBe('all');
        expect(toggleWeekday('all', 1)).toEqual([1]);
        expect(fromCronFields({ minute: '0', hour: '8', dayOfMonth: '1', month: '*', dayOfWeek: '*' })).toEqual({
            type: 'custom',
        });
    });
});
