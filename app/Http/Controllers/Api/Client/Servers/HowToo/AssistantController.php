<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\HowToo\AiAssistantService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Services\HowToo\Ai\AiStreamCancelledException;
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
            $request->user(),
            $request->string('message')->toString(),
            $request->input('history', []),
            $request->input('section'),
            $request->input('error'),
            $request->input('server_status'),
        ));
    }

    public function stream(AssistantRequest $request, Server $server): StreamedResponse
    {
        return response()->stream(function () use ($request, $server): void {
            @set_time_limit(max(60, min((int) config('howtoo.assistant.total_timeout_seconds', 90), 180)) + 15);
            $this->sendEvent('status', ['state' => 'thinking']);

            if (connection_aborted()) {
                return;
            }

            try {
                $this->assistant->stream(
                    $server,
                    $request->user(),
                    $request->string('message')->toString(),
                    $request->input('history', []),
                    $request->input('section'),
                    $request->input('error'),
                    $request->input('server_status'),
                    fn (string $delta) => $this->sendConnectedEvent('delta', ['content' => $delta]),
                    fn () => $this->sendConnectedEvent('status', ['state' => 'thinking']),
                    fn () => $this->sendConnectedEvent('reset', ['reason' => 'provider_fallback']),
                );

                if (!connection_aborted()) {
                    $this->sendEvent('done', ['completed' => true]);
                }
            } catch (AiStreamCancelledException) {
                return;
            } catch (DisplayException $exception) {
                if (!connection_aborted()) {
                    $this->sendEvent('error', ['message' => $exception->getMessage()]);
                }
            } catch (\Throwable) {
                Log::warning('AI assistant streaming request failed.', [
                    'server_id' => $server->id,
                ]);
                if (!connection_aborted()) {
                    $this->sendEvent('error', ['message' => 'The assistant could not answer right now. Please try again shortly.']);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sendConnectedEvent(string $event, array $payload): void
    {
        if (connection_aborted()) {
            throw new AiStreamCancelledException();
        }

        $this->sendEvent($event, $payload);
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
