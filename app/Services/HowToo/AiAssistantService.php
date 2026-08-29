<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\HowToo\Ai\AiProviderPrompt;
use Pterodactyl\Services\HowToo\Ai\AiAssistantProviderManager;

final class AiAssistantService
{
    private const MAX_MESSAGE_LENGTH = 3000;
    private const MAX_HISTORY_MESSAGES = 10;

    public function __construct(
        private AiAssistantProviderManager $providers,
        private AiAssistantPromptBuilder $promptBuilder,
        private AiConversationShortcut $shortcuts,
    ) {
    }

    public function ask(
        Server $server,
        User $user,
        string $message,
        array $history,
        ?string $section,
        ?string $error,
        ?string $liveStatus,
        ?callable $onAttempt = null,
    ): array {
        $shortcut = $this->shortcuts->response($message);
        if ($shortcut !== null) {
            return ['answer' => $shortcut];
        }

        $result = $this->providers->generate(
            $this->prompt($server, $user, $message, $history, $liveStatus, $section, $error),
            $onAttempt,
        );

        return [
            'answer' => mb_substr($result->answer, 0, 12000),
        ];
    }

    public function stream(
        Server $server,
        User $user,
        string $message,
        array $history,
        ?string $section,
        ?string $error,
        ?string $liveStatus,
        callable $onDelta,
        ?callable $onAttempt = null,
        ?callable $onReset = null,
    ): array {
        $shortcut = $this->shortcuts->response($message);
        if ($shortcut !== null) {
            $onDelta($shortcut);

            return ['answer' => $shortcut];
        }

        $result = $this->providers->stream(
            $this->prompt($server, $user, $message, $history, $liveStatus, $section, $error),
            $onDelta,
            $onAttempt,
            $onReset,
        );

        return ['answer' => mb_substr($result->answer, 0, 12000)];
    }

    private function sanitizeHistory(array $history): array
    {
        return collect(array_slice($history, -self::MAX_HISTORY_MESSAGES))
            ->filter(fn ($message) => is_array($message) && in_array($message['role'] ?? null, ['user', 'assistant'], true))
            ->map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => $this->sanitize((string) ($message['content'] ?? ''), self::MAX_MESSAGE_LENGTH),
            ])
            ->filter(fn (array $message) => $message['content'] !== '')
            ->values()
            ->all();
    }

    private function prompt(
        Server $server,
        User $user,
        string $message,
        array $history,
        ?string $liveStatus,
        ?string $section,
        ?string $error,
    ): AiProviderPrompt {
        $messages = $this->sanitizeHistory($history);
        $messages[] = ['role' => 'user', 'content' => $this->sanitize($message, self::MAX_MESSAGE_LENGTH)];

        return new AiProviderPrompt(
            $this->promptBuilder->build($server, $user, $liveStatus, $section, $error),
            $messages,
        );
    }

    private function sanitize(string $value, int $limit): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(mb_substr($value, 0, $limit));
    }
}
