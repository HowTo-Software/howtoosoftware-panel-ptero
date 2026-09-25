<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Helpers\Utilities;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Eloquent\ScheduleRepository;
use Pterodactyl\Repositories\Eloquent\TaskRepository;
use Pterodactyl\Exceptions\Service\ServiceLimitExceededException;
use Pterodactyl\Services\Schedules\ProcessScheduleService;
use Pterodactyl\Transformers\Api\Client\ScheduleTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\ViewScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\StoreScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\DeleteScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\UpdateScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\TriggerScheduleRequest;

class ScheduleController extends ClientApiController
{
    /**
     * ScheduleController constructor.
     */
    public function __construct(
        private ScheduleRepository $repository,
        private TaskRepository $taskRepository,
        private ConnectionInterface $connection,
        private ProcessScheduleService $service,
    )
    {
        parent::__construct();
    }

    /**
     * Returns all the schedules belonging to a given server.
     */
    public function index(ViewScheduleRequest $request, Server $server): array
    {
        $schedules = $server->schedules->loadMissing('tasks');

        return $this->fractal->collection($schedules)
            ->transformWith($this->getTransformer(ScheduleTransformer::class))
            ->toArray();
    }

    /**
     * Store a new schedule for a server.
     *
     * @throws DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(StoreScheduleRequest $request, Server $server): array
    {
        /** @var Schedule $model */
        [$model, $task] = $this->connection->transaction(function () use ($request, $server) {
            $model = $this->repository->create([
                'server_id' => $server->id,
                'name' => $request->input('name'),
                'cron_day_of_week' => $request->input('day_of_week'),
                'cron_month' => $request->input('month'),
                'cron_day_of_month' => $request->input('day_of_month'),
                'cron_hour' => $request->input('hour'),
                'cron_minute' => $request->input('minute'),
                'is_active' => (bool) $request->input('is_active'),
                'only_when_online' => $request->boolean('only_when_online'),
                'next_run_at' => $this->getNextRunAt($request),
            ]);

            $task = $request->filled('task') ? $this->createPrimaryTask($request, $server, $model) : null;

            return [$model, $task];
        });

        Activity::event('server:schedule.create')
            ->subject($model)
            ->property('name', $model->name)
            ->log();

        if ($task) {
            Activity::event('server:task.create')
                ->subject($model, $task)
                ->property(['name' => $model->name, 'action' => $task->action, 'payload' => $task->payload])
                ->log();
        }

        $model->load('tasks');

