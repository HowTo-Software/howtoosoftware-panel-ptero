<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Psr\Log\LoggerInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;

final class AiAssistantProviderManager
{
    /** @var array<string, AiProviderAdapter> */
    private array $adapters = [];
    private AiPromptBudgeter $budgeter;

    /** @param iterable<AiProviderAdapter> $adapters */
    public function __construct(
        private AiCredentialRepository $credentials,
        iterable $adapters,
        private LoggerInterface $logger,
        private int $totalTimeoutSeconds = 90,
        ?AiPromptBudgeter $budgeter = null,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->name()] = $adapter;
        }

        $this->budgeter = $budgeter ?? new AiPromptBudgeter();
    }

    public function generate(AiProviderPrompt $prompt, ?callable $onAttempt = null): AiProviderResult
    {
        return $this->execute($prompt, false, null, $onAttempt, null);
    }

    public function stream(
        AiProviderPrompt $prompt,
        callable $onDelta,
        ?callable $onAttempt = null,
        ?callable $onReset = null,
    ): AiProviderResult {
        return $this->execute($prompt, true, $onDelta, $onAttempt, $onReset);
    }

    private function execute(
        AiProviderPrompt $prompt,
        bool $stream,
        ?callable $onDelta,
        ?callable $onAttempt,
        ?callable $onReset,
    ): AiProviderResult {
        $attempts = 0;
        $deadline = microtime(true) + $this->totalTimeoutSeconds();
        $prompt = $this->budgeter->compact($prompt);
        $queue = $this->attemptQueue();

        if ($queue === []) {
            $this->logger->warning('Ollama AI assistant is not configured or is temporarily cooling down.', [
                'provider' => 'ollama',
            ]);
            throw new DisplayException('The local AI assistant is not configured or is temporarily unavailable.');
        }

        foreach ($queue as $attempt) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 5) {
                break;
            }

            $name = $attempt['provider'];
            $adapter = $attempt['adapter'];
            $credential = $attempt['credential']->withTimeout($this->attemptTimeout(
                $attempt['credential'],
                $prompt,
                $remaining,
            ));

            ++$attempts;
            if ($onAttempt) {
                $onAttempt();
            }

            try {
                if ($stream && $adapter instanceof StreamingAiProviderAdapter) {
                    $answer = $adapter->stream($credential, $prompt, $onDelta ?? static fn (): null => null);
                } else {
                    $answer = $adapter->generate($credential, $prompt);
                    if ($stream && $onDelta) {
                        $onDelta($answer);
                    }
                }
                $this->credentials->markHealthy($credential);
                $this->logger->info('AI assistant response generated.', [
                    'provider' => $name,
                    'model' => $credential->model,
                ]);

                return new AiProviderResult($name, $answer);
            } catch (AiStreamCancelledException $exception) {
                throw $exception;
            } catch (AiProviderException $exception) {
                if ($exception->blamesCredential()) {
                    $this->credentials->putOnCooldown($credential, $exception->cooldownSeconds(), $exception->reason);
                }
                $this->logger->warning('Ollama AI assistant provider request failed.', [
                    'provider' => $name,
                    'model' => $credential->model,
                    'reason' => $exception->reason,
                    'status' => $exception->status,
                ]);

                throw new DisplayException($this->providerError($exception));
            } catch (\Throwable $exception) {
                $this->logger->error('Ollama AI assistant failed unexpectedly.', [
                    'provider' => $name,
                    'model' => $credential->model,
                    'exception' => $exception::class,
                    'message' => $this->safeExceptionMessage($exception, $credential),
                ]);

                throw new DisplayException('The local AI assistant encountered an internal error. Please try again shortly.');
            }
        }

        $this->logger->warning('Ollama AI assistant timed out before an attempt could complete.', ['attempts' => $attempts]);
        throw new DisplayException('The local AI assistant took too long to answer. Please try again.');
    }

    private function attemptQueue(): array
    {
        $queue = [];

        foreach ($this->credentials->orderedAiProviders() as $provider) {
            $name = $provider['name'];
            $adapter = $this->adapters[$name] ?? null;
            if (!$adapter) {
                continue;
            }

            $credentials = $this->credentials->availableAiCredentials(
                $name,
                $provider['model'],
                (int) ($provider['timeout_seconds'] ?? 25),
            );
            foreach (array_slice($credentials, 0, 1) as $credential) {
                $queue[] = [
                    'provider' => $name,
                    'adapter' => $adapter,
                    'credential' => $credential,
                ];
            }
        }

        return $queue;
    }

    private function attemptTimeout(
        AiProviderCredential $credential,
        AiProviderPrompt $prompt,
        int $remainingSeconds,
    ): int {
        $promptLength = mb_strlen($prompt->system)
            + collect($prompt->messages)->sum(fn (array $message): int => mb_strlen((string) ($message['content'] ?? '')));
        $complexityBoost = match (true) {
            $promptLength >= 15000 => 15,
            $promptLength >= 9000 => 10,
            default => 0,
        };
        $desired = min(150, $credential->timeoutSeconds + $complexityBoost);

        return max(5, min($desired, $remainingSeconds));
    }

    private function providerError(AiProviderException $exception): string
    {
        return match ($exception->reason) {
            AiProviderException::TIMEOUT => 'The local AI assistant took too long to answer. Please try again.',
            AiProviderException::RATE_LIMIT => 'The local AI assistant is busy. Please wait a moment and try again.',
            AiProviderException::INVALID_CREDENTIAL => 'The local AI assistant authentication is invalid. Contact an administrator.',
            AiProviderException::MODEL_NOT_FOUND => 'The configured local AI model is unavailable. Contact an administrator.',
            default => 'The local AI assistant is temporarily unavailable. Please try again shortly.',
        };
    }

    private function safeExceptionMessage(\Throwable $exception, AiProviderCredential $credential): string
    {
        $message = $exception->getMessage();
        foreach (array_filter([$credential->secret, $credential->baseUrl]) as $sensitive) {
            $message = str_replace($sensitive, '[redacted]', $message);
        }
        $message = preg_replace('/Authorization:\s*Bearer\s+\S+/i', 'Authorization: Bearer [redacted]', $message) ?? '';

        return mb_substr($message, 0, 500);
    }

    private function totalTimeoutSeconds(): int
    {
        return max(60, min($this->totalTimeoutSeconds, 180));
    }
}
