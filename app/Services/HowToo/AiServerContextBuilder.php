<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ActivityLog;

final class AiServerContextBuilder
{
    private const SENSITIVE_NAME = '/(?:pass(?:word)?|token|secret|api[_-]?key|private|credential|auth|webhook|database[_-]?url|db[_-]?(?:pass|password))/i';
    private const ALLOWED_STATES = ['offline', 'starting', 'stopping', 'running'];

    public function __construct(private ServerGameContext $gameContext)
    {
    }

    public function build(
        Server $server,
        ?string $liveStatus,
        ?string $section,
        ?string $reportedError,
        bool $includeRecentEvents = false,
    ): array {
        $relations = collect(['egg', 'nest', 'node', 'variables'])
            ->reject(fn (string $relation): bool => $server->relationLoaded($relation))
            ->all();
        if ($relations !== []) {
            $server->loadMissing($relations);
        }
        $game = $this->gameContext->for($server);

        return [
            'server_name' => $this->sanitize($server->name, 100),
            'game' => $game['game'],
            'status' => in_array($liveStatus, self::ALLOWED_STATES, true)
                ? $liveStatus
                : ($server->status ?: 'unknown'),
            'egg' => $this->sanitize((string) $server->egg?->name, 100),
            'node' => $this->sanitize((string) $server->node->name, 100),
            'panel_section' => $this->sanitize((string) $section, 80),
            'resource_limits' => [
                'memory_mb' => $server->memory,
                'disk_mb' => $server->disk,
                'cpu_percent' => $server->cpu,
            ],
            'startup_template' => $this->redact($server->startup, 800),
            'visible_variables' => $this->visibleVariables($server),
            'reported_error' => $this->redact((string) $reportedError, 2500),
            'recent_events' => $includeRecentEvents ? $this->recentEvents($server) : [],
        ];
    }

    private function recentEvents(Server $server): array
    {
        try {
            $events = $server->relationLoaded('activity')
                ? $server->getRelation('activity')
                : $server->activity()
                    ->whereNotIn('activity_logs.event', ActivityLog::DISABLED_EVENTS)
                    ->orderByDesc('activity_logs.timestamp')
                    ->limit(5)
                    ->get(['activity_logs.event', 'activity_logs.timestamp']);

            return collect($events)
                ->sortByDesc('timestamp')
                ->take(5)
                ->map(fn (ActivityLog $event): array => [
                    'event' => $this->sanitize($event->event, 120),
                    'timestamp' => $event->timestamp->toIso8601String(),
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function visibleVariables(Server $server): array
    {
        return $server->variables
            ->filter(fn ($variable): bool => (bool) $variable->user_viewable)
            ->reject(fn ($variable): bool => preg_match(self::SENSITIVE_NAME, (string) $variable->env_variable) === 1)
            ->take(30)
            ->map(function ($variable): array {
                $value = filled($variable->server_value) ? $variable->server_value : $variable->default_value;

                return [
                    'name' => $this->sanitize((string) $variable->env_variable, 80),
                    'value' => $this->redact((string) $value, 240),
                ];
            })
            ->values()
            ->all();
    }

    private function redact(string $value, int $limit): string
    {
        $value = preg_replace(
            '/\b(pass(?:word)?|token|secret|api[_-]?key|credential|auth)\s*([=:]|\s)\s*("[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s;]+)/i',
            '$1$2[redacted]',
            $value,
        ) ?? '';
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=:-]{8,}/i', 'Bearer [redacted]', $value) ?? '';
        $value = preg_replace('#(https?://)[^\s/@:]+:[^\s/@]+@#i', '$1[redacted]@', $value) ?? '';

        return $this->sanitize($value, $limit);
    }

    private function sanitize(string $value, int $limit): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(mb_substr($value, 0, $limit));
    }
}
