import { rawDataToServerSchedule, Schedule } from '@/api/server/schedules/getServerSchedules';
import http from '@/api/http';

type TaskDraft = { action: string; payload: string; timeOffset?: number; continueOnFailure?: boolean };
type Data = Pick<Schedule, 'cron' | 'name' | 'onlyWhenOnline' | 'isActive'> & { id?: number; task?: TaskDraft };

export default async (uuid: string, schedule: Data): Promise<Schedule> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/schedules${schedule.id ? `/${schedule.id}` : ''}`, {
        is_active: schedule.isActive,
        only_when_online: schedule.onlyWhenOnline,
        name: schedule.name,
        minute: schedule.cron.minute,
        hour: schedule.cron.hour,
        day_of_month: schedule.cron.dayOfMonth,
        month: schedule.cron.month,
        day_of_week: schedule.cron.dayOfWeek,
        ...(schedule.task
            ? {
                  task: {
                      action: schedule.task.action,
                      payload: schedule.task.payload,
                      time_offset: schedule.task.timeOffset ?? 0,
                      continue_on_failure: schedule.task.continueOnFailure ?? false,
                  },
              }
            : {}),
    });

    return rawDataToServerSchedule(data.attributes);
};
