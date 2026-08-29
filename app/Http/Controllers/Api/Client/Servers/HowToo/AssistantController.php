<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\HowToo\AiAssistantService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\AssistantRequest;

class AssistantController extends ClientApiController
{
    public function __construct(private AiAssistantService $assistant)
    {
        parent::__construct();
    }

    public function __invoke(AssistantRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->assistant->ask(
            $server,
            $request->string('message')->toString(),
            $request->input('history', []),
            $request->input('section'),
            $request->input('error'),
            $request->input('server_status'),
            $request->user()->can(Permission::ACTION_ACTIVITY_READ, $server),
        ));
    }

    public function stream(AssistantRequest $request, Server $server): StreamedResponse
    {
        return response()->stream(function () use ($request, $server): void {
            $this->sendEvent('status', ['state' => 'thinking']);

            if (connection_aborted()) {
                return;
            }

            try {
                $result = $this->assistant->ask(
                    $server,
                    $request->string('message')->toString(),
                    $request->input('history', []),
                    $request->input('section'),
                    $request->input('error'),
                    $request->input('server_status'),
                    $request->user()->can(Permission::ACTION_ACTIVITY_READ, $server),
                    fn () => $this->sendEvent('status', ['state' => 'thinking']),
                );

                if (!connection_aborted()) {
                    $this->sendEvent('message', ['answer' => $result['answer']]);
                    $this->sendEvent('done', ['completed' => true]);
                }
            } catch (DisplayException $exception) {
                $this->sendEvent('error', ['message' => $exception->getMessage()]);
            } catch (\Throwable) {
                Log::warning('AI assistant streaming request failed.', [
                    'server_id' => $server->id,
                ]);
                $this->sendEvent('error', ['message' => 'The assistant could not answer right now. Please try again shortly.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sendEvent(string $event, array $payload): void
    {
        echo "event: $event\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
