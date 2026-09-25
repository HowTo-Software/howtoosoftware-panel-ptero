<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Schedules;

use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends ViewScheduleRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SCHEDULE_CREATE;
    }

    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        if (!$this->exists('task')) {
            return true;
        }

        $server = $this->route()->parameter('server');
        $task = $this->input('task');
        if (!is_array($task)) {
            return true;
        }

        $action = $task['action'] ?? null;
        $payload = $task['payload'] ?? null;
        if (!is_string($action) || (!is_null($payload) && !is_string($payload))) {
            return true;
        }

        $permission = Task::permissionForAction($action, $payload);

        return is_null($permission) || ($server instanceof Server && $this->user()->can($permission, $server));
    }

    public function rules(): array
    {
        $rules = Schedule::getRules();

        return [
            'name' => $rules['name'],
            'is_active' => array_merge(['filled'], $rules['is_active']),
            'minute' => $rules['cron_minute'],
            'hour' => $rules['cron_hour'],
            'day_of_month' => $rules['cron_day_of_month'],
            'day_of_week' => $rules['cron_day_of_week'],
            'month' => $rules['cron_month'],
            'only_when_online' => ['sometimes', 'boolean'],
            'task' => ['sometimes', 'array:action,payload,time_offset,continue_on_failure', 'min:1'],
            'task.action' => ['required_with:task', Rule::in([Task::ACTION_COMMAND, Task::ACTION_POWER, Task::ACTION_BACKUP])],
            'task.payload' => [
                'required_unless:task.action,' . Task::ACTION_BACKUP,
                'string',
                'nullable',
                Rule::when($this->input('task.action') === Task::ACTION_POWER, [Rule::in(Task::POWER_ACTIONS)]),
            ],
            'task.time_offset' => ['sometimes', 'numeric', 'min:0', 'max:900'],
            'task.continue_on_failure' => ['sometimes', 'boolean'],
        ];
    }
}