        return $this->fractal->item($model)
            ->transformWith($this->getTransformer(ScheduleTransformer::class))
            ->toArray();
    }

    /**
     * Returns a specific schedule for the server.
     */
    public function view(ViewScheduleRequest $request, Server $server, Schedule $schedule): array
    {
        if ($schedule->server_id !== $server->id) {
            throw new NotFoundHttpException();
        }

        $schedule->loadMissing('tasks');

        return $this->fractal->item($schedule)
            ->transformWith($this->getTransformer(ScheduleTransformer::class))
            ->toArray();
    }

    /**
     * Updates a given schedule with the new data provided.
     *
     * @throws DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateScheduleRequest $request, Server $server, Schedule $schedule): array
    {
        $active = (bool) $request->input('is_active');

        $data = [
            'name' => $request->input('name'),
            'cron_day_of_week' => $request->input('day_of_week'),
            'cron_month' => $request->input('month'),
            'cron_day_of_month' => $request->input('day_of_month'),
            'cron_hour' => $request->input('hour'),
            'cron_minute' => $request->input('minute'),
            'is_active' => $active,
            'only_when_online' => (bool) $request->input('only_when_online'),
            'next_run_at' => $this->getNextRunAt($request),
        ];

        // Toggle the processing state of the scheduled task when it is enabled or disabled so that an
        // invalid state can be reset without manual database intervention.
        //
        // @see https://github.com/pterodactyl/panel/issues/2425
        if ($schedule->is_active !== $active) {
            $data['is_processing'] = false;
        }

        [$task, $taskWasCreated] = $this->connection->transaction(function () use ($request, $server, $schedule, $data) {
            $this->repository->update($schedule->id, $data);
            if (!$request->filled('task')) {
                return [null, false];
            }

            $primaryTask = $schedule->tasks()->orderBy('sequence_id')->first();
            if (is_null($primaryTask)) {
                return [$this->createPrimaryTask($request, $server, $schedule), true];
            }

            $attributes = $this->primaryTaskAttributes($request, $primaryTask->sequence_id);
            $this->taskRepository->update($primaryTask->id, $attributes);

            return [$primaryTask->refresh(), false];
        });

        Activity::event('server:schedule.update')
            ->subject($schedule)
            ->property(['name' => $schedule->name, 'active' => $active])
            ->log();

        if ($task) {
            Activity::event($taskWasCreated ? 'server:task.create' : 'server:task.update')
                ->subject($schedule, $task)
                ->property(['name' => $schedule->name, 'action' => $task->action, 'payload' => $task->payload])
                ->log();
        }

        $schedule->load('tasks');

        return $this->fractal->item($schedule->refresh()->load('tasks'))
            ->transformWith($this->getTransformer(ScheduleTransformer::class))
            ->toArray();
    }

    /**
     * Executes a given schedule immediately rather than waiting on it's normally scheduled time
     * to pass. This does not care about the schedule state.
     *
     * Tasks are not checked against their action permissions here. They cannot be created or
     * modified without that permission, and anyone reaching this endpoint can already run them
     * by re-pointing the cron expression.
     *
     * @see https://github.com/pterodactyl/panel/issues/5671
     *
     * @throws \Throwable
     */
    public function execute(TriggerScheduleRequest $request, Server $server, Schedule $schedule): JsonResponse
    {
        $this->service->handle($schedule, true);

        Activity::event('server:schedule.execute')->subject($schedule)->property('name', $schedule->name)->log();

        return new JsonResponse([], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * Deletes a schedule and it's associated tasks.
     */
    public function delete(DeleteScheduleRequest $request, Server $server, Schedule $schedule): JsonResponse
    {
        $this->repository->delete($schedule->id);

        Activity::event('server:schedule.delete')->subject($schedule)->property('name', $schedule->name)->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the next run timestamp based on the cron data provided.
     *
     * @throws DisplayException
     */
    protected function getNextRunAt(Request $request): Carbon
    {
        try {
            return Utilities::getScheduleNextRunDate(
                $request->input('minute'),
                $request->input('hour'),
                $request->input('day_of_month'),
                $request->input('month'),
                $request->input('day_of_week')
            );
        } catch (\Exception $exception) {
            throw new DisplayException('The cron data provided does not evaluate to a valid expression.');
        }
    }

    private function createPrimaryTask(StoreScheduleRequest $request, Server $server, Schedule $schedule): Task
    {
        $data = $request->input('task');
        $limit = config('pterodactyl.client_features.schedules.per_schedule_task_limit', 10);
        if ($limit < 1) {
            throw new ServiceLimitExceededException("Schedules may not have more than $limit tasks associated with them.");
        }

        if ($data['action'] === Task::ACTION_BACKUP && $server->backup_limit === 0) {
            throw new \Pterodactyl\Exceptions\Http\HttpForbiddenException("A backup task cannot be created when the server's backup limit is set to 0.");
        }

        return $this->taskRepository->create(array_merge(
            ['schedule_id' => $schedule->id],
            $this->primaryTaskAttributes($request, 1)
        ));
    }

    private function primaryTaskAttributes(StoreScheduleRequest $request, int $sequenceId): array
    {
        $data = $request->input('task');

        return [
            'sequence_id' => $sequenceId,
            'action' => $data['action'],
            'payload' => $data['payload'] ?? '',
            'time_offset' => $data['time_offset'] ?? 0,
            'continue_on_failure' => (bool) ($data['continue_on_failure'] ?? false),
        ];
    }
}
